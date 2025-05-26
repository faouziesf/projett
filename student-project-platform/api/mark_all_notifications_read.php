<?php
// api/mark_all_notifications_read.php - Marquer toutes les notifications comme lues
header('Content-Type: application/json');
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    $db = getDB();
    
    // Marquer toutes les notifications de l'utilisateur comme lues
    $db->query("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Notifications marquées comme lues']);
    
} catch (Exception $e) {
    error_log('Erreur mark_all_notifications_read.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>