<?php
// api/update_task.php - API pour mettre à jour une tâche
header('Content-Type: application/json');
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$taskId = $input['task_id'] ?? 0;
$status = $input['status'] ?? '';

if (!$taskId || !in_array($status, ['todo', 'in_progress', 'completed'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

try {
    $db = getDB();
    
    // Vérifier que l'utilisateur peut accéder à cette tâche
    $task = $db->fetch("
        SELECT t.*, p.id as project_id
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.id = ?
    ", [$taskId]);
    
    if (!$task || !canAccessProject($_SESSION['user_id'], $task['project_id'])) {
        echo json_encode(['success' => false, 'message' => 'Tâche non trouvée ou accès non autorisé']);
        exit();
    }
    
    // Mettre à jour la tâche
    $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
    
    $db->query("
        UPDATE tasks 
        SET status = ?, completed_at = ?
        WHERE id = ?
    ", [$status, $completedAt, $taskId]);
    
    // Mettre à jour le pourcentage du projet
    $projectProgress = updateProjectProgress($task['project_id']);
    
    // Notifier les membres du projet si la tâche est terminée
    if ($status === 'completed') {
        $members = getProjectMembers($task['project_id']);
        foreach ($members as $member) {
            if ($member['id'] != $_SESSION['user_id']) {
                addNotification(
                    $member['id'],
                    $task['project_id'],
                    'Tâche terminée',
                    'La tâche "' . $task['title'] . '" a été marquée comme terminée',
                    'success'
                );
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'project_progress' => $projectProgress,
        'message' => 'Tâche mise à jour avec succès'
    ]);
    
} catch (Exception $e) {
    error_log('Erreur update_task.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}