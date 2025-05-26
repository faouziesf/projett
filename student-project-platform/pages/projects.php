<?php
// pages/projects.php - Liste des projets
$pageTitle = 'Mes Projets';
require_once '../includes/header.php';

requireLogin();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

// Filtres
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Récupérer les projets
$myProjects = getUserProjects($userId);

// Appliquer les filtres
if (!empty($statusFilter)) {
    $myProjects = array_filter($myProjects, function($project) use ($statusFilter) {
        return $project['status'] === $statusFilter;
    });
}

if (!empty($searchQuery)) {
    $myProjects = array_filter($myProjects, function($project) use ($searchQuery) {
        return stripos($project['title'], $searchQuery) !== false || 
               stripos($project['description'], $searchQuery) !== false;
    });
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Mes Projets</h1>
                <p class="text-muted">Gérez tous vos projets académiques et professionnels</p>
            </div>
            <div>
                <a href="/pages/create_project.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nouveau projet
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Rechercher</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Titre ou description..." 
                                   value="<?php echo e($searchQuery); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="planning" <?php echo $statusFilter === 'planning' ? 'selected' : ''; ?>>
                                Planification
                            </option>
                            <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>
                                En cours
                            </option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>
                                Terminé
                            </option>
                            <option value="paused" <?php echo $statusFilter === 'paused' ? 'selected' : ''; ?>>
                                En pause
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary me-2">
                            <i class="bi bi-funnel"></i> Filtrer
                        </button>
                        <a href="/pages/projects.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Reset
                        </a>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end justify-content-end">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary active" onclick="toggleView('grid')">
                                <i class="bi bi-grid-3x3"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleView('list')">
                                <i class="bi bi-list"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Liste des projets -->
<div class="row" id="projects-container">
    <?php if (empty($myProjects)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-folder-x display-1 text-muted"></i>
                <h4 class="mt-3">Aucun projet trouvé</h4>
                <p class="text-muted">
                    <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                        Aucun projet ne correspond aux critères de recherche.
                    <?php else: ?>
                        Vous n'avez pas encore de projet. Commencez par en créer un !
                    <?php endif; ?>
                </p>
                <?php if (empty($searchQuery) && empty($statusFilter)): ?>
                <a href="/pages/create_project.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Créer mon premier projet
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <?php foreach ($myProjects as $project): ?>
    <div class="col-lg-4 col-md-6 mb-4 project-item">
        <div class="card project-card status-<?php echo $project['status']; ?> h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="card-title mb-0">
                        <a href="/pages/project_detail.php?id=<?php echo $project['id']; ?>" 
                           class="text-decoration-none">
                            <?php echo e($project['title']); ?>
                        </a>
                    </h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="/pages/project_detail.php?id=<?php echo $project['id']; ?>">
                                    <i class="bi bi-eye"></i> Voir détails
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/pages/edit_project.php?id=<?php echo $project['id']; ?>">
                                    <i class="bi bi-pencil"></i> Modifier
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="#" 
                                   onclick="deleteProject(<?php echo $project['id']; ?>)">
                                    <i class="bi bi-trash"></i> Supprimer
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="mb-3">
                    <span class="badge bg-<?php echo getStatusClass($project['status']); ?> mb-2">
                        <?php echo getStatusLabel($project['status']); ?>
                    </span>
                    <?php if (!empty($project['domain'])): ?>
                    <span class="badge bg-light text-dark ms-1"><?php echo e($project['domain']); ?></span>
                    <?php endif; ?>
                </div>
                
                <p class="card-text text-muted text-truncate-2">
                    <?php echo e($project['description'] ?: 'Aucune description'); ?>
                </p>
                
                <div class="row align-items-center mb-3">
                    <div class="col-8">
                        <small class="text-muted d-block">Progression</small>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-<?php echo getStatusClass($project['status']); ?>" 
                                 style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="progress-circle small" data-progress="<?php echo $project['progress_percentage']; ?>">
                            <?php echo $project['progress_percentage']; ?>%
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center text-muted small">
                    <div>
                        <i class="bi bi-calendar"></i>
                        <?php echo formatDate($project['start_date']); ?>
                    </div>
                    <?php if ($project['first_name']): ?>
                    <div>
                        <i class="bi bi-person"></i>
                        <?php echo e($project['first_name'] . ' ' . $project['last_name']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-footer bg-transparent border-top-0">
                <div class="d-flex gap-2">
                    <a href="/pages/project_detail.php?id=<?php echo $project['id']; ?>" 
                       class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-eye"></i> Détails
                    </a>
                    <a href="/pages/edit_project.php?id=<?php echo $project['id']; ?>" 
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleView(view) {
    const container = document.getElementById('projects-container');
    const buttons = document.querySelectorAll('[onclick^="toggleView"]');
    
    // Mettre à jour les boutons
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    if (view === 'list') {
        container.classList.add('list-view');
        document.querySelectorAll('.project-item').forEach(item => {
            item.className = 'col-12 mb-3 project-item';
        });
    } else {
        container.classList.remove('list-view');
        document.querySelectorAll('.project-item').forEach(item => {
            item.className = 'col-lg-4 col-md-6 mb-4 project-item';
        });
    }
}

function deleteProject(projectId) {
    if (confirmDelete('Êtes-vous sûr de vouloir supprimer ce projet ? Cette action est irréversible.')) {
        fetch('/api/delete_project.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({project_id: projectId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur lors de la suppression: ' + data.message);
            }
        });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>