<?php
/**
 * Stats Controller - Statistiques et graphiques pour une feuille de notes
 */

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Services\NoteService;

class StatsController
{
    /**
     * Obtenir les statistiques d'une feuille de notes
     * GET /api/feuilles/{feuilleId}/stats
     */
    public static function index(string $feuilleId): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier la feuille
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$feuilleId, $authUser['id']]);
        $feuille = $stmt->fetch();
        if (!$feuille) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        // Récupérer toutes les données
        $stmt = $db->prepare('SELECT * FROM eleves WHERE feuille_id = ? ORDER BY numero_ordre ASC');
        $stmt->execute([$feuilleId]);
        $eleves = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$feuilleId]);
        $evaluations = $stmt->fetchAll();
        
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$feuilleId]);
        $epreuves = $stmt->fetchAll();
        
        // Récupérer les notes
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
        
        // Calculer les statistiques
        $elevesWithGrades = NoteService::calculateGrades($eleves, $evaluations, $epreuves, $notesEvaluations, $notesEpreuves);
        $stats = NoteService::getStatistics($elevesWithGrades);
        
        // Statistiques supplémentaires
        
        // Moyennes par évaluation
        $moyennesEvaluations = [];
        foreach ($evaluations as $eval) {
            $notes = array_column(array_filter($elevesWithGrades, function($e) use ($eval) {
                return isset($e['notes_evaluations'][$eval['id']]) && $e['notes_evaluations'][$eval['id']] !== null;
            }), 'notes_evaluations');
            
            $notesEval = array_map(function($n) use ($eval) { return $n[$eval['id']] ?? null; }, $notes);
            $notesEval = array_filter($notesEval, function($n) { return $n !== null; });
            
            if (!empty($notesEval)) {
                $moyennesEvaluations[] = [
                    'nom' => $eval['nom'],
                    'moyenne' => round(array_sum($notesEval) / count($notesEval), 2),
                    'coefficient' => $eval['coefficient'],
                    'bareme' => $eval['bareme'],
                ];
            }
        }
        
        // Distribution des évaluations (histogramme)
        $distributionEvaluations = [];
        $palier = [0, 5, 8, 10, 12, 14, 16, 18, 20];
        foreach ($evaluations as $eval) {
            $dist = array_fill(0, count($palier) - 1, 0);
            $stmt = $db->prepare('SELECT note FROM notes_evaluations WHERE evaluation_id = ? AND note IS NOT NULL');
            $stmt->execute([$eval['id']]);
            foreach ($stmt->fetchAll() as $n) {
                $note = (float)$n['note'];
                for ($i = 0; $i < count($palier) - 1; $i++) {
                    if ($note >= $palier[$i] && $note < $palier[$i + 1]) {
                        $dist[$i]++;
                        break;
                    }
                }
                if ($note == 20) $dist[count($dist) - 1]++;
            }
            $distributionEvaluations[] = [
                'nom' => $eval['nom'],
                'distribution' => $dist
            ];
        }
        
        Response::success([
            'feuille' => $feuille,
            'stats' => $stats,
            'moyennes_evaluations' => $moyennesEvaluations,
            'distribution_evaluations' => $distributionEvaluations,
            'palier' => $palier,
        ]);
    }
}