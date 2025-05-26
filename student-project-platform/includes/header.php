<?php
// includes/header.php - En-tête commun
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser();
$notifications = $currentUser ? getUnreadNotifications($currentUser['id']) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Plateforme de Suivi des Projets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/pages/dashboard.php">
                <i class="bi bi-clipboard-check"></i> Projets Étudiants
            </a>
            
            <?php if (isLoggedIn()): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/pages/dashboard.php">
                            <i class="bi bi-house"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/pages/projects.php">
                            <i class="bi bi-folder"></i> Mes Projets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/pages/create_project.php">
                            <i class="bi bi-plus-circle"></i> Nouveau Projet
                        </a>
                    </li>
                    <?php if (isSupervisor()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/pages/reports.php">
                            <i class="bi bi-graph-up"></i> Rapports
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- Notifications -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php if (count($notifications) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo count($notifications); ?>
                            </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <?php if (empty($notifications)): ?>
                            <li><span class="dropdown-item-text text-muted">Aucune nouvelle notification</span></li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                        <strong><?php echo e($notification['title']); ?></strong><br>
                                        <small class="text-muted"><?php echo e($notification['message']); ?></small><br>
                                        <small class="text-muted"><?php echo formatDateTime($notification['created_at']); ?></small>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <!-- Profil utilisateur -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> 
                            <?php echo e($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-header"><?php echo e($currentUser['email']); ?></span></li>
                            <li><span class="dropdown-item-text badge bg-<?php echo $currentUser['role'] == 'supervisor' ? 'warning' : 'info'; ?>">
                                <?php echo $currentUser['role'] == 'supervisor' ? 'Encadrant' : 'Étudiant'; ?>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Déconnexion
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <main class="container mt-4">
        <?php
        // Afficher les messages flash
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
            echo e($_SESSION['success']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
            unset($_SESSION['success']);
        }
        
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
            echo e($_SESSION['error']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
            unset($_SESSION['error']);
        }
        
        if (isset($_SESSION['warning'])) {
            echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
            echo e($_SESSION['warning']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
            unset($_SESSION['warning']);
        }
        ?>