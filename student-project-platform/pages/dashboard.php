<?php
// pages/dashboard.php - Tableau de bord
$pageTitle = 'Tableau de bord';
require_once '../includes/header.php';

requireLogin();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

// Statistiques générales
$db = getDB();

// Mes projets
$myProjects = getUserProjects($userId);
$totalProjects = count($myProjects);

// Statistiques par statut
$stats = [
    'in_progress' => 0,
    'completed' => 0,
    'paused' => 0,
    'planning' => 0
];

foreach ($myProjects as $project) {
    if (isset($stats[$project['status']])) {
        $stats[$project['status']]++;
    }
}

// Tâches récentes
$recentTasks = $db->fetchAll("
    SELECT t.*, p.title as project_title, u.first_name, u.last_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE p.id IN (
        SELECT project_id FROM project_members WHERE user_id = ?
        UNION
        SELECT id FROM projects WHERE created_by = ?
    )
    ORDER BY t.created_at DESC
    LIMIT 10
", [$userId, $userId]);

// Projets récents
$recentProjects = array_slice($myProjects, 0, 5);

// Notifications récentes
$recentNotifications = getUnreadNotifications($userId);
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Tableau de bord</h1>
                <p class="text-muted">Bonjour <?php echo e($currentUser['first_name']); ?>, voici un aperçu de vos projets</p>
            </div>
            <div>
                <a href="/pages/create_project.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouveau projet
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-folder display-4 mb-3"></i>
                <h2 class="display-4"><?php echo $totalProjects; ?></h2>
                <p class="mb-0">Projets Total</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card bg-success">
            <div class="card-body text-center">
                <i class="bi bi-check-circle display-4 mb-3"></i>
                <h2 class="display-4"><?php echo $stats['completed']; ?></h2>
                <p class="mb-0">Terminés</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card bg-info">
            <div class="card-body text-center">
                <i class="bi bi-play-circle display-4 mb-3"></i>
                <h2 class="display-4"><?php echo $stats['in_progress']; ?></h2>
                <p class="mb-0">En cours</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card bg-warning">
            <div class="card-body text-center">
                <i class="bi bi-pause-circle display-4 mb-3"></i>
                <h2 class="display-4"><?php echo $stats['paused']; ?></h2>
                <p class="mb-0">En pause</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Projets récents -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-folder-fill text-primary"></i> Projets récents
                </h5>
                <a href="/pages/projects.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentProjects)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-folder-x display-4 text-muted"></i>
                    <p class="text-muted mt-2">Aucun projet pour le moment</p>
                    <a href="/pages/create_project.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Créer un projet
                    </a>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($recentProjects as $project): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card project-card status-<?php echo $project['status']; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0">
                                        <a href="/pages/project_detail.php?id=<?php echo $project['id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo e($project['title']); ?>
                                        </a>
                                    </h6>
                                    <span class="badge bg-<?php echo getStatusClass($project['status']); ?>">
                                        <?php echo getStatusLabel($project['status']); ?>
                                    </span>
                                </div>
                                <p class="card-text text-muted small text-truncate-2">
                                    <?php echo e($project['description']); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php echo e($project['domain']); ?>
                                    </small>
                                    <div class="progress-circle" data-progress="<?php echo $project['progress_percentage']; ?>">
                                        <?php echo $project['progress_percentage']; ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Tâches récentes -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-list-task text-info"></i> Tâches récentes
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentTasks)): ?>
                <p class="text-muted text-center">Aucune tâche</p>
                <?php else: ?>
                <?php foreach (array_slice($recentTasks, 0, 5) as $task): ?>
                <div class="task-item status-<?php echo $task['status']; ?> mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <strong class="small"><?php echo e($task['title']); ?></strong><br>
                            <small class="text-muted"><?php echo e($task['project_title']); ?></small>
                        </div>
                        <span class="badge bg-<?php echo getStatusClass($task['status']); ?> small">
                            <?php echo getStatusLabel($task['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Notifications -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-bell text-warning"></i> Notifications récentes
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentNotifications)): ?>
                <p class="text-muted text-center">Aucune notification</p>
                <?php else: ?>
                <?php foreach (array_slice($recentNotifications, 0, 3) as $notification): ?>
                <div class="notification-item mb-3">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <span class="badge bg-<?php echo $notification['type']; ?> rounded-pill">&nbsp;</span>
                        </div>
                        <div class="flex-grow-1 ms-2">
                            <strong class="small"><?php echo e($notification['title']); ?></strong><br>
                            <small class="text-muted"><?php echo e($notification['message']); ?></small><br>
                            <small class="text-muted"><?php echo formatDateTime($notification['created_at']); ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>