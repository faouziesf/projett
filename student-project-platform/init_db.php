<?php
// init_db.php - Script d'initialisation de la base de données

try {
    // Créer le dossier database s'il n'existe pas
    if (!file_exists('database')) {
        mkdir('database', 0777, true);
    }
    
    // Connexion à SQLite
    $pdo = new PDO('sqlite:database/students_projects.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Création des tables
    
    // Table users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        role ENUM('student', 'supervisor') DEFAULT 'student',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Table projects
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        domain VARCHAR(100),
        start_date DATE,
        end_date DATE,
        status ENUM('planning', 'in_progress', 'completed', 'paused') DEFAULT 'planning',
        progress_percentage INTEGER DEFAULT 0,
        supervisor_id INTEGER,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (supervisor_id) REFERENCES users(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // Table project_members
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        user_id INTEGER,
        role ENUM('leader', 'member') DEFAULT 'member',
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(project_id, user_id)
    )");
    
    // Table tasks
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        assigned_to INTEGER,
        status ENUM('todo', 'in_progress', 'completed') DEFAULT 'todo',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        due_date DATE,
        completed_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(id)
    )");
    
    // Table comments
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        user_id INTEGER,
        comment TEXT NOT NULL,
        type ENUM('comment', 'recommendation') DEFAULT 'comment',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Table documents
    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        uploaded_by INTEGER,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_size INTEGER,
        mime_type VARCHAR(100),
        upload_path VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
    )");
    
    // Table notifications
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        project_id INTEGER,
        title VARCHAR(200) NOT NULL,
        message TEXT,
        type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
        is_read BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    
    // Insérer des données de test
    
    // Utilisateurs de test
    $pdo->exec("INSERT OR IGNORE INTO users (username, email, password, first_name, last_name, role) VALUES 
        ('admin', 'admin@example.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'Admin', 'User', 'supervisor'),
        ('etudiant1', 'etudiant1@example.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', 'Jean', 'Dupont', 'student'),
        ('etudiant2', 'etudiant2@example.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', 'Marie', 'Martin', 'student'),
        ('prof1', 'prof1@example.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', 'Dr. Pierre', 'Durand', 'supervisor')
    ");
    
    // Projet de test
    $pdo->exec("INSERT OR IGNORE INTO projects (title, description, domain, start_date, end_date, supervisor_id, created_by) VALUES 
        ('Application Web de Gestion', 'Développement d\'une application web pour la gestion des stocks', 'Informatique', '2024-01-15', '2024-06-15', 4, 2)
    ");
    
    echo "Base de données initialisée avec succès !<br>";
    echo "Utilisateurs de test créés :<br>";
    echo "- Admin: admin / admin123<br>";
    echo "- Étudiant 1: etudiant1 / password<br>";
    echo "- Étudiant 2: etudiant2 / password<br>";
    echo "- Professeur: prof1 / password<br>";
    
} catch (PDOException $e) {
    die("Erreur lors de l'initialisation de la base de données : " . $e->getMessage());
}