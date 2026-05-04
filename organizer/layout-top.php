<?php
    /** Organizator paneli ortak layout
     * 
    */

    //Aktif menu tespiti
    $currentPage = basename($_SERVER['PHP_SELF']);

    $menuItem = [
        ['file' => 'dashboard.php',                 'label' => 'Dashboard',         'icon' => 'dashboard'],
        ['file' => 'tournaments.php',               'label' => 'Tournaments',       'icon' => 'trophy'],
        ['file' => 'tournament-create.php',         'label' => 'New Tournaments',   'icon' => 'plus'],
        ['file' => 'match-results.php',             'label' => 'Match Results',     'icon' => 'match'],
        ['file' => 'teams.php',                     'label' => 'Teams',             'icon' => 'team'],
        ['file' => 'players.php',                   'label' => 'Players',           'icon' => 'player'],
    ];

    //Bekleyen mac sayisi
    try {
        $pendingMatches = $pdo->prepare("
        SELECT COUNT(*) FROM Matches
        WHERE winner_id IS NULL
            AND tournament_id IN (
                SELECT id FROM Tournament WHERE organizer_id = ?
             )
        ");
        $pendingMatches->execute([$_SESSION['user_id']]);
        $pendingCount = (int) $pendingMatches->fetchColumn();
    } catch (Exception $e) {
        $pendingCount = 0;
    }

    function svgIcon(string $name): string {
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'trophy'    => '<path d="M6 9H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><path d="M8 5h8v14H8z"/><path d="M12 19v2M8 21h8"/>',
        'plus'      => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'match'     => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'team'      => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'player'    => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    ];
    return $icons[$name] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Panel') ?>- Ikarus Organizer</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/utils.css">
    <link rel="stylesheet" href="../assets/css/organizer/organizer-core.css">
    <?php
        /**
         * 2. Dinamik CSS Yükleyici
         * Sayfa adını (örn: tournaments.php -> tournaments) otomatik alır.
         */
        $currentPage = basename($_SERVER['PHP_SELF'], '.php'); 

        // CSS dosyasının yolunu belirliyoruz
        $cssFilePath = "../assets/css/organizer/organizer-{$currentPage}.css";

        // 3. Dosya fiziksel olarak sunucuda var mı? (Gereksiz 404 hatalarını önler)
        if (file_exists(__DIR__ . '/' . $cssFilePath)) {
            // Dosya varsa HTML'e dahil et
            // Not: Geliştirme aşamasında CSS önbelleğe takılmasın diye sonuna "?v=time()" eklendi.
            echo '<link rel="stylesheet" href="' . $cssFilePath . '?v=' . time() . '">';
        }
    ?>
</head>

<body class="organizer-body">
<div class="op-shell">
 
    <!-- SIDEBAR -->
    <aside class="op-sidebar">
        <a href="../pages/index.php" class="op-sb-logo">
            IKA<span>RUS</span>
        </a>
 
        <div class="op-sb-section">Management</div>
        <?php foreach ($menuItem as $item): ?>
            <?php
                $isActive = $currentPage === $item['file'];
                $badge = ($item['file'] === 'match-results.php' && $pendingCount > 0)
                    ? '<span class="op-sb-badge">' . $pendingCount . '</span>'
                    : '';
            ?>
            <a href="<?= $item['file'] ?>" class="op-sb-item <?= $isActive ? 'active' : '' ?>">
                <svg class="op-sb-icon" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <?= svgIcon($item['icon']) ?>
                </svg>
                <?= $item['label'] ?>
                <?= $badge ?>
            </a>
        <?php endforeach; ?>
 
        <div class="op-sb-bottom">
            <div class="op-sb-section">Profile</div>

            <?php if (isAdmin()): ?>
                <!-- Admin ise Admin Paneline geçiş linki -->
                <a href="../admin/dashboard.php" class="op-sb-item op-sb-item--admin">
                    <svg class="op-sb-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Admin Panel
                    <span class="op-sb-badge op-sb-badge--admin">⚡</span>
                </a>
            <?php endif; ?>
            
                <a href="../pages/index.php" class="op-sb-item">
                <svg class="op-sb-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Back to site
            </a>
            <a href="../auth/logout.php" class="op-sb-item op-sb-item--danger">
                <svg class="op-sb-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Logout
            </a>
            <div class="op-sb-user">
                <div class="op-sb-avatar"><?= strtoupper(substr(getUsername(), 0, 2)) ?></div>
                <div class="op-sb-uinfo">
                    <div class="op-sb-uname"><?= getUsername() ?></div>
                    <div class="op-sb-urole">Organizer</div>
                </div>
            </div>
        </div>
    </aside>
 
    <!-- MAIN -->
    <div class="op-main">
        <!-- TOPBAR -->
        <div class="op-topbar">
            <div>
                <h1 class="op-topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
                <p class="op-topbar-sub"><?= htmlspecialchars($pageSubtitle ?? '') ?></p>
            </div>
            <div class="op-topbar-actions">
                <?php if (!empty($pageAction)): ?>
                    <a href="<?= $pageAction['href'] ?>" class="op-btn op-btn--primary">
                        <?= $pageAction['label'] ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
 
        <!-- SAYFA İÇERİĞİ BURAYA -->
        <div class="op-body">
