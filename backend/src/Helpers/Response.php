<?php
/**
 * Response Helper - Standardise les réponses JSON
 */

namespace App\Helpers;

class Response
{
    /**
     * Envoyer une réponse JSON
     *
     * @param mixed $data Données à retourner
     * @param int $statusCode Code HTTP
     * @param string $message Message de statut
     */
    public static function json($data = null, int $statusCode = 200, string $message = 'OK'): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'status' => $statusCode >= 200 && $statusCode < 300 ? 'success' : 'error',
            'message' => $message,
            'data' => $data
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Réponse de succès
     */
    public static function success($data = null, string $message = 'Opération réussie'): void
    {
        self::json($data, 200, $message);
    }

    /**
     * Réponse de création
     */
    public static function created($data = null, string $message = 'Ressource créée'): void
    {
        self::json($data, 201, $message);
    }

    /**
     * Réponse d'erreur
     */
    public static function error(string $message = 'Erreur', int $statusCode = 400, $errors = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'status' => 'error',
            'message' => $message,
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Erreur 400 - Mauvaise requête
     */
    public static function badRequest(string $message = 'Requête invalide', $errors = null): void
    {
        self::error($message, 400, $errors);
    }

    /**
     * Erreur 401 - Non authentifié
     */
    public static function unauthorized(string $message = 'Non authentifié'): void
    {
        self::error($message, 401);
    }

    /**
     * Erreur 403 - Accès interdit
     */
    public static function forbidden(string $message = 'Accès interdit'): void
    {
        self::error($message, 403);
    }

    /**
     * Erreur 404 - Non trouvé
     */
    public static function notFound(string $message = 'Ressource non trouvée'): void
    {
        self::error($message, 404);
    }

    /**
     * Erreur 500 - Erreur serveur
     */
    public static function serverError(string $message = 'Erreur interne du serveur'): void
    {
        self::error($message, 500);
    }
}