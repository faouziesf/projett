<?php
// pages/reports.php - Rapports et analyses (pour superviseurs)
$pageTitle = 'Rapports et Analyses';
require_once '../includes/header.php';

requireLogin();

// Seuls les superviseurs peuvent accéder aux rapports
if (!isSupervisor()) {
    $_SESSION['error'] = 'Accès réservé aux superviseurs.';
    header('Location: /pages/dashboard.php');
    exit();
}

$db = getDB();
$currentUser = getCurrentUser();

// Statistiques générales
$totalProjects = $db->fetch("SELECT COUNT(*) as count FROM projects")['count'];
$totalStudents = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'];
$totalSupervisors = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'supervisor'")['count'];
$totalTasks = $db->fetch("SELECT COUNT(*) as count FROM tasks")['count'];
$completedTasks = $db->fetch("SELECT COUNT(*) as count FROM tasks WHERE status = 'completed'")['count'];

// Projets par statut
$projectStats = $db->fetchAll("
    SELECT status, COUNT(*) as count 
    FROM projects 
    GROUP BY status
");

$statusCounts = [
    'planning' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'paused' => 0
];

foreach ($projectStats as $stat) {
    $statusCounts[$stat['status']] = $stat['count'];
}

// Projets supervisés par l'utilisateur actuel
$myProjects = $db->fetchAll("
    SELECT p.*, 
           u.first_name as creator_first_name, u.last_name as creator_last_name,
           COALESCE(t.total_tasks, 0) as total_tasks,
           COALESCE(t.completed_tasks, 0) as completed_tasks,
           COALESCE(c.total_comments, 0) as total_comments,
           COALESCE(d.total_documents, 0) as total_documents
    FROM projects p
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN (
        SELECT project_id, 
               COUNT(*) as total_tasks,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM tasks
        GROUP BY project_id
    ) t ON p.id = t.project_id
    LEFT JOIN (
        SELECT project_id, COUNT(*) as total_comments
        FROM comments
        GROUP BY project_id
    ) c ON p.id = c.project_id
    LEFT JOIN (
        SELECT project_id, COUNT(*) as total_documents
        FROM documents
        GROUP BY project_id
    ) d ON p.id = d.project_id
    WHERE p.supervisor_id = ?
    ORDER BY p.created_at DESC
", [$currentUser['id']]);

// Répartition des projets par domaine
$domainStats = $db->fetchAll("
    SELECT 
        CASE 
            WHEN domain IS NULL OR domain = '' THEN 'Non spécifié'
            ELSE domain 
        END as domain, 
        COUNT(*) as count 
    FROM projects 
    GROUP BY domain 
    ORDER BY count DESC
    LIMIT 10
");

$avgCompletionQuery = $db->fetch("
    SELECT AVG(julianday(COALESCE(
        (SELECT MAX(created_at) FROM tasks WHERE project_id = projects.id AND status = 'completed'),
        datetime('now')
    )) - julianday(start_date)) as avg_days
    FROM projects 
    WHERE status = 'completed' AND start_date IS NOT NULL
");

// Projets récemment créés
$recentProjects = $db->fetchAll("
    SELECT p.*, 
           u.first_name as creator_first_name, u.last_name as creator_last_name,
           s.first_name as supervisor_first_name, s.last_name as supervisor_last_name
    FROM projects p
    JOIN users u ON p.created_by = u.id
    LEFT JOIN users s ON p.supervisor_id = s.id
    ORDER BY p.created_at DESC
    LIMIT 15
");

// Statistiques des tâches par priorité
$tasksByPriority = $db->fetchAll("
    SELECT priority, COUNT(*) as count 
    FROM tasks 
    GROUP BY priority
    ORDER BY 
        CASE priority 
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
        END
");

// Top étudiants les plus actifs
$activeStudents = $db->fetchAll("
    SELECT u.first_name, u.last_name, u.email,
           COUNT(DISTINCT p.id) as projects_count,
           COUNT(t.id) as tasks_count,
           COUNT(c.id) as comments_count
    FROM users u
    LEFT JOIN project_members pm ON u.id = pm.user_id
    LEFT JOIN projects p ON pm.project_id = p.id
    LEFT JOIN tasks t ON p.id = t.project_id AND t.assigned_to = u.id
    LEFT JOIN comments c ON p.id = c.project_id AND c.user_id = u.id
    WHERE u.role = 'student'
    GROUP BY u.id
    HAVING projects_count > 0
    ORDER BY (projects_count + tasks_count + comments_count) DESC
    LIMIT 10
");

// Évolution mensuelle des projets (6 derniers mois)
$monthlyProjects = $db->fetchAll("
    SELECT 
        strftime('%Y-%m', created_at) as month,
        COUNT(*) as count
    FROM projects 
    WHERE created_at >= date('now', '-6 months')
    GROUP BY strftime('%Y-%m', created_at)
    ORDER BY month
");
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-graph-up text-primary"></i> Rapports et Analyses
                </h1>
                <p class="text-muted">Tableau de bord analytique pour superviseurs</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportData()">
                    <i class="bi bi-download"></i> Exporter
                </button>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques globales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-folder display-4 mb-3"></i>
                <h2 class="display-4 mb-1"><?php echo $totalProjects; ?></h2>
                <p class="mb-0">Projets Total</p>
                <small class="text-light">Tous statuts confondus</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card bg-info">
            <div class="card-body text-center">
                <i class="bi bi-people display-4 mb-3"></i>
                <h2 class="display-4 mb-1"><?php echo $totalStudents; ?></h2>
                <p class="mb-0">Étudiants</p>
                <small class="text-light">Utilisateurs actifs</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card bg-warning">
            <div class="card-body text-center">
                <i class="bi bi-list-task display-4 mb-3"></i>
                <h2 class="display-4 mb-1"><?php echo $completedTasks; ?>/<?php echo $totalTasks; ?></h2>
                <p class="mb-0">Tâches Terminées</p>
                <small class="text-light"><?php echo $totalTasks > 0 ? round(($completedTasks/$totalTasks)*100, 1) : 0; ?>% de completion</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card bg-success">
            <div class="card-body text-center">
                <i class="bi bi-clock display-4 mb-3"></i>
                <h2 class="display-4 mb-1"><?php echo $avgCompletionTime ? round($avgCompletionTime) : 0; ?></h2>
                <p class="mb-0">Jours Moyen/Projet</p>
                <small class="text-light">Temps de réalisation</small>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et analyses -->
<div class="row mb-4">
    <!-- Graphique des statuts -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pie-chart text-primary"></i> Répartition par Statut
                </h5>
                <span class="badge bg-secondary"><?php echo $totalProjects; ?> projets</span>
            </div>
            <div class="card-body">
                <canvas id="statusChart" height="250" 
                        data-planning="<?php echo $statusCounts['planning']; ?>"
                        data-in-progress="<?php echo $statusCounts['in_progress']; ?>"
                        data-completed="<?php echo $statusCounts['completed']; ?>"
                        data-paused="<?php echo $statusCounts['paused']; ?>"></canvas>
                
                <!-- Légende détaillée -->
                <div class="row mt-3">
                    <div class="col-6">
                        <div class="d-flex align-items-center mb-2">
                            <div class="badge bg-secondary me-2">&nbsp;</div>
                            <small>Planification: <?php echo $statusCounts['planning']; ?></small>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="badge bg-info me-2">&nbsp;</div>
                            <small>En cours: <?php echo $statusCounts['in_progress']; ?></small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center mb-2">
                            <div class="badge bg-success me-2">&nbsp;</div>
                            <small>Terminés: <?php echo $statusCounts['completed']; ?></small>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="badge bg-warning me-2">&nbsp;</div>
                            <small>En pause: <?php echo $statusCounts['paused']; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top domaines -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart text-info"></i> Domaines d'Étude
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($domainStats)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-graph-down text-muted display-4"></i>
                    <p class="text-muted mt-2">Aucune donnée disponible</p>
                </div>
                <?php else: ?>
                <?php foreach ($domainStats as $index => $domain): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                        <span><?php echo e($domain['domain']); ?></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="progress me-2" style="width: 80px; height: 20px;">
                            <div class="progress-bar bg-info" 
                                 style="width: <?php echo $totalProjects > 0 ? ($domain['count'] / $totalProjects) * 100 : 0; ?>%"></div>
                        </div>
                        <span class="badge bg-info"><?php echo $domain['count']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Mes projets supervisés -->
<?php if (!empty($myProjects)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-check text-success"></i> Mes Projets Supervisés
                </h5>
                <span class="badge bg-success fs-6"><?php echo count($myProjects); ?> projets</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Projet</th>
                                <th>Créateur</th>
                                <th>Domaine</th>
                                <th>Statut</th>
                                <th>Progression</th>
                                <th>Tâches</th>
                                <th>Activité</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myProjects as $project): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo e($project['title']); ?></strong>
                                        <?php if (!empty($project['description'])): ?>
                                        <br><small class="text-muted">
                                            <?php echo e(substr($project['description'], 0, 60) . (strlen($project['description']) > 60 ? '...' : '')); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="member-avatar small me-2">
                                            <?php echo strtoupper(substr($project['creator_first_name'], 0, 1) . substr($project['creator_last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <small><strong><?php echo e($project['creator_first_name'] . ' ' . $project['creator_last_name']); ?></strong></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($project['domain']): ?>
                                    <span class="badge bg-light text-dark"><?php echo e($project['domain']); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusClass($project['status']); ?>">
                                        <?php echo getStatusLabel($project['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress me-2" style="width: 60px; height: 15px;">
                                            <div class="progress-bar bg-<?php echo getStatusClass($project['status']); ?>" 
                                                 style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $project['progress_percentage']; ?>%</small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($project['total_tasks'] > 0): ?>
                                    <span class="text-success fw-bold"><?php echo $project['completed_tasks']; ?></span>
                                    <span class="text-muted">/ <?php echo $project['total_tasks']; ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">Aucune</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <?php if ($project['total_comments'] > 0): ?>
                                        <span class="badge bg-info" title="Commentaires">
                                            <i class="bi bi-chat"></i> <?php echo $project['total_comments']; ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($project['total_documents'] > 0): ?>
                                        <span class="badge bg-secondary" title="Documents">
                                            <i class="bi bi-file"></i> <?php echo $project['total_documents']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo formatDate($project['created_at']); ?></small>
                                </td>
                                <td>
                                    <a href="/pages/project_detail.php?id=<?php echo $project['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Voir détails">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Section étudiants actifs et projets récents -->
<div class="row mb-4">
    <!-- Étudiants les plus actifs -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-trophy text-warning"></i> Top Étudiants Actifs
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($activeStudents)): ?>
                <p class="text-muted text-center">Aucune activité détectée</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($activeStudents, 0, 5) as $index => $student): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="member-avatar me-3">
                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                <br><small class="text-muted"><?php echo e($student['email']); ?></small>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="d-flex gap-1 mb-1">
                                <span class="badge bg-primary" title="Projets">
                                    <?php echo $student['projects_count']; ?> <i class="bi bi-folder"></i>
                                </span>
                                <span class="badge bg-info" title="Tâches">
                                    <?php echo $student['tasks_count']; ?> <i class="bi bi-check"></i>
                                </span>
                                <span class="badge bg-warning" title="Commentaires">
                                    <?php echo $student['comments_count']; ?> <i class="bi bi-chat"></i>
                                </span>
                            </div>
                            <small class="text-muted">
                                Score: <?php echo $student['projects_count'] + $student['tasks_count'] + $student['comments_count']; ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Tâches par priorité -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-exclamation-triangle text-danger"></i> Tâches par Priorité
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($tasksByPriority)): ?>
                <p class="text-muted text-center">Aucune tâche créée</p>
                <?php else: ?>
                <?php foreach ($tasksByPriority as $priority): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-<?php echo $priority['priority'] === 'high' ? 'exclamation-triangle text-danger' : ($priority['priority'] === 'medium' ? 'dash-circle text-warning' : 'circle text-success'); ?> me-2"></i>
                        <span class="text-capitalize"><?php echo $priority['priority'] === 'high' ? 'Élevée' : ($priority['priority'] === 'medium' ? 'Moyenne' : 'Faible'); ?></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="progress me-2" style="width: 100px; height: 20px;">
                            <div class="progress-bar bg-<?php echo $priority['priority'] === 'high' ? 'danger' : ($priority['priority'] === 'medium' ? 'warning' : 'success'); ?>" 
                                 style="width: <?php echo $totalTasks > 0 ? ($priority['count'] / $totalTasks) * 100 : 0; ?>%"></div>
                        </div>
                        <span class="badge bg-<?php echo $priority['priority'] === 'high' ? 'danger' : ($priority['priority'] === 'medium' ? 'warning' : 'success'); ?>">
                            <?php echo $priority['count']; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        Total: <strong><?php echo $totalTasks; ?></strong> tâches créées
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Projets récents -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history text-secondary"></i> Projets Récents
                </h5>
                <a href="/pages/projects.php" class="btn btn-sm btn-outline-primary">
                    Voir tous les projets
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Projet</th>
                                <th>Créé par</th>
                                <th>Superviseur</th>
                                <th>Domaine</th>
                                <th>Statut</th>
                                <th>Date de création</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentProjects as $project): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($project['title']); ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="member-avatar small me-2">
                                            <?php echo strtoupper(substr($project['creator_first_name'], 0, 1) . substr($project['creator_last_name'], 0, 1)); ?>
                                        </div>
                                        <?php echo e($project['creator_first_name'] . ' ' . $project['creator_last_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($project['supervisor_first_name']): ?>
                                    <small><?php echo e($project['supervisor_first_name'] . ' ' . $project['supervisor_last_name']); ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($project['domain']): ?>
                                    <span class="badge bg-light text-dark small"><?php echo e($project['domain']); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusClass($project['status']); ?> small">
                                        <?php echo getStatusLabel($project['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo formatDateTime($project['created_at']); ?></small>
                                </td>
                                <td>
                                    <a href="/pages/project_detail.php?id=<?php echo $project['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inclure Chart.js depuis CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique de répartition par statut
const statusChartCanvas = document.getElementById('statusChart');
if (statusChartCanvas) {
    const ctx = statusChartCanvas.getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['En cours', 'Terminés', 'En pause', 'Planification'],
            datasets: [{
                data: [
                    parseInt(statusChartCanvas.dataset.inProgress) || 0,
                    parseInt(statusChartCanvas.dataset.completed) || 0,
                    parseInt(statusChartCanvas.dataset.paused) || 0,
                    parseInt(statusChartCanvas.dataset.planning) || 0
                ],
                backgroundColor: ['#0dcaf0', '#198754', '#ffc107', '#6c757d'],
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false // Masquer la légende par défaut
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            },
            cutout: '60%'
        }
    });
}

// Fonction pour exporter les données
function exportData() {
    const data = {
        totalProjects: <?php echo $totalProjects; ?>,
        totalStudents: <?php echo $totalStudents; ?>,
        totalSupervisors: <?php echo $totalSupervisors; ?>,
        totalTasks: <?php echo $totalTasks; ?>,
        completedTasks: <?php echo $completedTasks; ?>,
        avgCompletionTime: <?php echo $avgCompletionTime ? round($avgCompletionTime) : 0; ?>,
        statusStats: {
            planning: <?php echo $statusCounts['planning']; ?>,
            inProgress: <?php echo $statusCounts['in_progress']; ?>,
            completed: <?php echo $statusCounts['completed']; ?>,
            paused: <?php echo $statusCounts['paused']; ?>
        },
        domains: <?php echo json_encode($domainStats); ?>,
        generatedAt: new Date().toLocaleString('fr-FR')
    };
    
    // Créer et télécharger le fichier JSON
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(data, null, 2));
    const dlAnchorElem = document.createElement('a');
    dlAnchorElem.setAttribute("href", dataStr);
    dlAnchorElem.setAttribute("download", "rapport_projets_" + new Date().toISOString().split('T')[0] + ".json");
    dlAnchorElem.click();
    
    showNotification('Données exportées avec succès', 'success');
}

// Initialiser les tooltips Bootstrap pour les badges d'activité
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Animation des compteurs au chargement
function animateCounters() {
    const counters = document.querySelectorAll('.display-4');
    counters.forEach(counter => {
        const target = parseInt(counter.textContent);
        if (isNaN(target)) return;
        
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            counter.textContent = Math.floor(current);
        }, 30);
    });
}

// Lancer l'animation au chargement
document.addEventListener('DOMContentLoaded', animateCounters);

// Fonction pour rafraîchir les données (appelée toutes les 5 minutes)
function refreshData() {
    // Recharger la page pour mettre à jour les statistiques
    location.reload();
}

// Auto-refresh toutes les 5 minutes
setInterval(refreshData, 5 * 60 * 1000);

// Indicateur de dernière mise à jour
document.addEventListener('DOMContentLoaded', function() {
    const updateTime = document.createElement('div');
    updateTime.className = 'text-muted text-center mt-4';
    updateTime.innerHTML = '<small><i class="bi bi-clock"></i> Dernière mise à jour: ' + new Date().toLocaleString('fr-FR') + '</small>';
    document.querySelector('main').appendChild(updateTime);
});
</script>

<style>
/* Styles spécifiques pour les rapports */
.stats-card {
    border: none;
    border-radius: 15px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.member-avatar.small {
    width: 30px;
    height: 30px;
    font-size: 0.8rem;
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
}

.card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
    background-color: #f8f9fa;
}

.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}

.list-group-item {
    transition: background-color 0.2s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

/* Animation pour les compteurs */
.display-4 {
    font-weight: bold;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Style pour l'impression */
@media print {
    .btn, .dropdown, .navbar {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }
    
    .stats-card {
        background: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
    }
    
    .bg-primary, .bg-info, .bg-warning, .bg-success {
        -webkit-print-color-adjust: exact;
    }
}

/* Responsive pour mobile */
@media (max-width: 768px) {
    .stats-card .display-4 {
        font-size: 2rem;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .member-avatar {
        width: 35px;
        height: 35px;
    }
}

/* Animation d'apparition */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.6s ease-out;
}

.stats-card:nth-child(1) { animation-delay: 0.1s; }
.stats-card:nth-child(2) { animation-delay: 0.2s; }
.stats-card:nth-child(3) { animation-delay: 0.3s; }
.stats-card:nth-child(4) { animation-delay: 0.4s; }
</style>

<?php require_once '../includes/footer.php'; ?>