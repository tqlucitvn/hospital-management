<?php
require_once 'includes/config.php';

// Verify CSRF if provided
if (isset($_GET['token']) && !verifyCsrfToken($_GET['token'])) {
    header('Location: dashboard.php?error=invalid_request');
    exit();
}

// Call logout function from config
logout();
?>
