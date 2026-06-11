<?php
/**
 * Database Configuration
 * 
 * Configuration SQLite pour l'application Gestion de Notes
 */

return [
    'driver' => 'sqlite',
    'path' => __DIR__ . '/../database/gestion_notes.db',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];