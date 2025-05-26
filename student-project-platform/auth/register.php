<?php
// auth/register.php - Page d'inscription
require_once '../includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    header('Location: /pages/dashboard.php');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? 'student';
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Le nom d\'utilisateur est requis.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Le nom d\'utilisateur doit faire au moins 3 caractères.';
    }
    
    if (empty($email)) {
        $errors[] = 'L\'email est requis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    }
    
    if (empty($password)) {
        $errors[] = 'Le mot de passe est requis.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Le mot de passe doit faire au moins 6 caractères.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    
    if (empty($first_name)) {
        $errors[] = 'Le prénom est requis.';
    }
    
    if (empty($last_name)) {
        $errors[] = 'Le nom est requis.';
    }
    
    if (!in_array($role, ['student', 'supervisor'])) {
        $errors[] = 'Le rôle sélectionné n\'est pas valide.';
    }
    
    // Vérifier l'unicité
    if (empty($errors)) {
        $db = getDB();
        
        $existingUser = $db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existingUser) {
            $errors[] = 'Ce nom d\'utilisateur ou cet email est déjà utilisé.';
        }
    }
    
    // Créer l'utilisateur
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $db->query("
                INSERT INTO users (username, email, password, first_name, last_name, role) 
                VALUES (?, ?, ?, ?, ?, ?)
            ", [$username, $email, $hashedPassword, $first_name, $last_name, $role]);
            
            $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
            
            // Réinitialiser le formulaire
            $_POST = [];
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la création du compte. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Plateforme de Suivi des Projets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card" style="max-width: 500px;">
            <div class="login-header">
                <h2 class="mb-0">
                    <i class="bi bi-person-plus"></i><br>
                    Créer un compte
                </h2>
                <p class="mb-0 mt-2">Rejoignez la plateforme</p>
            </div>
            
            <div class="login-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle"></i>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo e($success); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">Prénom</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo e($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Nom</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo e($_POST['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo e($_POST['username'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="student" <?php echo ($_POST['role'] ?? 'student') == 'student' ? 'selected' : ''; ?>>
                                Étudiant
                            </option>
                            <option value="supervisor" <?php echo ($_POST['role'] ?? '') == 'supervisor' ? 'selected' : ''; ?>>
                                Encadrant/Superviseur
                            </option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmer</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-person-plus"></i> Créer mon compte
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="text-muted mb-3">Déjà un compte ?</p>
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="bi bi-box-arrow-in-right"></i> Se connecter
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>