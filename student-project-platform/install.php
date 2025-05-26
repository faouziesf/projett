<?php
// install.php - Installation automatique ultra-simple
echo "🚀 Installation de la Plateforme de Projets Étudiants\n";
echo "===================================================\n\n";

// 1. Créer les dossiers nécessaires
echo "📁 Création des dossiers...\n";
$folders = ['database', 'uploads', 'uploads/documents', 'logs'];
foreach ($folders as $folder) {
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
        echo "✅ $folder créé\n";
    }
}

// 2. Créer le fichier .env
echo "\n⚙️ Configuration...\n";
if (!file_exists('.env')) {
    $env = 'APP_NAME="Plateforme Projets Étudiants"
APP_URL=http://localhost:8000
DB_PATH=database/students_projects.db
SESSION_LIFETIME=120
';
    file_put_contents('.env', $env);
    echo "✅ .env créé\n";
}

// 3. Créer la base de données avec des données de test
echo "\n🗄️ Création de la base de données...\n";
try {
    $pdo = new PDO('sqlite:database/students_projects.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer les tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        role TEXT DEFAULT 'student',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        domain VARCHAR(100),
        start_date DATE,
        end_date DATE,
        status TEXT DEFAULT 'planning',
        progress_percentage INTEGER DEFAULT 0,
        supervisor_id INTEGER,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        user_id INTEGER,
        role TEXT DEFAULT 'member',
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        assigned_to INTEGER,
        status TEXT DEFAULT 'todo',
        priority TEXT DEFAULT 'medium',
        due_date DATE,
        completed_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        user_id INTEGER,
        comment TEXT NOT NULL,
        type TEXT DEFAULT 'comment',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        uploaded_by INTEGER,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_size INTEGER,
        mime_type VARCHAR(100),
        upload_path VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        project_id INTEGER,
        title VARCHAR(200) NOT NULL,
        message TEXT,
        type TEXT DEFAULT 'info',
        is_read INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insérer des utilisateurs de test
    $pdo->exec("INSERT OR IGNORE INTO users (username, email, password, first_name, last_name, role) VALUES 
        ('admin', 'admin@test.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'Admin', 'User', 'supervisor'),
        ('etudiant1', 'etudiant1@test.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', 'Jean', 'Dupont', 'student'),
        ('prof1', 'prof1@test.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', 'Dr. Pierre', 'Durand', 'supervisor')
    ");
    
    echo "✅ Base de données créée avec succès\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

// 4. Créer .htaccess simple
if (!file_exists('.htaccess')) {
    $htaccess = 'RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

<FilesMatch "\.(db|sqlite|env|log)$">
    Order allow,deny
    Deny from all
</FilesMatch>';
    file_put_contents('.htaccess', $htaccess);
    echo "✅ .htaccess créé\n";
}

echo "\n🎉 Installation terminée !\n";
echo "========================\n\n";
echo "🔗 Pour démarrer :\n";
echo "   php -S localhost:8000\n\n";
echo "🌐 Ouvrir dans le navigateur :\n";
echo "   http://localhost:8000\n\n";
echo "👤 Comptes de test :\n";
echo "   • admin / admin123\n";
echo "   • etudiant1 / password\n";
echo "   • prof1 / password\n\n";
echo "✨ C'est prêt à utiliser !\n";
?>