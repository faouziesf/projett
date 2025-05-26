<?php
// pages/edit_project.php - Modifier un projet
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
$currentMembers = getProjectMembers($projectId);

// Vérifier que l'utilisateur peut modifier ce projet (créateur ou superviseur)
$canEdit = ($project['created_by'] == $currentUser['id']) || 
           ($project['supervisor_id'] == $currentUser['id']) || 
           isSupervisor();

if (!$canEdit) {
    $_SESSION['error'] = 'Vous n\'avez pas les permissions pour modifier ce projet.';
    header('Location: /pages/project_detail.php?id=' . $projectId);
    exit();
}

// Récupérer les données pour les formulaires
$supervisors = $db->fetchAll("SELECT id, first_name, last_name, email FROM users WHERE role = 'supervisor' ORDER BY first_name, last_name");
$students = $db->fetchAll("SELECT id, first_name, last_name, email FROM users WHERE role = 'student' ORDER BY first_name, last_name");

// IDs des membres actuels
$currentMemberIds = array_column($currentMembers, 'id');

$errors = [];
$pageTitle = 'Modifier - ' . $project['title'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $supervisor_id = $_POST['supervisor_id'] ?? '';
    $status = $_POST['status'] ?? $project['status'];
    $team_members = $_POST['team_members'] ?? [];
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Le titre du projet est requis.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description du projet est requise.';
    }
    
    if (empty($start_date)) {
        $errors[] = 'La date de début est requise.';
    }
    
    if (!empty($end_date) && !empty($start_date) && strtotime($end_date) <= strtotime($start_date)) {
        $errors[] = 'La date de fin doit être postérieure à la date de début.';
    }
    
    if (!empty($supervisor_id) && !is_numeric($supervisor_id)) {
        $errors[] = 'Superviseur invalide.';
    }
    
    if (!in_array($status, ['planning', 'in_progress', 'completed', 'paused'])) {
        $errors[] = 'Statut invalide.';
    }
    
    // Mettre à jour le projet
    if (empty($errors)) {
        try {
            $db->getConnection()->beginTransaction();
            
            // Mettre à jour les informations du projet
            $db->query("
                UPDATE projects 
                SET title = ?, description = ?, domain = ?, start_date = ?, end_date = ?, 
                    supervisor_id = ?, status = ?
                WHERE id = ?
            ", [$title, $description, $domain, $start_date, $end_date ?: null, $supervisor_id ?: null, $status, $projectId]);
            
            // Gérer les membres de l'équipe
            // Supprimer les anciens membres (sauf le créateur)
            $db->query("
                DELETE FROM project_members 
                WHERE project_id = ? AND user_id != ? AND role != 'leader'
            ", [$projectId, $project['created_by']]);
            
            // Ajouter les nouveaux membres
            if (!empty($team_members)) {
                foreach ($team_members as $memberId) {
                    if (is_numeric($memberId) && $memberId != $project['created_by']) {
                        // Vérifier si le membre n'existe pas déjà
                        $exists = $db->fetch("
                            SELECT COUNT(*) as count 
                            FROM project_members 
                            WHERE project_id = ? AND user_id = ?
                        ", [$projectId, $memberId]);
                        
                        if ($exists['count'] == 0) {
                            $db->query("
                                INSERT INTO project_members (project_id, user_id, role) 
                                VALUES (?, ?, 'member')
                            ", [$projectId, $memberId]);
                            
                            // Notifier le nouveau membre s'il n'était pas déjà membre
                            if (!in_array($memberId, $currentMemberIds)) {
                                addNotification(
                                    $memberId,
                                    $projectId,
                                    'Ajouté au projet',
                                    'Vous avez été ajouté au projet "' . $title . '"',
                                    'info'
                                );
                            }
                        }
                    }
                }
            }
            
            // Notifier le superviseur s'il a changé
            if (!empty($supervisor_id) && $supervisor_id != $project['supervisor_id']) {
                addNotification(
                    $supervisor_id,
                    $projectId,
                    'Projet assigné pour supervision',
                    'Le projet "' . $title . '" vous a été assigné pour supervision',
                    'info'
                );
            }
            
            $db->getConnection()->commit();
            
            $_SESSION['success'] = 'Projet modifié avec succès !';
            header('Location: /pages/project_detail.php?id=' . $projectId);
            exit();
            
        } catch (Exception $e) {
            $db->getConnection()->rollback();
            $errors[] = 'Erreur lors de la modification du projet. Veuillez réessayer.';
        }
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/pages/projects.php">Projets</a></li>
                        <li class="breadcrumb-item"><a href="/pages/project_detail.php?id=<?php echo $projectId; ?>"><?php echo e($project['title']); ?></a></li>
                        <li class="breadcrumb-item active">Modifier</li>
                    </ol>
                </nav>
                <h1 class="h3 mb-0">Modifier le Projet</h1>
                <p class="text-muted">Mettre à jour les informations du projet</p>
            </div>
            <div>
                <a href="/pages/project_detail.php?id=<?php echo $projectId; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Retour au projet
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pencil text-primary"></i> Informations du projet
                </h5>
            </div>
            <div class="card-body">
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
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Titre du projet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo e($_POST['title'] ?? $project['title']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="domain" class="form-label">Domaine</label>
                                <input type="text" class="form-control" id="domain" name="domain" 
                                       placeholder="ex: Informatique, Marketing..." 
                                       value="<?php echo e($_POST['domain'] ?? $project['domain']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Décrivez les objectifs, les livrables et les échéances du projet..." required><?php echo e($_POST['description'] ?? $project['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Date de début <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo e($_POST['start_date'] ?? $project['start_date']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">Date de fin prévue</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo e($_POST['end_date'] ?? $project['end_date']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Statut</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="planning" <?php echo ($_POST['status'] ?? $project['status']) === 'planning' ? 'selected' : ''; ?>>
                                        Planification
                                    </option>
                                    <option value="in_progress" <?php echo ($_POST['status'] ?? $project['status']) === 'in_progress' ? 'selected' : ''; ?>>
                                        En cours
                                    </option>
                                    <option value="completed" <?php echo ($_POST['status'] ?? $project['status']) === 'completed' ? 'selected' : ''; ?>>
                                        Terminé
                                    </option>
                                    <option value="paused" <?php echo ($_POST['status'] ?? $project['status']) === 'paused' ? 'selected' : ''; ?>>
                                        En pause
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="supervisor_id" class="form-label">Superviseur/Encadrant</label>
                        <select class="form-select" id="supervisor_id" name="supervisor_id">
                            <option value="">Sélectionner un superviseur (optionnel)</option>
                            <?php foreach ($supervisors as $supervisor): ?>
                            <option value="<?php echo $supervisor['id']; ?>" 
                                    <?php echo ($_POST['supervisor_id'] ?? $project['supervisor_id']) == $supervisor['id'] ? 'selected' : ''; ?>>
                                <?php echo e($supervisor['first_name'] . ' ' . $supervisor['last_name'] . ' (' . $supervisor['email'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Membres de l'équipe -->
                    <div class="mb-4">
                        <label class="form-label">Membres de l'équipe</label>
                        <div class="card">
                            <div class="card-body">
                                <!-- Chef de projet (non modifiable) -->
                                <div class="mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="member-avatar">
                                            <?php 
                                            $creator = null;
                                            foreach ($currentMembers as $member) {
                                                if ($member['project_role'] === 'leader') {
                                                    $creator = $member;
                                                    break;
                                                }
                                            }
                                            if ($creator) {
                                                echo strtoupper(substr($creator['first_name'], 0, 1) . substr($creator['last_name'], 0, 1));
                                            }
                                            ?>
                                        </div>
                                        <div>
                                            <strong>
                                                <?php echo $creator ? e($creator['first_name'] . ' ' . $creator['last_name']) : 'N/A'; ?>
                                            </strong>
                                            <span class="badge bg-primary ms-2">Chef de projet</span><br>
                                            <small class="text-muted">
                                                <?php echo $creator ? e($creator['email']) : ''; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($students)): ?>
                                <hr>
                                <p class="small text-muted mb-2">Sélectionnez les membres de votre équipe :</p>
                                <div class="row">
                                    <?php 
                                    $selectedMembers = $_POST['team_members'] ?? $currentMemberIds;
                                    ?>
                                    <?php foreach ($students as $student): ?>
                                    <?php if ($student['id'] != $project['created_by']): // Exclure le créateur ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="team_members[]" value="<?php echo $student['id']; ?>" 
                                                   id="member_<?php echo $student['id']; ?>"
                                                   <?php echo in_array($student['id'], $selectedMembers) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="member_<?php echo $student['id']; ?>">
                                                <strong><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo e($student['email']); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-muted text-center">Aucun autre étudiant disponible.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="/pages/project_detail.php?id=<?php echo $projectId; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Validation côté client
document.getElementById('end_date').addEventListener('change', function() {
    const startDate = document.getElementById('start_date').value;
    const endDate = this.value;
    
    if (startDate && endDate && new Date(endDate) <= new Date(startDate)) {
        alert('La date de fin doit être postérieure à la date de début.');
        this.value = '';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>