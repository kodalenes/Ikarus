<?php
require_once __DIR__ . '/guard.php';

$orgId = $_SESSION['user_id'];

// ─── POST: Disqualify operations ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action       = $_POST['action']        ?? '';
    $teamId       = (int)($_POST['team_id'] ?? 0);
    $tournamentId = (int)($_POST['tournament_id'] ?? 0);

    $authCheck = $pdo->prepare("
        SELECT tt.team_id FROM tournament_teams tt
        JOIN Tournament t ON t.id = tt.tournament_id
        WHERE tt.team_id = ? AND tt.tournament_id = ? AND t.organizer_id = ?
    ");
    $authCheck->execute([$teamId, $tournamentId, $orgId]);

    if (!$authCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Authorization failed.']);
        exit;
    }

    if ($action === 'disqualify') {
        try {
            $pdo->prepare("
                DELETE FROM tournament_teams
                WHERE team_id = ? AND tournament_id = ?
            ")->execute([$teamId, $tournamentId]);

            echo json_encode(['status' => 'success', 'message' => 'Team removed from tournament.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    exit;
}

// ─── Fetch Organizer's Tournaments (for filter) ──────────────────────────────
try {
    $stmtTours = $pdo->prepare("
        SELECT id, name, status
        FROM Tournament
        WHERE organizer_id = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
    ");
    $stmtTours->execute([$orgId]);
    $myTournaments = $stmtTours->fetchAll();
} catch (Exception $e) {
    die("Tournaments Query Error: " . $e->getMessage());
}

// ─── Filters ────────────────────────────────────────────────────────────
$selectedTournament = (int)($_GET['tournament_id'] ?? 0);
$searchQuery        = trim($_GET['search'] ?? '');

// ─── Fetch Team List ────────────────────────────────────────────────────────
$teams = [];
if (!empty($myTournaments)) {
    $where  = ['t.organizer_id = ?', 't.deleted_at IS NULL', 'tm.deleted_at IS NULL'];
    $params = [$orgId];

    if ($selectedTournament > 0) {
        $where[]  = 'tt.tournament_id = ?';
        $params[] = $selectedTournament;
    }
    
    if ($searchQuery !== '') {
        $where[]  = '(tm.name LIKE ? OR tm.tag LIKE ?)';
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    try {
        // DÜZELTME 1: Kartezyen çarpımı önlemek için 'JOIN Player p' kaldırıldı.
        // DÜZELTME 2: Oyun ismini 'g.name AS game_name' olarak aldık.
        // DÜZELTME 3: tm.tag ve tm.region eklendi.
        $stmtTeams = $pdo->prepare("
            SELECT
                tm.id,
                tm.name,
                tm.tag,
                tm.region,
                tm.captain_id,
                cap.username AS captain_name,
                t.id         AS tournament_id,
                t.name       AS tournament_name,
                t.status     AS tournament_status,
                g.name       AS game_name,
                tt.registered_at,
                COUNT(DISTINCT m.id)  AS match_count,
                COALESCE(SUM(
                    CASE WHEN m.winner_id = tm.id THEN 1 ELSE 0 END
                ), 0)                 AS win_count
            FROM tournament_teams tt
            JOIN Team       tm  ON tm.id  = tt.team_id
            JOIN Tournament t   ON t.id   = tt.tournament_id
            LEFT JOIN Game  g   ON g.id   = t.game_id
            LEFT JOIN Player    cap ON cap.id = tm.captain_id
            LEFT JOIN Matches   m   ON (m.team1_id = tm.id OR m.team2_id = tm.id)
                                    AND m.tournament_id = tt.tournament_id
                                    AND m.score_team1 IS NOT NULL
                                    AND m.deleted_at IS NULL
            $whereSql
            GROUP BY
                tm.id, tm.name, tm.tag, tm.region, tm.captain_id,
                cap.username, t.id, t.name, t.status, g.name, tt.registered_at
            ORDER BY tt.registered_at DESC
        ");
        $stmtTeams->execute($params);
        $teams = $stmtTeams->fetchAll();
    } catch (Exception $e) {
        die("Teams Query Error: " . $e->getMessage());
    }
}

// ─── Fetch Members for Each Team (for expand panel) ──────────────────────────────
$memberMap = [];
if (!empty($teams)) {
    $teamIds = array_values(array_unique(array_column($teams, 'id')));
    $inPlaceholders = implode(',', array_fill(0, count($teamIds), '?'));
    
    try {
        $stmtMembers = $pdo->prepare("
            SELECT id, username, team_id, role
            FROM Player
            WHERE team_id IN ($inPlaceholders) AND deleted_at IS NULL
            ORDER BY username
        ");
        $stmtMembers->execute($teamIds);
        foreach ($stmtMembers->fetchAll() as $m) {
            $memberMap[$m['team_id']][] = $m;
        }
    } catch (Exception $e) {
        die("Members Query Error: " . $e->getMessage());
    }
}

// ─── Summary Statistics ───────────────────────────────────────────────────
$totalTeams  = count($teams);
$liveTeams   = count(array_filter($teams, fn($t) => $t['tournament_status'] === 'live'));
$regTeams    = count(array_filter($teams, fn($t) => $t['tournament_status'] === 'registration'));

$pageTitle    = 'Teams';
$pageSubtitle = 'Teams registered in your tournaments';

require_once __DIR__ . '/layout-top.php';
?>

<!-- ─── STAT CARDS ──────────────────────────────────────────────────────── -->
<div class="op-stat-grid" style="grid-template-columns: repeat(3,1fr); margin-bottom:16px;">
    <div class="op-stat-card animate-in" style="--delay: 100ms;">
        <div class="op-stat-label">Total Teams</div>
        <div class="op-stat-val"><?= $totalTeams ?></div>
        <div class="op-stat-sub">
            <?= count($myTournaments) ?> tournament<?= count($myTournaments) !== 1 ? 's' : '' ?>
        </div>
    </div>
    <div class="op-stat-card animate-in" style="--delay: 150ms;">
        <div class="op-stat-label">In Live Tournaments</div>
        <div class="op-stat-val"><?= $liveTeams ?></div>
        <div class="op-stat-sub">Currently competing</div>
    </div>
    <div class="op-stat-card animate-in" style="--delay: 200ms;">
        <div class="op-stat-label">In Registration</div>
        <div class="op-stat-val"><?= $regTeams ?></div>
        <div class="op-stat-sub">Waiting to start</div>
    </div>
</div>

<!-- ─── FILTER BAR ──────────────────────────────────────────────────── -->
<form method="GET" class="tm-filter-bar op-card animate-in" style="margin-bottom:16px; --delay: 250ms;">
    <select class="op-select" name="tournament_id" onchange="this.form.submit()" style="min-width:220px;">
        <option value="0">All Tournaments</option>
        <?php foreach ($myTournaments as $tour): ?>
            <option value="<?= $tour['id'] ?>"
                <?= ($selectedTournament === (int)$tour['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($tour['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input class="op-input" type="text" name="search"
           placeholder="Search team name or tag..."
           value="<?= htmlspecialchars($searchQuery) ?>"
           style="flex:1; min-width:160px;">

    <button class="op-btn-sm op-btn-sm--accent" type="submit">Search</button>

    <?php if ($searchQuery || $selectedTournament): ?>
        <a href="teams.php" class="op-btn-sm">Clear</a>
    <?php endif; ?>
</form>

<!-- ─── TEAMS LIST ────────────────────────────────────────────────────────── -->
<?php if (empty($myTournaments)): ?>
    <div class="op-card op-empty animate-in" style="--delay: 300ms;">
        You haven't created any tournaments yet.
        <a href="tournament-create.php" class="op-btn op-btn--primary" style="margin-top:12px;">
            + Create Tournament
        </a>
    </div>

<?php elseif (empty($teams)): ?>
    <div class="op-card op-empty animate-in" style="--delay: 300ms;">
        No teams found<?= $searchQuery ? ' matching "'.htmlspecialchars($searchQuery).'"' : '' ?>.
    </div>

<?php else: ?>
    <div class="op-card animate-in" style="--delay: 300ms;">
        <table class="op-table">
            <thead>
                <tr>
                    <th>Team</th>
                    <th>Game</th>
                    <th>Tournament</th>
                    <th>Members</th>
                    <th>Stats</th>
                    <th>Captain</th>
                    <th>Registered</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team):
                    // Üye sayısını SQL'den değil, çektiğimiz PHP dizisinden buluyoruz
                    $members   = $memberMap[$team['id']] ?? [];
                    $memberCount = count($members);
                    
                    $winRate   = $team['match_count'] > 0
                        ? round($team['win_count'] / $team['match_count'] * 100)
                        : 0;

                    $tourStatusMap = [
                        'live'         => ['op-badge--live', 'Live'],
                        'registration' => ['op-badge--open', 'Registration'],
                        'upcoming'     => ['op-badge--soon', 'Upcoming'],
                        'finished'     => ['op-badge--done', 'Finished'],
                    ];
                    $ts = $tourStatusMap[$team['tournament_status']] ?? ['op-badge--done', $team['tournament_status']];

                    $rowKey = $team['id'] . '_' . $team['tournament_id'];
                    
                    // Takım tag'i boşsa ID'sini gösterir
                    $displayTag = $team['tag'] ?: $team['id'];
                ?>
                <!-- MAIN ROW -->
                <tr class="tm-team-row" data-key="<?= $rowKey ?>">

                    <!-- Team Name -->
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="tm-row-avatar">
                                <?= strtoupper(substr($team['name'], 0, 2)) ?>
                            </div>
                            <div>
                                <div class="op-td-name"><?= htmlspecialchars($team['name']) ?></div>
                                <div class="op-td-sub">#<?= htmlspecialchars($displayTag) ?>
                                    <?php if ($team['region']): ?>
                                        · <?= htmlspecialchars($team['region']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Game (DÜZELTİLDİ) -->
                    <td class="op-td-muted"><?= htmlspecialchars($team['game_name'] ?? '—') ?></td>

                    <!-- Tournament -->
                    <td>
                        <div class="op-td-name" style="font-size:12px">
                            <?= htmlspecialchars($team['tournament_name']) ?>
                        </div>
                        <span class="op-badge <?= $ts[0] ?>" style="margin-top:4px; display:inline-block;">
                            <?= $ts[1] ?>
                        </span>
                    </td>

                    <!-- Members Count -->
                    <td>
                        <div class="tm-member-pips">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <div class="tm-pip <?= $i < $memberCount ? 'tm-pip--filled' : '' ?>"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="op-td-sub"><?= $memberCount ?> / 5</div>
                    </td>

                    <!-- Match Stats (DÜZELTİLDİ) -->
                    <td>
                        <?php if ($team['match_count'] > 0): ?>
                            <div class="op-td-name" style="font-size:13px;">
                                <?= $team['win_count'] ?>W / <?= $team['match_count'] - $team['win_count'] ?>L
                            </div>
                            <div class="tm-wr-bar">
                                <div class="tm-wr-fill" style="width:<?= $winRate ?>%"></div>
                            </div>
                            <div class="op-td-sub">%<?= $winRate ?> win rate</div>
                        <?php else: ?>
                            <span class="op-td-muted">No matches</span>
                        <?php endif; ?>
                    </td>

                    <!-- Captain -->
                    <td class="op-td-muted">
                        <?= $team['captain_name'] ? htmlspecialchars($team['captain_name']) : '—' ?>
                    </td>

                    <!-- Register Date -->
                    <td class="op-td-muted" style="font-size:11px; white-space:nowrap;">
                        <?= date('d M Y', strtotime($team['registered_at'])) ?>
                    </td>

                    <!-- Actions -->
                    <td>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <!-- Show Members -->
                            <button
                                class="op-btn-sm op-btn-sm--accent tm-expand-btn"
                                onclick="toggleMembers('<?= $rowKey ?>')"
                                title="Show members">
                                <span id="arrow-<?= $rowKey ?>">▸</span> Members
                            </button>

                            <!-- Disqualify -->
                            <?php if (in_array($team['tournament_status'], ['registration', 'upcoming', 'live'])): ?>
                                <button
                                    class="op-btn-sm op-btn-sm--danger"
                                    onclick="confirmDisqualify(<?= $team['id'] ?>, <?= $team['tournament_id'] ?>, '<?= htmlspecialchars($team['name'], ENT_QUOTES) ?>')">
                                    Remove
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <!-- EXPAND: MEMBERS ROW -->
                <tr class="tm-members-row" id="members-<?= $rowKey ?>" style="display:none;">
                    <td colspan="8" style="padding:0;">
                        <div class="tm-members-panel">
                            <div class="tm-members-panel-title">
                                Team Members — <?= htmlspecialchars($team['name']) ?>
                            </div>
                            <?php if (empty($members)): ?>
                                <span class="op-td-muted">No members found.</span>
                            <?php else: ?>
                                <div class="tm-members-grid">
                                    <?php foreach ($members as $m): ?>
                                        <div class="tm-member-chip <?= $m['id'] == $team['captain_id'] ? 'tm-member-chip--captain' : '' ?>">
                                            <div class="tm-chip-avatar">
                                                <?= strtoupper(substr($m['username'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="tm-chip-name"><?= htmlspecialchars($m['username']) ?></div>
                                                <div class="tm-chip-role">
                                                    <?php if ($m['id'] == $team['captain_id']): ?>
                                                        <span style="color:#EF9F27;">★ Captain</span>
                                                    <?php else: ?>
                                                        <?= !empty($m['role']) ? htmlspecialchars($m['role']) : 'Member' ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ─── CONFIRMATION MODAL (Disqualify) ──────────────────────────────────────── -->
<div id="dq-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.72); z-index:300; align-items:center; justify-content:center;">
    <div class="tm-confirm-box">
        <div style="font-size:28px; text-align:center; margin-bottom:10px;">⚠️</div>
        <div class="tm-confirm-title">Remove Team?</div>
        <div class="tm-confirm-sub" id="dq-message"></div>
        <div class="tm-confirm-actions">
            <button class="op-btn op-btn--ghost" onclick="closeDq()">Cancel</button>
            <button class="op-btn-sm op-btn-sm--danger" style="padding:8px 20px; font-size:13px;" id="dq-confirm-btn">Remove</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout-bottom.php'; ?>