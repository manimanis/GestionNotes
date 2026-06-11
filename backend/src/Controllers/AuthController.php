<?php
/**
 * Auth Controller - Authentification (Inscription, Connexion, Profil)
 */

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\JWT;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\UUID;

class AuthController
{
    /**
     * Inscription d'un enseignant
     * POST /api/register
     */
    public static function register(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $validator = new Validator($data);
        $errors = $validator
            ->required('identifiant', 'Identifiant')
            ->identifiant10('identifiant', 'Identifiant')
            ->required('nom', 'Nom')
            ->required('prenom', 'Prénom')
            ->required('email', 'Email')
            ->email('email', 'Email')
            ->required('mot_de_passe', 'Mot de passe')
            ->password('mot_de_passe', 'Mot de passe')
            ->required('date_naissance', 'Date de naissance')
            ->date('date_naissance', 'Y-m-d', 'Date de naissance')
            ->required('lycee', 'Lycée')
            ->required('telephone', 'Téléphone')
            ->telephone('telephone', 'Téléphone')
            ->validate();
        
        if ($errors) {
            Response::badRequest('Erreur de validation', $errors);
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Vérifier unicité email
        $stmt = $db->prepare('SELECT id FROM enseignants WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            Response::badRequest('Cet email est déjà utilisé.');
        }
        
        // Vérifier unicité identifiant
        $stmt = $db->prepare('SELECT id FROM enseignants WHERE identifiant = ?');
        $stmt->execute([$data['identifiant']]);
        if ($stmt->fetch()) {
            Response::badRequest('Cet identifiant est déjà utilisé.');
        }
        
        // Créer l'enseignant
        $id = UUID::generate();
        $hashedPassword = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);
        
        $stmt = $db->prepare('
            INSERT INTO enseignants (id, identifiant, nom, prenom, email, mot_de_passe, date_naissance, lycee, telephone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        try {
            $stmt->execute([
                $id,
                $data['identifiant'],
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $hashedPassword,
                $data['date_naissance'],
                $data['lycee'],
                $data['telephone']
            ]);
        } catch (\PDOException $e) {
            Response::serverError('Erreur lors de l\'inscription: ' . $e->getMessage());
        }
        
        // Générer le token JWT
        $token = JWT::generate(['user_id' => $id]);
        
        Response::created([
            'token' => $token,
            'user' => [
                'id' => $id,
                'identifiant' => $data['identifiant'],
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'],
            ]
        ], 'Inscription réussie');
    }
    
    /**
     * Connexion d'un enseignant
     * POST /api/login
     */
    public static function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $validator = new Validator($data);
        $errors = $validator
            ->required('email', 'Email')
            ->email('email', 'Email')
            ->required('mot_de_passe', 'Mot de passe')
            ->validate();
        
        if ($errors) {
            Response::badRequest('Erreur de validation', $errors);
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Chercher l'enseignant par email
        $stmt = $db->prepare('SELECT * FROM enseignants WHERE email = ?');
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($data['mot_de_passe'], $user['mot_de_passe'])) {
            Response::unauthorized('Email ou mot de passe incorrect.');
        }
        
        // Générer le token JWT
        $token = JWT::generate(['user_id' => $user['id']]);
        
        Response::success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'identifiant' => $user['identifiant'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
            ]
        ], 'Connexion réussie');
    }
    
    /**
     * Récupérer le profil de l'utilisateur connecté
     * GET /api/user
     */
    public static function user(): void
    {
        $authUser = \App\Middleware\AuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('
            SELECT id, identifiant, nom, prenom, email, date_naissance, lycee, telephone, photo, created_at
            FROM enseignants WHERE id = ?
        ');
        $stmt->execute([$authUser['id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            Response::notFound('Utilisateur non trouvé.');
        }
        
        Response::success($user);
    }
    
    /**
     * Mettre à jour le profil enseignant
     * PUT /api/profile
     */
    public static function updateProfile(): void
    {
        $authUser = \App\Middleware\AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $db = Database::getInstance()->getConnection();
        
        // Champs modifiables
        $allowedFields = ['nom', 'prenom', 'lycee', 'telephone'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            Response::badRequest('Aucun champ à mettre à jour.');
        }
        
        // Ajouter updated_at
        $updates[] = "updated_at = datetime('now')";
        $params[] = $authUser['id'];
        
        $sql = 'UPDATE enseignants SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Retourner les nouvelles infos
        $stmt = $db->prepare('SELECT id, identifiant, nom, prenom, email, date_naissance, lycee, telephone, photo FROM enseignants WHERE id = ?');
        $stmt->execute([$authUser['id']]);
        $user = $stmt->fetch();
        
        Response::success($user, 'Profil mis à jour avec succès.');
    }
    
    /**
     * Upload photo de profil
     * POST /api/profile/photo
     */
    public static function uploadPhoto(): void
    {
        $authUser = \App\Middleware\AuthMiddleware::authenticate();
        
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            Response::badRequest('Fichier photo requis.');
        }
        
        $file = $_FILES['photo'];
        
        // Valider le type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            Response::badRequest('Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.');
        }
        
        // Valider la taille (max 2 Mo)
        if ($file['size'] > 2 * 1024 * 1024) {
            Response::badRequest('Le fichier ne doit pas dépasser 2 Mo.');
        }
        
        // Créer le dossier uploads
        $uploadDir = __DIR__ . '/../../public/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Générer un nom unique
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $authUser['id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . '/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Response::serverError('Erreur lors du téléchargement du fichier.');
        }
        
        // Mettre à jour en base
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('UPDATE enseignants SET photo = ?, updated_at = datetime(\'now\') WHERE id = ?');
        $stmt->execute(['/uploads/' . $filename, $authUser['id']]);
        
        Response::success(['photo' => '/uploads/' . $filename], 'Photo mise à jour avec succès.');
    }
}