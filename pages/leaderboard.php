<?php
// Bu satır, pages klasöründen çıkıp includes içindeki db.php'yi bulur.
include_once '../includes/db.php'; 

// BAĞLANTI TESTİ:
if (isset($pdo)) {
    // Eğer bağlantı varsa bu yazı çıkar.
} else {
    die("Hata: db.php dosyasına ulaşılamadı veya bağlantı kurulamadı!");
}

// DB bağlantısı
require_once __DIR__ . '/../includes/db.php';

// ── OYUNCU SIRALAMASINI ÇEK ──────────────────────────────────────────────────
// Her oyuncunun kazandığı ve oynadığı maç sayısını hesapla
// Kazanma = home_team kazandıysa home takımın oyuncusu, away kazandıysa away takımın oyuncusu
$playerSql = "
    SELECT
        p.id,
        p.username,
        t.name        AS team_name,
        g.name        AS game_name,
        COUNT(DISTINCT m.id)                                         AS total_matches,
        SUM(
            CASE
                WHEN p.team_id = m.home_team_id AND m.score_team1 > m.score_team2 THEN 1
                WHEN p.team_id = m.away_team_id AND m.score_team2 > m.score_team1 THEN 1
                ELSE 0
            END
        )                                                            AS wins,
        ROUND(
            SUM(
                CASE
                    WHEN p.team_id = m.home_team_id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN p.team_id = m.away_team_id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100
        , 0)                                                         AS win_rate,
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
        , 0)                                                         AS points
    FROM Player p
    LEFT JOIN Team t       ON t.id = p.team_id
    LEFT JOIN Game g       ON g.id = (
        SELECT tt.game_id
        FROM tournament_team tmt
        JOIN Tournament tt ON tt.id = tmt.tournament_id
        WHERE tmt.team_id = p.team_id
        ORDER BY tmt.registered_at DESC
        LIMIT 1
    )
    LEFT JOIN Matches m    ON (m.home_team_id = p.team_id OR m.away_team_id = p.team_id)
                           AND m.score_team1 IS NOT NULL
              
    WHERE p.user_type = 'player'
    GROUP BY p.id, p.username, t.name, g.name
    ORDER BY points DESC
";
$players = $pdo->query($playerSql)->fetchAll();

// ── TAKIM SIRALAMASINI ÇEK ───────────────────────────────────────────────────
$teamSql = "
    SELECT
        t.id,
        t.name,
        t.rank_point,
        g.name  AS game_name,
        COUNT(DISTINCT m.id)                                              AS total_matches,
        SUM(
            CASE
                WHEN m.home_team_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                WHEN m.away_team_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                ELSE 0
            END
        )                                                                 AS wins,
        ROUND(
            SUM(
                CASE
                    WHEN m.home_team_id = t.id AND m.score_team1 > m.score_team2 THEN 1
                    WHEN m.away_team_id = t.id AND m.score_team2 > m.score_team1 THEN 1
                    ELSE 0
                END
            ) / NULLIF(COUNT(DISTINCT m.id), 0) * 100
        , 0)                                                              AS win_rate,
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
        , 0)                                                              AS points
    FROM Team t
    LEFT JOIN tournament_team tmt ON tmt.team_id = t.id
    LEFT JOIN Tournament      tn  ON tn.id = tmt.tournament_id
    LEFT JOIN Game            g   ON g.id  = tn.game_id
    LEFT JOIN Matches         m   ON (m.home_team_id = t.id OR m.away_team_id = t.id)
                                  AND m.score_team1 IS NOT NULL               
    GROUP BY t.id, t.name, t.rank_point, g.name
    ORDER BY points DESC
";
$teams = $pdo->query($teamSql)->fetchAll();

// ── OTURUM: giriş yapan kullanıcı ───────────────────────────────────────────
session_start();
$me_id      = $_SESSION['player_id'] ?? null;
$me_team_id = $_SESSION['team_id']   ?? null;

