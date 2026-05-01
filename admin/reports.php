<?php
require_once __DIR__ . '/guard.php';

// ─── Genel Site İstatistikleri ────────────────────────────────────────────
try {
    $overview = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM Player)                                               AS total_players,
            (SELECT COUNT(*) FROM Player WHERE user_type = 'organizer')                AS total_organizers,
            (SELECT COUNT(*) FROM Team)                                                 AS total_teams,
            (SELECT COUNT(*) FROM Tournament)                                           AS total_tournaments,
            (SELECT COUNT(*) FROM Tournament WHERE status = 'live')                    AS live_tournaments,
            (SELECT COUNT(*) FROM Tournament WHERE status = 'finished')                AS finished_tournaments,
            (SELECT COUNT(*) FROM Matches)                                              AS total_matches,
            (SELECT COUNT(*) FROM Matches WHERE score_team1 IS NOT NULL)               AS played_matches,
            (SELECT COUNT(*) FROM Matches WHERE score_team1 IS NULL)                   AS pending_matches,
            (SELECT COUNT(*) FROM Game)                                                 AS total_games,
            (SELECT COALESCE(SUM(prize_pool), 0) FROM Tournament)                      AS total_prize,
            (SELECT COALESCE(SUM(prize_pool), 0) FROM Tournament WHERE status='finished') AS paid_prize
    ")->fetch();
} catch (Exception $e) {
    $overview = array_fill_keys([
        'total_players','total_organizers','total_teams','total_tournaments',
        'live_tournaments','finished_tournaments','total_matches','played_matches',
        'pending_matches','total_games','total_prize','paid_prize'
    ], 0);
}

