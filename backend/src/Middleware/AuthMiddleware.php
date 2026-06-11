<?php
/**
 * Auth Middleware - Protection JWT des routes API
 * 
 * Vérifie la validité du token JWT et injecte l'utilisateur
 */

namespace App\Middleware;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Helpers\Database;

class AuthMiddleware
{
    /**
     * Vérifier l'authentification
     *
     * @return array Données de l'utilisateur authentifié
     */
    public static function authenticate(): array
    {
        $token = self::extractToken();
        
        if ($token === null) {
            Response::unauthorized('Token d\'authentification requis.');
        }
        
        $payload = JWT::validate($token);
        
        if ($payload === null) {
            Response::unauthorized('Token invalide ou expiré.');
        }
        
        // Vérifier que l'utilisateur existe toujours
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id, identifiant, nom, prenom, email FROM enseignants WHERE id = ?');
        $stmt->execute([$payload['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            Response::unauthorized('Utilisateur non trouvé.');
        }
        
        return $user;
    }
    
    /**
     * Extraire le token JWT de l'en-tête Authorization
     */
    private static function extractToken(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Fallback: vérifier dans les cookies
        if (isset($_COOKIE['token'])) {
            return $_COOKIE['token'];
        }
        
        return null;
    }
}