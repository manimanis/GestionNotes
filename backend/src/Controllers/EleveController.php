<?php
/**
 * Eleve Controller - Gestion des élèves dans une feuille de notes
 */

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\UUID;
use App\Middleware\AuthMiddleware;

class EleveController
{
    /**
     * Lister les élèves d'une feuille
     * GET /api/feuilles/{feuilleId}/eleves
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
        
        $stmt = $db->prepare('SELECT * FROM eleves WHERE feuille_id = ? ORDER BY numero_ordre ASC');
        $stmt->execute([$feuilleId]);
        
        Response::success($stmt->fetchAll());
    }

    /**
     * Ajouter un élève
     * POST /api/eleves
     */
    public static function store(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $validator = new Validator($data);
        $errors = $validator
            ->required('feuille_id', 'Feuille')
            ->required('identifiant', 'Identifiant élève')
            ->required('numero_ordre', 'Numéro d\'ordre')
            ->numeric('numero_ordre', 'Numéro d\'ordre')
            ->required('nom', 'Nom')
            ->required('prenom', 'Prénom')
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
        
        // Vérifier unicité identifiant élève
        $stmt = $db->prepare('SELECT id FROM eleves WHERE identifiant = ?');
        $stmt->execute([$data['identifiant']]);
        if ($stmt->fetch()) {
            Response::badRequest('Cet identifiant élève existe déjà.');
        }
        
        $id = UUID::generate();
        
        $stmt = $db->prepare('
            INSERT INTO eleves (id, feuille_id, identifiant, numero_ordre, nom, prenom, nom_tuteur, prenom_tuteur)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $id,
            $data['feuille_id'],
            $data['identifiant'],
            (int)$data['numero_ordre'],
            $data['nom'],
            $data['prenom'],
            $data['nom_tuteur'] ?? null,
            $data['prenom_tuteur'] ?? null
        ]);
        
        // Créer les entrées vides pour les notes si les évaluations/épreuves existent
        $stmt = $db->prepare('SELECT id FROM evaluations WHERE feuille_id = ?');
        $stmt->execute([$data['feuille_id']]);
        $evaluations = $stmt->fetchAll();
        
        foreach ($evaluations as $eval) {
            $stmt = $db->prepare('INSERT OR IGNORE INTO notes_evaluations (id, eleve_id, evaluation_id) VALUES (?, ?, ?)');
            $stmt->execute([UUID::generate(), $id, $eval['id']]);
        }
        
        $stmt = $db->prepare('SELECT id FROM epreuves WHERE feuille_id = ?');
        $stmt->execute([$data['feuille_id']]);
        $epreuves = $stmt->fetchAll();
        
        foreach ($epreuves as $ep) {
            $stmt = $db->prepare('INSERT OR IGNORE INTO notes_epreuves (id, eleve_id, epreuve_id) VALUES (?, ?, ?)');
            $stmt->execute([UUID::generate(), $id, $ep['id']]);
        }
        
        $stmt = $db->prepare('SELECT * FROM eleves WHERE id = ?');
        $stmt->execute([$id]);
        
