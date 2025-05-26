<?php
// api/update_project_status.php - API pour changer le statut d'un projet
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
$status = $input['status'] ?? '';

if (!$projectId || !in_array($status, ['planning', 'in_progress', 'completed', 'paused'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

try {
    $db = getDB();
    
    // Vérifier l'accès au projet
    if (!canAccessProject($_SESSION['user_id'], $projectId)) {
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
        exit();
    }
    
    // Mettre à jour le statut
    $db->query("UPDATE projects SET status = ? WHERE id = ?", [$status, $projectId]);
    
    // Notifier les membres
    $members = getProjectMembers($projectId);
    $currentUser = getCurrentUser();
    $statusLabel = getStatusLabel($status);
    
    foreach ($members as $member) {
        if ($member['id'] != $_SESSION['user_id']) {
            addNotification(
                $member['id'],
                $projectId,
                'Statut du projet modifié',
                'Le statut du projet a été changé vers "' . $statusLabel . '" par ' . $currentUser['first_name'] . ' ' . $currentUser['last_name'],
                'info'
            );
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
    
} catch (Exception $e) {
    error_log('Erreur update_project_status.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>