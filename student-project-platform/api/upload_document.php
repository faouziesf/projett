<?php
// api/upload_document.php - API pour uploader un document
header('Content-Type: application/json');
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$projectId = $_POST['project_id'] ?? 0;

if (!$projectId || !canAccessProject($_SESSION['user_id'], $projectId)) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé au projet']);
    exit();
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier uploadé ou erreur lors de l\'upload']);
    exit();
}

$file = $_FILES['document'];
$originalName = $file['name'];
$fileSize = $file['size'];
$mimeType = $file['type'];
$tmpName = $file['tmp_name'];

// Types de fichiers autorisés
$allowedTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain',
    'image/jpeg',
    'image/png',
    'image/gif'
];

// Vérifier le type MIME
if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé']);
    exit();
}

// Vérifier la taille (max 10MB)
$maxSize = 10 * 1024 * 1024; // 10MB
if ($fileSize > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 10MB)']);
    exit();
}

try {
    // Créer le dossier uploads s'il n'existe pas
    $uploadDir = dirname(__DIR__) . '/uploads/documents/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Générer un nom de fichier unique
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    // Déplacer le fichier
    if (!move_uploaded_file($tmpName, $uploadPath)) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier']);
        exit();
    }
    
    // Enregistrer en base de données
    $db = getDB();
    $db->query("
        INSERT INTO documents (project_id, uploaded_by, filename, original_name, file_size, mime_type, upload_path) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ", [$projectId, $_SESSION['user_id'], $filename, $originalName, $fileSize, $mimeType, $uploadPath]);
    
    // Notifier les membres du projet
    $members = getProjectMembers($projectId);
    $currentUser = getCurrentUser();
    
    foreach ($members as $member) {
        if ($member['id'] != $_SESSION['user_id']) {
            addNotification(
                $member['id'],
                $projectId,
                'Nouveau document ajouté',
                $currentUser['first_name'] . ' ' . $currentUser['last_name'] . ' a ajouté le document "' . $originalName . '"',
                'info'
            );
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Document uploadé avec succès',
        'filename' => $filename,
        'original_name' => $originalName
    ]);
    
} catch (Exception $e) {
    error_log('Erreur upload_document.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}