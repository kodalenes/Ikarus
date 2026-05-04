<?php

    #If session doesnt started start it
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/remember_me.php';


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

    function getCurrentUserRow(): ?array {
        static $cachedUser = false;
        global $pdo;

        if ($cachedUser !== false) {
            return $cachedUser;
        }

        if (!isset($_SESSION['user_id'])) {
            $cachedUser = null;
            return null;
        }

        try {
            $avatarSelect = playerAvatarColumnExists() ? 'avatar_url' : 'NULL AS avatar_url';
            $stmt = $pdo->prepare("
                SELECT id, username, email, birth_date, team_id, role, user_type, {$avatarSelect}
                FROM Player
                WHERE id = ? AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $cachedUser = $stmt->fetch() ?: null;
        } catch (Exception $e) {
            $cachedUser = null;
        }

        return $cachedUser;
    }

    function playerAvatarColumnExists(): bool {
        static $hasAvatarColumn = null;
        global $pdo;

        if ($hasAvatarColumn !== null) {
            return $hasAvatarColumn;
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM Player LIKE 'avatar_url'");
            $hasAvatarColumn = (bool)$stmt->fetch();
        } catch (Exception $e) {
            $hasAvatarColumn = false;
        }

        return $hasAvatarColumn;
    }

    function getUserInitials(?string $name = null): string {
        $name = trim($name ?? (getCurrentUserRow()['username'] ?? 'User'));
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    function getCurrentUserAvatarUrl(): ?string {
        $user = getCurrentUserRow();
        return !empty($user['avatar_url']) ? $user['avatar_url'] : null;
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
