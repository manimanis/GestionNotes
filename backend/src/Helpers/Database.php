<?php
/**
 * Database Connection - Singleton SQLite PDO
 * 
 * Gère la connexion à la base de données SQLite
 */

namespace App\Helpers;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';
        
        try {
            $dbPath = $config['path'];
            $dbDir = dirname($dbPath);
            
            // Créer le dossier database si nécessaire
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $this->pdo = new PDO(
                "sqlite:{$dbPath}",
                null,
                null,
                $config['options']
            );
            
            // Activer les clés étrangères
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            
        } catch (PDOException $e) {
            throw new \RuntimeException("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir l'instance unique de Database
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtenir la connexion PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }
    
    /**
     * Initialiser la base de données avec le schéma SQL
     */
    public function initialize(): void
    {
        $schemaPath = __DIR__ . '/../../database/schema.sql';
        
        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            if ($sql !== false) {
                $this->pdo->exec($sql);
            }
        }
    }
    
    /**
     * Empêcher le clonage
     */
    private function __clone() {}
    
    /**
     * Empêcher la désérialisation
     */
    public function __wakeup()
    {
        throw new \RuntimeException("Cannot unserialize singleton");
    }
}