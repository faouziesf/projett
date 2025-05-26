<?php
// config/database.php - Configuration avec Composer et dotenv

// Charger l'autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Charger les variables d'environnement
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Database {
    private static $instance = null;
    private $pdo;
    private $logger;
    
    private function __construct() {
        $this->setupLogger();
        $this->connect();
    }
    
    private function setupLogger() {
        $this->logger = new Logger('database');
        $logPath = $_ENV['LOG_PATH'] ?? 'logs/app.log';
        
        // Créer le dossier logs s'il n'existe pas
        $logDir = dirname(__DIR__ . '/' . $logPath);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../' . $logPath, Logger::DEBUG));
    }
    
    private function connect() {
        try {
            $dbPath = $_ENV['DB_PATH'] ?? 'database/students_projects.db';
            $fullPath = __DIR__ . '/../' . $dbPath;
            
            // Créer le dossier database s'il n'existe pas
            $dbDir = dirname($fullPath);
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            
            $this->pdo = new PDO('sqlite:' . $fullPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Activer les foreign keys pour SQLite
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
            $this->logger->info('Connexion à la base de données établie', ['db_path' => $fullPath]);
            
        } catch (PDOException $e) {
            $this->logger->error('Erreur de connexion à la base de données', [
                'error' => $e->getMessage(),
                'db_path' => $fullPath ?? 'N/A'
            ]);
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
            
            $this->logger->debug('Requête SQL exécutée', [
                'sql' => $sql,
                'params' => $params
            ]);
            
            return $stmt;
        } catch (PDOException $e) {
            $this->logger->error('Erreur SQL', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
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

// Fonction helper pour obtenir la connexion
function getDB() {
    return Database::getInstance();
}