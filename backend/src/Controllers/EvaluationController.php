<?php
/**
 * Evaluation Controller - Gestion des évaluations
 * 
 * CRUD pour les évaluations et gestion des notes d'évaluations
 */

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\UUID;
use App\Middleware\AuthMiddleware;

class EvaluationController
{
    /**
     * Lister les évaluations d'une feuille
     * GET /api/feuilles/{feuilleId}/evaluations
     */
    public static function index(string $feuilleId): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier que la feuille appartient à l'enseignant
        $stmt = $db->prepare('SELECT id FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$feuilleId, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$feuilleId]);
        
        Response::success($stmt->fetchAll());
    }

    /**
     * Ajouter une évaluation
     * POST /api/evaluations
     */
    public static function store(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $validator = new Validator($data);
        $errors = $validator
            ->required('feuille_id', 'Feuille')
            ->required('nom', 'Nom de l\'évaluation')
            ->numeric('bareme', 'Barème')
            ->between('bareme', 0, 100, 'Barème')
            ->numeric('coefficient', 'Coefficient')
            ->between('coefficient', 0, 100, 'Coefficient')
            ->validate();
        
        if ($errors) {
            Response::badRequest('Erreur de validation', $errors);
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier que la feuille appartient à l'enseignant
        $stmt = $db->prepare('SELECT id FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$data['feuille_id'], $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        // Déterminer le prochain ordre
        $stmt = $db->prepare('SELECT COALESCE(MAX(ordre), 0) + 1 as next_ordre FROM evaluations WHERE feuille_id = ?');
        $stmt->execute([$data['feuille_id']]);
        $nextOrdre = (int)$stmt->fetch()['next_ordre'];
        
        $id = UUID::generate();
        
        $stmt = $db->prepare('
            INSERT INTO evaluations (id, feuille_id, nom, date_evaluation, bareme, coefficient, ordre)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $id,
            $data['feuille_id'],
            $data['nom'],
            $data['date_evaluation'] ?? null,
            (float)($data['bareme'] ?? 20),
            (float)($data['coefficient'] ?? 1),
            $nextOrdre
        ]);
        
        // Créer des entrées de notes pour tous les élèves existants
        $stmt = $db->prepare('SELECT id FROM eleves WHERE feuille_id = ?');
        $stmt->execute([$data['feuille_id']]);
        $eleves = $stmt->fetchAll();
        
        foreach ($eleves as $eleve) {
            $stmt = $db->prepare('INSERT OR IGNORE INTO notes_evaluations (id, eleve_id, evaluation_id) VALUES (?, ?, ?)');
            $stmt->execute([UUID::generate(), $eleve['id'], $id]);
        }
        
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE id = ?');
        $stmt->execute([$id]);
        
        Response::created($stmt->fetch(), 'Évaluation créée avec succès.');
    }

    /**
     * Mettre à jour une évaluation
     * PUT /api/evaluations/{id}
     */
    public static function update(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier que l'évaluation existe
        $stmt = $db->prepare('
            SELECT ev.* FROM evaluations ev
            JOIN feuilles_notes fn ON ev.feuille_id = fn.id
            WHERE ev.id = ? AND fn.enseignant_id = ?
        ');
        $stmt->execute([$id, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Évaluation non trouvée.');
        }
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $allowedFields = ['nom', 'date_evaluation', 'bareme', 'coefficient'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            Response::badRequest('Aucun champ à modifier.');
        }
        
        $updates[] = "updated_at = datetime('now')";
        $params[] = $id;
        
        $sql = 'UPDATE evaluations SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $db->prepare($sql)->execute($params);
        
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE id = ?');
        $stmt->execute([$id]);
        
        Response::success($stmt->fetch(), 'Évaluation mise à jour avec succès.');
    }

    /**
     * Supprimer une évaluation
     * DELETE /api/evaluations/{id}
     */
    public static function destroy(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('
            SELECT ev.* FROM evaluations ev
            JOIN feuilles_notes fn ON ev.feuille_id = fn.id
            WHERE ev.id = ? AND fn.enseignant_id = ?
        ');
        $stmt->execute([$id, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Évaluation non trouvée.');
        }
        
        $db->prepare('DELETE FROM evaluations WHERE id = ?')->execute([$id]);
        
        Response::success(null, 'Évaluation supprimée avec succès.');
    }
}