// ─── Son 6 Ay Kayıt Trendi ────────────────────────────────────────────────
try {
    $registrationTrend = $pdo->query("
        SELECT
            DATE_FORMAT(registered_at, '%Y-%m') AS month,
            DATE_FORMAT(registered_at, '%b %Y') AS label,
            COUNT(*) AS cnt
        FROM Player
        WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(registered_at, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll();
} catch (Exception $e) {
    $registrationTrend = [];
}

// ─── Oyun Bazlı Turnuva Dağılımı ─────────────────────────────────────────
try {
    $gameDistribution = $pdo->query("
        SELECT
            g.name,
            COUNT(t.id)                                                         AS total,
            COUNT(CASE WHEN t.status IN ('live','registration') THEN 1 END)    AS active,
            COALESCE(SUM(t.prize_pool), 0)                                      AS prize
        FROM Game g
        LEFT JOIN Tournament t ON t.game_id = g.id
        GROUP BY g.id, g.name
        ORDER BY total DESC
        LIMIT 8
    ")->fetchAll();
} catch (Exception $e) {
    $gameDistribution = [];
}

// ─── En Aktif Organizatörler ──────────────────────────────────────────────
try {
    $topOrganizers = $pdo->query("
        SELECT
            p.id, p.username,
            COUNT(t.id)                             AS tournament_count,
            COUNT(CASE WHEN t.status = 'live' THEN 1 END) AS live_count,
            COALESCE(SUM(t.prize_pool), 0)          AS total_prize
        FROM Player p
        JOIN Tournament t ON t.organizer_id = p.id
        GROUP BY p.id, p.username
        ORDER BY tournament_count DESC
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    $topOrganizers = [];
}

// ─── En Başarılı Takımlar (galibiyet sayısına göre) ───────────────────────
try {
    $topTeams = $pdo->query("
        SELECT
            tm.id, tm.name,
            COUNT(m.id) AS total_matches,
            SUM(
                (m.home_team_id = tm.id AND m.score_team1 > m.score_team2) OR
                (m.away_team_id = tm.id AND m.score_team2 > m.score_team1)
            ) AS wins
        FROM Team tm
        JOIN Matches m ON (m.home_team_id = tm.id OR m.away_team_id = tm.id)
        WHERE m.score_team1 IS NOT NULL
        GROUP BY tm.id, tm.name
        HAVING total_matches > 0
        ORDER BY wins DESC, total_matches DESC
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    $topTeams = [];
}

// ─── Son 6 Ay Turnuva Trendi ──────────────────────────────────────────────
try {
    $tournamentTrend = $pdo->query("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            DATE_FORMAT(created_at, '%b %Y') AS label,
            COUNT(*) AS cnt
        FROM Tournament
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll();
} catch (Exception $e) {
    $tournamentTrend = [];
}

// ─── Durum Dağılımı ───────────────────────────────────────────────────────
try {
    $statusDist = $pdo->query("
        SELECT status, COUNT(*) AS cnt
        FROM Tournament
        GROUP BY status
        ORDER BY cnt DESC
    ")->fetchAll();
} catch (Exception $e) {
    $statusDist = [];
}

$pageTitle    = 'Reports';
$pageSubtitle = 'Platform analytics & statistics';

require_once __DIR__ . '/layout-top.php';

// Hesaplanan metrikler
$matchPlayRate  = $overview['total_matches'] > 0
    ? round($overview['played_matches'] / $overview['total_matches'] * 100)
    : 0;
$avgTeamsPerTour = $overview['total_tournaments'] > 0
    ? round($overview['total_teams'] / $overview['total_tournaments'], 1)
    : 0;
?>

<!-- ─── OVERVIEW STAT CARDS ──────────────────────────────────────────────── -->
<div class="adm-stat-grid" style="grid-template-columns: repeat(4,1fr); margin-bottom:16px;">

    <div class="op-stat-card">
        <div class="op-stat-label">Total Players</div>
        <div class="op-stat-val"><?= number_format($overview['total_players']) ?></div>
        <div class="op-stat-sub"><?= $overview['total_organizers'] ?> organizers</div>
    </div>

    <div class="op-stat-card">
        <div class="op-stat-label">Tournaments</div>
        <div class="op-stat-val"><?= number_format($overview['total_tournaments']) ?></div>
        <div class="op-stat-sub">
            <?= $overview['live_tournaments'] ?> live ·
            <?= $overview['finished_tournaments'] ?> finished
        </div>
    </div>

    <div class="op-stat-card">
        <div class="op-stat-label">Matches Played</div>
        <div class="op-stat-val"><?= number_format($overview['played_matches']) ?></div>
        <div class="op-stat-sub">
            <?= $matchPlayRate ?>% completion ·
            <?= $overview['pending_matches'] ?> pending
        </div>
    </div>

    <div class="op-stat-card">
        <div class="op-stat-label">Total Prize Pool</div>
        <div class="op-stat-val adm-prize-val">
            ₺<?= number_format($overview['total_prize'], 0, ',', '.') ?>
        </div>
        <div class="op-stat-sub">
            ₺<?= number_format($overview['paid_prize'], 0, ',', '.') ?> distributed
        </div>
    </div>

</div>

<!-- ─── TREND GRAFİKLERİ ─────────────────────────────────────────────────── -->
<div class="adm-two-col" style="margin-bottom:16px;">

    <!-- Kayıt Trendi -->
    <div class="op-card">
        <div class="op-card-head">
            <span class="op-card-title">Player Registrations (Last 6 Months)</span>
        </div>
        <div class="adm-chart-wrap">
            <?php if (empty($registrationTrend)): ?>
                <div class="op-empty" style="padding:24px">No registration data yet.</div>
            <?php else:
                $maxReg = max(array_column($registrationTrend, 'cnt')) ?: 1;
            ?>
                <div class="adm-bar-chart">
                    <?php foreach ($registrationTrend as $row):
                        $pct = round($row['cnt'] / $maxReg * 100);
                    ?>
                        <div class="adm-bar-item">
                            <div class="adm-bar-value"><?= $row['cnt'] ?></div>
                            <div class="adm-bar-col-wrap">
                                <div class="adm-bar-col" style="height:<?= $pct ?>%"></div>
                            </div>
                            <div class="adm-bar-label"><?= htmlspecialchars($row['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Turnuva Trendi -->
    <div class="op-card">
        <div class="op-card-head">
            <span class="op-card-title">Tournaments Created (Last 6 Months)</span>
        </div>
        <div class="adm-chart-wrap">
            <?php if (empty($tournamentTrend)): ?>
                <div class="op-empty" style="padding:24px">No tournament data yet.</div>
            <?php else:
                $maxTour = max(array_column($tournamentTrend, 'cnt')) ?: 1;
            ?>
                <div class="adm-bar-chart">
                    <?php foreach ($tournamentTrend as $row):
                        $pct = round($row['cnt'] / $maxTour * 100);
                    ?>
                        <div class="adm-bar-item">
                            <div class="adm-bar-value"><?= $row['cnt'] ?></div>
                            <div class="adm-bar-col-wrap">
                                <div class="adm-bar-col adm-bar-col--highlight" style="height:<?= $pct ?>%"></div>
                            </div>
                            <div class="adm-bar-label"><?= htmlspecialchars($row['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ─── OYUN DAĞILIMI + DURUM DAĞILIMI ───────────────────────────────────── -->
<div class="adm-two-col" style="margin-bottom:16px;">

    <!-- Oyun Dağılımı -->
    <div class="op-card">
        <div class="op-card-head">
            <span class="op-card-title">Tournaments by Game</span>
        </div>
        <?php if (empty($gameDistribution)): ?>
            <div class="op-empty">No game data.</div>
        <?php else:
            $maxGame = max(array_column($gameDistribution, 'total')) ?: 1;
        ?>
            <div class="adm-hbar-list">
                <?php foreach ($gameDistribution as $g):
                    $pct = round($g['total'] / $maxGame * 100);
                ?>
                    <div class="adm-hbar-row">
                        <div class="adm-hbar-label"><?= htmlspecialchars($g['name']) ?></div>
                        <div class="adm-hbar-track">
                            <div class="adm-hbar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <div class="adm-hbar-meta">
                            <span><?= $g['total'] ?></span>
                            <?php if ($g['active'] > 0): ?>
                                <span class="op-badge op-badge--live" style="font-size:9px; padding:1px 5px;">
                                    <?= $g['active'] ?> active
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Durum Dağılımı (donut-style) -->
    <div class="op-card">
        <div class="op-card-head">
            <span class="op-card-title">Tournament Status Distribution</span>
        </div>
        <?php if (empty($statusDist)): ?>
            <div class="op-empty">No data.</div>
        <?php else:
            $totalForDist = array_sum(array_column($statusDist, 'cnt'));
            $statusColors = [
                'live'         => '#f87171',
                'registration' => '#97c459',
                'upcoming'     => '#489fb5',
                'draft'        => '#EF9F27',
                'finished'     => '#6b6880',
            ];
            $statusLabels = [
                'live'         => 'Live',
                'registration' => 'Registration',
                'upcoming'     => 'Upcoming',
                'draft'        => 'Draft',
                'finished'     => 'Finished',
            ];
        ?>
            <div class="adm-dist-list">
                <?php foreach ($statusDist as $row):
                    $pct   = $totalForDist > 0 ? round($row['cnt'] / $totalForDist * 100) : 0;
                    $color = $statusColors[$row['status']] ?? '#6b6880';
                    $label = $statusLabels[$row['status']] ?? ucfirst($row['status']);
                ?>
                    <div class="adm-dist-row">
                        <div class="adm-dist-dot" style="background:<?= $color ?>"></div>
                        <div class="adm-dist-label"><?= $label ?></div>
                        <div class="adm-dist-bar-track">
                            <div class="adm-dist-bar-fill" style="width:<?= $pct ?>%; background:<?= $color ?>"></div>
                        </div>
                        <div class="adm-dist-count">
                            <?= $row['cnt'] ?>
                            <span class="adm-dist-pct"><?= $pct ?>%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="adm-dist-total">Total: <?= $totalForDist ?> tournaments</div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- ─── TOP ORGANIZERS + TOP TEAMS ───────────────────────────────────────── -->
<div class="adm-two-col">

    <!-- Top Organizatörler -->
    <div class="op-card">
        <div class="op-card-head">
            <span class="op-card-title">Top Organizers</span>
            <a href="users.php?role=organizer" class="op-link">All →</a>
        </div>
        <?php if (empty($topOrganizers)): ?>
            <div class="op-empty">No organizer data yet.</div>
        <?php else: ?>
            <table class="op-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Organizer</th>
                        <th>Tournaments</th>
                        <th>Live</th>
                        <th>Prize</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topOrganizers as $i => $org): ?>
                    <tr>
                        <td>
                            <span class="adm-rank-num adm-rank-num--<?= $i + 1 ?>">
                                <?= $i + 1 ?>
                            </span>
                        </td>
                        <td>
                            <div class="adm-user-cell">
                                <div class="adm-u-avatar">
                                    <?= strtoupper(substr($org['username'], 0, 2)) ?>
                                </div>
                                <span class="op-td-name"><?= htmlspecialchars($org['username']) ?></span>
                            </div>
                        </td>
                        <td class="op-td-muted"><?= $org['tournament_count'] ?></td>
                        <td>
                            <?php if ($org['live_count'] > 0): ?>
                                <span class="op-badge op-badge--live"><?= $org['live_count'] ?></span>
                            <?php else: ?>
                                <span class="op-td-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="op-td-prize">
                            <?= $org['total_prize'] > 0
                                ? '₺' . number_format($org['total_prize'], 0, ',', '.')
                                : '<span class="op-td-muted">—</span>' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Top Takımlar -->
    <div class="op-card">
        <div class="op-card-head">
            <span class="op-card-title">Top Teams by Wins</span>
        </div>
        <?php if (empty($topTeams)): ?>
            <div class="op-empty">No match results yet.</div>
        <?php else: ?>
            <table class="op-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Team</th>
                        <th>Wins</th>
                        <th>Matches</th>
                        <th>Win Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topTeams as $i => $team):
                        $wr = $team['total_matches'] > 0
                            ? round($team['wins'] / $team['total_matches'] * 100)
                            : 0;
                    ?>
                    <tr>
                        <td>
                            <span class="adm-rank-num adm-rank-num--<?= $i + 1 ?>">
                                <?= $i + 1 ?>
                            </span>
                        </td>
                        <td>
                            <div class="adm-user-cell">
                                <div class="adm-u-avatar" style="border-radius:6px;">
                                    <?= strtoupper(substr($team['name'], 0, 2)) ?>
                                </div>
                                <span class="op-td-name"><?= htmlspecialchars($team['name']) ?></span>
                            </div>
                        </td>
                        <td style="color:var(--accent); font-weight:600;"><?= $team['wins'] ?></td>
                        <td class="op-td-muted"><?= $team['total_matches'] ?></td>
                        <td>
                            <div class="adm-wr-wrap">
                                <div class="adm-wr-bar">
                                    <div class="adm-wr-fill" style="width:<?= $wr ?>%"></div>
                                </div>
                                <span class="adm-wr-text">%<?= $wr ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/layout-bottom.php'; ?>