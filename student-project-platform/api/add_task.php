<?php
// api/add_task.php - API pour ajouter une tâche
header('Content-Type: application/json');
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$projectId = $input['project_id'] ?? 0;
$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$assignedTo = $input['assigned_to'] ?? null;
$priority = $input['priority'] ?? 'medium';
$dueDate = $input['due_date'] ?? null;

if (!$projectId || empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

if (!in_array($priority, ['low', 'medium', 'high'])) {
    $priority = 'medium';
}

try {
    $db = getDB();
    
    // Vérifier l'accès au projet
    if (!canAccessProject($_SESSION['user_id'], $projectId)) {
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé au projet']);
        exit();
    }
    
    // Vérifier que la personne assignée est membre du projet
    if (!empty($assignedTo)) {
        $isMember = $db->fetch("
            SELECT COUNT(*) as count 
            FROM project_members 
            WHERE project_id = ? AND user_id = ?
        ", [$projectId, $assignedTo]);
        
        if ($isMember['count'] == 0) {
            echo json_encode(['success' => false, 'message' => 'La personne assignée n\'est pas membre du projet']);
            exit();
        }
    }
    
    // Ajouter la tâche
    $db->query("
        INSERT INTO tasks (project_id, title, description, assigned_to, priority, due_date, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'todo')
    ", [$projectId, $title, $description, $assignedTo ?: null, $priority, $dueDate ?: null]);
    
    $taskId = $db->lastInsertId();
    
    // Notifier la personne assignée
    if (!empty($assignedTo) && $assignedTo != $_SESSION['user_id']) {
        $currentUser = getCurrentUser();
        addNotification(
            $assignedTo,
            $projectId,
            'Nouvelle tâche assignée',
            'La tâche "' . $title . '" vous a été assignée par ' . $currentUser['first_name'] . ' ' . $currentUser['last_name'],
            'info'
        );
    }
    
    // Notifier les autres membres du projet
    $members = getProjectMembers($projectId);
    $currentUser = getCurrentUser();
    
    foreach ($members as $member) {
        if ($member['id'] != $_SESSION['user_id'] && $member['id'] != $assignedTo) {
            addNotification(
                $member['id'],
                $projectId,
                'Nouvelle tâche créée',
                'Une nouvelle tâche "' . $title . '" a été créée par ' . $currentUser['first_name'] . ' ' . $currentUser['last_name'],
                'info'
            );
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Tâche créée avec succès',
        'task_id' => $taskId
    ]);
    
} catch (Exception $e) {
    error_log('Erreur add_task.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}