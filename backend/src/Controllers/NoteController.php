<?php
/**
 * Note Controller - Gestion des notes d'évaluations et d'épreuves
 * 
 * Permet de sauvegarder les notes des élèves avec recalcul automatique
 */

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\UUID;
use App\Middleware\AuthMiddleware;
use App\Services\NoteService;

class NoteController
{
    /**
     * Enregistrer une note d'évaluation
     * POST /api/notes-evaluations
     */
    public static function saveEvaluationNote(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $validator = new Validator($data);
        $errors = $validator
            ->required('eleve_id', 'Élève')
            ->required('evaluation_id', 'Évaluation')
            ->numeric('note', 'Note')
            ->between('note', 0, 20, 'Note')
            ->validate();
        
        if ($errors) {
            Response::badRequest('Erreur de validation', $errors);
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier les droits
        $stmt = $db->prepare('
            SELECT ne.*, ev.feuille_id FROM notes_evaluations ne
            JOIN evaluations ev ON ne.evaluation_id = ev.id
            JOIN feuilles_notes fn ON ev.feuille_id = fn.id
            WHERE ne.eleve_id = ? AND ne.evaluation_id = ? AND fn.enseignant_id = ?
        ');
        $stmt->execute([$data['eleve_id'], $data['evaluation_id'], $authUser['id']]);
        $noteEval = $stmt->fetch();
        
        if (!$noteEval) {
            Response::notFound('Note d\'évaluation non trouvée ou accès non autorisé.');
        }
        
        $note = $data['note'];
        
        // Si note est vide ou null, on met NULL
        if ($note === '' || $note === null || $note === 'null') {
            $stmt = $db->prepare('UPDATE notes_evaluations SET note = NULL, updated_at = datetime(\'now\') WHERE id = ?');
            $stmt->execute([$noteEval['id']]);
        } else {
            $note = (float)$note;
            if ($note < 0 || $note > 20) {
                Response::badRequest('La note doit être comprise entre 0 et 20.');
            }
            
            // Vérifier le barème de l'évaluation
            $stmt = $db->prepare('SELECT bareme FROM evaluations WHERE id = ?');
            $stmt->execute([$data['evaluation_id']]);
            $eval = $stmt->fetch();
            $bareme = (float)$eval['bareme'];
            
            if ($note > $bareme) {
                Response::badRequest("La note ne peut pas dépasser le barème ({$bareme}).");
            }
            
            $stmt = $db->prepare('UPDATE notes_evaluations SET note = ?, updated_at = datetime(\'now\') WHERE id = ?');
            $stmt->execute([$note, $noteEval['id']]);
        }
        
        // Recalculer les notes d'épreuves pour cette feuille
        self::recalculateEpreuvesForEleve($noteEval['feuille_id'], $data['eleve_id']);
        
        Response::success([
            'eleve_id' => $data['eleve_id'],
            'evaluation_id' => $data['evaluation_id'],
            'note' => $note ?? null
        ], 'Note enregistrée avec succès.');
    }

    /**
     * Enregistrer plusieurs notes à la fois (batch)
     * POST /api/notes-evaluations/batch
     */
    public static function saveBatchEvaluationNotes(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (!isset($data['notes']) || !is_array($data['notes'])) {
            Response::badRequest('Format invalide. Un tableau "notes" est requis.');
        }
        
        $db = Database::getInstance()->getConnection();
        $feuilleIds = [];
        
        foreach ($data['notes'] as $item) {
            if (!isset($item['eleve_id']) || !isset($item['evaluation_id']) || !isset($item['note'])) {
                continue;
            }
            
            $note = $item['note'];
            
            // Vérifier les droits
            $stmt = $db->prepare('
                SELECT ne.*, ev.feuille_id FROM notes_evaluations ne
                JOIN evaluations ev ON ne.evaluation_id = ev.id
                JOIN feuilles_notes fn ON ev.feuille_id = fn.id
                WHERE ne.eleve_id = ? AND ne.evaluation_id = ? AND fn.enseignant_id = ?
            ');
            $stmt->execute([$item['eleve_id'], $item['evaluation_id'], $authUser['id']]);
            $noteEval = $stmt->fetch();
            
            if ($noteEval) {
                if ($note === '' || $note === null || $note === 'null') {
                    $db->prepare('UPDATE notes_evaluations SET note = NULL, updated_at = datetime(\'now\') WHERE id = ?')
                       ->execute([$noteEval['id']]);
                } else {
                    $noteFloat = (float)$note;
                    if ($noteFloat >= 0 && $noteFloat <= 20) {
                        $db->prepare('UPDATE notes_evaluations SET note = ?, updated_at = datetime(\'now\') WHERE id = ?')
                           ->execute([$noteFloat, $noteEval['id']]);
                    }
                }
                $feuilleIds[$noteEval['feuille_id']] = true;
            }
        }
        
        // Recalculer pour chaque feuille concernée
        foreach (array_keys($feuilleIds) as $feuilleId) {
            $stmt = $db->prepare('SELECT id FROM eleves WHERE feuille_id = ?');
            $stmt->execute([$feuilleId]);
            foreach ($stmt->fetchAll() as $eleve) {
                self::recalculateEpreuvesForEleve($feuilleId, $eleve['id']);
            }
        }
        
        Response::success(null, 'Notes enregistrées avec succès.');
    }

    /**
     * Enregistrer une note d'épreuve (normalement calculée automatiquement)
     * POST /api/notes-epreuves
     */
    public static function saveEpreuveNote(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $validator = new Validator($data);
        $errors = $validator
            ->required('eleve_id', 'Élève')
            ->required('epreuve_id', 'Épreuve')
            ->numeric('note', 'Note')
            ->between('note', 0, 20, 'Note')
            ->validate();
        
        if ($errors) {
            Response::badRequest('Erreur de validation', $errors);
        }
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('
            SELECT ne.*, ep.feuille_id FROM notes_epreuves ne
            JOIN epreuves ep ON ne.epreuve_id = ep.id
            JOIN feuilles_notes fn ON ep.feuille_id = fn.id
            WHERE ne.eleve_id = ? AND ne.epreuve_id = ? AND fn.enseignant_id = ?
        ');
        $stmt->execute([$data['eleve_id'], $data['epreuve_id'], $authUser['id']]);
        $noteEp = $stmt->fetch();
        
        if (!$noteEp) {
            Response::notFound('Note d\'épreuve non trouvée ou accès non autorisé.');
        }
        
        $note = (float)$data['note'];
        if ($note < 0 || $note > 20) {
            Response::badRequest('La note doit être comprise entre 0 et 20.');
        }
        
        $stmt = $db->prepare('UPDATE notes_epreuves SET note = ?, updated_at = datetime(\'now\') WHERE id = ?');
        $stmt->execute([$note, $noteEp['id']]);
        
        Response::success([
            'eleve_id' => $data['eleve_id'],
            'epreuve_id' => $data['epreuve_id'],
            'note' => $note
        ], 'Note d\'épreuve enregistrée avec succès.');
    }

    /**
     * Recalculer les notes d'épreuves pour un élève
     */
    private static function recalculateEpreuvesForEleve(string $feuilleId, string $eleveId): void
    {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ?');
        $stmt->execute([$feuilleId]);
        $epreuves = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ?');
        $stmt->execute([$feuilleId]);
        $evaluations = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT evaluation_id, note FROM notes_evaluations WHERE eleve_id = ?');
        $stmt->execute([$eleveId]);
        $notesEval = [];
        foreach ($stmt->fetchAll() as $n) {
            $notesEval[$n['evaluation_id']] = $n['note'];
        }
        
        foreach ($epreuves as $epreuve) {
            $note = NoteService::calculateEpreuveNote($epreuve['formule'], $notesEval, $evaluations);
            if ($note !== null) {
                $stmt = $db->prepare('UPDATE notes_epreuves SET note = ?, updated_at = datetime(\'now\') WHERE eleve_id = ? AND epreuve_id = ?');
                $stmt->execute([$note, $eleveId, $epreuve['id']]);
            }
        }
    }
}