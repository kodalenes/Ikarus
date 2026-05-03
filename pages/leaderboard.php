<?php
// Include database and session management
require_once __DIR__ . '/../includes/session.php';

// ── FETCH PLAYER LEADERBOARD ──────────────────────────────────────────────────
$playerSql = "
    SELECT
        p.id,
        p.username,
        t.name AS team_name,
        g.name AS game_name,
        COUNT(DISTINCT m.id) AS total_matches,
        SUM(
            CASE
                WHEN p.team_id = m.home_team_id AND m.score_team1 > m.score_team2 THEN 1
                WHEN p.team_id = m.away_team_id AND m.score_team2 > m.score_team1 THEN 1
                ELSE 0
            END
        ) AS wins,
        ROUND(
            SUM(
                CASE
                    WHEN p.team_id = m.home_team_id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN p.team_id = m.away_team_id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100
        , 0) AS win_rate,
        ROUND(
            SUM(
                CASE
                    WHEN p.team_id = m.home_team_id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN p.team_id = m.away_team_id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) * 40
            +
            SUM(
                CASE
                    WHEN p.team_id = m.home_team_id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN p.team_id = m.away_team_id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100 * 20
        , 0) AS points
    FROM Player p
    LEFT JOIN Team t ON t.id = p.team_id
    LEFT JOIN Game g ON g.id = (
        SELECT tt.game_id
        FROM tournament_teams tmt 
        JOIN Tournament tt ON tt.id = tmt.tournament_id
        WHERE tmt.team_id = p.team_id
        ORDER BY tmt.registered_at DESC
        LIMIT 1
    )
    LEFT JOIN Matches m ON (m.home_team_id = p.team_id OR m.away_team_id = p.team_id)
                           AND m.score_team1 IS NOT NULL
    WHERE p.user_type = 'player'
    GROUP BY p.id, p.username, t.name, g.name
    ORDER BY points DESC
";
$playersData = $pdo->query($playerSql)->fetchAll();

// ── FETCH TEAM LEADERBOARD ───────────────────────────────────────────────────
$teamSql = "
    SELECT
        t.id,
        t.name,
        t.rank_point,
        g.name AS game_name,
        COUNT(DISTINCT m.id) AS total_matches,
        SUM(
            CASE
                WHEN m.home_team_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                WHEN m.away_team_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                ELSE 0
            END
        ) AS wins,
        ROUND(
            SUM(
                CASE
                    WHEN m.home_team_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN m.away_team_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100
        , 0) AS win_rate,
        ROUND(
            SUM(
                CASE
                    WHEN m.home_team_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN m.away_team_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) * 40
            +
            SUM(
                CASE
                    WHEN m.home_team_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN m.away_team_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100 * 20
        , 0) AS points
    FROM Team t
    LEFT JOIN tournament_teams tmt ON tmt.team_id = t.id
    LEFT JOIN Tournament tn ON tn.id = tmt.tournament_id
    LEFT JOIN Game g ON g.id = tn.game_id
    LEFT JOIN Matches m ON (m.home_team_id = t.id OR m.away_team_id = t.id)
                           AND m.score_team1 IS NOT NULL               
    GROUP BY t.id, t.name, t.rank_point, g.name
    ORDER BY points DESC
";
$teamsData = $pdo->query($teamSql)->fetchAll();

// ── SESSION: current user ───────────────────────────────────────────
$me_id      = $_SESSION['user_id'] ?? null;
$me_team_id = $_SESSION['team_id'] ?? null;

// ── HELPER FUNCTIONS ─────────────────────────────────
function getInitials(string $name): string {
    $words = explode(' ', $name);
    if (count($words) >= 2) return strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    return strtoupper(mb_substr($name, 0, 2));
}

// Prepare data for JS
$jsPlayers = array_map(function($p) use ($me_id) {
    return [
        'id'      => (int)$p['id'],
        'init'    => getInitials($p['username']),
        'name'    => $p['username'],
        'tag'     => $p['team_name'] ?? '—',
        'game'    => $p['game_name'] ?? '—',
        'wins'    => (int)$p['wins'],
        'matches' => (int)$p['total_matches'],
        'wr'      => (int)$p['win_rate'],
        'points'  => (int)$p['points'],
        'me'      => $me_id && (int)$p['id'] === (int)$me_id,
    ];
}, $playersData);

$jsTeams = array_map(function($t) use ($me_team_id) {
    return [
        'id'      => (int)$t['id'],
        'init'    => getInitials($t['name']),
        'name'    => $t['name'],
        'tag'     => $t['game_name'] ?? '—',
        'game'    => $t['game_name'] ?? '—',
        'wins'    => (int)$t['wins'],
        'matches' => (int)$t['total_matches'],
        'wr'      => (int)$t['win_rate'],
        'points'  => (int)$t['points'],
        'me'      => $me_team_id && (int)$t['id'] === (int)$me_team_id,
    ];
}, $teamsData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ikarus — Leaderboard</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/utils.css">
    <link rel="stylesheet" href="../assets/css/leaderboard.css">
</head>
<body>

<?php include_once __DIR__ . '/../includes/header.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h1 class="page-title">Leaderboard</h1>
      <p class="page-sub">Global Ranking · All Time</p>
    </div>
    <div class="game-filter" id="gameFilter">
      <button class="gf-btn active" onclick="filterGame('all',this)">All</button>
      <button class="gf-btn" onclick="filterGame('cs2',this)">CS2</button>
      <button class="gf-btn" onclick="filterGame('val',this)">Valorant</button>
      <button class="gf-btn" onclick="filterGame('fc',this)">FC 25</button>
      <button class="gf-btn" onclick="filterGame('lol',this)">LoL</button>
    </div>
  </div>

  <section class="podium-section" id="podium"></section>

  <div class="table-card">
    <div class="tabs-row">
      <button class="tab-btn active" onclick="switchTab('players',this)">Players</button>
      <button class="tab-btn" onclick="switchTab('teams',this)">Teams</button>
    </div>

    <div id="tab-players">
      <div class="search-row">
        <input class="lb-search" placeholder="Search player..." oninput="filterSearch(this.value,'players')">
        <span class="result-count" id="player-count"></span>
      </div>
      <table class="lb-table">
        <thead>
          <tr>
            <th style="width:44px">#</th>
            <th>Player</th>
            <th>Game</th>
            <th>Wins</th>
            <th>Matches</th>
            <th>Win Rate</th>
            <th>Points</th>
          </tr>
        </thead>
        <tbody id="player-tbody"></tbody>
      </table>
      <div class="formula-note">Points = <em>(Wins × 40) + (Win Rate × 20)</em></div>
    </div>

    <div id="tab-teams" style="display:none">
      <div class="search-row">
        <input class="lb-search" placeholder="Search team..." oninput="filterSearch(this.value,'teams')">
        <span class="result-count" id="team-count"></span>
      </div>
      <table class="lb-table">
        <thead>
          <tr>
            <th style="width:44px">#</th>
            <th>Team</th>
            <th>Game</th>
            <th>Wins</th>
            <th>Matches</th>
            <th>Win Rate</th>
            <th>Points</th>
          </tr>
        </thead>
        <tbody id="team-tbody"></tbody>
      </table>
      <div class="formula-note">Points = <em>(Wins × 40) + (Win Rate × 20)</em></div>
    </div>
  </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Data Transfer: Sadece değişkenleri aktarıyoruz -->
<script>
    const playersData = <?= json_encode($jsPlayers, JSON_UNESCAPED_UNICODE) ?>;
    const teamsData = <?= json_encode($jsTeams, JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- Harici JS dosyamızı çağırıyoruz -->
<script src="../assets/js/leaderboard.js"></script>

</body>
</html>