<?php
// pages/create_project.php - Créer un nouveau projet
$pageTitle = 'Nouveau Projet';
require_once '../includes/header.php';

requireLogin();

$currentUser = getCurrentUser();
$errors = [];
$success = '';

// Récupérer la liste des superviseurs
$db = getDB();
$supervisors = $db->fetchAll("SELECT id, first_name, last_name, email FROM users WHERE role = 'supervisor' ORDER BY first_name, last_name");

// Récupérer la liste des étudiants pour les membres d'équipe
$students = $db->fetchAll("SELECT id, first_name, last_name, email FROM users WHERE role = 'student' AND id != ? ORDER BY first_name, last_name", [$currentUser['id']]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $supervisor_id = $_POST['supervisor_id'] ?? '';
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
    
    // Créer le projet
    if (empty($errors)) {
        try {
            $db->getConnection()->beginTransaction();
            
            // Insérer le projet
            $db->query("
                INSERT INTO projects (title, description, domain, start_date, end_date, supervisor_id, created_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'planning')
            ", [$title, $description, $domain, $start_date, $end_date ?: null, $supervisor_id ?: null, $currentUser['id']]);
            
            $projectId = $db->lastInsertId();
            
            // Ajouter le créateur comme leader du projet
            $db->query("
                INSERT INTO project_members (project_id, user_id, role) 
                VALUES (?, ?, 'leader')
            ", [$projectId, $currentUser['id']]);
            
            // Ajouter les membres de l'équipe
            if (!empty($team_members)) {
                foreach ($team_members as $memberId) {
                    if (is_numeric($memberId) && $memberId != $currentUser['id']) {
                        $db->query("
                            INSERT INTO project_members (project_id, user_id, role) 
                            VALUES (?, ?, 'member')
                        ", [$projectId, $memberId]);
                        
                        // Notifier le nouveau membre
                        addNotification(
                            $memberId,
                            $projectId,
                            'Nouveau projet assigné',
                            'Vous avez été ajouté au projet "' . $title . '"',
                            'info'
                        );
                    }
                }
            }
            
            // Notifier le superviseur s'il y en a un
            if (!empty($supervisor_id)) {
                addNotification(
                    $supervisor_id,
                    $projectId,
                    'Nouveau projet à superviser',
                    'Un nouveau projet "' . $title . '" vous a été assigné pour supervision',
                    'info'
                );
            }
            
            $db->getConnection()->commit();
            
            $_SESSION['success'] = 'Projet créé avec succès !';
            header('Location: /pages/project_detail.php?id=' . $projectId);
            exit();
            
        } catch (Exception $e) {
            $db->getConnection()->rollback();
            $errors[] = 'Erreur lors de la création du projet. Veuillez réessayer.';
        }
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Nouveau Projet</h1>
                <p class="text-muted">Créez un nouveau projet et constituez votre équipe</p>
            </div>
            <div>
                <a href="/pages/projects.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Retour aux projets
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
                    <i class="bi bi-plus-circle text-primary"></i> Informations du projet
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
                                       value="<?php echo e($_POST['title'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="domain" class="form-label">Domaine</label>
                                <input type="text" class="form-control" id="domain" name="domain" 
                                       placeholder="ex: Informatique, Marketing..." 
                                       value="<?php echo e($_POST['domain'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Décrivez les objectifs, les livrables et les échéances du projet..." required><?php echo e($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Date de début <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo e($_POST['start_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">Date de fin prévue</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo e($_POST['end_date'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="supervisor_id" class="form-label">Superviseur/Encadrant</label>
                        <select class="form-select" id="supervisor_id" name="supervisor_id">
                            <option value="">Sélectionner un superviseur (optionnel)</option>
                            <?php foreach ($supervisors as $supervisor): ?>
                            <option value="<?php echo $supervisor['id']; ?>" 
                                    <?php echo ($_POST['supervisor_id'] ?? '') == $supervisor['id'] ? 'selected' : ''; ?>>
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
                                <div class="mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="member-avatar">
                                            <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo e($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></strong>
                                            <span class="badge bg-primary ms-2">Chef de projet</span><br>
                                            <small class="text-muted"><?php echo e($currentUser['email']); ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($students)): ?>
                                <hr>
                                <p class="small text-muted mb-2">Sélectionnez les membres de votre équipe :</p>
                                <div class="row">
                                    <?php foreach ($students as $student): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="team_members[]" value="<?php echo $student['id']; ?>" 
                                                   id="member_<?php echo $student['id']; ?>"
                                                   <?php echo in_array($student['id'], $_POST['team_members'] ?? []) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="member_<?php echo $student['id']; ?>">
                                                <strong><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo e($student['email']); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-muted text-center">Aucun autre étudiant disponible pour rejoindre l'équipe.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="/pages/projects.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Créer le projet
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

// Auto-focus sur le titre
document.getElementById('title').focus();
</script>

<?php require_once '../includes/footer.php'; ?>