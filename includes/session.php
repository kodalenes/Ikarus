<?php

    require_once '../includes/db.php';
    require_once '../includes/remember_me.php';

    #If session doesnt started start it
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    #Session yoksa remember cookie ye bak
    if (!isset($_SESSION['user_id'])) {
        checkRememberToken($pdo);
    }

    #Check user log in status func
    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    function getUsername() : string{
        return htmlspecialchars($_SESSION['username'] ?? '');
    }

    //Check user type func
    function getUserType() : string {
        return $_SESSION['user_type'] ?? 'guest';
    }
?>