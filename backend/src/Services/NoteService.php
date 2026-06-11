<?php
/**
 * NoteService - Moteur de calcul des notes, épreuves, moyennes et classement
 * 
 * Ce service gère :
 * - Le calcul des notes d'épreuves à partir des formules
 * - Le calcul de la moyenne pondérée
 * - Le classement des élèves
 * - L'observation automatique
 */

namespace App\Services;

class NoteService
{
    /**
     * Calculer toutes les notes, moyennes et rangs pour une feuille
     *
     * @param array $eleves Liste des élèves
     * @param array $evaluations Liste des évaluations
     * @param array $epreuves Liste des épreuves avec formules
     * @param array $notesEvaluations Notes des évaluations [eleve_id][eval_id] => note
     * @param array $notesEpreuves Notes des épreuves (existantes) [eleve_id][epreuve_id] => note
     * @return array Élèves enrichis avec notes d'épreuves, moyennes, rangs, observations
     */
    public static function calculateGrades(
        array $eleves,
        array $evaluations,
        array $epreuves,
        array $notesEvaluations,
        array $notesEpreuves
    ): array {
        $result = [];
        $moyennes = [];

        foreach ($eleves as $eleve) {
            $eleveId = $eleve['id'];
            
            // 1. Calculer les notes d'épreuves à partir des formules
            $notesEpreuvesCalculees = [];
            foreach ($epreuves as $epreuve) {
                $noteEpreuve = self::calculateEpreuveNote(
                    $epreuve['formule'],
                    $notesEvaluations[$eleveId] ?? [],
                    $evaluations
                );
                $notesEpreuvesCalculees[$epreuve['id']] = $noteEpreuve;
                
                // Sauvegarder la note calculée
                $notesEpreuves[$eleveId][$epreuve['id']] = $noteEpreuve;
            }
            
            // 2. Calculer la moyenne pondérée
            $moyenne = self::calculateWeightedAverage(
                $notesEpreuvesCalculees,
                $epreuves
            );
            
            $moyennes[$eleveId] = $moyenne;
            
            // 3. Observation automatique
            $observation = self::generateObservation($moyenne);
            
            // 4. Préparer les données de l'élève enrichi
            $eleveData = $eleve;
            $eleveData['notes_evaluations'] = $notesEvaluations[$eleveId] ?? [];
            $eleveData['notes_epreuves'] = $notesEpreuvesCalculees;
            $eleveData['moyenne'] = $moyenne;
            $eleveData['observation'] = $observation;
            $eleveData['rang'] = 0; // Sera calculé après
            
            $result[] = $eleveData;
        }
        
        // 5. Calculer les rangs
        $result = self::calculateRanks($result);
        
        return $result;
    }

    /**
     * Calculer la note d'une épreuve basée sur sa formule
     *
     * Format de la formule : 
     * [{"eval":"eval_id","coef":0.5},{"eval":"eval_id2","coef":0.5}]
     *
     * @param string $formule JSON contenant la configuration
     * @param array $notesEvaluation Notes de l'élève [eval_id] => note
     * @param array $evaluations Liste des évaluations avec leurs barèmes
     * @return float|null Note calculée ou null si impossible
     */
    public static function calculateEpreuveNote(
        string $formule,
        array $notesEvaluation,
        array $evaluations
    ): ?float {
        $config = json_decode($formule, true);
        
        if (!$config || !is_array($config) || empty($config)) {
            return null;
        }
        
        // Index des évaluations par id
        $evalIndex = [];
        foreach ($evaluations as $e) {
            $evalIndex[$e['id']] = $e;
        }
        
        $totalNote = 0;
        $totalCoef = 0;
        $allPresent = true;
        
        foreach ($config as $item) {
            $evalId = $item['eval'] ?? null;
            $coef = (float)($item['coef'] ?? 1);
            
            if (!$evalId || !isset($notesEvaluation[$evalId])) {
                $allPresent = false;
                continue;
            }
            
            $note = (float)$notesEvaluation[$evalId];
            $bareme = isset($evalIndex[$evalId]) ? (float)$evalIndex[$evalId]['bareme'] : 20;
            
            // Normaliser sur 20 si nécessaire
            if ($bareme > 0 && $bareme !== 20.0) {
                $note = ($note / $bareme) * 20;
            }
            
            $totalNote += $note * $coef;
            $totalCoef += $coef;
        }
        
        if ($totalCoef === 0) {
            return null;
        }
        
        return round($totalNote / $totalCoef, 2);
    }

    /**
     * Calculer la moyenne pondérée à partir des notes d'épreuves
     *
     * Moyenne = Σ(note_epreuve × coefficient) / Σ(coefficients)
     *
     * @param array $notesEpreuves Notes d'épreuves calculées [epreuve_id] => note
     * @param array $epreuves Liste des épreuves avec coefficients
     * @return float|null Moyenne pondérée ou null si pas de données
     */
    public static function calculateWeightedAverage(
        array $notesEpreuves,
        array $epreuves
    ): ?float {
        $totalNote = 0;
        $totalCoef = 0;
        
        foreach ($epreuves as $epreuve) {
            $epreuveId = $epreuve['id'];
            $coef = (float)$epreuve['coefficient'];
            
            if (isset($notesEpreuves[$epreuveId]) && $notesEpreuves[$epreuveId] !== null) {
                $totalNote += (float)$notesEpreuves[$epreuveId] * $coef;
                $totalCoef += $coef;
            }
        }
        
        if ($totalCoef === 0) {
            return null;
        }
        
        return round($totalNote / $totalCoef, 2);
    }

