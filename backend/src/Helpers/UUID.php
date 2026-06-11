<?php
/**
 * UUID v4 Generator
 * 
 * Génère des identifiants uniques universels
 */

namespace App\Helpers;

class UUID
{
    /**
     * Générer un UUID v4
     *
     * @return string UUID format: 8-4-4-4-12
     */
    public static function generate(): string
    {
        $data = random_bytes(16);
        
        // Version 4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Variant
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}