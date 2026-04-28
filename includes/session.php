<?php

    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/remember_me.php';

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

    function isOrganizer() : bool {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'organizer';
    }

    function isAdmin() : bool {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }

    function requireLogin() : void {
        if (!isLoggedIn()) {
            header('Location: ../pages/index.php?modal=login');
            exit;
        }
    }

    function requireOrganizer() : void {
        requireLogin();
        if (!isOrganizer() && !isAdmin()) {
            header('Location: ../pagex/index.php');
            exit;
        }
    }
?>