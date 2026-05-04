<?php
// Include database and session management
require_once __DIR__ . '/../includes/session.php';
$customTitle = 'Leadeboard';
$extraCss = ['leaderboard'];

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
                WHEN p.team_id = m.team1_id AND m.score_team1 > m.score_team2 THEN 1
                WHEN p.team_id = m.team2_id AND m.score_team2 > m.score_team1 THEN 1
                ELSE 0
            END
        ) AS wins,
        ROUND(
            SUM(
                CASE
                    WHEN p.team_id = m.team1_id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN p.team_id = m.team2_id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100
        , 0) AS win_rate,
        ROUND(
            SUM(
                CASE
                    WHEN p.team_id = m.team1_id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN p.team_id = m.team2_id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) * 40
            +
            SUM(
                CASE
                    WHEN p.team_id = m.team1_id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN p.team_id = m.team2_id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100 * 20
        , 0) AS points
    FROM Player p
    LEFT JOIN Team t ON t.id = p.team_id AND t.deleted_at IS NULL
    LEFT JOIN Game g ON g.id = (
        SELECT tt.game_id
        FROM tournament_teams tmt 
        JOIN Tournament tt ON tt.id = tmt.tournament_id
        WHERE tmt.team_id = p.team_id AND tt.deleted_at IS NULL
        ORDER BY tmt.registered_at DESC
        LIMIT 1
    )
    LEFT JOIN Matches m ON (m.team1_id = p.team_id OR m.team2_id = p.team_id)
                           AND m.score_team1 IS NOT NULL
                           AND m.deleted_at IS NULL
    WHERE p.user_type = 'player' AND p.deleted_at IS NULL
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
                WHEN m.team1_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                WHEN m.team2_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                ELSE 0
            END
        ) AS wins,
        ROUND(
            SUM(
                CASE
                    WHEN m.team1_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN m.team2_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100
        , 0) AS win_rate,
        ROUND(
            SUM(
                CASE
                    WHEN m.team1_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN m.team2_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) * 40
            +
            SUM(
                CASE
                    WHEN m.team1_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN m.team2_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100 * 20
        , 0) AS points
    FROM Team t
    LEFT JOIN tournament_teams tmt ON tmt.team_id = t.id
    LEFT JOIN Tournament tn ON tn.id = tmt.tournament_id AND tn.deleted_at IS NULL
    LEFT JOIN Game g ON g.id = tn.game_id AND g.deleted_at IS NULL
    LEFT JOIN Matches m ON (m.team1_id = t.id OR m.team2_id = t.id)
                           AND m.score_team1 IS NOT NULL
                           AND m.deleted_at IS NULL
    WHERE t.deleted_at IS NULL                       
    GROUP BY t.id, t.name, t.rank_point, g.name
    ORDER BY points DESC
";
$teamsData = $pdo->query($teamSql)->fetchAll();

// ── FETCH ACTIVE GAMES FOR FILTER ───────────────────────────────────────────
$gamesSql = "
    SELECT g.id, g.name, COUNT(t.id) as t_count 
    FROM Game g
    LEFT JOIN Tournament t ON g.id = t.game_id AND t.deleted_at IS NULL
    WHERE g.deleted_at IS NULL 
    GROUP BY g.id, g.name
    ORDER BY t_count DESC, g.name ASC
";
$gamesList = $pdo->query($gamesSql)->fetchAll();

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
  <?php require_once '../includes/head.php' ?>
</head>
<body>

<?php include_once __DIR__ . '/../includes/header.php'; ?>

<main class="content">
  <div class="page-header animate-in" style="--delay: 100ms;">
    <div>
      <h1 class="page-title">Leaderboard</h1>
      <p class="page-sub">Global Ranking · All Time</p>
    </div>
    <div class="game-filter" id="gameFilter">
      <button class="gf-btn active" onclick="filterGame('all',this)">All</button>
      
      <?php 
        $visibleGames = array_slice($gamesList, 0, 3);
        $dropdownGames = array_slice($gamesList, 3);
      ?>
      
      <?php foreach ($visibleGames as $g): ?>
        <button class="gf-btn" onclick="filterGame('<?= htmlspecialchars($g['name'], ENT_QUOTES) ?>',this)">
          <?= htmlspecialchars($g['name']) ?>
        </button>
      <?php endforeach; ?>

      <?php if (count($dropdownGames) > 0): ?>
        <select class="gf-select" onchange="if(this.value) { filterGame(this.value, this); this.value=''; }">
          <option value="" disabled selected>Other Games...</option>
          <?php foreach ($dropdownGames as $g): ?>
            <option value="<?= htmlspecialchars($g['name'], ENT_QUOTES) ?>"><?= htmlspecialchars($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>
  </div>

  <section class="podium-section animate-in" id="podium" style="--delay: 200ms;"></section>

  <div class="table-card animate-in" style="--delay: 300ms;">
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