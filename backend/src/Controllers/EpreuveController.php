<?php
/**
 * Epreuve Controller - Gestion des épreuves calculées
 * 
 * CRUD pour les épreuves : une épreuve a une formule JSON
 * Exemple : [{"eval":"eval_id","coef":0.5},{"eval":"eval_id2","coef":0.5}]
 */

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\UUID;
use App\Middleware\AuthMiddleware;
use App\Services\NoteService;

class EpreuveController
{
    /**
     * Lister les épreuves d'une feuille
     * GET /api/feuilles/{feuilleId}/epreuves
     */
    public static function index(string $feuilleId): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('SELECT id FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$feuilleId, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$feuilleId]);
        
        Response::success($stmt->fetchAll());
    }

    /**
     * Ajouter une épreuve
     * POST /api/epreuves
     */
    public static function store(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $validator = new Validator($data);
        $errors = $validator
            ->required('feuille_id', 'Feuille')
            ->required('nom', 'Nom de l\'épreuve')
            ->required('formule', 'Formule')
            ->numeric('coefficient', 'Coefficient')
            ->between('coefficient', 0, 100, 'Coefficient')
            ->validate();
        
        if ($errors) {
            Response::badRequest('Erreur de validation', $errors);
        }
        
        // Valider que la formule est un JSON valide
        $formule = json_decode($data['formule'], true);
        if ($formule === null) {
            Response::badRequest('La formule doit être un JSON valide.');
        }
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('SELECT id FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$data['feuille_id'], $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        // Valider que les évaluations référencées existent
        if (is_array($formule)) {
            foreach ($formule as $item) {
                if (isset($item['eval'])) {
                    $stmt = $db->prepare('SELECT id FROM evaluations WHERE id = ? AND feuille_id = ?');
                    $stmt->execute([$item['eval'], $data['feuille_id']]);
                    if (!$stmt->fetch()) {
                        Response::badRequest("L'évaluation '{$item['eval']}' n'existe pas dans cette feuille.");
                    }
                }
            }
        }
        
        // Prochain ordre
        $stmt = $db->prepare('SELECT COALESCE(MAX(ordre), 0) + 1 as next_ordre FROM epreuves WHERE feuille_id = ?');
        $stmt->execute([$data['feuille_id']]);
        $nextOrdre = (int)$stmt->fetch()['next_ordre'];
        
        $id = UUID::generate();
        
        $stmt = $db->prepare('
            INSERT INTO epreuves (id, feuille_id, nom, formule, coefficient, ordre)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $id,
            $data['feuille_id'],
            $data['nom'],
            $data['formule'],
            (float)($data['coefficient'] ?? 1),
            $nextOrdre
        ]);
        
        // Créer des entrées vides pour les notes d'épreuves
        $stmt = $db->prepare('SELECT id FROM eleves WHERE feuille_id = ?');
        $stmt->execute([$data['feuille_id']]);
        $eleves = $stmt->fetchAll();
        
        foreach ($eleves as $eleve) {
            $stmt = $db->prepare('INSERT OR IGNORE INTO notes_epreuves (id, eleve_id, epreuve_id) VALUES (?, ?, ?)');
            $stmt->execute([UUID::generate(), $eleve['id'], $id]);
        }
        
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE id = ?');
        $stmt->execute([$id]);
        
        Response::created($stmt->fetch(), 'Épreuve créée avec succès.');
    }

    /**
     * Mettre à jour une épreuve
     * PUT /api/epreuves/{id}
     */
    public static function update(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('
            SELECT ep.* FROM epreuves ep
            JOIN feuilles_notes fn ON ep.feuille_id = fn.id
            WHERE ep.id = ? AND fn.enseignant_id = ?
        ');
        $stmt->execute([$id, $authUser['id']]);
        $epreuve = $stmt->fetch();
        if (!$epreuve) {
            Response::notFound('Épreuve non trouvée.');
        }
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $allowedFields = ['nom', 'formule', 'coefficient'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'formule') {
                    $formule = json_decode($data['formule'], true);
                    if ($formule === null) {
                        Response::badRequest('La formule doit être un JSON valide.');
                    }
                }
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            Response::badRequest('Aucun champ à modifier.');
        }
        
        $updates[] = "updated_at = datetime('now')";
        $params[] = $id;
        
        $sql = 'UPDATE epreuves SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $db->prepare($sql)->execute($params);
        
        // Recalculer les notes d'épreuves
        self::recalculateForFeuille($epreuve['feuille_id']);
        
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE id = ?');
        $stmt->execute([$id]);
        
        Response::success($stmt->fetch(), 'Épreuve mise à jour avec succès.');
    }

    /**
     * Supprimer une épreuve
     * DELETE /api/epreuves/{id}
     */
    public static function destroy(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('
            SELECT ep.* FROM epreuves ep
            JOIN feuilles_notes fn ON ep.feuille_id = fn.id
            WHERE ep.id = ? AND fn.enseignant_id = ?
        ');
        $stmt->execute([$id, $authUser['id']]);
        $epreuve = $stmt->fetch();
        if (!$epreuve) {
            Response::notFound('Épreuve non trouvée.');
        }
        
        $feuilleId = $epreuve['feuille_id'];
        $db->prepare('DELETE FROM epreuves WHERE id = ?')->execute([$id]);
        
        self::recalculateForFeuille($feuilleId);
        
        Response::success(null, 'Épreuve supprimée avec succès.');
    }

    /**
     * Recalculer les notes pour une feuille
     */
    private static function recalculateForFeuille(string $feuilleId): void
    {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('SELECT * FROM eleves WHERE feuille_id = ?');
        $stmt->execute([$feuilleId]);
        $eleves = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ?');
        $stmt->execute([$feuilleId]);
        $evaluations = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ?');
        $stmt->execute([$feuilleId]);
        $epreuves = $stmt->fetchAll();
        
        foreach ($eleves as $eleve) {
            $stmt = $db->prepare('SELECT evaluation_id, note FROM notes_evaluations WHERE eleve_id = ?');
            $stmt->execute([$eleve['id']]);
            $notesEval = [];
            foreach ($stmt->fetchAll() as $n) {
                $notesEval[$n['evaluation_id']] = $n['note'];
            }
            
            foreach ($epreuves as $epreuve) {
                $note = NoteService::calculateEpreuveNote($epreuve['formule'], $notesEval, $evaluations);
                if ($note !== null) {
                    $stmt = $db->prepare('UPDATE notes_epreuves SET note = ?, updated_at = datetime(\'now\') WHERE eleve_id = ? AND epreuve_id = ?');
                    $stmt->execute([$note, $eleve['id'], $epreuve['id']]);
                }
            }
        }
    }
}