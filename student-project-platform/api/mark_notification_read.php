<?php
// api/mark_notification_read.php - Marquer une notification comme lue
header('Content-Type: application/json');
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['notification_id'] ?? 0;

if (!$notificationId) {
    echo json_encode(['success' => false, 'message' => 'ID de notification manquant']);
    exit();
}

try {
    $db = getDB();
    
    // Vérifier que la notification appartient à l'utilisateur
    $notification = $db->fetch("SELECT * FROM notifications WHERE id = ? AND user_id = ?", [$notificationId, $_SESSION['user_id']]);
    
    if (!$notification) {
        echo json_encode(['success' => false, 'message' => 'Notification non trouvée']);
        exit();
    }
    
    // Marquer comme lue
    markNotificationAsRead($notificationId);
    
    echo json_encode(['success' => true, 'message' => 'Notification marquée comme lue']);
    
} catch (Exception $e) {
    error_log('Erreur mark_notification_read.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>