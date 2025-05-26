<?php
// simple_setup.php - Installation simple sans dépendances lourdes

echo "🚀 Installation Simple de la Plateforme\n";
echo "======================================\n\n";

try {
    // 1. Vérifier PHP
    echo "1. Vérification de PHP...\n";
    if (version_compare(PHP_VERSION, '7.4.0') < 0) {
        throw new Exception("❌ PHP 7.4+ requis. Version: " . PHP_VERSION);
    }
    echo "✅ PHP " . PHP_VERSION . " (OK)\n";
    
    // 2. Vérifier SQLite
    echo "\n2. Vérification de SQLite...\n";
    if (!extension_loaded('pdo_sqlite')) {
        echo "⚠️  PDO SQLite non détecté, tentative de chargement...\n";
        if (!extension_loaded('sqlite3')) {
            throw new Exception("❌ SQLite3 requis mais non disponible");
        }
    }
    echo "✅ SQLite disponible\n";
    
    // 3. Créer les dossiers
    echo "\n3. Création des dossiers...\n";
    $folders = ['database', 'uploads', 'uploads/documents', 'logs'];
    foreach ($folders as $folder) {
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
            echo "✅ Dossier '$folder' créé\n";
        } else {
            echo "ℹ️  Dossier '$folder' existe\n";
        }
    }
    
    // 4. Créer fichier .env simple
    echo "\n4. Configuration...\n";
    if (!file_exists('.env')) {
        $envContent = <<<ENV
APP_NAME="Plateforme Projets Étudiants"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_PATH=database/students_projects.db
SESSION_LIFETIME=120
MAX_FILE_SIZE=10485760
ENV;
        file_put_contents('.env', $envContent);
        echo "✅ Fichier .env créé\n";
    }
    
    // 5. Initialiser la base de données
    echo "\n5. Base de données...\n";
    if (file_exists('init_db.php')) {
        include 'init_db.php';
    } else {
        echo "⚠️  Fichier init_db.php non trouvé\n";
    }
    
    // 6. Créer .htaccess simple
    if (!file_exists('.htaccess')) {
        $htaccess = <<<HTACCESS
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

<FilesMatch "\.(db|sqlite|env|log)$">
    Order allow,deny
    Deny from all
</FilesMatch>
HTACCESS;
        file_put_contents('.htaccess', $htaccess);
        echo "✅ .htaccess créé\n";
    }
    
    echo "\n🎉 Installation terminée !\n";
    echo "========================\n";
    echo "🔗 Pour démarrer le serveur :\n";
    echo "   php -S localhost:8000\n\n";
    echo "🔗 Puis aller sur :\n"; 
    echo "   http://localhost:8000\n\n";
    echo "👤 Comptes de test :\n";
    echo "   admin / admin123\n";
    echo "   etudiant1 / password\n";
    echo "   prof1 / password\n\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
?>