    /**
     * Calculer les rangs des élèves
     * Gère les ex æquo
     *
     * @param array $eleves Liste des élèves avec leur moyenne
     * @return array Élèves avec rang calculé
     */
    public static function calculateRanks(array $eleves): array
    {
        if (empty($eleves)) {
            return $eleves;
        }
        
        // Trier par moyenne décroissante
        usort($eleves, function ($a, $b) {
            $moyA = $a['moyenne'] ?? -1;
            $moyB = $b['moyenne'] ?? -1;
            return $moyB <=> $moyA;
        });
        
        // Attribuer les rangs avec gestion des ex æquo
        $currentRang = 1;
        $previousMoyenne = null;
        $skipCount = 0;
        
        foreach ($eleves as $i => &$eleve) {
            $moyenne = $eleve['moyenne'] ?? null;
            
            if ($moyenne === null) {
                $eleve['rang'] = '-';
                continue;
            }
            
            if ($previousMoyenne !== null && $moyenne < $previousMoyenne) {
                $currentRang += $skipCount;
                $skipCount = 1;
            } elseif ($previousMoyenne !== null && $moyenne === $previousMoyenne) {
                $skipCount++;
            } else {
                $skipCount = 1;
            }
            
            $eleve['rang'] = $currentRang;
            $previousMoyenne = $moyenne;
        }
        unset($eleve);
        
        // Trier par numero_ordre pour le retour
        usort($eleves, function ($a, $b) {
            return (int)($a['numero_ordre'] ?? 0) <=> (int)($b['numero_ordre'] ?? 0);
        });
        
        return $eleves;
    }

    /**
     * Générer une observation automatique basée sur la moyenne
     *
     * @param float|null $moyenne Moyenne de l'élève
     * @return string Observation
     */
    public static function generateObservation(?float $moyenne): string
    {
        if ($moyenne === null) {
            return 'Notes insuffisantes';
        }
        
        if ($moyenne >= 16) {
            return 'Excellent';
        } elseif ($moyenne >= 14) {
            return 'Très bien';
        } elseif ($moyenne >= 12) {
            return 'Bien';
        } elseif ($moyenne >= 10) {
            return 'Passable';
        } else {
            return 'Insuffisant';
        }
    }

    /**
     * Obtenir les statistiques de la classe
     *
     * @param array $eleves Liste des élèves avec moyennes
     * @return array Statistiques
     */
    public static function getStatistics(array $eleves): array
    {
        $moyennes = array_filter(array_column($eleves, 'moyenne'), function ($m) {
            return $m !== null;
        });
        
        if (empty($moyennes)) {
            return [
                'moyenne_classe' => 0,
                'min' => 0,
                'max' => 0,
                'effectif' => count($eleves),
                'notes_renseignees' => 0,
                'distribution' => [0, 0, 0, 0, 0], // [0-7, 7-10, 10-13, 13-16, 16-20]
                'top5' => [],
            ];
        }
        
        $moyenneClasse = round(array_sum($moyennes) / count($moyennes), 2);
        $min = round(min($moyennes), 2);
        $max = round(max($moyennes), 2);
        
        // Distribution des notes
        $distribution = [0, 0, 0, 0, 0]; // [0-7, 7-10, 10-13, 13-16, 16-20]
        foreach ($moyennes as $m) {
            if ($m < 7) $distribution[0]++;
            elseif ($m < 10) $distribution[1]++;
            elseif ($m < 13) $distribution[2]++;
            elseif ($m < 16) $distribution[3]++;
            else $distribution[4]++;
        }
        
        // Top 5 élèves
        $sorted = $eleves;
        usort($sorted, function ($a, $b) {
            return ($b['moyenne'] ?? 0) <=> ($a['moyenne'] ?? 0);
        });
        $top5 = array_slice($sorted, 0, 5);
        
        return [
            'moyenne_classe' => $moyenneClasse,
            'min' => $min,
            'max' => $max,
            'effectif' => count($eleves),
            'notes_renseignees' => count($moyennes),
            'distribution' => $distribution,
            'top5' => array_map(function ($e) {
                return [
                    'nom' => $e['nom'],
                    'prenom' => $e['prenom'],
                    'moyenne' => $e['moyenne'],
                    'rang' => $e['rang'],
                ];
            }, $top5),
        ];
    }

    /**
     * Enregistrer les notes d'épreuves calculées en base de données
     *
     * @param string $feuilleId ID de la feuille
     * @param array $notesEpreuvesCalculées Notes calculées [eleve_id][epreuve_id] => note
     */
    public static function saveCalculatedEpreuveNotes(string $feuilleId, array $notesEpreuvesCalculées): void
    {
        $db = \App\Helpers\Database::getInstance()->getConnection();
        
        foreach ($notesEpreuvesCalculées as $eleveId => $epreuves) {
            foreach ($epreuves as $epreuveId => $note) {
                if ($note !== null) {
                    $stmt = $db->prepare('
                        UPDATE notes_epreuves 
                        SET note = ?, updated_at = datetime(\'now\')
                        WHERE eleve_id = ? AND epreuve_id = ?
                    ');
                    $stmt->execute([$note, $eleveId, $epreuveId]);
                }
            }
        }
    }
}