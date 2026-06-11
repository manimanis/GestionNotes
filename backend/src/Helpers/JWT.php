<?php
/**
 * JWT Helper - JSON Web Token pour l'authentification
 * 
 * Implémentation légère de JWT sans bibliothèque externe
 */

namespace App\Helpers;

class JWT
{
    private static string $secretKey = 'GestionNotes2025SecretKey!@#$%^&*()_+SuperSecure';
    private static string $algorithm = 'HS256';
    private static int $expiration = 86400; // 24 heures en secondes

    /**
     * Générer un token JWT
     *
     * @param array $payload Données à encoder
     * @return string Token JWT
     */
    public static function generate(array $payload): string
    {
        $header = self::base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => self::$algorithm
        ]));

        // Ajouter les timestamps
        $payload['iat'] = time();
        $payload['exp'] = time() + self::$expiration;
        
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payloadEncoded", self::$secretKey, true)
        );
        
        return "$header.$payloadEncoded.$signature";
    }

    /**
     * Valider et décoder un token JWT
     *
     * @param string $token Token JWT
     * @return array|null Payload décodé ou null si invalide
     */
    public static function validate(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Vérifier la signature
        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', "$headerB64.$payloadB64", self::$secretKey, true)
        );

        if (!hash_equals($expectedSignature, $signatureB64)) {
            return null;
        }

        // Décoder le payload
        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if ($payload === null) {
            return null;
        }

        // Vérifier l'expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Encoder en base64 URL-safe
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Décoder du base64 URL-safe
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}