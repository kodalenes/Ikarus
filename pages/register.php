<?php
    require_once '../includes/session.php';
    if (isLoggedIn()) {
        header('Location: /index.php');
        exit;
    }
header('Location: ../index.php?modal=login');
    exit;
?>
