<?php
// api/delete_project.php - API pour supprimer un projet
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

if (!$projectId) {
    echo json_encode(['success' => false, 'message' => 'ID de projet manquant']);
    exit();
}

try {
    $db = getDB();
    
    // Vérifier que l'utilisateur peut supprimer ce projet
    $project = $db->fetch("SELECT * FROM projects WHERE id = ?", [$projectId]);
    
    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Projet non trouvé']);
        exit();
    }
    
    // Seul le créateur ou un superviseur peut supprimer
    if ($project['created_by'] != $_SESSION['user_id'] && !isSupervisor()) {
        echo json_encode(['success' => false, 'message' => 'Permission refusée']);
        exit();
    }
    
    $db->getConnection()->beginTransaction();
    
    // Supprimer les documents physiques
    $documents = $db->fetchAll("SELECT filename, upload_path FROM documents WHERE project_id = ?", [$projectId]);
    foreach ($documents as $doc) {
        if (file_exists($doc['upload_path'])) {
            unlink($doc['upload_path']);
        }
    }
    
    // Supprimer le projet (les foreign keys feront le reste avec ON DELETE CASCADE)
    $db->query("DELETE FROM projects WHERE id = ?", [$projectId]);
    
    $db->getConnection()->commit();
    
    echo json_encode(['success' => true, 'message' => 'Projet supprimé avec succès']);
    
} catch (Exception $e) {
    $db->getConnection()->rollback();
    error_log('Erreur delete_project.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>