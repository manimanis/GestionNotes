<?php
/**
 * FeuilleValidate Controller - Validation en lot des modifications d'une feuille
 * 
 * Reçoit toutes les données modifiées en mémoire et les sauvegarde en base
 * dans une seule transaction.
 */

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Helpers\UUID;
use App\Middleware\AuthMiddleware;
use App\Services\NoteService;

class FeuilleValidateController
{
    /**
     * Valider toutes les modifications d'une feuille
     * POST /api/feuilles/{id}/validate
     */
    public static function validate(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier que la feuille appartient à l'enseignant
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$id, $authUser['id']]);
        $feuille = $stmt->fetch();
        
        if (!$feuille) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $eleves = $data['eleves'] ?? [];
        $evaluations = $data['evaluations'] ?? [];
        $epreuves = $data['epreuves'] ?? [];
        $notesEvaluations = $data['notes_evaluations'] ?? [];
        
        $db->beginTransaction();
        
        try {
            // ============================================
            // 1. ÉLÈVES
            // ============================================
            $existingEleveIds = [];
            $stmt = $db->prepare('SELECT id FROM eleves WHERE feuille_id = ?');
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $row) {
                $existingEleveIds[$row['id']] = true;
            }
            
            $submittedEleveIds = [];
            
            foreach ($eleves as $eleve) {
                $eleveId = $eleve['id'] ?? null;
                
                if ($eleveId && isset($existingEleveIds[$eleveId])) {
                    $submittedEleveIds[$eleveId] = true;
                    $db->prepare('
                        UPDATE eleves SET 
                            identifiant = ?, numero_ordre = ?, nom = ?, prenom = ?,
                            nom_tuteur = ?, prenom_tuteur = ?, updated_at = datetime(\'now\')
                        WHERE id = ?
                    ')->execute([
                        $eleve['identifiant'] ?? '',
                        (int)($eleve['numero_ordre'] ?? 0),
                        $eleve['nom'] ?? '',
                        $eleve['prenom'] ?? '',
                        $eleve['nom_tuteur'] ?? null,
                        $eleve['prenom_tuteur'] ?? null,
                        $eleveId
                    ]);
                } else {
                    $newEleveId = $eleve['id'] ?? UUID::generate();
                    $submittedEleveIds[$newEleveId] = true;
                    
                    $db->prepare('
                        INSERT INTO eleves (id, feuille_id, identifiant, numero_ordre, nom, prenom, nom_tuteur, prenom_tuteur)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ')->execute([
                        $newEleveId,
                        $id,
                        $eleve['identifiant'] ?? '',
                        (int)($eleve['numero_ordre'] ?? 0),
                        $eleve['nom'] ?? '',
                        $eleve['prenom'] ?? '',
                        $eleve['nom_tuteur'] ?? null,
                        $eleve['prenom_tuteur'] ?? null
                    ]);
                }
            }
            
            // Supprimer les élèves absents
            foreach ($existingEleveIds as $eleveId => $v) {
                if (!isset($submittedEleveIds[$eleveId])) {
                    $db->prepare('DELETE FROM eleves WHERE id = ?')->execute([$eleveId]);
                }
            }
            
            // ============================================
            // 2. ÉVALUATIONS
            // ============================================
            $existingEvalIds = [];
            $stmt = $db->prepare('SELECT id FROM evaluations WHERE feuille_id = ?');
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $row) {
                $existingEvalIds[$row['id']] = true;
            }
            
            $submittedEvalIds = [];
            
            foreach ($evaluations as $eval) {
                $evalId = $eval['id'] ?? null;
                
                if ($evalId && isset($existingEvalIds[$evalId])) {
                    $submittedEvalIds[$evalId] = true;
                    $db->prepare('
                        UPDATE evaluations SET 
                            nom = ?, date_evaluation = ?, bareme = ?, coefficient = ?, ordre = ?,
                            updated_at = datetime(\'now\')
                        WHERE id = ?
                    ')->execute([
                        $eval['nom'] ?? '',
                        $eval['date_evaluation'] ?? null,
                        (float)($eval['bareme'] ?? 20),
                        (float)($eval['coefficient'] ?? 1),
                        (int)($eval['ordre'] ?? 0),
                        $evalId
                    ]);
                } else {
                    $newEvalId = $eval['id'] ?? UUID::generate();
                    $submittedEvalIds[$newEvalId] = true;
                    
                    $db->prepare('
                        INSERT INTO evaluations (id, feuille_id, nom, date_evaluation, bareme, coefficient, ordre)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ')->execute([
                        $newEvalId,
                        $id,
                        $eval['nom'] ?? '',
                        $eval['date_evaluation'] ?? null,
                        (float)($eval['bareme'] ?? 20),
                        (float)($eval['coefficient'] ?? 1),
                        (int)($eval['ordre'] ?? 0)
                    ]);
                }
            }
            
            // Supprimer les évaluations absentes
            foreach ($existingEvalIds as $evalId => $v) {
                if (!isset($submittedEvalIds[$evalId])) {
                    $db->prepare('DELETE FROM evaluations WHERE id = ?')->execute([$evalId]);
                }
            }
            
            // ============================================
            // 3. ÉPREUVES
            // ============================================
            $existingEpIds = [];
            $stmt = $db->prepare('SELECT id FROM epreuves WHERE feuille_id = ?');
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $row) {
                $existingEpIds[$row['id']] = true;
            }
            
            $submittedEpIds = [];
            
            foreach ($epreuves as $epreuve) {
                $epId = $epreuve['id'] ?? null;
                
                if ($epId && isset($existingEpIds[$epId])) {
                    $submittedEpIds[$epId] = true;
                    $db->prepare('
                        UPDATE epreuves SET 
                            nom = ?, formule = ?, coefficient = ?, ordre = ?,
                            updated_at = datetime(\'now\')
                        WHERE id = ?
                    ')->execute([
                        $epreuve['nom'] ?? '',
                        $epreuve['formule'] ?? '[]',
                        (float)($epreuve['coefficient'] ?? 1),
                        (int)($epreuve['ordre'] ?? 0),
                        $epId
                    ]);
                } else {
                    $newEpId = $epreuve['id'] ?? UUID::generate();
                    $submittedEpIds[$newEpId] = true;
                    
                    $db->prepare('
                        INSERT INTO epreuves (id, feuille_id, nom, formule, coefficient, ordre)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ')->execute([
                        $newEpId,
                        $id,
                        $epreuve['nom'] ?? '',
                        $epreuve['formule'] ?? '[]',
                        (float)($epreuve['coefficient'] ?? 1),
                        (int)($epreuve['ordre'] ?? 0)
                    ]);
                }
            }
            
            // Supprimer les épreuves absentes
            foreach ($existingEpIds as $epId => $v) {
                if (!isset($submittedEpIds[$epId])) {
                    $db->prepare('DELETE FROM epreuves WHERE id = ?')->execute([$epId]);
                }
            }
            
            // ============================================
            // 4. NOTES D'ÉVALUATIONS
            // ============================================
            $stmt = $db->prepare('SELECT id FROM eleves WHERE feuille_id = ?');
            $stmt->execute([$id]);
            $currentEleveIds = [];
            foreach ($stmt->fetchAll() as $row) {
                $currentEleveIds[$row['id']] = true;
            }
            
            $stmt = $db->prepare('SELECT id FROM evaluations WHERE feuille_id = ?');
            $stmt->execute([$id]);
            $currentEvalIds = [];
            foreach ($stmt->fetchAll() as $row) {
                $currentEvalIds[$row['id']] = true;
            }
            
            // S'assurer que les entrées notes_evaluations existent
            foreach ($currentEleveIds as $eleveId => $v) {
                foreach ($currentEvalIds as $evalId => $v2) {
                    $stmt = $db->prepare('SELECT id FROM notes_evaluations WHERE eleve_id = ? AND evaluation_id = ?');
                    $stmt->execute([$eleveId, $evalId]);
                    if (!$stmt->fetch()) {
                        $db->prepare('INSERT INTO notes_evaluations (id, eleve_id, evaluation_id) VALUES (?, ?, ?)')
                           ->execute([UUID::generate(), $eleveId, $evalId]);
                    }
                }
            }
            
            // Mettre à jour les notes reçues
            foreach ($notesEvaluations as $key => $note) {
                $parts = explode(':', $key);
                if (count($parts) !== 2) continue;
                list($eleveId, $evalId) = $parts;
                
                if (!isset($currentEleveIds[$eleveId]) || !isset($currentEvalIds[$evalId])) continue;
                
                $stmt = $db->prepare('SELECT id FROM notes_evaluations WHERE eleve_id = ? AND evaluation_id = ?');
                $stmt->execute([$eleveId, $evalId]);
                $noteEntry = $stmt->fetch();
                
                if ($noteEntry) {
                    if ($note === null || $note === '' || $note === 'null') {
                        $db->prepare('UPDATE notes_evaluations SET note = NULL, updated_at = datetime(\'now\') WHERE id = ?')
                           ->execute([$noteEntry['id']]);
                    } else {
                        $noteFloat = (float)$note;
                        if ($noteFloat < 0) $noteFloat = 0;
                        $db->prepare('UPDATE notes_evaluations SET note = ?, updated_at = datetime(\'now\') WHERE id = ?')
                           ->execute([$noteFloat, $noteEntry['id']]);
                    }
                }
            }
            
            // ============================================
            // 5. RECALCULER LES NOTES D'ÉPREUVES
            // ============================================
            $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ?');
            $stmt->execute([$id]);
            $allEpreuves = $stmt->fetchAll();
            
            $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ?');
            $stmt->execute([$id]);
            $allEvaluations = $stmt->fetchAll();
            
            foreach ($currentEleveIds as $eleveId => $v) {
                // Créer les entrées notes_epreuves si nécessaire
                foreach ($allEpreuves as $ep) {
                    $stmt = $db->prepare('SELECT id FROM notes_epreuves WHERE eleve_id = ? AND epreuve_id = ?');
                    $stmt->execute([$eleveId, $ep['id']]);
                    if (!$stmt->fetch()) {
                        $db->prepare('INSERT INTO notes_epreuves (id, eleve_id, epreuve_id) VALUES (?, ?, ?)')
                           ->execute([UUID::generate(), $eleveId, $ep['id']]);
                    }
                }
                
                // Récupérer les notes d'évaluations
                $stmt = $db->prepare('SELECT evaluation_id, note FROM notes_evaluations WHERE eleve_id = ?');
                $stmt->execute([$eleveId]);
                $notesEval = [];
                foreach ($stmt->fetchAll() as $n) {
                    $notesEval[$n['evaluation_id']] = $n['note'];
                }
                
                // Calculer chaque épreuve
                foreach ($allEpreuves as $epreuve) {
                    $note = NoteService::calculateEpreuveNote($epreuve['formule'], $notesEval, $allEvaluations);
                    if ($note !== null) {
                        $db->prepare('UPDATE notes_epreuves SET note = ?, updated_at = datetime(\'now\') WHERE eleve_id = ? AND epreuve_id = ?')
                           ->execute([$note, $eleveId, $epreuve['id']]);
                    } else {
                        $db->prepare('UPDATE notes_epreuves SET note = NULL, updated_at = datetime(\'now\') WHERE eleve_id = ? AND epreuve_id = ?')
                           ->execute([$eleveId, $epreuve['id']]);
                    }
                }
            }
            
            $db->commit();
            
            Response::success(null, 'Modifications sauvegardées avec succès.');
            
        } catch (\Exception $e) {
            $db->rollBack();
            Response::error('Erreur lors de la sauvegarde: ' . $e->getMessage());
        }
    }
}