<?php
// setup.php - Script d'installation avec Composer
echo "🚀 Installation de la Plateforme de Projets Étudiants\n";
echo "====================================================\n\n";

try {
    // 1. Vérifier si on est en mode CLI ou web
    $isCLI = php_sapi_name() === 'cli';
    if (!$isCLI) {
        echo "<pre>";
    }
    
    // 2. Vérifier les prérequis
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
    
    // 3. Vérifier que Composer a été exécuté
    if (!file_exists('vendor/autoload.php')) {
        throw new Exception("❌ Veuillez d'abord exécuter 'composer install'");
    }
    echo "✅ Vendor Composer disponible\n";
    
    // 4. Charger l'autoloader
    require_once 'vendor/autoload.php';
    
    // 5. Créer le fichier .env s'il n'existe pas
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
    
    // 6. Créer les dossiers nécessaires
    echo "\n3. Création des dossiers...\n";
    $folders = [
        'database',
        'uploads',
        'uploads/documents',
        'logs'
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
    
    // 7. Initialiser la base de données
    echo "\n4. Initialisation de la base de données...\n";
    if (file_exists('init_db.php')) {
        include 'init_db.php';
    } else {
        echo "⚠️ Fichier init_db.php non trouvé\n";
    }
    
    // 8. Créer le fichier .gitignore
    echo "\n5. Configuration Git...\n";
    $gitignoreContent = <<<GITIGNORE
# Dépendances
/vendor/
/node_modules/

# Environnement
.env
.env.local

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

GITIGNORE;
    
    if (!file_exists('.gitignore')) {
        file_put_contents('.gitignore', $gitignoreContent);
        echo "✅ Fichier .gitignore créé\n";
    }
    
    // 9. Créer des fichiers .gitkeep
    $gitkeepFiles = [
        'uploads/.gitkeep',
        'logs/.gitkeep'
    ];
    
    foreach ($gitkeepFiles as $file) {
        if (!file_exists($file)) {
            touch($file);
            echo "✅ Fichier $file créé\n";
        }
    }
    
    // 10. Configuration finale
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
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json
</IfModule>
HTACCESS;
    
    if (!file_exists('.htaccess')) {
        file_put_contents('.htaccess', $htaccessContent);
        echo "✅ Fichier .htaccess créé\n";
    }
    
    echo "\n🎉 Installation terminée avec succès !\n";
    echo "=====================================\n\n";
    
    echo "🔗 URLs d'accès :\n";
    echo "   - Application : http://localhost:8000\n";
    echo "   - Connexion   : http://localhost:8000/auth/login.php\n\n";
    
    echo "👤 Comptes de test :\n";
    echo "   - Admin      : admin / admin123\n";
    echo "   - Étudiant   : etudiant1 / password\n";
    echo "   - Superviseur: prof1 / password\n\n";
    
    echo "🚀 Pour démarrer le serveur :\n";
    echo "   composer serve\n";
    echo "   ou\n";
    echo "   php -S localhost:8000\n\n";
    
    if (!$isCLI) {
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "\n❌ Erreur d'installation : " . $e->getMessage() . "\n";
    if (!$isCLI) {
        echo "</pre>";
    }
    exit(1);
}