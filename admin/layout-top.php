<?php
    //butun admin sayfalari bu dosyayi require eder

    $currentPage = basename($_SERVER['PHP_SELF']);

    $menuItems = [
        ['file' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['file' => 'users.php', 'label' => 'Users', 'icon' => 'users'],
        ['file' => 'tournaments.php', 'label' => 'All Tournaments', 'icon' => 'trophy'],
        ['file' => 'games.php', 'label' => 'Games', 'icon' => 'gamepad'],
        ['file' => 'reports.php', 'label' => 'Reports', 'icon' => 'report'],
    ];

    //Bekleyen sonucsuz mac sayisi
    try {
        $pendingCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM Matches WHERE score_team1 IS NULL"
        )->fetchColumn();
    } catch (Exception $e) {
        $pendingCount = 0;
    }

    function adminSvgIcon(string $name) : string {
        $icons = [
            'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'trophy'    => '<path d="M6 9H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><path d="M8 5h8v14H8z"/><path d="M12 19v2M8 21h8"/>',
            'gamepad'   => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4"/><circle cx="15" cy="12" r="1"/><circle cx="18" cy="10" r="1"/>',
            'report'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        ];
        return $icons[$name] ?? '';
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin Panel') ?> — Ikarus</title>

    <link rel="shortcut icon" href="/assets/images/Ikarus_Logo.webp" type="image/x-icon"> <!--[cite: 1] -->

    <!-- Global CSS -->
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/utils.css">
    
    <!-- Organizer (Temel panel yapısı) ve Admin üzerine yazan CSS -->
    <link rel="stylesheet" href="../assets/css/organizer/organizer-core.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="organizer-body">
<div class="op-shell">
 
    <!-- SIDEBAR -->
    <aside class="op-sidebar adm-sidebar">
        <a href="../index.php" class="op-sb-logo adm-logo">
            IKA<span>RUS</span>
            <span class="adm-logo-badge">ADMIN</span>
        </a>
 
        <div class="op-sb-section">System</div>
        <?php foreach ($menuItems as $item):
            $isActive = $currentPage === $item['file'];
            $badge = ($item['file'] === 'users.php')
                ? '' // İleride banned user sayısı eklenebilir
                : '';
        ?>
            <a href="<?= $item['file'] ?>" class="op-sb-item <?= $isActive ? 'active' : '' ?>">
                <svg class="op-sb-icon" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <?= adminSvgIcon($item['icon']) ?>
                </svg>
                <?= $item['label'] ?>
                <?= $badge ?>
            </a>
        <?php endforeach; ?>
 
        <div class="op-sb-bottom">
            <div class="op-sb-section">Navigation</div>
            <a href="../organizer/dashboard.php" class="op-sb-item">
                <svg class="op-sb-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
                Organizer Panel
            </a>
            <a href="../index.php" class="op-sb-item">
                <svg class="op-sb-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Back to Site
            </a>
            <a href="../auth/logout.php" class="op-sb-item op-sb-item--danger">
                <svg class="op-sb-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Logout
            </a>
            <div class="op-sb-user">
                <div class="op-sb-avatar adm-avatar"><?= strtoupper(substr(getUsername(), 0, 2)) ?></div>
                <div class="op-sb-uinfo">
                    <div class="op-sb-uname"><?= getUsername() ?></div>
                    <div class="op-sb-urole adm-role">Administrator</div>
                </div>
            </div>
        </div>
    </aside>
 
    <!-- MAIN -->
    <div class="op-main">
        <div class="op-topbar adm-topbar">
            <div>
                <h1 class="op-topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
                <p class="op-topbar-sub"><?= htmlspecialchars($pageSubtitle ?? '') ?></p>
            </div>
            <div class="op-topbar-actions">
                <span class="adm-env-badge">⚡ Admin Mode</span>
                <?php if (!empty($pageAction)): ?>
                    <a href="<?= $pageAction['href'] ?>" class="op-btn adm-btn-primary">
                        <?= $pageAction['label'] ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
 
        <div class="op-body">
