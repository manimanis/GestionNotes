<?php
/**
 * Feuille Controller - Gestion des feuilles de notes
 * CRUD complet : Créer, Lire, Modifier, Supprimer
 */

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\UUID;
use App\Middleware\AuthMiddleware;

class FeuilleController
{
    /**
     * Lister toutes les feuilles de l'enseignant connecté
     * GET /api/feuilles
     */
    public static function index(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Filtre optionnel par année scolaire
        $anneeScolaire = $_GET['annee_scolaire'] ?? null;
        
        $whereClause = 'fn.enseignant_id = ?';
        $params = [$authUser['id']];
        
        if ($anneeScolaire) {
            $whereClause .= ' AND fn.annee_scolaire = ?';
            $params[] = $anneeScolaire;
        }
        
        $stmt = $db->prepare("
            SELECT fn.*, 
                   (SELECT COUNT(*) FROM eleves WHERE feuille_id = fn.id) as nombre_eleves,
                   (SELECT COUNT(*) FROM evaluations WHERE feuille_id = fn.id) as nombre_evaluations
            FROM feuilles_notes fn
            WHERE {$whereClause}
            ORDER BY fn.annee_scolaire DESC, fn.classe ASC, fn.trimestre ASC
        ");
        $stmt->execute($params);
        $feuilles = $stmt->fetchAll();
        
        // Nombre total d'élèves uniques (par identifiant) à travers toutes les feuilles filtrées
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT e.identifiant) as total
            FROM eleves e
            JOIN feuilles_notes fn ON e.feuille_id = fn.id
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $totalEleves = (int) ($stmt->fetch()['total'] ?? 0);
        
        // Nombre total de classes distinctes dans les feuilles filtrées
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT classe) as total
            FROM feuilles_notes fn
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $totalClasses = (int) ($stmt->fetch()['total'] ?? 0);
        
