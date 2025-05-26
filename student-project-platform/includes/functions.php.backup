<?php
// includes/functions.php - Fonctions utilitaires (version protégée)

// Protection contre les inclusions multiples
if (defined('FUNCTIONS_LOADED')) {
    return;
}
define('FUNCTIONS_LOADED', true);

// Inclure la configuration de base de données une seule fois
require_once __DIR__ . '/../config/database.php';

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

// Vérifier si l'utilisateur est un superviseur
if (!function_exists('isSupervisor')) {
    function isSupervisor() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'supervisor';
    }
}

// Rediriger si non connecté
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: /auth/login.php');
            exit();
        }
    }
}

// Obtenir les informations de l'utilisateur connecté
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        if (!isLoggedIn()) {
            return null;
        }
        
        $db = getDB();
        return $db->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }
}

// Obtenir tous les projets d'un utilisateur
if (!function_exists('getUserProjects')) {
    function getUserProjects($userId) {
        $db = getDB();
        return $db->fetchAll("
            SELECT p.*, u.first_name, u.last_name 
            FROM projects p 
            LEFT JOIN users u ON p.supervisor_id = u.id
            WHERE p.id IN (
                SELECT project_id FROM project_members WHERE user_id = ?
            ) OR p.created_by = ?
            ORDER BY p.created_at DESC
        ", [$userId, $userId]);
    }
}

// Obtenir les détails d'un projet
if (!function_exists('getProject')) {
    function getProject($projectId) {
        $db = getDB();
        return $db->fetch("
            SELECT p.*, u.first_name as supervisor_first_name, u.last_name as supervisor_last_name,
                   c.first_name as creator_first_name, c.last_name as creator_last_name
            FROM projects p 
            LEFT JOIN users u ON p.supervisor_id = u.id
            LEFT JOIN users c ON p.created_by = c.id
            WHERE p.id = ?
        ", [$projectId]);
    }
}

// Obtenir les membres d'un projet
if (!function_exists('getProjectMembers')) {
    function getProjectMembers($projectId) {
        $db = getDB();
        return $db->fetchAll("
            SELECT u.*, pm.role as project_role
            FROM users u
            JOIN project_members pm ON u.id = pm.user_id
            WHERE pm.project_id = ?
            ORDER BY pm.role DESC, u.first_name
        ", [$projectId]);
    }
}

// Obtenir les tâches d'un projet
if (!function_exists('getProjectTasks')) {
    function getProjectTasks($projectId) {
        $db = getDB();
        return $db->fetchAll("
            SELECT t.*, u.first_name, u.last_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.project_id = ?
            ORDER BY t.created_at DESC
        ", [$projectId]);
    }
}

// Calculer le pourcentage d'avancement d'un projet
if (!function_exists('calculateProjectProgress')) {
    function calculateProjectProgress($projectId) {
        $db = getDB();
        $totalTasks = $db->fetch("SELECT COUNT(*) as count FROM tasks WHERE project_id = ?", [$projectId])['count'];
        
        if ($totalTasks == 0) {
            return 0;
        }
        
        $completedTasks = $db->fetch("SELECT COUNT(*) as count FROM tasks WHERE project_id = ? AND status = 'completed'", [$projectId])['count'];
        
        return round(($completedTasks / $totalTasks) * 100);
    }
}

// Mettre à jour le pourcentage d'avancement d'un projet
if (!function_exists('updateProjectProgress')) {
    function updateProjectProgress($projectId) {
        $progress = calculateProjectProgress($projectId);
        $db = getDB();
        $db->query("UPDATE projects SET progress_percentage = ? WHERE id = ?", [$progress, $projectId]);
        return $progress;
    }
}

// Ajouter une notification
if (!function_exists('addNotification')) {
    function addNotification($userId, $projectId, $title, $message, $type = 'info') {
        $db = getDB();
        $db->query("
            INSERT INTO notifications (user_id, project_id, title, message, type) 
            VALUES (?, ?, ?, ?, ?)
        ", [$userId, $projectId, $title, $message, $type]);
    }
}

// Obtenir les notifications non lues d'un utilisateur
if (!function_exists('getUnreadNotifications')) {
    function getUnreadNotifications($userId) {
        $db = getDB();
        return $db->fetchAll("
            SELECT n.*, p.title as project_title
            FROM notifications n
            LEFT JOIN projects p ON n.project_id = p.id
            WHERE n.user_id = ? AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT 10
        ", [$userId]);
    }
}

// Marquer une notification comme lue
if (!function_exists('markNotificationAsRead')) {
    function markNotificationAsRead($notificationId) {
        $db = getDB();
        $db->query("UPDATE notifications SET is_read = 1 WHERE id = ?", [$notificationId]);
    }
}

// Vérifier si un utilisateur est membre d'un projet
if (!function_exists('isProjectMember')) {
    function isProjectMember($userId, $projectId) {
        $db = getDB();
        $result = $db->fetch("
            SELECT COUNT(*) as count 
            FROM project_members 
            WHERE user_id = ? AND project_id = ?
        ", [$userId, $projectId]);
        
        return $result['count'] > 0;
    }
}

// Vérifier si un utilisateur peut accéder à un projet
if (!function_exists('canAccessProject')) {
    function canAccessProject($userId, $projectId) {
        $db = getDB();
        $project = $db->fetch("SELECT * FROM projects WHERE id = ?", [$projectId]);
        
        if (!$project) {
            return false;
        }
        
        // Le créateur peut toujours accéder
        if ($project['created_by'] == $userId) {
            return true;
        }
        
        // Le superviseur peut toujours accéder
        if ($project['supervisor_id'] == $userId) {
            return true;
        }
        
        // Les membres peuvent accéder
        return isProjectMember($userId, $projectId);
    }
}

// Formater une date
if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (!$date) return 'Non définie';
        return date('d/m/Y', strtotime($date));
    }
}

// Formater une date et heure
if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime) {
        if (!$datetime) return 'Non définie';
        return date('d/m/Y H:i', strtotime($datetime));
    }
}

// Obtenir la classe CSS pour le statut
if (!function_exists('getStatusClass')) {
    function getStatusClass($status) {
        switch ($status) {
            case 'completed':
                return 'success';
            case 'in_progress':
                return 'primary';
            case 'paused':
                return 'warning';
            case 'planning':
                return 'secondary';
            default:
                return 'secondary';
        }
    }
}

// Obtenir le libellé du statut
if (!function_exists('getStatusLabel')) {
    function getStatusLabel($status) {
        switch ($status) {
            case 'completed':
                return 'Terminé';
            case 'in_progress':
                return 'En cours';
            case 'paused':
                return 'En pause';
            case 'planning':
                return 'Planification';
            case 'todo':
                return 'À faire';
            default:
                return ucfirst($status);
        }
    }
}

// Sécuriser l'affichage HTML
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}