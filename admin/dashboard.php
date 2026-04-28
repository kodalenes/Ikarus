<?php
    require_once __DIR__ . '/guard.php';

    $pageTitle = 'Dashboard';
    $pageSubtitle = 'Site-wide overview';

    //Site geneli istatistikler
    try {
        $stats = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM Player)                                       AS total_players,
                (SELECT COUNT(*) FROM Player WHERE user_type = 'organizer')         AS total_organizers,
                (SELECT COUNT(*) FROM Player WHERE user_type = 'admin')             AS total_admins,
                (SELECT COUNT(*) FROM Team)                                         AS total_teams,
                (SELECT COUNT(*) FROM Tournament)                                   AS total_tournaments,
                (SELECT COUNT(*) FROM Tournament WHERE status = 'live')             AS live_tournaments,  
                (SELECT COUNT(*) FROM Tournament WHERE status = 'registration')     AS reg_tournaments,
                (SELECT COUNT(*) FROM Matches )                                     AS total_matches, 
                (SELECT COUNT(*) FROM Matches WHERE score_team1 IS NULL)            AS pending_matches,
                (SELECT COALESCE(SUM(prize_pool), 0) FROM Tournament)               AS total_prize,
                (SELECT COUNT(*) FROM Game)                                         AS total_games
        ")->fetch();
    } catch (Exception $e) {
        //Hata cikarsa cekilmek istenen verileri 0 olarak atiyoruz
        $stats = array_fill_keys([
            'total_players','total_organizers','total_admins','total_teams',
            'total_tournaments','live_tournaments','reg_tournaments',
            'total_matches','pending_matches','total_prize','total_games'
        ], 0);
    }

    //Son kayit olan  5 kulanici
    try {
        $recentUsers = $pdo->query("
            SELECT id, username, email, user_type, registered_at
            FROM Player
            ORDER BY registered_at DESC
            LIMIT 5
        ")->fetchAll();
    } catch (Exception $e) {
        $recentUsers = [];
    }

    //Son olusturulan 5 turnuva
    try {
        $recentTournaments = $pdo->query("
            SELECT t.id, t.name, t.status, t.prize_pool, t.created_at,
                    p.username AS organizer_name,
                    g.name AS game_name
            FROM Tournament t
            LEFT JOIN Player p ON p.id = t.organizer_id
            LEFT JOIN Game g ON g.id = t.game_id
            ORDER BY t.created_at DESC
            LIMIT 5
        ")->fetchAll();
    } catch (Exception $e) {
        $recentTournaments = [];
    }

    require_once __DIR__ . '/layout-top.php';
?>

<!-- ─── STAT CARDS ─────────────────────────────────────────────────────────── -->
<div class="op-stat-grid adm-stat-grid">
 
    <div class="op-stat-card">
        <div class="op-stat-label">Total Players</div>
        <div class="op-stat-val"><?= number_format($stats['total_players']) ?></div>
        <div class="op-stat-sub">
            <?= $stats['total_organizers'] ?> organizer · <?= $stats['total_admins'] ?> admin
        </div>
    </div>
 
    <div class="op-stat-card">
        <div class="op-stat-label">Teams</div>
        <div class="op-stat-val"><?= number_format($stats['total_teams']) ?></div>
        <div class="op-stat-sub">Registered teams</div>
    </div>
 
    <div class="op-stat-card">
        <div class="op-stat-label">Tournaments</div>
        <div class="op-stat-val"><?= number_format($stats['total_tournaments']) ?></div>
        <div class="op-stat-sub">
            <?= $stats['live_tournaments'] ?> live · <?= $stats['reg_tournaments'] ?> open
        </div>
    </div>
 
    <div class="op-stat-card">
        <div class="op-stat-label">Total Prize Pool</div>
        <div class="op-stat-val adm-prize-val">
            ₺<?= number_format($stats['total_prize'], 0, ',', '.') ?>
        </div>
        <div class="op-stat-sub">Across all tournaments</div>
    </div>
 
    <div class="op-stat-card">
        <div class="op-stat-label">Matches Played</div>
        <div class="op-stat-val"><?= number_format($stats['total_matches']) ?></div>
        <div class="op-stat-sub">Total recorded</div>
    </div>
 
    <div class="op-stat-card <?= $stats['pending_matches'] > 0 ? 'op-stat-card--warn' : '' ?>">
        <div class="op-stat-label">Pending Results</div>
        <div class="op-stat-val"><?= number_format($stats['pending_matches']) ?></div>
        <div class="op-stat-sub">
            <?php if ($stats['pending_matches'] > 0): ?>
                Awaiting score entry
            <?php else: ?>
                All up to date ✓
            <?php endif; ?>
        </div>
    </div>
 
</div>
 
<!-- ─── İKİ KOLON: SON KULLANICILAR + SON TURNUVALAR ─────────────────────── -->
<div class="adm-two-col">
 
    <!-- Son Kullanıcılar -->
    <div class="op-card">
        <div class="op-card-head">
            <span class="op-card-title">Recent Registrations</span>
            <a href="users.php" class="op-link">Manage All →</a>
        </div>
 
        <?php if (empty($recentUsers)): ?>
            <div class="op-empty">No users found.</div>
        <?php else: ?>
            <table class="op-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Type</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentUsers as $u):
                        $roleMap = [
                            'admin'     => ['adm-badge--admin',  'Admin'],
                            'organizer' => ['op-badge--open',    'Organizer'],
                            'player'    => ['op-badge--done',    'Player'],
                        ];
                        $r = $roleMap[$u['user_type']] ?? ['op-badge--done', $u['user_type']];
                    ?>
                    <tr>
                        <td>
                            <div class="op-td-name"><?= htmlspecialchars($u['username']) ?></div>
                            <div class="op-td-sub"><?= htmlspecialchars($u['email']) ?></div>
                        </td>
                        <td><span class="op-badge <?= $r[0] ?>"><?= $r[1] ?></span></td>
                        <td class="op-td-muted">
                            <?= $u['registered_at'] ? date('d M Y', strtotime($u['registered_at'])) : '—' ?>
                        </td>
                        <td>
                            <a href="users.php?id=<?= $u['id'] ?>" class="op-btn-sm op-btn-sm--accent">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
 
    <!-- Son Turnuvalar -->
    <div class="op-card">
        <div class="op-card-head">
            <span class="op-card-title">Recent Tournaments</span>
            <a href="tournaments.php" class="op-link">All →</a>
        </div>
 
        <?php if (empty($recentTournaments)): ?>
            <div class="op-empty">No tournaments found.</div>
        <?php else: ?>
            <?php foreach ($recentTournaments as $t):
                $statusMap = [
                    'live'         => ['op-badge--live',  'Live'],
                    'registration' => ['op-badge--open',  'Open'],
                    'upcoming'     => ['op-badge--soon',  'Upcoming'],
                    'finished'     => ['op-badge--done',  'Done'],
                    'draft'        => ['op-badge--draft', 'Draft'],
                ];
                $s = $statusMap[$t['status']] ?? ['op-badge--done', $t['status']];
            ?>
            <div class="adm-t-row">
                <div class="adm-t-info">
                    <div class="op-td-name"><?= htmlspecialchars($t['name']) ?></div>
                    <div class="op-td-sub">
                        <?= htmlspecialchars($t['game_name'] ?? '—') ?>
                        · by <?= htmlspecialchars($t['organizer_name'] ?? '—') ?>
                    </div>
                </div>
                <span class="op-badge <?= $s[0] ?>"><?= $s[1] ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
 
</div>
 
<?php require_once __DIR__ . '/layout-bottom.php'; ?>