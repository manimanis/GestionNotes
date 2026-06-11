# 📚 Gestion de Notes

Application web complète de gestion de carnet de notes pour enseignants, développée avec **Vue.js 3** (frontend) et **PHP 8** (backend), servie entièrement par **Apache** sur le port 80.

---

## 🌐 Accès

| Page | URL |
|------|-----|
| Application | http://127.0.0.1/GestionNotes/ |
| API Health | http://127.0.0.1/GestionNotes/api/ |
| API Login | http://127.0.0.1/GestionNotes/api/login |

---

## 🏗 Architecture

```
GestionNotes/
├── .htaccess              ← Routage Apache (port 80)
├── index.html             ← Point d'entrée frontend
├── api/
│   └── index.php          ← Point d'entrée API (autoloader + routes)
├── backend/
│   ├── database/
│   │   └── schema.sql     ← Schéma SQLite + données de test
│   ├── src/
│   │   ├── Controllers/   ← 9 contrôleurs (Auth, Feuille, Eleve, Evaluation, Epreuve, Note, Stats, Export, FeuilleValidate)
│   │   ├── Helpers/       ← Database, JWT, UUID, Response, Validator
│   │   ├── Middleware/     ← AuthMiddleware (JWT)
│   │   └── Services/      ← NoteService (moteur de calcul)
│   └── config/
├── frontend/
│   ├── src/
│   │   ├── views/         ← 7 pages Vue.js
│   │   ├── stores/        ← Pinia (auth, theme, année scolaire)
│   │   ├── components/    ← Sidebar
│   │   ├── router/        ← Vue Router
│   │   ├── api/           ← Client Axios
│   │   └── assets/        ← CSS global
│   ├── dist/              ← Build de production
│   └── package.json
└── assets/                ← Assets buildés (copiés depuis dist/)
```

---

## 🚀 Installation

### Prérequis

- **XAMPP** (Apache + PHP 8) installé
- **mod_rewrite** activé dans `conf/httpd.conf`
- **Node.js** (pour le développement frontend)

### 1. Activer mod_rewrite

Dans `C:\xampp-school\apache\conf\httpd.conf` :
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

### 2. Configuration Apache

```apache
<Directory "C:/xampp-school/htdocs/GestionNotes">
    AllowOverride All
    Require all granted
</Directory>
```

### 3. Redémarrer Apache

```bash
C:\xampp-school\apache\bin\httpd.exe -k restart
```

### 4. Premier accès

Ouvrir http://127.0.0.1/GestionNotes/ dans un navigateur. La base de données SQLite est créée automatiquement au premier appel API.

### 5. Développement frontend (optionnel)

```bash
cd frontend
npm install
npm run dev
# Accès : http://localhost:3000/GestionNotes/
```

### 6. Build de production

```bash
cd frontend
npm run build
```

Le script de build copie automatiquement les fichiers dans le répertoire racine.

---

## 🔑 Comptes de test

| Email | Mot de passe |
|-------|-------------|
| mohamed.benali@email.com | Test123! |
| sarra.trabelsi@email.com | Test123! |

---

## 📋 Fonctionnalités

### Authentification
- Inscription / Connexion / Déconnexion
- Authentification JWT (token dans localStorage)
- Profil utilisateur (modification, photo)

### Gestion des feuilles de notes
- CRUD complet (Créer, Lire, Modifier, Supprimer)
- Duplication de feuilles (avec élèves, évaluations, épreuves et notes)
- Import de données (élèves + notes) via CSV ou collage
- **Sélection de l'année scolaire** (filtre global dans la barre latérale)

### Gestion des élèves
- Ajout / Modification / Suppression
- Import CSV et import par collage
- Identifiant unique par élève

### Évaluations et épreuves
- Évaluations dynamiques (nom, barème, coefficient)
- Épreuves calculées selon formules JSON configurables
- Édition inline type Excel

### Calcul des notes
- Moyenne pondérée par épreuve
- Classement avec gestion des ex æquo
- Observations automatiques (Excellent, Très bien, Bien, Passable, Insuffisant)

### Statistiques
- Dashboard avec statistiques (feuilles, élèves, classes)
- Graphiques Chart.js (distribution, top 5, moyennes)
- Export CSV / JSON

### Interface
- Mode sombre (dark mode)
- Responsive design
- Sélecteur d'année scolaire global

---

## 🧮 Logique de calcul

1. **Évaluations** : Notes saisies par l'enseignant (barème configurable)
2. **Épreuves** : Calculées selon formule JSON configurable
3. **Moyenne** : Σ(note_epreuve × coefficient) / Σ(coefficients)
4. **Rang** : Classement avec gestion des ex æquo
5. **Observation** : ≥16 Excellent, ≥14 Très bien, ≥12 Bien, ≥10 Passable, <10 Insuffisant

---

## 📡 API REST

### Routes principales

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/api/login` | Connexion |
| POST | `/api/register` | Inscription |
| GET | `/api/user` | Profil utilisateur |
| PUT | `/api/profile` | Mise à jour profil |
| GET | `/api/feuilles` | Liste des feuilles (filtrable par année scolaire) |
| POST | `/api/feuilles` | Créer une feuille |
| GET | `/api/feuilles/{id}` | Détail d'une feuille |
| PUT | `/api/feuilles/{id}` | Modifier une feuille |
| DELETE | `/api/feuilles/{id}` | Supprimer une feuille |
| POST | `/api/feuilles/{id}/duplicate` | Dupliquer une feuille |
| POST | `/api/feuilles/{id}/import-data` | Importer des données |
| GET | `/api/feuilles/{id}/eleves` | Liste des élèves |
| POST | `/api/eleves` | Ajouter un élève |
| GET | `/api/feuilles/{id}/evaluations` | Liste des évaluations |
| POST | `/api/evaluations` | Ajouter une évaluation |
| GET | `/api/feuilles/{id}/epreuves` | Liste des épreuves |
| POST | `/api/epreuves` | Ajouter une épreuve |
| POST | `/api/notes-evaluations` | Sauvegarder une note d'évaluation |
| POST | `/api/notes-evaluations/batch` | Sauvegarder des notes en lot |
| POST | `/api/notes-epreuves` | Sauvegarder une note d'épreuve |
| GET | `/api/feuilles/{id}/stats` | Statistiques d'une feuille |
| GET | `/api/feuilles/{id}/export/csv` | Export CSV |
| GET | `/api/feuilles/{id}/export/json` | Export JSON |

### Paramètres de filtrage

L'endpoint `GET /api/feuilles` accepte un paramètre optionnel `annee_scolaire` pour filtrer par année scolaire :
```
GET /api/feuilles?annee_scolaire=2025-2026
```

---

## 🛠 Technologies

| Couche | Technologie |
|--------|-------------|
| Frontend | Vue.js 3, Vue Router, Pinia, Axios, Chart.js |
| Backend | PHP 8, SQLite (PDO) |
| Authentification | JWT (JSON Web Token) |
| Build | Vite 5 |
| Serveur | Apache (mod_php, port 80) |

---

## 📁 Base de données

La base de données SQLite est située dans `backend/database/` et contient les tables suivantes :

- `enseignants` — Comptes enseignants
- `classes` — Classes
- `feuilles_notes` — Feuilles de notes (classe, matière, trimestre, année scolaire)
- `eleves` — Élèves (identifiant unique par feuille)
- `evaluations` — Évaluations (nom, barème, coefficient)
- `notes_evaluations` — Notes d'évaluations
- `epreuves` — Épreuves (formules JSON)
- `notes_epreuves` — Notes d'épreuves

---

## 📄 Licence

Projet privé — Tous droits réservés.