        Response::created($stmt->fetch(), 'Élève ajouté avec succès.');
    }

    /**
     * Mettre à jour un élève
     * PUT /api/eleves/{id}
     */
    public static function update(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier que l'élève existe et appartient à une feuille de l'enseignant
        $stmt = $db->prepare('
            SELECT e.* FROM eleves e
            JOIN feuilles_notes fn ON e.feuille_id = fn.id
            WHERE e.id = ? AND fn.enseignant_id = ?
        ');
        $stmt->execute([$id, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Élève non trouvé.');
        }
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $allowedFields = ['identifiant', 'nom', 'prenom', 'numero_ordre', 'nom_tuteur', 'prenom_tuteur'];
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
        
        $sql = 'UPDATE eleves SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $db->prepare($sql)->execute($params);
        
        $stmt = $db->prepare('SELECT * FROM eleves WHERE id = ?');
        $stmt->execute([$id]);
        
        Response::success($stmt->fetch(), 'Élève mis à jour avec succès.');
    }

    /**
     * Supprimer un élève
     * DELETE /api/eleves/{id}
     */
    public static function destroy(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('
            SELECT e.* FROM eleves e
            JOIN feuilles_notes fn ON e.feuille_id = fn.id
            WHERE e.id = ? AND fn.enseignant_id = ?
        ');
        $stmt->execute([$id, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Élève non trouvé.');
        }
        
        $db->prepare('DELETE FROM eleves WHERE id = ?')->execute([$id]);
        
        Response::success(null, 'Élève supprimé avec succès.');
    }

    /**
     * Import CSV d'élèves
     * POST /api/eleves/import
     */
    public static function importCsv(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            Response::badRequest('Fichier CSV requis.');
        }
        
        $feuilleId = $_POST['feuille_id'] ?? null;
        if (!$feuilleId) {
            Response::badRequest('ID de la feuille requis.');
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier la feuille
        $stmt = $db->prepare('SELECT id FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$feuilleId, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        $file = $_FILES['csv']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            Response::serverError('Erreur lors de la lecture du fichier.');
        }
        
        // Lire l'en-tête
        $header = fgetcsv($handle);
        $expectedHeaders = ['nom', 'prenom', 'numero_ordre', 'identifiant', 'nom_tuteur', 'prenom_tuteur'];
        
        if (!$header || count($header) < 4) {
            fclose($handle);
            Response::badRequest('Format CSV invalide. Format attendu: nom, prenom, numero_ordre, identifiant[, nom_tuteur, prenom_tuteur]');
        }
        
        $imported = 0;
        $errors = [];
        $row = 1;
        
        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            $data = [
                'feuille_id' => $feuilleId,
                'nom' => $line[0] ?? '',
                'prenom' => $line[1] ?? '',
                'numero_ordre' => $line[2] ?? 0,
                'identifiant' => $line[3] ?? '',
                'nom_tuteur' => $line[4] ?? null,
                'prenom_tuteur' => $line[5] ?? null,
            ];
            
            if (empty($data['nom']) || empty($data['prenom']) || empty($data['identifiant'])) {
                continue;
            }
            
            try {
                $stmt = $db->prepare('SELECT id FROM eleves WHERE identifiant = ?');
                $stmt->execute([$data['identifiant']]);
                if ($stmt->fetch()) {
                    $errors[] = "Ligne {$row}: Identifiant déjà existant";
                    continue;
                }
                
                $id = UUID::generate();
                $stmt = $db->prepare('
                    INSERT INTO eleves (id, feuille_id, identifiant, numero_ordre, nom, prenom, nom_tuteur, prenom_tuteur)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $id,
                    $data['feuille_id'],
                    $data['identifiant'],
                    (int)$data['numero_ordre'],
                    $data['nom'],
                    $data['prenom'],
                    $data['nom_tuteur'],
                    $data['prenom_tuteur']
                ]);
                $imported++;
            } catch (\PDOException $e) {
                $errors[] = "Ligne {$row}: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        Response::success([
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => $row - 1
        ], "{$imported} élèves importés avec succès.");
    }

    /**
     * Import d'élèves par copier-coller depuis Excel
     * POST /api/eleves/import-paste
     */
    public static function importPaste(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $feuilleId = $data['feuille_id'] ?? null;
        $rows = $data['rows'] ?? [];
        
        if (!$feuilleId) {
            Response::badRequest('ID de la feuille requis.');
        }
        
        if (empty($rows)) {
            Response::badRequest('Aucune donnée à importer.');
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier la feuille
        $stmt = $db->prepare('SELECT id FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$feuilleId, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        $imported = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $numero = $row['numero_ordre'] ?? ($index + 1);
            $identifiant = trim($row['identifiant'] ?? '');
            $nom = trim($row['nom'] ?? '');
            $prenom = trim($row['prenom'] ?? '');
            
            if (empty($nom) || empty($prenom) || empty($identifiant)) {
                $errors[] = "Ligne " . ($index + 1) . ": champs manquants (nom, prénom et identifiant requis)";
                continue;
            }
            
            try {
                // Vérifier unicité identifiant
                $stmt = $db->prepare('SELECT id FROM eleves WHERE identifiant = ?');
                $stmt->execute([$identifiant]);
                if ($stmt->fetch()) {
                    $errors[] = "Ligne " . ($index + 1) . ": identifiant '{$identifiant}' déjà existant";
                    continue;
                }
                
                $id = UUID::generate();
                $stmt = $db->prepare('
                    INSERT INTO eleves (id, feuille_id, identifiant, numero_ordre, nom, prenom)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $id,
                    $feuilleId,
                    $identifiant,
                    (int)$numero,
                    $nom,
                    $prenom
                ]);
                
                // Créer les entrées vides pour les notes
                $stmt = $db->prepare('SELECT id FROM evaluations WHERE feuille_id = ?');
                $stmt->execute([$feuilleId]);
                $evaluations = $stmt->fetchAll();
                
                foreach ($evaluations as $eval) {
                    $stmt = $db->prepare('INSERT OR IGNORE INTO notes_evaluations (id, eleve_id, evaluation_id) VALUES (?, ?, ?)');
                    $stmt->execute([UUID::generate(), $id, $eval['id']]);
                }
                
                $stmt = $db->prepare('SELECT id FROM epreuves WHERE feuille_id = ?');
                $stmt->execute([$feuilleId]);
                $epreuves = $stmt->fetchAll();
                
                foreach ($epreuves as $ep) {
                    $stmt = $db->prepare('INSERT OR IGNORE INTO notes_epreuves (id, eleve_id, epreuve_id) VALUES (?, ?, ?)');
                    $stmt->execute([UUID::generate(), $id, $ep['id']]);
                }
                
                $imported++;
            } catch (\PDOException $e) {
                $errors[] = "Ligne " . ($index + 1) . ": " . $e->getMessage();
            }
        }
        
        Response::success([
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => count($rows)
        ], "{$imported} élèves importés avec succès.");
    }
}
