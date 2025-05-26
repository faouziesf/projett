<?php
// index.php - Page d'accueil
require_once 'includes/functions.php';

// Rediriger vers le dashboard si connecté, sinon vers login
if (isLoggedIn()) {
    header('Location: /pages/dashboard.php');
} else {
    header('Location: /auth/login.php');
}
exit();