// ── YARDIMCI FONKSİYONLAR ───────────────────────────────────────────────────
function initials(string $name): string {
    $words = explode(' ', $name);
    if (count($words) >= 2) return strtoupper(mb_substr($words[0],0,1) . mb_substr($words[1],0,1));
    return strtoupper(mb_substr($name, 0, 2));
}

function wrClass(int $wr): string {
    if ($wr >= 70) return 'wr-high';
    if ($wr >= 50) return 'wr-mid';
    return 'wr-low';
}

function rankColorClass(int $i): string {
    if ($i === 0) return 'rank-gold';
    if ($i === 1) return 'rank-silver';
    if ($i === 2) return 'rank-bronze';
    return 'rank-num';
}

function avatarClass(int $i): string {
    if ($i === 0) return 'gold';
    if ($i === 1) return 'silver';
    if ($i === 2) return 'bronze';
    return 'default';
}

function gameTagClass(string $game): string {
    $g = strtolower($game);
    if (str_contains($g, 'cs'))        return 'cs2';
    if (str_contains($g, 'valorant'))  return 'val';
    if (str_contains($g, 'fc'))        return 'fc';
    if (str_contains($g, 'league'))    return 'lol';
    return '';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IKARUS — Leaderboard</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d0f14;--surface:rgba(255,255,255,0.02);--border:rgba(255,255,255,0.08);
  --border-subtle:rgba(255,255,255,0.04);--text:#e8e9ef;--text-dim:rgba(255,255,255,0.45);
  --text-muted:rgba(255,255,255,0.25);--accent:#489fb5;--accent-bg:rgba(72,159,181,0.08);
  --accent-border:rgba(72,159,181,0.25);--gold:#EF9F27;--silver:#B4B2A9;--bronze:#D85A30;
  --green:#97c459;--red:#f87171;
}
body{background:var(--bg);color:var(--text);font-family:'SF Pro Display',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;min-height:100vh}

