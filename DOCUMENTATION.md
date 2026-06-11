# 📚 Gestion de Notes - Application Enseignant

Application complète de gestion de carnet de notes pour enseignants.
**Entièrement servi par Apache sur le port 80** (mod_php).

## 🌐 Accès

| Page | URL |
|------|-----|
| Frontend | http://127.0.0.1/GestionNotes/ |
| API Health | http://127.0.0.1/GestionNotes/api/health |
| API Login | http://127.0.0.1/GestionNotes/api/login |

## 🏗 Architecture

```
C:\xampp-school\htdocs\GestionNotes\
├── .htaccess           ← Routage Apache (serveur seul, port 80)
├── api.php             ← Point d'entrée API (mod_php)
├── backend/
│   ├── database/       ← SQLite + schéma
│   ├── src/
│   │   ├── Controllers/  ← 8 contrôleurs
│   │   ├── Helpers/      ← JWT, DB, Validation...
│   │   ├── Middleware/   ← Auth JWT
│   │   └── Services/     ← Moteur de calcul
│   └── config/
├── frontend/
│   ├── dist/           ← Build statique (servi par Apache)
│   └── src/            ← Sources Vue.js
└── DOCUMENTATION.md
```

## 🚀 Installation et démarrage

### 1. Prérequis

- **XAMPP** (Apache + PHP 8) doit être installé
- **mod_rewrite** activé dans `conf/httpd.conf`
- Le projet est dans `C:\xampp-school\htdocs\GestionNotes`

### 2. Activer mod_rewrite

Dans `C:\xampp-school\apache\conf\httpd.conf` :
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

### 3. Configuration Apache

Dans votre VirtualHost, assurez-vous d'avoir :
```apache
<Directory "C:/xampp-school/htdocs/GestionNotes">
    AllowOverride All
    Require all granted
</Directory>
```

Pas besoin de DocumentRoot spécial, le `.htaccess` à la racine du projet gère tout le routage.

### 4. Redémarrer Apache

```bash
# Depuis le panneau de contrôle XAMPP ou en ligne de commande :
C:\xampp-school\apache\bin\httpd.exe -k restart
```

### 5. Premier accès

Ouvrir http://127.0.0.1/GestionNotes/ dans un navigateur.
La base de données SQLite est créée automatiquement au premier appel API.

### 6 (optionnel) Développement frontend

```bash
cd frontend
npm install
npm run dev
# Accès : http://localhost:3000/GestionNotes/
# Les appels API sont proxifiés vers Apache (http://127.0.0.1/GestionNotes/api)
```

## 🔑 L'API fonctionne sur le port 80 (mod_php)

Tout est sur le **même serveur Apache**, **même port 80** :

```
Requête navigateur :
  http://127.0.0.1/GestionNotes/api/login
    ↓ Apache reçoit la requête
    ↓ .htaccess redirige vers api.php
    ↓ mod_php exécute api.php
    ↓ api.php traite la route /login
    ↓ Réponse JSON renvoyée au navigateur
```

### Comptes de test

| Email | Mot de passe |
|-------|-------------|
| demo@test.com | Demo1234! |
| test@test.com | Test1234! |

## 📁 Structure des fichiers

### Racine du projet
| Fichier | Rôle |
|---------|------|
| `.htaccess` | Routage Apache (/api/* → api.php, /* → frontend/dist/) |
| `api.php` | Point d'entrée API unique (autoloader + routes) |

### Backend (backend/src/)
| Dossier | Contenu |
|---------|---------|
| `Controllers/` | Auth, Feuille, Eleve, Evaluation, Epreuve, Note, Stats, Export |
| `Helpers/` | Database, JWT, UUID, Response, Validator |
| `Middleware/` | AuthMiddleware (vérification JWT) |
| `Services/` | NoteService (moteur de calcul) |

### Frontend (frontend/src/)
| Dossier | Contenu |
|---------|---------|
| `views/` | 7 pages (Login, Register, Dashboard, Profil, Feuilles, FeuilleDetail, Stats) |
| `stores/` | Pinia (auth, theme) |
| `router/` | Vue Router (base: /GestionNotes/) |
| `api/` | Client Axios (baseURL: /GestionNotes/api) |
| `components/` | Sidebar |
| `assets/` | CSS global |

## 🧮 Logique de calcul

1. **Évaluations** : Notes saisies par l'enseignant
2. **Épreuves** : Calculées selon formule JSON configurable
3. **Moyenne** : Σ(note_epreuve × coefficient) / Σ(coefficients)
4. **Rang** : Classement avec gestion des ex æquo
5. **Observation** : ≥16 Excellent, ≥14 Très bien, ≥12 Bien, ≥10 Passable, <10 Insuffisant

## ✅ Fonctionnalités implémentées

- Authentification JWT (inscription/connexion/déconnexion)
- CRUD feuilles de notes
- Gestion des élèves (CRUD + import CSV)
- Évaluations dynamiques (nom, barème, coefficient)
- Épreuves calculées (formules JSON)
- Édition inline type Excel
- Moyenne pondérée et classement
- Observations automatiques
- Statistiques Chart.js (distribution, top 5, moyennes)
- Export CSV/JSON
- Mode sombre (dark mode)
- Responsive design
- **Tout sur Apache (port 80) - pas de serveur supplémentaire**