        Response::success([
            'feuilles' => $feuilles,
            'stats' => [
                'total_eleves' => $totalEleves,
                'total_classes' => $totalClasses,
            ],
        ]);
    }

    /**
     * Afficher une feuille avec tous ses détails
     * GET /api/feuilles/{id}
     */
    public static function show(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Récupérer la feuille
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$id, $authUser['id']]);
        $feuille = $stmt->fetch();
        
        if (!$feuille) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        // Récupérer les élèves
        $stmt = $db->prepare('SELECT * FROM eleves WHERE feuille_id = ? ORDER BY numero_ordre ASC');
        $stmt->execute([$id]);
        $eleves = $stmt->fetchAll();
        
        // Récupérer les évaluations
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$id]);
        $evaluations = $stmt->fetchAll();
        
        // Récupérer les épreuves
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$id]);
        $epreuves = $stmt->fetchAll();
        
        // Récupérer les notes d'évaluations
        $notesEvaluations = [];
        if (!empty($eleves)) {
            $stmt = $db->prepare('SELECT * FROM notes_evaluations WHERE eleve_id IN (SELECT id FROM eleves WHERE feuille_id = ?)');
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $note) {
                $notesEvaluations[$note['eleve_id']][$note['evaluation_id']] = $note['note'];
            }
        }
        
        // Récupérer les notes d'épreuves
        $notesEpreuves = [];
        if (!empty($eleves)) {
            $stmt = $db->prepare('SELECT * FROM notes_epreuves WHERE eleve_id IN (SELECT id FROM eleves WHERE feuille_id = ?)');
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $note) {
                $notesEpreuves[$note['eleve_id']][$note['epreuve_id']] = $note['note'];
            }
        }
        
        // Calculer les moyennes et rangs
        $elevesWithNotes = \App\Services\NoteService::calculateGrades($eleves, $evaluations, $epreuves, $notesEvaluations, $notesEpreuves);
        
        Response::success([
            'feuille' => $feuille,
            'eleves' => $elevesWithNotes,
            'evaluations' => $evaluations,
            'epreuves' => $epreuves,
        ]);
    }

    /**
     * Créer une nouvelle feuille de notes
     * POST /api/feuilles
     */
    public static function store(): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $validator = new Validator($data);
        $errors = $validator
            ->required('classe', 'Classe')
            ->required('matiere', 'Matière')
            ->required('trimestre', 'Trimestre')
            ->inList('trimestre', ['1', '2', '3'], 'Trimestre')
            ->required('annee_scolaire', 'Année scolaire')
            ->validate();
        
        if ($errors) {
            Response::badRequest('Erreur de validation', $errors);
        }
        
        $db = Database::getInstance()->getConnection();
        $id = UUID::generate();
        
        $stmt = $db->prepare('
            INSERT INTO feuilles_notes (id, enseignant_id, classe, matiere, trimestre, annee_scolaire)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $id,
            $authUser['id'],
            $data['classe'],
            $data['matiere'],
            (int)$data['trimestre'],
            $data['annee_scolaire']
        ]);
        
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ?');
        $stmt->execute([$id]);
        
        Response::created($stmt->fetch(), 'Feuille de notes créée avec succès.');
    }

    /**
     * Mettre à jour une feuille de notes
     * PUT /api/feuilles/{id}
     */
    public static function update(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier que la feuille existe
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$id, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $allowedFields = ['classe', 'matiere', 'trimestre', 'annee_scolaire'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'trimestre') {
                    if (!in_array((string)$data[$field], ['1', '2', '3'])) {
                        Response::badRequest('Le trimestre doit être 1, 2 ou 3.');
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
        
        $sql = 'UPDATE feuilles_notes SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $db->prepare($sql)->execute($params);
        
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ?');
        $stmt->execute([$id]);
        
        Response::success($stmt->fetch(), 'Feuille de notes mise à jour avec succès.');
    }

    /**
     * Dupliquer une feuille de notes (avec élèves, évaluations, épreuves et notes)
     * POST /api/feuilles/{id}/duplicate
     */
    public static function duplicate(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier que la feuille source existe
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$id, $authUser['id']]);
        $feuilleSource = $stmt->fetch();
        
        if (!$feuilleSource) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        
        // Début de la transaction
        $db->beginTransaction();
        
        try {
            // Créer la nouvelle feuille
            $nouveauId = UUID::generate();
            $nouvelleClasse = $data['classe'] ?? $feuilleSource['classe'];
            $nouvelleMatiere = $data['matiere'] ?? $feuilleSource['matiere'];
            $nouveauTrimestre = $data['trimestre'] ?? $feuilleSource['trimestre'];
            $nouvelleAnnee = $data['annee_scolaire'] ?? $feuilleSource['annee_scolaire'];
            
            $db->prepare('
                INSERT INTO feuilles_notes (id, enseignant_id, classe, matiere, trimestre, annee_scolaire)
                VALUES (?, ?, ?, ?, ?, ?)
            ')->execute([
                $nouveauId,
                $authUser['id'],
                $nouvelleClasse,
                $nouvelleMatiere,
                (int)$nouveauTrimestre,
                $nouvelleAnnee
            ]);
            
            // Récupérer les évaluations de la source
            $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ? ORDER BY ordre ASC');
            $stmt->execute([$id]);
            $evaluationsSource = $stmt->fetchAll();
            
            // Mapping anciens IDs → nouveaux IDs pour les évaluations
            $evaluationsMap = [];
            
            foreach ($evaluationsSource as $evalSource) {
                $nouvelEvalId = UUID::generate();
                $evaluationsMap[$evalSource['id']] = $nouvelEvalId;
                
                $db->prepare('
                    INSERT INTO evaluations (id, feuille_id, nom, date_evaluation, bareme, coefficient, ordre)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ')->execute([
                    $nouvelEvalId,
                    $nouveauId,
                    $evalSource['nom'],
                    $evalSource['date_evaluation'] ?? null,
                    $evalSource['bareme'] ?? 20,
                    $evalSource['coefficient'],
                    $evalSource['ordre']
                ]);
            }
            
            // Récupérer les élèves de la source
            $stmt = $db->prepare('SELECT * FROM eleves WHERE feuille_id = ? ORDER BY numero_ordre ASC');
            $stmt->execute([$id]);
            $elevesSource = $stmt->fetchAll();
            
            // Mapping anciens IDs → nouveaux IDs pour les élèves
            $elevesMap = [];
            
            foreach ($elevesSource as $eleveSource) {
                $nouvelEleveId = UUID::generate();
                $elevesMap[$eleveSource['id']] = $nouvelEleveId;
                
                // Générer un nouvel identifiant unique pour l'élève dupliqué
                $nouvelIdentifiant = $eleveSource['identifiant'];
                
                $db->prepare('
                    INSERT INTO eleves (id, feuille_id, identifiant, numero_ordre, nom, prenom, nom_tuteur, prenom_tuteur)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ')->execute([
                    $nouvelEleveId,
                    $nouveauId,
                    $nouvelIdentifiant,
                    $eleveSource['numero_ordre'],
                    $eleveSource['nom'],
                    $eleveSource['prenom'],
                    $eleveSource['nom_tuteur'] ?? null,
                    $eleveSource['prenom_tuteur'] ?? null
                ]);
            }
            
            // Récupérer les épreuves de la source
            $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ? ORDER BY ordre ASC');
            $stmt->execute([$id]);
            $epreuvesSource = $stmt->fetchAll();
            
            // Mapping anciens IDs → nouveaux IDs pour les épreuves
            $epreuvesMap = [];
            
            foreach ($epreuvesSource as $epreuveSource) {
                $nouvelEpreuveId = UUID::generate();
                $epreuvesMap[$epreuveSource['id']] = $nouvelEpreuveId;
                
                // Mettre à jour la formule avec les nouveaux IDs d'évaluations
                $formule = json_decode($epreuveSource['formule'], true);
                if (is_array($formule)) {
                    foreach ($formule as &$item) {
                        if (isset($item['eval']) && isset($evaluationsMap[$item['eval']])) {
                            $item['eval'] = $evaluationsMap[$item['eval']];
                        }
                    }
                    unset($item);
                    $nouvelleFormule = json_encode($formule);
                } else {
                    $nouvelleFormule = $epreuveSource['formule'];
                }
                
                $db->prepare('
                    INSERT INTO epreuves (id, feuille_id, nom, formule, coefficient, ordre)
                    VALUES (?, ?, ?, ?, ?, ?)
                ')->execute([
                    $nouvelEpreuveId,
                    $nouveauId,
                    $epreuveSource['nom'],
                    $nouvelleFormule,
                    $epreuveSource['coefficient'],
                    $epreuveSource['ordre']
                ]);
            }
            
            // Dupliquer les notes d'évaluations (avec les nouveaux IDs élèves et évaluations)
            if (!empty($elevesMap)) {
                $stmt = $db->prepare('
                    SELECT ne.* FROM notes_evaluations ne
                    JOIN eleves e ON ne.eleve_id = e.id
                    WHERE e.feuille_id = ?
                ');
                $stmt->execute([$id]);
                $notesEvaluationsSource = $stmt->fetchAll();
                
                foreach ($notesEvaluationsSource as $noteEval) {
                    if (isset($elevesMap[$noteEval['eleve_id']]) && isset($evaluationsMap[$noteEval['evaluation_id']])) {
                        $db->prepare('
                            INSERT INTO notes_evaluations (id, eleve_id, evaluation_id, note)
                            VALUES (?, ?, ?, ?)
                        ')->execute([
                            UUID::generate(),
                            $elevesMap[$noteEval['eleve_id']],
                            $evaluationsMap[$noteEval['evaluation_id']],
                            $noteEval['note']
                        ]);
                    }
                }
            }
            
            // Dupliquer les notes d'épreuves (avec les nouveaux IDs élèves)
            if (!empty($elevesMap) && !empty($epreuvesMap)) {
                $stmt = $db->prepare('
                    SELECT ne.* FROM notes_epreuves ne
                    JOIN eleves e ON ne.eleve_id = e.id
                    WHERE e.feuille_id = ?
                ');
                $stmt->execute([$id]);
                $notesEpreuvesSource = $stmt->fetchAll();
                
                foreach ($notesEpreuvesSource as $noteEpreuve) {
                    if (isset($elevesMap[$noteEpreuve['eleve_id']]) && isset($epreuvesMap[$noteEpreuve['epreuve_id']])) {
                        $db->prepare('
                            INSERT INTO notes_epreuves (id, eleve_id, epreuve_id, note)
                            VALUES (?, ?, ?, ?)
                        ')->execute([
                            UUID::generate(),
                            $elevesMap[$noteEpreuve['eleve_id']],
                            $epreuvesMap[$noteEpreuve['epreuve_id']],
                            $noteEpreuve['note']
                        ]);
                    }
                }
            }
            
            $db->commit();
            
        } catch (\Exception $e) {
            $db->rollBack();
            Response::error('Erreur lors de la duplication: ' . $e->getMessage());
        }
        
        // Récupérer la nouvelle feuille
        $stmt = $db->prepare('
            SELECT fn.*, 
                   (SELECT COUNT(*) FROM eleves WHERE feuille_id = fn.id) as nombre_eleves,
                   (SELECT COUNT(*) FROM evaluations WHERE feuille_id = fn.id) as nombre_evaluations
            FROM feuilles_notes fn
            WHERE fn.id = ?
        ');
        $stmt->execute([$nouveauId]);
        
        Response::created($stmt->fetch(), 'Feuille de notes dupliquée avec succès. Élèves, évaluations, épreuves et notes copiés.');
    }

    /**
     * Importer des données (élèves + notes) dans une feuille
     * POST /api/feuilles/{id}/import-data
     *
     * L'ordre des colonnes est fixé :
     * N° Ordre | Nom | Prénom | Identifiant | Note Eval 1 | Note Eval 2 | ...
     *
     * Les évaluations sont identifiées par leur ordre (ordre ASC).
     * Si un élève existe déjà (identifiant), ses notes sont mises à jour.
     * Si un élève n'existe pas, il est créé.
     */
    public static function importData(string $id): void
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
        $rows = $data['rows'] ?? [];

        if (empty($rows)) {
            Response::badRequest('Aucune donnée à importer.');
        }

        // Récupérer les évaluations de la feuille (triées par ordre)
        $stmt = $db->prepare('SELECT * FROM evaluations WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$id]);
        $evaluations = $stmt->fetchAll();

        if (empty($evaluations)) {
            Response::badRequest('Aucune évaluation définie dans cette feuille. Veuillez d\'abord créer des évaluations.');
        }

        // Récupérer les épreuves de la feuille
        $stmt = $db->prepare('SELECT * FROM epreuves WHERE feuille_id = ? ORDER BY ordre ASC');
        $stmt->execute([$id]);
        $epreuves = $stmt->fetchAll();

        $imported = 0;
        $updated = 0;
        $errors = [];

        $db->beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $numeroOrdre = isset($row['numero_ordre']) ? (int)$row['numero_ordre'] : ($index + 1);
                $nom = trim($row['nom'] ?? '');
                $prenom = trim($row['prenom'] ?? '');
                $identifiant = trim($row['identifiant'] ?? '');

                if (empty($nom) || empty($prenom) || empty($identifiant)) {
                    $errors[] = "Ligne " . ($index + 1) . ": champs manquants (nom, prénom et identifiant requis)";
                    continue;
                }

                // Vérifier si l'élève existe déjà (par identifiant dans cette feuille)
                $stmt = $db->prepare('SELECT id FROM eleves WHERE identifiant = ? AND feuille_id = ?');
                $stmt->execute([$identifiant, $id]);
                $existingEleve = $stmt->fetch();

                if ($existingEleve) {
                    $eleveId = $existingEleve['id'];
                    // Mettre à jour l'élève existant
                    $db->prepare('UPDATE eleves SET numero_ordre = ?, nom = ?, prenom = ?, updated_at = datetime(\'now\') WHERE id = ?')
                       ->execute([$numeroOrdre, $nom, $prenom, $eleveId]);
                    $updated++;
                } else {
                    // Créer un nouvel élève
                    $eleveId = UUID::generate();
                    $db->prepare('
                        INSERT INTO eleves (id, feuille_id, identifiant, numero_ordre, nom, prenom)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ')->execute([$eleveId, $id, $identifiant, $numeroOrdre, $nom, $prenom]);

                    // Créer les entrées vides pour les notes d'évaluations
                    foreach ($evaluations as $eval) {
                        $db->prepare('INSERT OR IGNORE INTO notes_evaluations (id, eleve_id, evaluation_id) VALUES (?, ?, ?)')
                           ->execute([UUID::generate(), $eleveId, $eval['id']]);
                    }

                    // Créer les entrées vides pour les notes d'épreuves
                    foreach ($epreuves as $ep) {
                        $db->prepare('INSERT OR IGNORE INTO notes_epreuves (id, eleve_id, epreuve_id) VALUES (?, ?, ?)')
                           ->execute([UUID::generate(), $eleveId, $ep['id']]);
                    }

                    $imported++;
                }

                // Sauvegarder les notes d'évaluations (colonnes 4+ = notes)
                // Les évaluations sont triées par ordre ASC, donc colonne index 4 = eval[0], 5 = eval[1], etc.
                $notesData = $row['notes'] ?? [];
                foreach ($notesData as $noteIndex => $noteValue) {
                    if ($noteIndex >= count($evaluations)) break;

                    $evalId = $evaluations[$noteIndex]['id'];
                    $bareme = (float)$evaluations[$noteIndex]['bareme'];

                    // Trouver ou créer l'entrée notes_evaluations
                    $stmt = $db->prepare('SELECT id FROM notes_evaluations WHERE eleve_id = ? AND evaluation_id = ?');
                    $stmt->execute([$eleveId, $evalId]);
                    $noteEntry = $stmt->fetch();

                    if (!$noteEntry) {
                        $noteEntryId = UUID::generate();
                        $db->prepare('INSERT INTO notes_evaluations (id, eleve_id, evaluation_id) VALUES (?, ?, ?)')
                           ->execute([$noteEntryId, $eleveId, $evalId]);
                    } else {
                        $noteEntryId = $noteEntry['id'];
                    }

                    // Traiter la note
                    $noteStr = trim((string)$noteValue);
                    if ($noteStr === '' || $noteStr === '-' || $noteStr === 'null') {
                        $db->prepare('UPDATE notes_evaluations SET note = NULL, updated_at = datetime(\'now\') WHERE id = ?')
                           ->execute([$noteEntryId]);
                    } else {
                        $note = (float)$noteStr;
                        if ($note < 0) $note = 0;
                        if ($note > $bareme) $note = $bareme;

                        $db->prepare('UPDATE notes_evaluations SET note = ?, updated_at = datetime(\'now\') WHERE id = ?')
                           ->execute([$note, $noteEntryId]);
                    }
                }

                // Recalculer les notes d'épreuves pour cet élève
                self::recalculateEpreuvesForEleve($id, $eleveId);
            }

            $db->commit();

        } catch (\Exception $e) {
            $db->rollBack();
            Response::error('Erreur lors de l\'importation: ' . $e->getMessage());
        }

        Response::success([
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'total_rows' => count($rows)
        ], "{$imported} élèves importés, {$updated} mis à jour.");
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
            $note = \App\Services\NoteService::calculateEpreuveNote($epreuve['formule'], $notesEval, $evaluations);
            if ($note !== null) {
                $db->prepare('UPDATE notes_epreuves SET note = ?, updated_at = datetime(\'now\') WHERE eleve_id = ? AND epreuve_id = ?')
                   ->execute([$note, $eleveId, $epreuve['id']]);
            }
        }
    }

    /**
     * Supprimer une feuille de notes
     * DELETE /api/feuilles/{id}
     */
    public static function destroy(string $id): void
    {
        $authUser = AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('SELECT * FROM feuilles_notes WHERE id = ? AND enseignant_id = ?');
        $stmt->execute([$id, $authUser['id']]);
        if (!$stmt->fetch()) {
            Response::notFound('Feuille de notes non trouvée.');
        }
        
        // La suppression en cascade gère les élèves, évaluations, etc.
        $db->prepare('DELETE FROM feuilles_notes WHERE id = ?')->execute([$id]);
        
        Response::success(null, 'Feuille de notes supprimée avec succès.');
    }
}