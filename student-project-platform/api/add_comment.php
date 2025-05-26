<?php
// api/add_comment.php - API pour ajouter un commentaire
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
$comment = trim($input['comment'] ?? '');
$type = $input['type'] ?? 'comment';

if (!$projectId || empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

if (!in_array($type, ['comment', 'recommendation'])) {
    $type = 'comment';
}

// Les recommandations ne peuvent être ajoutées que par les superviseurs
if ($type === 'recommendation' && !isSupervisor()) {
    echo json_encode(['success' => false, 'message' => 'Seuls les superviseurs peuvent ajouter des recommandations']);
    exit();
}

try {
    $db = getDB();
    
    // Vérifier l'accès au projet
    if (!canAccessProject($_SESSION['user_id'], $projectId)) {
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé au projet']);
        exit();
    }
    
    // Ajouter le commentaire
    $db->query("
        INSERT INTO comments (project_id, user_id, comment, type) 
        VALUES (?, ?, ?, ?)
    ", [$projectId, $_SESSION['user_id'], $comment, $type]);
    
    // Récupérer les informations de l'utilisateur
    $currentUser = getCurrentUser();
    
    // Notifier les membres du projet (sauf celui qui a ajouté le commentaire)
    $members = getProjectMembers($projectId);
    $notificationTitle = $type === 'recommendation' ? 'Nouvelle recommandation' : 'Nouveau commentaire';
    $notificationMessage = ($type === 'recommendation' ? 'Recommandation' : 'Commentaire') . 
                          ' de ' . $currentUser['first_name'] . ' ' . $currentUser['last_name'];
    
    foreach ($members as $member) {
        if ($member['id'] != $_SESSION['user_id']) {
            addNotification(
                $member['id'],
                $projectId,
                $notificationTitle,
                $notificationMessage,
                $type === 'recommendation' ? 'warning' : 'info'
            );
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => ($type === 'recommendation' ? 'Recommandation' : 'Commentaire') . ' ajouté avec succès',
        'comment' => [
            'comment' => $comment,
            'type' => $type,
            'author_name' => $currentUser['first_name'] . ' ' . $currentUser['last_name'],
            'created_at' => date('d/m/Y H:i')
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Erreur add_comment.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}