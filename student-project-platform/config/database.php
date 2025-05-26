<?php
// config/database.php - Version simple et robuste

// Chargement simple des variables d'environnement
function loadEnv($file) {
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && $line[0] !== '#') {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value, '"\'');
            }
        }
    }
}

loadEnv(__DIR__ . '/../.env');

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dbPath = $_ENV['DB_PATH'] ?? 'database/students_projects.db';
            $fullPath = __DIR__ . '/../' . $dbPath;
            
            // Créer le dossier si nécessaire
            $dbDir = dirname($fullPath);
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            
            $this->pdo = new PDO('sqlite:' . $fullPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
        } catch (Exception $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (Exception $e) {
            error_log("Erreur SQL : " . $e->getMessage());
            throw $e;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
}

function getDB() {
    return Database::getInstance();
}