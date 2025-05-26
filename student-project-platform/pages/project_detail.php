<?php
// pages/project_detail.php - Détails d'un projet (nom corrigé)
require_once '../includes/header.php';

requireLogin();

$currentUser = getCurrentUser();
$projectId = $_GET['id'] ?? 0;

if (!$projectId || !canAccessProject($currentUser['id'], $projectId)) {
    $_SESSION['error'] = 'Projet non trouvé ou accès non autorisé.';
    header('Location: /pages/projects.php');
    exit();
}

$db = getDB();
$project = getProject($projectId);
$members = getProjectMembers($projectId);
$tasks = getProjectTasks($projectId);

// Récupérer les commentaires
$comments = $db->fetchAll("
    SELECT c.*, u.first_name, u.last_name
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.project_id = ?
    ORDER BY c.created_at DESC
", [$projectId]);

// Récupérer les documents
$documents = $db->fetchAll("
    SELECT d.*, u.first_name, u.last_name
    FROM documents d
    JOIN users u ON d.uploaded_by = u.id
    WHERE d.project_id = ?
    ORDER BY d.created_at DESC
", [$projectId]);

// Mettre à jour le pourcentage si nécessaire
$currentProgress = updateProjectProgress($projectId);

$pageTitle = $project['title'];
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/pages/projects.php">Projets</a></li>
                        <li class="breadcrumb-item active"><?php echo e($project['title']); ?></li>
                    </ol>
                </nav>
                <h1 class="h3 mb-2"><?php echo e($project['title']); ?></h1>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-<?php echo getStatusClass($project['status']); ?> fs-6">
                        <?php echo getStatusLabel($project['status']); ?>
                    </span>
                    <?php if (!empty($project['domain'])): ?>
                    <span class="badge bg-light text-dark"><?php echo e($project['domain']); ?></span>
                    <?php endif; ?>
                    <div class="progress-circle" data-progress="<?php echo $currentProgress; ?>">
                        <?php echo $currentProgress; ?>%
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="/pages/edit_project.php?id=<?php echo $projectId; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-pencil"></i> Modifier
                </a>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="changeStatus('planning')">
                            <i class="bi bi-clock"></i> Planification
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="changeStatus('in_progress')">
                            <i class="bi bi-play"></i> En cours
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="changeStatus('completed')">
                            <i class="bi bi-check"></i> Terminé
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="changeStatus('paused')">
                            <i class="bi bi-pause"></i> En pause
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteProject(<?php echo $projectId; ?>)">
                            <i class="bi bi-trash"></i> Supprimer
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Informations principales -->
    <div class="col-lg-8">
        <!-- Description -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-file-text text-primary"></i> Description
                </h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(e($project['description'])); ?></p>
            </div>
        </div>
        
        <!-- Tâches -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-task text-info"></i> Tâches (<?php echo count($tasks); ?>)
                </h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="bi bi-plus"></i> Ajouter une tâche
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-list-task display-4 text-muted"></i>
                    <p class="text-muted mt-2">Aucune tâche créée pour ce projet</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="bi bi-plus"></i> Créer la première tâche
                    </button>
                </div>
                <?php else: ?>
                <div class="tasks-list">
                    <?php foreach ($tasks as $task): ?>
                    <div class="task-item status-<?php echo $task['status']; ?> mb-3" data-task-id="<?php echo $task['id']; ?>">
                        <div class="d-flex align-items-start">
                            <div class="form-check me-3">
                                <input class="form-check-input task-checkbox" type="checkbox" 
                                       data-task-id="<?php echo $task['id']; ?>"
                                       <?php echo $task['status'] === 'completed' ? 'checked' : ''; ?>>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0 <?php echo $task['status'] === 'completed' ? 'text-decoration-line-through text-muted' : ''; ?>">
                                        <?php echo e($task['title']); ?>
                                    </h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : 'success'); ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                        <span class="badge bg-<?php echo getStatusClass($task['status']); ?>">
                                            <?php echo getStatusLabel($task['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($task['description'])): ?>
                                <p class="text-muted small mb-2"><?php echo e($task['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center text-muted small">
                                    <div>
                                        <?php if ($task['first_name']): ?>
                                        <i class="bi bi-person"></i> <?php echo e($task['first_name'] . ' ' . $task['last_name']); ?>
                                        <?php else: ?>
                                        <i class="bi bi-person"></i> Non assignée
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($task['due_date']): ?>
                                        <i class="bi bi-calendar"></i> <?php echo formatDate($task['due_date']); ?>
                                        <?php endif; ?>
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
        
        <!-- Commentaires et Recommandations -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-chat-dots text-warning"></i> Commentaires et Recommandations
                </h5>
            </div>
            <div class="card-body">
                <!-- Formulaire d'ajout de commentaire -->
                <form class="comment-form mb-4">
                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                    <div class="mb-3">
                        <textarea class="form-control" name="comment" rows="3" 
                                  placeholder="Ajouter un commentaire ou une recommandation..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" id="comment" value="comment" checked>
                                <label class="form-check-label" for="comment">Commentaire</label>
                            </div>
                            <?php if (isSupervisor()): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" id="recommendation" value="recommendation">
                                <label class="form-check-label" for="recommendation">Recommandation</label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Publier
                        </button>
                    </div>
                </form>
                
                <!-- Liste des commentaires -->
                <div class="comments-list">
                    <?php if (empty($comments)): ?>
                    <p class="text-muted text-center">Aucun commentaire pour le moment</p>
                    <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                    <div class="comment-item type-<?php echo $comment['type']; ?> mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center">
                                <div class="member-avatar small me-2">
                                    <?php echo strtoupper(substr($comment['first_name'], 0, 1) . substr($comment['last_name'], 0, 1)); ?>
                                </div>
                                <strong><?php echo e($comment['first_name'] . ' ' . $comment['last_name']); ?></strong>
                                <?php if ($comment['type'] === 'recommendation'): ?>
                                <span class="badge bg-warning ms-2">Recommandation</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?php echo formatDateTime($comment['created_at']); ?></small>
                        </div>
                        <p class="mb-0"><?php echo nl2br(e($comment['comment'])); ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar droite -->
    <div class="col-lg-4">
        <!-- Informations du projet -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-info-circle text-info"></i> Informations
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted">Date de début:</td>
                        <td><?php echo formatDate($project['start_date']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Date de fin:</td>
                        <td><?php echo formatDate($project['end_date']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Superviseur:</td>
                        <td>
                            <?php if ($project['supervisor_first_name']): ?>
                            <?php echo e($project['supervisor_first_name'] . ' ' . $project['supervisor_last_name']); ?>
                            <?php else: ?>
                            <span class="text-muted">Non assigné</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Créé par:</td>
                        <td><?php echo e($project['creator_first_name'] . ' ' . $project['creator_last_name']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Progression:</td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-<?php echo getStatusClass($project['status']); ?>" 
                                     style="width: <?php echo $currentProgress; ?>%">
                                    <?php echo $currentProgress; ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Membres de l'équipe -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-people text-success"></i> Équipe (<?php echo count($members); ?>)
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($members as $member): ?>
                <div class="d-flex align-items-center mb-3">
                    <div class="member-avatar">
                        <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <strong><?php echo e($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                        <?php if ($member['project_role'] === 'leader'): ?>
                        <span class="badge bg-primary ms-1">Chef</span>
                        <?php endif; ?>
                        <br>
                        <small class="text-muted"><?php echo e($member['email']); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Documents -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="bi bi-file-earmark text-secondary"></i> Documents
                </h6>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="bi bi-upload"></i>
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                <p class="text-muted text-center small">Aucun document</p>
                <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                <div class="document-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <strong class="small"><?php echo e($doc['original_name']); ?></strong><br>
                            <small class="text-muted">
                                Par <?php echo e($doc['first_name'] . ' ' . $doc['last_name']); ?><br>
                                <?php echo formatDateTime($doc['created_at']); ?>
                            </small>
                        </div>
                        <a href="/uploads/documents/<?php echo e($doc['filename']); ?>" 
                           class="btn btn-sm btn-outline-secondary" download>
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter Tâche -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle Tâche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTaskForm">
                <div class="modal-body">
                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                    <div class="mb-3">
                        <label class="form-label">Titre de la tâche</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Priorité</label>
                                <select class="form-select" name="priority">
                                    <option value="low">Faible</option>
                                    <option value="medium" selected>Moyenne</option>
                                    <option value="high">Élevée</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Assignée à</label>
                                <select class="form-select" name="assigned_to">
                                    <option value="">Non assignée</option>
                                    <?php foreach ($members as $member): ?>
                                    <option value="<?php echo $member['id']; ?>">
                                        <?php echo e($member['first_name'] . ' ' . $member['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date d'échéance</label>
                        <input type="date" class="form-control" name="due_date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer la tâche</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Upload Document -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                    <div class="mb-3">
                        <label class="form-label">Sélectionner un fichier</label>
                        <input type="file" class="form-control" name="document" required>
                        <div class="form-text">Types acceptés: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Uploader</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Gestion des tâches
document.getElementById('addTaskForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    fetch('/api/add_task.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur: ' + data.message);
        }
    });
});

// Gestion de l'upload
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/upload_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur: ' + data.message);
        }
    });
});

// Changer le statut du projet
function changeStatus(newStatus) {
    if (confirm('Êtes-vous sûr de vouloir changer le statut du projet ?')) {
        fetch('/api/update_project_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                project_id: <?php echo $projectId; ?>,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}

// Supprimer le projet
function deleteProject(projectId) {
    if (confirmDelete('Êtes-vous sûr de vouloir supprimer ce projet ? Cette action supprimera également toutes les tâches, commentaires et documents associés.')) {
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
                window.location.href = '/pages/projects.php';
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>