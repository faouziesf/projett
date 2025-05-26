<?php
// setup.php - Script d'installation avec Composer
require_once 'vendor/autoload.php';

echo "🚀 Installation de la Plateforme de Projets Étudiants\n";
echo "====================================================\n\n";

try {
    // 1. Vérifier les prérequis
    echo "1. Vérification des prérequis...\n";
    
    if (version_compare(PHP_VERSION, '7.4.0') < 0) {
        throw new Exception("❌ PHP 7.4+ requis. Version actuelle: " . PHP_VERSION);
    }
    echo "✅ PHP " . PHP_VERSION . " (OK)\n";
    
    if (!class_exists('PDO')) {
        throw new Exception("❌ Extension PDO requise");
    }
    echo "✅ PDO disponible\n";
    
    if (!extension_loaded('sqlite3')) {
        throw new Exception("❌ Extension SQLite3 requise");
    }
    echo "✅ SQLite3 disponible\n";
    
    // 2. Créer le fichier .env s'il n'existe pas
    echo "\n2. Configuration de l'environnement...\n";
    if (!file_exists('.env')) {
        if (file_exists('.env.example')) {
            copy('.env.example', '.env');
            echo "✅ Fichier .env créé à partir de .env.example\n";
        } else {
            throw new Exception("❌ Fichier .env.example manquant");
        }
    } else {
        echo "ℹ️ Fichier .env existe déjà\n";
    }
    
    // 3. Charger les variables d'environnement
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    // 4. Créer les dossiers nécessaires
    echo "\n3. Création des dossiers...\n";
    $folders = [
        'database',
        'uploads',
        'uploads/documents',
        'logs',
        'src',
        'src/Controllers',
        'src/Models',
        'src/Services',
        'src/Utils',
        'tests'
    ];
    
    foreach ($folders as $folder) {
        if (!file_exists($folder)) {
            if (mkdir($folder, 0777, true)) {
                echo "✅ Dossier '$folder' créé\n";
            } else {
                throw new Exception("❌ Impossible de créer le dossier '$folder'");
            }
        } else {
            echo "ℹ️ Dossier '$folder' existe déjà\n";
        }
    }
    
    // 5. Initialiser la base de données
    echo "\n4. Initialisation de la base de données...\n";
    require_once 'init_db.php';
    
    // 6. Créer le fichier .gitignore
    echo "\n5. Configuration Git...\n";
    $gitignoreContent = <<<GITIGNORE
# Dépendances
/vendor/
/node_modules/

# Environnement
.env
.env.local
.env.*.local

# Base de données
/database/*.db
/database/*.sqlite

# Logs
/logs/*.log

# Uploads
/uploads/*
!/uploads/.gitkeep

# Cache
/cache/
/tmp/

# IDE
.vscode/
.idea/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Composer
composer.lock
GITIGNORE;
    
    if (!file_exists('.gitignore')) {
        file_put_contents('.gitignore', $gitignoreContent);
        echo "✅ Fichier .gitignore créé\n";
    }
    
    // 7. Créer des fichiers .gitkeep
    $gitkeepFiles = [
        'uploads/.gitkeep',
        'logs/.gitkeep',
        'tests/.gitkeep'
    ];
    
    foreach ($gitkeepFiles as $file) {
        if (!file_exists($file)) {
            touch($file);
        }
    }
    
    // 8. Configuration finale
    echo "\n6. Configuration finale...\n";
    
    // Créer un fichier de configuration pour Apache si nécessaire
    $htaccessContent = <<<HTACCESS
# Configuration Apache pour la Plateforme
RewriteEngine On

# Redirection vers index.php pour les routes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Sécurité - Bloquer l'accès aux fichiers sensibles
<FilesMatch "\.(db|sqlite|log|env)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Bloquer l'accès au dossier vendor
<Directory "vendor">
    Order allow,deny
    Deny from all
</Directory>

# Headers de sécurité
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net;"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json
</IfModule>

# Cache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>
HTACCESS;
    
    if (!file_exists('.htaccess')) {
        file_put_contents('.htaccess', $htaccessContent);
        echo "✅ Fichier .htaccess créé\n";
    }
    
    echo "\n🎉 Installation terminée avec succès !\n";
    echo "=====================================\n\n";
    
    echo "🔗 URLs d'accès :\n";
    echo "   - Application : " . ($_ENV['APP_URL'] ?? 'http://localhost:8000') . "\n";
    echo "   - Connexion   : " . ($_ENV['APP_URL'] ?? 'http://localhost:8000') . "/auth/login.php\n\n";
    
    echo "👤 Comptes de test :\n";
    echo "   - Admin      : admin / admin123\n";
    echo "   - Étudiant   : etudiant1 / password\n";
    echo "   - Superviseur: prof1 / password\n\n";
    
    echo "🚀 Pour démarrer le serveur :\n";
    echo "   composer serve\n";
    echo "   ou\n";
    echo "   php -S localhost:8000\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Erreur d'installation : " . $e->getMessage() . "\n";
    exit(1);
}