/* NAV */
.nav{display:flex;align-items:center;justify-content:space-between;padding:16px 32px;border-bottom:0.5px solid var(--border);position:sticky;top:0;z-index:50;background:rgba(13,15,20,0.92);backdrop-filter:blur(12px)}
.nav-logo{font-size:15px;font-weight:500;letter-spacing:4px;color:#fff}
.nav-links{display:flex;gap:24px}
.nav-links a{font-size:12px;color:var(--text-dim);text-decoration:none;letter-spacing:1px;transition:color .15s}
.nav-links a:hover{color:var(--text)}
.nav-links a.active{color:var(--accent)}
.nav-right{display:flex;align-items:center;gap:10px}
.welcome{font-size:12px;color:var(--text-dim)}
.welcome strong{color:var(--text)}
.btn-ghost{padding:6px 14px;border:0.5px solid rgba(255,255,255,0.18);border-radius:6px;background:transparent;color:rgba(255,255,255,0.6);font-size:11px;cursor:pointer;letter-spacing:1px}

/* CONTENT */
.content{padding:28px 32px;max-width:1200px;margin:0 auto}
.page-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:24px}
.page-title{font-size:20px;font-weight:500;color:#fff;letter-spacing:.5px}
.page-sub{font-size:13px;color:var(--text-muted);margin-top:4px}

/* GAME FILTER */
.game-filter{display:flex;gap:6px}
.gf-btn{padding:6px 14px;border:0.5px solid var(--border);border-radius:6px;background:transparent;color:var(--text-muted);font-size:11px;cursor:pointer;letter-spacing:.5px;transition:all .15s;font-family:inherit}
.gf-btn:hover{border-color:var(--accent-border);color:var(--text-dim)}
.gf-btn.active{border-color:var(--accent);color:var(--accent);background:var(--accent-bg)}

/* PODIUM */
.podium-section{display:flex;align-items:flex-end;justify-content:center;gap:10px;margin-bottom:28px;padding:8px 60px 0}
.podium-slot{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;max-width:160px}
.pod-avatar{border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;color:#0d0f14;flex-shrink:0}
.pod-name{font-size:13px;font-weight:500;color:#fff;text-align:center}
.pod-pts{font-size:11px;color:var(--text-muted);text-align:center}
.pod-base{border-radius:8px 8px 0 0;width:100%;display:flex;align-items:center;justify-content:center}
.rank-label{font-size:11px;letter-spacing:1.5px;font-weight:600;padding:0 8px}
.podium-slot.first .pod-avatar{width:54px;height:54px;font-size:16px;background:var(--gold);box-shadow:0 0 0 3px rgba(239,159,39,.25),0 0 24px rgba(239,159,39,.15)}
.podium-slot.first .pod-base{height:72px;background:rgba(239,159,39,.08);border:0.5px solid rgba(239,159,39,.2)}
.podium-slot.first .rank-label{color:var(--gold)}
.podium-slot.second .pod-avatar{width:44px;height:44px;font-size:14px;background:var(--silver);box-shadow:0 0 0 2px rgba(180,178,169,.2)}
.podium-slot.second .pod-base{height:50px;background:rgba(180,178,169,.06);border:0.5px solid rgba(180,178,169,.12)}
.podium-slot.second .rank-label{color:var(--silver)}
.podium-slot.third .pod-avatar{width:40px;height:40px;font-size:13px;background:var(--bronze);box-shadow:0 0 0 2px rgba(216,90,48,.2)}
.podium-slot.third .pod-base{height:36px;background:rgba(216,90,48,.06);border:0.5px solid rgba(216,90,48,.12)}
.podium-slot.third .rank-label{color:var(--bronze)}

/* TABLE CARD */
.table-card{border:0.5px solid var(--border);border-radius:12px;overflow:hidden;background:var(--surface)}
.tabs-row{display:flex;border-bottom:0.5px solid var(--border);padding:0 16px}
.tab-btn{padding:12px 20px;background:transparent;border:none;border-bottom:2px solid transparent;color:var(--text-dim);font-size:13px;cursor:pointer;letter-spacing:.5px;margin-bottom:-0.5px;transition:all .15s;font-family:inherit}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent)}
.tab-btn:hover:not(.active){color:var(--text)}
.search-row{display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:0.5px solid var(--border-subtle);background:rgba(255,255,255,.01)}
.lb-search{flex:1;max-width:240px;padding:7px 12px;background:rgba(255,255,255,.04);border:0.5px solid rgba(255,255,255,.08);border-radius:7px;color:var(--text);font-size:12px;outline:none;font-family:inherit;transition:border-color .15s}
.lb-search::placeholder{color:var(--text-muted)}
.lb-search:focus{border-color:var(--accent-border)}
.result-count{font-size:11px;color:var(--text-muted);margin-left:auto;letter-spacing:.5px}

/* TABLE */
.lb-table{width:100%;border-collapse:collapse}
.lb-table th{font-size:10px;color:var(--text-muted);letter-spacing:2px;text-transform:uppercase;padding:10px 16px;text-align:left;border-bottom:0.5px solid var(--border-subtle);white-space:nowrap}
.lb-table td{padding:10px 16px;border-bottom:0.5px solid var(--border-subtle);font-size:13px;vertical-align:middle}
.lb-table tr:last-child td{border-bottom:none}
.lb-table tr:hover td{background:rgba(255,255,255,.015)}
.lb-table tr.me td{background:rgba(239,159,39,.03)}
.lb-table tr.me td:first-child{border-left:2px solid rgba(239,159,39,.6);padding-left:14px}
.rank-num{font-size:12px;color:var(--text-muted);width:32px;text-align:center;display:inline-block}
.rank-gold{color:var(--gold);font-weight:600}
.rank-silver{color:var(--silver);font-weight:600}
.rank-bronze{color:var(--bronze);font-weight:600}
.player-cell{display:flex;align-items:center;gap:10px}
.p-av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;flex-shrink:0}
.p-av.default{color:var(--accent);background:rgba(72,159,181,.12)}
.p-av.gold{background:rgba(239,159,39,.15);color:var(--gold)}
.p-av.silver{background:rgba(180,178,169,.15);color:var(--silver)}
.p-av.bronze{background:rgba(216,90,48,.15);color:var(--bronze)}
.p-nm{color:var(--text);font-weight:500;font-size:13px}
.p-tag{font-size:11px;color:var(--text-muted);margin-top:1px}
.game-tag{font-size:10px;padding:2px 7px;border-radius:4px;background:rgba(255,255,255,.05);color:rgba(255,255,255,.4);letter-spacing:.3px;white-space:nowrap}
.game-tag.cs2{background:rgba(255,165,0,.08);color:rgba(255,165,0,.65)}
.game-tag.val{background:rgba(255,70,84,.08);color:rgba(255,120,130,.8)}
.game-tag.fc{background:rgba(0,200,100,.08);color:rgba(0,200,100,.7)}
.game-tag.lol{background:rgba(200,155,60,.08);color:rgba(200,155,60,.75)}
.wr-cell{display:flex;align-items:center;gap:8px}
.wr-bar{width:52px;height:3px;background:rgba(255,255,255,.07);border-radius:2px;overflow:hidden;flex-shrink:0}
.wr-fill{height:100%;border-radius:2px}
.wr-high{background:var(--green)}
.wr-mid{background:var(--accent)}
.wr-low{background:var(--red)}
.wr-text{font-size:12px;color:rgba(255,255,255,.55);min-width:34px}
.pts-cell{font-size:14px;font-weight:500;color:var(--accent)}
.wins-cell{color:rgba(255,255,255,.7)}
.matches-cell{color:rgba(255,255,255,.4)}
.me-badge{font-size:9px;padding:1px 6px;border-radius:3px;background:rgba(239,159,39,.12);color:rgba(239,159,39,.8);border:0.5px solid rgba(239,159,39,.2);letter-spacing:.5px;margin-left:6px;vertical-align:middle}
.formula-note{font-size:11px;color:rgba(255,255,255,.18);padding:11px 16px;border-top:0.5px solid var(--border-subtle);text-align:right}
.formula-note em{color:rgba(72,159,181,.6);font-style:normal}
.empty-state{text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav-logo">IKARUS</div>
  <div class="nav-links">
    <a href="tournaments.php">Tournaments</a>
    <a href="team.php">Team</a>
    <a href="leaderboard.php" class="active">Leaderboard</a>
  </div>
  <div class="nav-right">
    <?php if ($me_id): ?>
      <span class="welcome">Welcome, <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong></span>
      <a href="logout.php"><button class="btn-ghost">Logout</button></a>
    <?php else: ?>
      <a href="login.php"><button class="btn-ghost">Login</button></a>
    <?php endif; ?>
  </div>
</nav>

<div class="content">

  <!-- BAŞLIK + FİLTRE -->
  <div class="page-header">
    <div>
      <div class="page-title">Leaderboard</div>
      <div class="page-sub">Genel sıralama · Tüm zamanlar</div>
    </div>
    <div class="game-filter" id="gameFilter">
      <button class="gf-btn active" onclick="filterGame('all',this)">Tümü</button>
      <button class="gf-btn" onclick="filterGame('cs2',this)">CS2</button>
      <button class="gf-btn" onclick="filterGame('val',this)">Valorant</button>
      <button class="gf-btn" onclick="filterGame('fc',this)">FC 25</button>
      <button class="gf-btn" onclick="filterGame('lol',this)">LoL</button>
    </div>
  </div>

  <!-- PODIUM -->
  <div class="podium-section" id="podium"></div>

  <!-- TABLO KARTI -->
  <div class="table-card">
    <div class="tabs-row">
      <button class="tab-btn active" onclick="switchTab('players',this)">Oyuncular</button>
      <button class="tab-btn" onclick="switchTab('teams',this)">Takımlar</button>
    </div>

    <!-- OYUNCULAR -->
    <div id="tab-players">
      <div class="search-row">
        <input class="lb-search" placeholder="Oyuncu ara..." oninput="filterSearch(this.value,'players')">
        <span class="result-count" id="player-count"></span>
      </div>
      <table class="lb-table">
        <thead>
          <tr>
            <th style="width:44px">#</th>
            <th>Oyuncu</th>
            <th>Oyun</th>
            <th>Galibiyet</th>
            <th>Maç</th>
            <th>Win Rate</th>
            <th>Puan</th>
          </tr>
        </thead>
        <tbody id="player-tbody"></tbody>
      </table>
      <div class="formula-note">Puan = <em>(Galibiyet × 40) + (Win Rate × 20)</em></div>
    </div>

    <!-- TAKIMLAR -->
    <div id="tab-teams" style="display:none">
      <div class="search-row">
        <input class="lb-search" placeholder="Takım ara..." oninput="filterSearch(this.value,'teams')">
        <span class="result-count" id="team-count"></span>
      </div>
      <table class="lb-table">
        <thead>
          <tr>
            <th style="width:44px">#</th>
            <th>Takım</th>
            <th>Oyun</th>
            <th>Galibiyet</th>
            <th>Maç</th>
            <th>Win Rate</th>
            <th>Puan</th>
          </tr>
        </thead>
        <tbody id="team-tbody"></tbody>
      </table>
      <div class="formula-note">Puan = <em>(Galibiyet × 40) + (Win Rate × 20)</em></div>
    </div>
  </div>

</div><!-- /content -->

<!-- PHP'den gelen veriyi JS'e aktar -->
<script>
const players = <?= json_encode(array_map(function($p, $i) use ($me_id) {
    return [
        'id'      => (int)$p['id'],
        'init'    => initials($p['username']),
        'name'    => $p['username'],
        'tag'     => $p['team_name'] ?? '—',
        'game'    => $p['game_name'] ?? '—',
        'wins'    => (int)$p['wins'],
        'matches' => (int)$p['total_matches'],
        'wr'      => (int)$p['win_rate'],
        'points'  => (int)$p['points'],
        'me'      => $me_id && (int)$p['id'] === (int)$me_id,
    ];
}, $players, array_keys($players)), JSON_UNESCAPED_UNICODE) ?>;

const teams = <?= json_encode(array_map(function($t, $i) use ($me_team_id) {
    return [
        'id'      => (int)$t['id'],
        'init'    => initials($t['name']),
        'name'    => $t['name'],
        'tag'     => $t['game_name'] ?? '—',
        'game'    => $t['game_name'] ?? '—',
        'wins'    => (int)$t['wins'],
        'matches' => (int)$t['total_matches'],
        'wr'      => (int)$t['win_rate'],
        'points'  => (int)$t['points'],
        'me'      => $me_team_id && (int)$t['id'] === (int)$me_team_id,
    ];
}, $teams, array_keys($teams)), JSON_UNESCAPED_UNICODE) ?>;

// ── STATE ──────────────────────────────────────────────────────────────────
let state = { tab: 'players', game: 'all', searchPlayers: '', searchTeams: '' };

// ── YARDIMCI ──────────────────────────────────────────────────────────────
function gameMatch(r, game) {
  if (game === 'all') return true;
  const g = (r.game || '').toLowerCase();
  if (game === 'val') return g.includes('valorant');
  if (game === 'cs2') return g.includes('cs');
  if (game === 'fc')  return g.includes('fc');
  if (game === 'lol') return g.includes('league');
  return true;
}
function wrClass(wr) { return wr >= 70 ? 'wr-high' : wr >= 50 ? 'wr-mid' : 'wr-low'; }
function rankColor(i){ return i===0?'rank-gold':i===1?'rank-silver':i===2?'rank-bronze':'rank-num'; }
function avatarCls(i){ return i===0?'gold':i===1?'silver':i===2?'bronze':'default'; }
function gameTagCls(g){
  const gl=(g||'').toLowerCase();
  if(gl.includes('cs'))       return 'cs2';
  if(gl.includes('valorant')) return 'val';
  if(gl.includes('fc'))       return 'fc';
  if(gl.includes('league'))   return 'lol';
  return '';
}

// ── RENDER ─────────────────────────────────────────────────────────────────
function render() {
  ['players','teams'].forEach(tab => {
    const raw    = tab === 'players' ? players : teams;
    const search = tab === 'players' ? state.searchPlayers : state.searchTeams;
    const filtered = raw.filter(r =>
      gameMatch(r, state.game) &&
      (!search || r.name.toLowerCase().includes(search) || r.tag.toLowerCase().includes(search))
    );
    // Puana göre sırala (zaten PHP'den sıralı geliyor ama filtre bozabilir)
    filtered.sort((a,b) => b.points - a.points);

    const tbody = document.getElementById(`${tab}-tbody`);
    if (!filtered.length) {
      tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">Bu filtreye uygun kayıt bulunamadı.</div></td></tr>`;
    } else {
      tbody.innerHTML = filtered.map((r, i) => {
        const meLabel = r.me ? '<span class="me-badge">SEN</span>' : '';
        const gc = gameTagCls(r.game);
        return `<tr class="${r.me ? 'me' : ''}">
          <td><span class="${rankColor(i)}">${i+1}</span></td>
          <td>
            <div class="player-cell">
              <div class="p-av ${avatarCls(i)}">${r.init}</div>
              <div>
                <div class="p-nm">${r.name}${meLabel}</div>
                <div class="p-tag">${r.tag}</div>
              </div>
            </div>
          </td>
          <td><span class="game-tag ${gc}">${r.game}</span></td>
          <td class="wins-cell">${r.wins}</td>
          <td class="matches-cell">${r.matches}</td>
          <td>
            <div class="wr-cell">
              <div class="wr-bar"><div class="wr-fill ${wrClass(r.wr)}" style="width:${r.wr}%"></div></div>
              <span class="wr-text">%${r.wr}</span>
            </div>
          </td>
          <td class="pts-cell">${r.points.toLocaleString('tr-TR')}</td>
        </tr>`;
      }).join('');
    }
    const el = document.getElementById(`${tab}-count`);
    if (el) el.textContent = `${filtered.length} ${tab==='players'?'oyuncu':'takım'}`;
  });
  updatePodium();
}

// ── PODIUM ─────────────────────────────────────────────────────────────────
function updatePodium() {
  const raw = state.tab === 'players' ? players : teams;
  const filtered = raw.filter(r => gameMatch(r, state.game));
  filtered.sort((a,b) => b.points - a.points);
  const order  = [filtered[1], filtered[0], filtered[2]]; // 2. | 1. | 3.
  const classes= ['second','first','third'];
  const ranks  = [2,1,3];
  document.getElementById('podium').innerHTML = order.map((r,i) => {
    if (!r) return `<div class="podium-slot ${classes[i]}"><div class="pod-base"></div></div>`;
    return `<div class="podium-slot ${classes[i]}">
      <div class="pod-avatar">${r.init}</div>
      <div class="pod-name">${r.name}</div>
      <div class="pod-pts">${r.points.toLocaleString('tr-TR')} puan</div>
      <div class="pod-base"><span class="rank-label">#${ranks[i]}</span></div>
    </div>`;
  }).join('');
}

// ── OLAYLAR ────────────────────────────────────────────────────────────────
function switchTab(tab, el) {
  state.tab = tab;
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tab-players').style.display = tab==='players' ? '' : 'none';
  document.getElementById('tab-teams').style.display   = tab==='teams'   ? '' : 'none';
  render();
}
function filterGame(game, el) {
  state.game = game;
  document.querySelectorAll('.gf-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  render();
}
function filterSearch(val, tab) {
  if (tab === 'players') state.searchPlayers = val.toLowerCase();
  else state.searchTeams = val.toLowerCase();
  render();
}

// ── İLK RENDER ─────────────────────────────────────────────────────────────
render();
</script>
</body>
</html>
