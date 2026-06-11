-- ============================================
-- Gestion de Notes - Schéma de la Base de Données
-- SQLite
-- ============================================

-- Création automatique de la DB si besoin
PRAGMA journal_mode=WAL;
PRAGMA foreign_keys=ON;

-- ============================================
-- TABLE : enseignants
-- ============================================
CREATE TABLE IF NOT EXISTS enseignants (
    id TEXT PRIMARY KEY,
    identifiant TEXT NOT NULL UNIQUE,
    nom TEXT NOT NULL,
    prenom TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    mot_de_passe TEXT NOT NULL,
    date_naissance TEXT NOT NULL,
    lycee TEXT NOT NULL,
    telephone TEXT NOT NULL,
    photo TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

-- ============================================
-- TABLE : classes
-- ============================================
CREATE TABLE IF NOT EXISTS classes (
    id TEXT PRIMARY KEY,
    nom TEXT NOT NULL,
    niveau TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

-- ============================================
-- TABLE : feuilles_notes
-- ============================================
CREATE TABLE IF NOT EXISTS feuilles_notes (
    id TEXT PRIMARY KEY,
    enseignant_id TEXT NOT NULL,
    classe TEXT NOT NULL,
    matiere TEXT NOT NULL,
    trimestre INTEGER NOT NULL CHECK(trimestre IN (1, 2, 3)),
    annee_scolaire TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE : eleves
-- ============================================
CREATE TABLE IF NOT EXISTS eleves (
    id TEXT PRIMARY KEY,
    feuille_id TEXT NOT NULL,
    identifiant TEXT NOT NULL UNIQUE,
    numero_ordre INTEGER NOT NULL,
    nom TEXT NOT NULL,
    prenom TEXT NOT NULL,
    nom_tuteur TEXT DEFAULT NULL,
    prenom_tuteur TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (feuille_id) REFERENCES feuilles_notes(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE : evaluations
-- ============================================
CREATE TABLE IF NOT EXISTS evaluations (
    id TEXT PRIMARY KEY,
    feuille_id TEXT NOT NULL,
    nom TEXT NOT NULL,
    date_evaluation TEXT DEFAULT NULL,
    bareme REAL NOT NULL DEFAULT 20,
    coefficient REAL NOT NULL DEFAULT 1,
    ordre INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (feuille_id) REFERENCES feuilles_notes(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE : notes_evaluations
-- ============================================
CREATE TABLE IF NOT EXISTS notes_evaluations (
    id TEXT PRIMARY KEY,
    eleve_id TEXT NOT NULL,
    evaluation_id TEXT NOT NULL,
    note REAL DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    UNIQUE(eleve_id, evaluation_id)
);

-- ============================================
-- TABLE : epreuves
-- ============================================
CREATE TABLE IF NOT EXISTS epreuves (
    id TEXT PRIMARY KEY,
    feuille_id TEXT NOT NULL,
    nom TEXT NOT NULL,
    formule TEXT NOT NULL,
    coefficient REAL NOT NULL DEFAULT 1,
    ordre INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (feuille_id) REFERENCES feuilles_notes(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE : notes_epreuves
-- ============================================
CREATE TABLE IF NOT EXISTS notes_epreuves (
    id TEXT PRIMARY KEY,
    eleve_id TEXT NOT NULL,
    epreuve_id TEXT NOT NULL,
    note REAL DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (epreuve_id) REFERENCES epreuves(id) ON DELETE CASCADE,
    UNIQUE(eleve_id, epreuve_id)
);

-- ============================================
-- INDEX
-- ============================================
CREATE INDEX IF NOT EXISTS idx_feuilles_enseignant ON feuilles_notes(enseignant_id);
CREATE INDEX IF NOT EXISTS idx_eleves_feuille ON eleves(feuille_id);
CREATE INDEX IF NOT EXISTS idx_evaluations_feuille ON evaluations(feuille_id);
CREATE INDEX IF NOT EXISTS idx_notes_eval_eleve ON notes_evaluations(eleve_id);
CREATE INDEX IF NOT EXISTS idx_notes_eval_evaluation ON notes_evaluations(evaluation_id);
CREATE INDEX IF NOT EXISTS idx_epreuves_feuille ON epreuves(feuille_id);
CREATE INDEX IF NOT EXISTS idx_notes_epreuve_eleve ON notes_epreuves(eleve_id);
CREATE INDEX IF NOT EXISTS idx_notes_epreuve_epreuve ON notes_epreuves(epreuve_id);

-- ============================================
-- DONNÉES DE TEST
-- ============================================

-- Mot de passe : Test123! (hashé avec bcrypt)
INSERT OR IGNORE INTO enseignants (id, identifiant, nom, prenom, email, mot_de_passe, date_naissance, lycee, telephone) VALUES
('a1b2c3d4-e5f6-7890-abcd-ef1234567890', '1234567890', 'Ben Ali', 'Mohamed', 'mohamed.benali@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1985-03-15', 'Lycée Pilote de Tunis', '+21698123456'),
('b2c3d4e5-f6a7-8901-bcde-f12345678901', '0987654321', 'Trabelsi', 'Sarra', 'sarra.trabelsi@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1990-07-22', 'Lycée Ibn Rachiq', '+21698765432');

-- Classes
INSERT OR IGNORE INTO classes (id, nom, niveau) VALUES
('c001-0001-0001-0001-000000000001', '4ème Mathématiques', '4ème'),
('c001-0001-0001-0001-000000000002', '4ème Sciences', '4ème'),
('c001-0001-0001-0001-000000000003', '3ème Lettres', '3ème');

-- Feuilles de notes
INSERT OR IGNORE INTO feuilles_notes (id, enseignant_id, classe, matiere, trimestre, annee_scolaire) VALUES
('f001-0001-0001-0001-000000000001', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890', '4ème Mathématiques', 'Mathématiques', 1, '2025-2026'),
('f001-0001-0001-0001-000000000002', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890', '4ème Mathématiques', 'Mathématiques', 2, '2025-2026'),
('f001-0001-0001-0001-000000000003', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890', '4ème Sciences', 'Physique', 1, '2025-2026');

-- Élèves de la feuille 1 (4ème Mathématiques - Trimestre 1)
INSERT OR IGNORE INTO eleves (id, feuille_id, identifiant, numero_ordre, nom, prenom, nom_tuteur, prenom_tuteur) VALUES
('e001-0001-0001-0001-000000000001', 'f001-0001-0001-0001-000000000001', '1000000000000001', 1, 'Khelil', 'Ahmed', 'Khelil', 'Mohamed'),
('e001-0001-0001-0001-000000000002', 'f001-0001-0001-0001-000000000001', '1000000000000002', 2, 'Mansouri', 'Inès', 'Mansouri', 'Hédi'),
('e001-0001-0001-0001-000000000003', 'f001-0001-0001-0001-000000000001', '1000000000000003', 3, 'Ben Amor', 'Sami', 'Ben Amor', 'Ali'),
('e001-0001-0001-0001-000000000004', 'f001-0001-0001-0001-000000000001', '1000000000000004', 4, 'Haddad', 'Nour', 'Haddad', 'Fathi'),
('e001-0001-0001-0001-000000000005', 'f001-0001-0001-0001-000000000001', '1000000000000005', 5, 'Jmaii', 'Omar', 'Jmaii', 'Khaled');

-- Évaluations de la feuille 1
INSERT OR IGNORE INTO evaluations (id, feuille_id, nom, date_evaluation, bareme, coefficient, ordre) VALUES
('ev001-0001-0001-0001-000000000001', 'f001-0001-0001-0001-000000000001', 'Devoir 1', '2025-10-15', 20, 1, 1),
('ev001-0001-0001-0001-000000000002', 'f001-0001-0001-0001-000000000001', 'Devoir 2', '2025-11-15', 20, 1, 2),
('ev001-0001-0001-0001-000000000003', 'f001-0001-0001-0001-000000000001', 'Interrogation 1', '2025-10-01', 10, 0.5, 3),
('ev001-0001-0001-0001-000000000004', 'f001-0001-0001-0001-000000000001', 'TP', '2025-11-01', 20, 0.5, 4);

-- Notes des évaluations pour la feuille 1
INSERT OR IGNORE INTO notes_evaluations (id, eleve_id, evaluation_id, note) VALUES
('ne001-001-001', 'e001-0001-0001-0001-000000000001', 'ev001-0001-0001-0001-000000000001', 15.5),
('ne001-001-002', 'e001-0001-0001-0001-000000000001', 'ev001-0001-0001-0001-000000000002', 14.0),
('ne001-001-003', 'e001-0001-0001-0001-000000000001', 'ev001-0001-0001-0001-000000000003', 8.5),
('ne001-001-004', 'e001-0001-0001-0001-000000000001', 'ev001-0001-0001-0001-000000000004', 16.0),
('ne001-002-001', 'e001-0001-0001-0001-000000000002', 'ev001-0001-0001-0001-000000000001', 12.0),
('ne001-002-002', 'e001-0001-0001-0001-000000000002', 'ev001-0001-0001-0001-000000000002', 13.5),
('ne001-002-003', 'e001-0001-0001-0001-000000000002', 'ev001-0001-0001-0001-000000000003', 7.0),
('ne001-002-004', 'e001-0001-0001-0001-000000000002', 'ev001-0001-0001-0001-000000000004', 14.5),
('ne001-003-001', 'e001-0001-0001-0001-000000000003', 'ev001-0001-0001-0001-000000000001', 17.0),
('ne001-003-002', 'e001-0001-0001-0001-000000000003', 'ev001-0001-0001-0001-000000000002', 16.5),
('ne001-003-003', 'e001-0001-0001-0001-000000000003', 'ev001-0001-0001-0001-000000000003', 9.0),
('ne001-003-004', 'e001-0001-0001-0001-000000000003', 'ev001-0001-0001-0001-000000000004', 18.0),
('ne001-004-001', 'e001-0001-0001-0001-000000000004', 'ev001-0001-0001-0001-000000000001', 8.5),
('ne001-004-002', 'e001-0001-0001-0001-000000000004', 'ev001-0001-0001-0001-000000000002', 10.0),
('ne001-004-003', 'e001-0001-0001-0001-000000000004', 'ev001-0001-0001-0001-000000000003', 5.0),
('ne001-004-004', 'e001-0001-0001-0001-000000000004', 'ev001-0001-0001-0001-000000000004', 12.0),
('ne001-005-001', 'e001-0001-0001-0001-000000000005', 'ev001-0001-0001-0001-000000000001', 18.5),
('ne001-005-002', 'e001-0001-0001-0001-000000000005', 'ev001-0001-0001-0001-000000000002', 19.0),
('ne001-005-003', 'e001-0001-0001-0001-000000000005', 'ev001-0001-0001-0001-000000000003', 9.5),
('ne001-005-004', 'e001-0001-0001-0001-000000000005', 'ev001-0001-0001-0001-000000000004', 17.5);

-- Épreuves de la feuille 1
INSERT OR IGNORE INTO epreuves (id, feuille_id, nom, formule, coefficient, ordre) VALUES
('ep001-0001-0001-0001-000000000001', 'f001-0001-0001-0001-000000000001', 'DC', '[{"eval":"ev001-0001-0001-0001-000000000001","coef":0.5},{"eval":"ev001-0001-0001-0001-000000000002","coef":0.5}]', 2, 1),
('ep001-0001-0001-0001-000000000002', 'f001-0001-0001-0001-000000000001', 'DS', '[{"eval":"ev001-0001-0001-0001-000000000003","coef":0.4},{"eval":"ev001-0001-0001-0001-000000000004","coef":0.6}]', 3, 2);