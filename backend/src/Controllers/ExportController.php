<?php
/**
 * Export Controller - Export des notes en CSV et JSON
 * 
 * Permet d'exporter les données d'une feuille de notes
 */

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Services\NoteService;

class ExportController
{
    /**
     * Exporter une feuille au format CSV
     * GET /api/feuilles/{feuilleId}/export/csv
     */
    public static function exportCsv(string $feuilleId): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$feuilleId, $authUser['id']]);
        $feuille = $stmt->fetch();
        if (!$feuille) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        // Récupérer les données
        $stmt = $db->prepare('SELECT * FROM eleves WHERE feuille_id = ? ORDER BY numero_ordre ASC');
        $stmt->execute([$feuilleId]);
        $eleves = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$feuilleId]);
        $evaluations = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$feuilleId]);
        $epreuves = $stmt->fetchAll();
        
        $notesEvaluations = [];
        if (!empty($eleves)) {
            $stmt = $db->prepare('SELECT * FROM notes_evaluations WHERE eleve_id IN (SELECT id FROM eleves WHERE feuille_id = ?)');
            $stmt->execute([$feuilleId]);
            foreach ($stmt->fetchAll() as $note) {
                $notesEvaluations[$note['eleve_id']][$note['evaluation_id']] = $note['note'];
            }
        }
        
        $notesEpreuves = [];
        if (!empty($eleves)) {
            $stmt = $db->prepare('SELECT * FROM notes_epreuves WHERE eleve_id IN (SELECT id FROM eleves WHERE feuille_id = ?)');
            $stmt->execute([$feuilleId]);
            foreach ($stmt->fetchAll() as $note) {
                $notesEpreuves[$note['eleve_id']][$note['epreuve_id']] = $note['note'];
            }
        }
        
        $elevesWithGrades = NoteService::calculateGrades($eleves, $evaluations, $epreuves, $notesEvaluations, $notesEpreuves);
        
        // Générer le CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="notes_' . $feuille['classe'] . '_' . $feuille['matiere'] . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM pour UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // En-têtes
        $headers = ['N°', 'Nom', 'Prénom', 'Identifiant'];
        foreach ($evaluations as $eval) {
            $headers[] = $eval['nom'] . ' (/' . $eval['bareme'] . ')';
        }
        foreach ($epreuves as $ep) {
            $headers[] = $ep['nom'] . ' (coef ' . $ep['coefficient'] . ')';
        }
        $headers[] = 'Moyenne';
        $headers[] = 'Rang';
        $headers[] = 'Observation';
        
        fputcsv($output, $headers, ';');
        
        // Données
        foreach ($elevesWithGrades as $eleve) {
            $row = [
                $eleve['numero_ordre'],
                $eleve['nom'],
                $eleve['prenom'],
                $eleve['identifiant'],
            ];
            
            foreach ($evaluations as $eval) {
                $note = $eleve['notes_evaluations'][$eval['id']] ?? '-';
                $row[] = str_replace(".", ",", $note !== null ? $note : '-');
            }
            
            foreach ($epreuves as $ep) {
                $note = $eleve['notes_epreuves'][$ep['id']] ?? '-';
                $row[] = str_replace(".", ",", $note !== null ? $note : '-');
            }
            
            $row[] = str_replace(".", ",", $eleve['moyenne'] !== null ? $eleve['moyenne'] : '-');
            $row[] = $eleve['rang'];
            $row[] = $eleve['observation'];
            
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Exporter une feuille au format JSON
     * GET /api/feuilles/{feuilleId}/export/json
     */
    public static function exportJson(string $feuilleId): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$feuilleId, $authUser['id']]);
        $feuille = $stmt->fetch();
        if (!$feuille) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        // Mêmes requêtes que pour le CSV
        $stmt = $db->prepare('SELECT * FROM eleves WHERE feuille_id = ? ORDER BY numero_ordre ASC');
        $stmt->execute([$feuilleId]);
        $eleves = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$feuilleId]);
        $evaluations = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$feuilleId]);
        $epreuves = $stmt->fetchAll();
        
        $notesEvaluations = [];
        if (!empty($eleves)) {
            $stmt = $db->prepare('SELECT * FROM notes_evaluations WHERE eleve_id IN (SELECT id FROM eleves WHERE feuille_id = ?)');
            $stmt->execute([$feuilleId]);
            foreach ($stmt->fetchAll() as $note) {
                $notesEvaluations[$note['eleve_id']][$note['evaluation_id']] = $note['note'];
            }
        }
        
        $notesEpreuves = [];
        if (!empty($eleves)) {
            $stmt = $db->prepare('SELECT * FROM notes_epreuves WHERE eleve_id IN (SELECT id FROM eleves WHERE feuille_id = ?)');
            $stmt->execute([$feuilleId]);
            foreach ($stmt->fetchAll() as $note) {
                $notesEpreuves[$note['eleve_id']][$note['epreuve_id']] = $note['note'];
            }
        }
        
        $elevesWithGrades = NoteService::calculateGrades($eleves, $evaluations, $epreuves, $notesEvaluations, $notesEpreuves);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="notes_' . $feuille['classe'] . '_' . $feuille['matiere'] . '.json"');
        
        echo json_encode([
            'feuille' => $feuille,
            'evaluations' => $evaluations,
            'epreuves' => $epreuves,
            'eleves' => $elevesWithGrades
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}