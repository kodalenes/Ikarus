<?php
    require_once '../includes/db.php';
    require_once '../includes/remember_me.php';
    require_once '../includes/session.php';

    if (isset($_SESSION['user_id'])) {
        clearRememberToken($pdo, $_SESSION['user_id']);
    }

    $_SESSION = [];//veriyi temizleriz

    //Cookieleride temizleyelim
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    header('Location: ../index.php');
    exit;
?>