<?php
    require_once 'includes/session.php';
    $customTitle = "Home";
    $extraCss = ['index'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once 'includes/head.php' ?>
</head>

<body>
    <?php require_once 'includes/header.php' ?>
    
    <main>
        <!--HERO KISMI-->
        <section class="hero animate-in" style="--delay: 100ms;">
            <div class="hero-inner">
                <p class="hero-eyebrow">Tournament Platform</p>
                <h1 class="hero-title">Compete. <br>Lead. <span>Rise</span></h1>
                <p class="hero-sub">Join tournaments, managing the team, climbing to the top of the rankings.</p>

                <div class="hero-cards">
                    <!--Tournaments -->
                    <a href="pages/tournaments.php" class="hero-card hero-card--primary">
                        <div class="hc-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 9H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2"/>
                                <path d="M18 9h2a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/>
                                <path d="M8 5h8v14H8z"/>
                                <path d="M12 19v2M8 21h8"/>
                            </svg>
                        </div>
                        <div class="hc-body">
                            <div class="hc-title">Tournaments</div>
                            <div class="hc-desc">Join tournaments.</div>
                        </div>
                        <div class="hc-arrow">→</div>
                    </a>

                    <!--Team -->
                    <?php if(isLoggedIn()):?>
                        <?php
                            //Kullanicinin takimi varmi?
                            $stmtTeam = $pdo->prepare(
                                "SELECT t.id, t.name
                                    FROM Team t
                                    JOIN Player p ON p.team_id = t.id
                                    WHERE p.id = ?"
                            );
                            $stmtTeam->execute([$_SESSION['user_id']]);
                            $myTeam = $stmtTeam->fetch();
                        ?>
                        <?php if($myTeam): ?>
                            <a href="pages/team.php" class="hero-card">
                                <div class="hc-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <div class="hc-body">
                                    <div class="hc-title">My Team</div>
                                    <div class="hc-desc">Manage and organize <?= htmlspecialchars($myTeam['name']) ?></div>
                                </div>
                                <div class="hc-arrow">→</div>
                            </a>
                        <?php else: ?>
                            <a href="pages/team.php?action=create" class="hero-card">
                                <div class="hc-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <div class="hc-body">
                                    <div class="hc-title">Create Team</div>
                                    <div class="hc-desc">Create your team, invite your friends.</div>
                                </div>
                                <div class="hc-arrow">→</div>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="pages/team.php" class="hero-card">
                            <div class="hc-icon">
                                 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <div class="hc-body">
                                <div class="hc-title">Team</div>
                                <div class="hc-desc">Manage your team or create one.</div>
                            </div>
                            <div class="hc-arrow">→</div>
                        </a>
                    <?php endif; ?>

                    <!--Leaderboard -->
                    <a href="pages/leaderboard.php" class="hero-card">
                        <div class="hc-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                                <polyline points="16 7 22 7 22 13"/>
                            </svg>
                        </div>
                        <div class="hc-body">
                            <div class="hc-title">Leaderboard</div>
                            <div class="hc-desc">Global and tournament leaderboards.</div>
                        </div>
                        <div class="hc-arrow">→</div>
                    </a>
                </div>
            </div>
        </section>

        <!--STATS BAR -->
        <?php 
            try {
                $stats = $pdo->query("
                    SELECT
                        (SELECT COUNT(*) FROM Player WHERE deleted_at IS NULL) AS total_players,
                        (SELECT COUNT(*) FROM Tournament WHERE status IN ('live','registration') AND deleted_at IS NULL) AS active_tournaments,
                        (SELECT COUNT(*) FROM Team WHERE deleted_at IS NULL) AS total_teams,
                        (SELECT COUNT(*) FROM Matches WHERE score_team1 IS NOT NULL AND deleted_at IS NULL) AS total_matches
                ")->fetch();
            } catch (Exception $e) {
                $stats = ['total_players' => 0, 'active_tournaments' => 0, 'total_teams => 0', 'total_matches' => 0];
            }
        ?>
        <section class="stats-bar animate-in" style="--delay: 180ms;">
            <div class="stats-inner">
                <div class="stat-item">
                    <span class="stat-num"><?= number_format($stats['total_players']) ?></span>
                    <span class="stat-label">Players</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-num"> <?= number_format($stats['active_tournaments']) ?></span>
                    <span class="stat-label">Active Tournaments</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-num"> <?= number_format($stats['total_matches']) ?></span>
                    <span class="stat-label">Matches Played</span>
                </div>
            </div>
        </section>

        <!--ACTIVE TOURNAMENTS AND LEADERBOARD -->
        <section class="home-grid animate-in" style="--delay: 260ms;">
            <div class="home-grid-inner">
            <!--Sol Aktif turnuvalar -->
            <div class="home-section animate-in" style="--delay: 320ms;">
                <div class="home-section-header">
                    <a href="pages/tournaments.php" class="home-section-title">Active Tournaments</a>
                    <a href="pages/tournaments.php" class="home-section-link">See All</a>
                </div>

                <?php
                    try {
                        $stmtT = $pdo->query("
                            SELECT t.id, t.name, t.status, t.prize_pool, t.max_teams, t.start_date,
                                g.name AS game_name,
                                COUNT(tt.team_id) AS registered_teams
                            FROM Tournament t
                            LEFT JOIN Game g ON g.id = t.game_id
                            LEFT JOIN tournament_team tt ON tt.tournament_id = t.id
                            WHERE t.status IN ('live','registration', 'upcoming') AND t.deleted_at IS NULL
                            GROUP BY t.id
                            ORDER BY FIELD(t.status, 'live','registration','upcoming'), t.start_date ASC
                            LIMIT 4
                            ");
                        $tournaments = $stmtT->fetchAll();
                    } catch (Exception $e) {
                        $tournaments = [];
                    }
                ?>

                <?php if(empty($tournaments)): ?>
                    <div class="empty-state">There is no active tournaments.</div>
                <?php else: ?>
                    <div class="t-list">
                        <?php foreach($tournaments as $t): ?>
                            <?php
                                $statusMap = [
                                    'live'          => ['label' => 'LIVE', 'class' => 'badge--live'],
                                    'registration'  => ['label' => 'Registration.', 'class' => 'badge--open'],
                                    'upcoming'  => ['label' => 'Upcoming', 'class' => 'badge--soon'],
                                ];
                                $s = $statusMap[$t['status']] ?? ['label' => $t['status'], 'class' => ''];
                                $fillPct = $t['max_teams'] > 0
                                    ? min(100, round($t['registered_teams'] / $t['max_teams'] * 100))
                                    : 0;
                            ?>
                             <a href="pages/tournament-detail.php?id=<?= $t['id'] ?>" class="t-card">
                                    <div class="t-card-top">
                                        <span class="t-badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
                                        <span class="t-game"><?= htmlspecialchars($t['game_name'] ?? '—') ?></span>
                                    </div>
                                    <div class="t-name"><?= htmlspecialchars($t['name']) ?></div>
                                    <div class="t-footer">
                                        <div class="t-slots">
                                            <div class="t-slots-bar">
                                                <div class="t-slots-fill" style="width:<?= $fillPct ?>%"></div>
                                            </div>
                                            <span class="t-slots-text"><?= $t['registered_teams'] ?>/<?= $t['max_teams'] ?></span>
                                        </div>
                                        <?php if ($t['prize_pool'] > 0): ?>
                                            <span class="t-prize">₺<?= number_format($t['prize_pool'], 0, ',', '.') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!--Sag Top 5 Leaderboard -->
            <div class="home-section animate-in" style="--delay: 380ms;">
                <div class="home-section-header">
                    <a href="pages/leaderboard.php" class="home-section-title">Leaderboard</a>
                    <a href="pages/leaderboard.php" class="home-section-link">See All</a>
                </div>

                <?php
                    try {
                        $stmtLb = $pdo->query("
                                SELECT
                                    p.id,
                                    p.username,
                                    COUNT(CASE WHEN m.home_team_id = t2.id AND m.score_team1 > m.score_team2 THEN 1
                                               WHEN m.away_team_id = t2.id AND m.score_team2 > m.score_team1 THEN 1
                                          END) AS wins,
                                    COUNT(m.id) AS total_matches,
                                    ROUND(
                                        COUNT(CASE WHEN m.home_team_id = t2.id AND m.score_team1 > m.score_team2 THEN 1
                                                   WHEN m.away_team_id = t2.id AND m.score_team2 > m.score_team1 THEN 1
                                              END) * 40 +
                                        IFNULL(
                                            COUNT(CASE WHEN m.home_team_id = t2.id AND m.score_team1 > m.score_team2 THEN 1
                                                       WHEN m.away_team_id = t2.id AND m.score_team2 > m.score_team1 THEN 1
                                                  END) / NULLIF(COUNT(m.id),0) * 100 * 20
                                        , 0)
                                    ) AS points
                                FROM Player p
                                LEFT JOIN Team t2 ON t2.id = p.team_id AND t2.deleted_at IS NULL
                                LEFT JOIN Matches m ON (m.home_team_id = t2.id OR m.away_team_id = t2.id)
                                    AND m.score_team1 IS NOT NULL AND m.deleted_at IS NULL
                                WHERE p.deleted_at IS NULL
                                GROUP BY p.id
                                ORDER BY points DESC, wins DESC
                                LIMIT 5
                            ");
                        $leaders = $stmtLb->fetchAll();
                    } catch (Exception $e) {
                        $leaders = [];
                    }
                ?>

                <?php if(empty($leaders)): ?>
                    <div class="empty-state">There is no leaderboard information!</div>
                <?php else: ?>
                    <div class="lb-list">
                        <?php foreach($leaders as $i => $row): ?>
                            <?php
                                $rankClass = match($i){
                                    0 => 'rank--gold',
                                    1 => 'rank--silver',
                                    2 => 'rank--bronze',
                                    default => ''
                                };
                                $initials = strtoupper(substr($row['username'], 0, 2));
                                $isMe = isLoggedIn() && $_SESSION['user_id'] == $row['id'];
                            ?>
                            <div class="lb-row <?= $isMe ? 'lb-row--me' : '' ?>" >
                                <span class="lb-rank <?= $rankClass ?>"><?= $i + 1 ?></span>
                                <div class="lb-avatar <?= $rankClass ?>"><?= $initials ?></div>
                                <div class="lb-info">
                                    <span class="lb-name"><?=  htmlspecialchars($row['username']) ?><?= $isMe ? '<span class="lb-you">You</span>' : '' ?></span>
                                    <span class="lb-wr">Win rate %<?= $row['total_matches'] > 0 ? round($row['wins'] / $row['total_matches'] * 100) : 0 ?></span>
                                </div>
                                <span class="lb-pts"><?= number_format($row['points']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        </section>
    </main>
    
    <?php require_once 'includes/footer.php' ?>
</body>

</html>
