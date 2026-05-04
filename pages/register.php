<?php
    require_once '../includes/session.php';
    if (isLoggedIn()) {
        header('Location: /pages/index.php');
        exit;
    }
header('Location: ../pages/index.php?modal=login');
    exit;
?>
