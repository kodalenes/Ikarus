<?php
    require_once __DIR__ . '/guard.php';

    $pageTitle = 'Dashboard';
    $pageSubtitle = 'Overview';
    $pageAction = ['href' => 'tournament-create.php', 'label' => '+ New Tournament'];

    // ─── STATS ──────────────────────────────────────────────────────────
    try {
        $orgId = $_SESSION['user_id'];

        // Parametreler :id1, :id2, :id3 vb. şeklinde benzersiz yapıldı
        $stats = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM Tournament WHERE organizer_id = :id1 AND deleted_at IS NULL) AS total,
                (SELECT COUNT(*) FROM Tournament WHERE organizer_id = :id2 AND status = 'live' AND deleted_at IS NULL) AS live,
                (SELECT COUNT(*) FROM Tournament WHERE organizer_id = :id3 AND status = 'registration' AND deleted_at IS NULL) AS reg, 
                (SELECT COALESCE(SUM(prize_pool),0) FROM Tournament WHERE organizer_id = :id4 AND deleted_at IS NULL) AS prize,
                (SELECT COUNT(*) 
                    FROM Matches m
                    JOIN Tournament t ON t.id = m.tournament_id
                    WHERE t.organizer_id = :id5 AND m.score_team1 IS NOT NULL AND t.deleted_at IS NULL) AS matches_done,
                (SELECT COUNT(*) 
                    FROM Matches m
                    JOIN Tournament t ON t.id = m.tournament_id
                    WHERE t.organizer_id = :id6 AND m.score_team1 IS NULL AND t.deleted_at IS NULL) AS matches_pending
        ");
        
        // 6 parametreye karşılık 6 kez $orgId gönderiyoruz
        $stats->execute([
            ':id1' => $orgId,
            ':id2' => $orgId,
            ':id3' => $orgId,
            ':id4' => $orgId,
            ':id5' => $orgId,
            ':id6' => $orgId
        ]);
        
        $s = $stats->fetch();
        
        if (!$s) {
            throw new Exception("Stats returned empty");
        }
    } catch (Exception $e) {
        die("Stats Query Error: " . $e->getMessage());
    }

    // ─── RECENT TOURNAMENTS ──────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.name, t.status, t.prize_pool, t.max_teams, t.start_date,
                g.name AS game, 
                COUNT(tt.team_id) AS registered
            FROM Tournament t
            LEFT JOIN Game g ON g.id = t.game_id
            LEFT JOIN tournament_teams tt ON tt.tournament_id = t.id
            WHERE t.organizer_id = ? AND t.deleted_at IS NULL
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$orgId]);
        $tournaments = $stmt->fetchAll();
    } catch (Exception $e) {
        // Hata varsa sayfayı durdur ve ekrana bas
        die("Tournaments Query Error: " . $e->getMessage());
    }

    require_once __DIR__ . '/layout-top.php';
?>

<!-- STAT CARDS-->
 <div class="op-stat-grid">
    <div class="op-stat-card">
        <div class="op-stat-label">Total Tournament</div>
        <div class="op-stat-val"><?= $s['total'] ?></div>
        <div class="op-stat-sub"><?= $s['live'] ?> active, <?= $s['reg'] ?> registration</div>
    </div>
    <div class="op-stat-card">
        <div class="op-stat-label">Total Prize Pool</div>
        <div class="op-stat-val" style="color: var(--highlight);">
            ₺<?= number_format($s['prize'], 0, ',', '.') ?>
        </div>
        <div class="op-stat-sub">In all tournaments</div>
    </div>
    <div class="op-stat-card">
        <div class="op-stat-label">Played Matches</div>
        <div class="op-stat-val"><?= $s['matches_done'] ?></div>
        <div class="op-stat-sub">Complete</div>
    </div>
    <div class="op-stat-card <?= $s['matches_pending'] > 0 ? 'op-stat-card--warn' : '' ?>">
        <div class="op-stat-label">Pending Result</div>
        <div class="op-stat-val"><?= $s['matches_pending'] ?></div>
        <div class="op-stat-sub">
            <?php if($s['matches_pending'] > 0): ?>
                <a href="match-results.php" class="op-link">Enter result →</a>
            <?php else: ?>
                All matches updated
            <?php endif; ?>
        </div>
    </div>
 </div>

 <?php if($s['matches_pending'] > 0): ?>
    <div class="op-alert op-alert--warn">
        <strong><?= $s['matches_pending'] ?> Match</strong> results is waiting to be entered.
        <a href="match-results.php" class="op-link">Go to Match Management</a>
    </div>
<?php endif; ?>

 <!-- LATEST TOURNAMENTS -->
 <div class="op-card">
    <div class="op-card-head">
        <span class="op-card-title">Latest Tournaments</span>
        <a href="tournaments.php" class="op-link">See All</a>
    </div>

    <?php if(empty($tournaments)): ?>
    <div class="op-empty">
        You haven't created a tournament yet.
        <a href="tournament-create.php" class="op-btn--primary" style="margin-top: 12px;display:inline-block">
            + Create first tournament
        </a>
    </div>
    <?php else: ?>
        <table class="op-table">
            <thead>
                <tr>
                    <th>Tournament</th>
                    <th>Game</th>
                    <th>Team</th>
                    <th>Prize</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tournaments as $t): 
                    $statusMap = [
                        'live'          => ['Live',             'op-badge--live'],
                        'registration'  => ['Registration' ,    'op-badge--open'],
                        'upcoming'      => ['Upcoming' ,        'op-badge--soon'],
                        'finished'      => ['Completed' ,       'op-badge--done'],
                        'draft'         => ['Draft' ,           'op-badge--draft'],
                    ];

                    $st = $statusMap[$t['status']] ?? [$t['status'], ''];
                ?>
                <tr>
                    <td>
                        <div class="op-td-name"><?= htmlspecialchars($t['name']) ?></div>
                        <div class="op-td-sub"><?= date('d M Y' , strtotime($t['start_date'])) ?></div>
                    </td>
                    <td class="op-td-muted"><?= htmlspecialchars($t['game'] ?? '-') ?></td>
                    <td class="op-td-muted"><?= $t['registered'] ?>/<?= $t['max_teams'] ?></td>
                    <td class="op-td-prize">
                        <?= $t['prize_pool'] > 0 ? '₺'.number_format($t['prize_pool'],0,',','.') : '—' ?>
                    </td>
                    <td><span class="op-badge <?= $st[1] ?>"><?= $st[0] ?></span></td>
                    <td>
                        <div class="op-row-actions">
                            <a href="tournament-create.php?id=<?= $t['id'] ?>" class="op-btn-sm op-btn-sm--accent">Edit</a>
                            <?php if($t['status'] !== 'finished'): ?>
                                <button class="op-btn-sm op-btn-sm--danger"
                                        onclick="deleteTournament(<?= $t['id'] ?>, '<?= htmlspecialchars($t['name']) ?>')">
                                    Delete    
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
 </div>

 <?php require_once __DIR__ . '/layout-bottom.php' ?>