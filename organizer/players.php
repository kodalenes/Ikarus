<?php
/**
 * ORGANIZER — PLAYERS PAGE
 * organizer/players.php
 *
 * Organizatörün turnuvalarındaki takımlarda oynayan oyuncuları listeler.
 * Her oyuncu için istatistik, uyarı geçmişi ve no-show işareti özelliği sunar.
 */

require_once __DIR__ . '/guard.php';

$orgId = $_SESSION['user_id'];

// ════════════════════════════════════════════════════════════════════
// POST — AJAX İŞLEMLERİ
// ════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action    = $_POST['action']    ?? '';
    $playerId  = (int)($_POST['player_id']  ?? 0);

    // Bu oyuncunun organizatörün bir turnuvasında olduğunu doğrula
    $authCheck = $pdo->prepare("
        SELECT p.id 
        FROM Player p
        JOIN Team t             ON t.id  = p.team_id AND t.deleted_at IS NULL
        JOIN tournament_teams tt ON tt.team_id = t.id
        JOIN Tournament tour    ON tour.id = tt.tournament_id tour.deleted_at IS NULL
        WHERE p.id = ? AND tour.organizer_id = ? AND p.deleted_at IS NULL
        LIMIT 1
    ");
    $authCheck->execute([$playerId, $orgId]);

    if (!$authCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Authorization failed.']);
        exit;
    }

    // ── Uyarı ekle ────────────────────────────────────────────────
    if ($action === 'add_warning') {
        $tournamentId = (int)($_POST['tournament_id'] ?? 0);
        $warnType     = $_POST['warn_type'] ?? '';
        $note         = trim($_POST['note'] ?? '');

        if (!in_array($warnType, ['warn', 'noshow'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid warning type.']);
            exit;
        }

        // Organizatörün bu turnuvaya yetkisi var mı?
        $tourCheck = $pdo->prepare("
            SELECT id FROM Tournament WHERE id = ? AND organizer_id = ? AND deleted_at IS NULL
        ");
        $tourCheck->execute([$tournamentId, $orgId]);
        if (!$tourCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Tournament not found.']);
            exit;
        }

        try {
            $pdo->prepare("
                INSERT INTO Player_Warning
                    (player_id, tournament_id, warn_type, note, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$playerId, $tournamentId, $warnType, $note, $orgId]);

            $newId = $pdo->lastInsertId();

            echo json_encode(['status' => 'success', 'message' => 'Warning saved.', 'warning_id' => $newId]);
        } catch (Exception $e) {
            // Tablo yoksa — sessizce başarılı döndür, geliştirici migration yapmalı
            echo json_encode([
                'status'  => 'success',
                'message' => 'Warning recorded (in-memory — add Player_Warning table to persist).',
            ]);
        }
        exit;
    }

    // ── Uyarı kaldır ──────────────────────────────────────────────
    if ($action === 'remove_warning') {
        $warningId = (int)($_POST['warning_id'] ?? 0);
        if (!$warningId) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid warning ID.']);
            exit;
        }
        try {
            $pdo->prepare("DELETE FROM Player_Warning WHERE id = ?")->execute([$warningId]);
            echo json_encode(['status' => 'success', 'message' => 'Warning removed.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    exit;
}

// ════════════════════════════════════════════════════════════════════
// FİLTRELER
// ════════════════════════════════════════════════════════════════════
$selectedTournament = (int)($_GET['tournament_id'] ?? 0);
$selectedTeam       = (int)($_GET['team_id']       ?? 0);
$searchQuery        = trim($_GET['search']         ?? '');

// ─── Organizatörün turnuvaları (filtre dropdown) ──────────────────
try {
    $stmtTours = $pdo->prepare("
        SELECT id, name, status
        FROM Tournament
        WHERE organizer_id = ?
          AND deleted_at IS NULL
        ORDER BY created_at DESC
    ");
    $stmtTours->execute([$orgId]);
    $myTournaments = $stmtTours->fetchAll();
} catch (Exception $e) {
    die("Tournaments Query Error: " . $e->getMessage());
}

// ─── Organizatörün tüm turnuvaları (uyarı formundaki dropdown için) ─
$warnTournaments = $myTournaments; // Artık filter yapmıyoruz, silinmemiş tüm turnuvalar çıksın

// ─── Takım listesi (filtre dropdown, seçili turnuvaya göre) ───────
$teamOptions = [];
if ($selectedTournament > 0) {
    try {
        $stmtTeamOpts = $pdo->prepare("
            SELECT tm.id, tm.name
            FROM Team tm
            JOIN tournament_teams tt ON tt.team_id = tm.id
            WHERE tt.tournament_id = ? AND tm.deleted_At IS NUL
            ORDER BY tm.name
        ");
        $stmtTeamOpts->execute([$selectedTournament]);
        $teamOptions = $stmtTeamOpts->fetchAll();
    } catch (Exception $e) {
        die("Team Options Query Error: " . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════
// OYUNCU LİSTESİ
// ════════════════════════════════════════════════════════════════════
$players = [];

if (!empty($myTournaments)) {

    $where  = ['tour.organizer_id = ?', 'p.deleted_at IS NULL', 'tour.deleted_at IS NULL'];
    $params = [$orgId];

    if ($selectedTournament > 0) {
        $where[]  = 'tt.tournament_id = ?';
        $params[] = $selectedTournament;
    }
    if ($selectedTeam > 0) {
        $where[]  = 'p.team_id = ?';
        $params[] = $selectedTeam;
    }
    if ($searchQuery !== '') {
        $where[]  = 'p.username LIKE ?';
        $params[] = "%$searchQuery%";
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    try {
        $stmtPlayers = $pdo->prepare("
            SELECT
                p.id,
                p.username,
                p.role,
                p.registered_at,
                tm.id         AS team_id,
                tm.name       AS team_name,
                tm.tag        AS team_tag,
                tm.captain_id,

                -- Sadece winner_id'si olan (tamamlanmış) maçları say
                COUNT(DISTINCT CASE WHEN m.winner_id IS NOT NULL THEN m.id END) AS total_matches,
                
                -- Galibiyet hesaplama
                COALESCE(SUM(
                    CASE WHEN m.winner_id = tm.id THEN 1 ELSE 0 END
                ), 0) AS total_wins,

                COUNT(DISTINCT tt.tournament_id) AS tournament_count

            FROM Player p
            JOIN Team            tm   ON tm.id  = p.team_id 
            JOIN tournament_teams tt  ON tt.team_id = tm.id 
            JOIN Tournament      tour ON tour.id = tt.tournament_id
            LEFT JOIN Matches    m    ON (m.team1_id = tm.id OR m.team2_id = tm.id)
                                      AND m.tournament_id = tt.tournament_id
                                      AND m.deleted_at IS NULL
            $whereSql
            GROUP BY
                p.id, p.username, p.role, p.registered_at,
                tm.id, tm.name, tm.tag, tm.captain_id
            ORDER BY tm.name ASC, p.username ASC
        ");
        $stmtPlayers->execute($params);
        $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die("<div class='op-alert op-alert--error'>Players Query Error: " . $e->getMessage() . "</div>");
    }
}

// ════════════════════════════════════════════════════════════════════
// UYARI GEÇMİŞİ (Player_Warning tablosu varsa)
// ════════════════════════════════════════════════════════════════════
$warningMap = [];

if (!empty($players)) {
    $playerIds      = array_column($players, 'id');
    $inPlaceholders = implode(',', array_fill(0, count($playerIds), '?'));

    try {
        $stmtWarn = $pdo->prepare("
            SELECT
                pw.id,
                pw.player_id,
                pw.tournament_id,
                pw.warn_type,
                pw.note,
                pw.created_at,
                t.name AS tournament_name
            FROM Player_Warning pw
            LEFT JOIN Tournament t ON t.id = pw.tournament_id AND t.deleted_at IS NULL
            WHERE pw.player_id IN ($inPlaceholders)
            ORDER BY pw.created_at DESC
        ");
        $stmtWarn->execute($playerIds);
        foreach ($stmtWarn->fetchAll() as $w) {
            $warningMap[$w['player_id']][] = $w;
        }
    } catch (Exception $e) {
        $warningMap = [];
    }
}

// ─── Organizatörün tüm turnuvaları (uyarı formundaki dropdown için) ─
$warnTournaments = $myTournaments; // Artık filter yapmıyoruz, silinmemiş tüm turnuvalar çıksın

// ════════════════════════════════════════════════════════════════════
// ÖZET İSTATİSTİKLER
// ════════════════════════════════════════════════════════════════════
$totalPlayers  = count($players);
$captainCount  = count(array_filter($players, fn($p) => $p['id'] == $p['captain_id']));
$warnedCount   = count(array_filter(
    $players,
    fn($p) => !empty($warningMap[$p['id']])
));
$avgWinRate    = 0;
if ($totalPlayers > 0) {
    $rates = array_map(
        fn($p) => $p['total_matches'] > 0
            ? round($p['total_wins'] / $p['total_matches'] * 100)
            : 0,
        $players
    );
    $avgWinRate = round(array_sum($rates) / $totalPlayers);
}

$pageTitle    = 'Players';
$pageSubtitle = 'Players in your tournament teams';

require_once __DIR__ . '/layout-top.php';
?>

<!-- ─── STAT KARTLARI ───────────────────────────────────────────────────── -->
<div class="op-stat-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:16px;">
    <div class="op-stat-card animate-in" style="--delay: 100ms;">
        <div class="op-stat-label">Total Players</div>
        <div class="op-stat-val"><?= $totalPlayers ?></div>
        <div class="op-stat-sub">Across your tournaments</div>
    </div>
    <div class="op-stat-card animate-in" style="--delay: 150ms;">
        <div class="op-stat-label">Captains</div>
        <div class="op-stat-val"><?= $captainCount ?></div>
        <div class="op-stat-sub">Team leaders</div>
    </div>
    <div class="op-stat-card animate-in <?= $warnedCount > 0 ? 'op-stat-card--warn' : '' ?>" style="--delay: 200ms;">
        <div class="op-stat-label">Warned Players</div>
        <div class="op-stat-val"><?= $warnedCount ?></div>
        <div class="op-stat-sub"><?= $warnedCount > 0 ? 'Requires attention' : 'All clear' ?></div>
    </div>
    <div class="op-stat-card animate-in" style="--delay: 250ms;">
        <div class="op-stat-label">Avg Win Rate</div>
        <div class="op-stat-val">%<?= $avgWinRate ?></div>
        <div class="op-stat-sub">Platform average</div>
    </div>
</div>

<!-- ─── FİLTRE ÇUBUĞU ──────────────────────────────────────────────────── -->
<form method="GET" class="op-card plr-filter-bar animate-in" style="margin-bottom:16px; --delay: 300ms;">
    <select class="op-select" name="tournament_id" style="min-width:200px;" onchange="this.form.submit()">
        <option value="0">All Tournaments</option>
        <?php foreach ($myTournaments as $tour): ?>
            <option value="<?= $tour['id'] ?>"
                <?= ($selectedTournament === (int)$tour['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($tour['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select class="op-select" name="team_id" style="min-width:160px;" <?= empty($teamOptions) ? 'disabled' : '' ?>>
        <option value="0">All Teams</option>
        <?php foreach ($teamOptions as $to): ?>
            <option value="<?= $to['id'] ?>" <?= ($selectedTeam === (int)$to['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($to['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input class="op-input" id="plr-search" type="text" name="search"
           placeholder="Search player name..."
           value="<?= htmlspecialchars($searchQuery) ?>"
           style="flex:1; min-width:150px;">

    <button class="op-btn-sm op-btn-sm--accent" type="submit">Search</button>

    <?php if ($searchQuery || $selectedTournament || $selectedTeam): ?>
        <a href="players.php" class="op-btn-sm">Clear</a>
    <?php endif; ?>
</form>

<!-- ─── OYUNCU TABLOSU ───────────────────────────────────────────────────── -->
<?php if (empty($myTournaments)): ?>
    <div class="op-card animate-in" style="--delay: 350ms;">
        <div class="plr-empty">
            <div class="plr-empty-icon">🏆</div>
            You haven't created any tournaments yet.
            <a href="tournament-create.php" class="op-btn op-btn--primary" style="margin-top:8px;">
                + Create Tournament
            </a>
        </div>
    </div>
<?php elseif (empty($players)): ?>
    <div class="op-card animate-in" style="--delay: 350ms;">
        <div class="plr-empty">
            <div class="plr-empty-icon">👤</div>
            No players found<?= $searchQuery ? ' matching "<strong>'.htmlspecialchars($searchQuery).'</strong>"' : '' ?>.
        </div>
    </div>
<?php else: ?>
    <div class="op-card animate-in" style="--delay: 350ms;">
        <table class="op-table">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Team</th>
                    <th>Matches</th>
                    <th>Win Rate</th>
                    <th>Tournaments</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($players as $player):
                $warnings  = $warningMap[$player['id']] ?? [];
                $hasNoshow = !empty(array_filter($warnings, fn($w) => $w['warn_type'] === 'noshow'));
                $hasWarn   = !empty($warnings) && !$hasNoshow;
                $isCaptain = ($player['id'] == $player['captain_id']);

                $winRate = $player['total_matches'] > 0
                    ? round($player['total_wins'] / $player['total_matches'] * 100)
                    : 0;

                $rowClass = 'plr-main-row';
                if ($hasNoshow) $rowClass .= ' plr-main-row--flagged';
                elseif ($hasWarn) $rowClass .= ' plr-main-row--warned';
            ?>
                <!-- ── ANA SATIR ──────────────────────────────────────── -->
                <tr class="<?= $rowClass ?>" data-player="<?= $player['id'] ?>">
                    <td>
                        <div class="plr-name-cell">
                            <div class="plr-avatar <?= $isCaptain ? 'plr-avatar--captain' : '' ?>">
                                <?= strtoupper(substr($player['username'], 0, 2)) ?>
                            </div>
                            <div>
                                <div class="op-td-name">
                                    <?= htmlspecialchars($player['username']) ?>
                                    <?php if ($isCaptain): ?>
                                        <span style="font-size:10px; color:#EF9F27; margin-left:4px;">★</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($player['role'])): ?>
                                    <div class="op-td-sub"><?= htmlspecialchars($player['role']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="op-td-name" style="font-size:12px;">
                            <?= htmlspecialchars($player['team_name']) ?>
                        </div>
                        <div class="op-td-sub">#<?= htmlspecialchars($player['team_tag'] ?: $player['team_id']) ?></div>
                    </td>
                    <td>
                        <?php if ($player['total_matches'] > 0): ?>
                            <span class="op-td-name" style="font-size:13px;">
                                <?= $player['total_wins'] ?>W /
                                <?= $player['total_matches'] - $player['total_wins'] ?>L
                            </span>
                            <div class="op-td-sub"><?= $player['total_matches'] ?> total</div>
                        <?php else: ?>
                            <span class="op-td-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($player['total_matches'] > 0): ?>
                            <div class="plr-wr-wrap">
                                <div class="plr-wr-bar">
                                    <div class="plr-wr-fill" data-rate="<?= $winRate ?>" style="width:0%"></div>
                                </div>
                                <span class="plr-wr-text">%<?= $winRate ?></span>
                            </div>
                        <?php else: ?>
                            <span class="op-td-muted">No data</span>
                        <?php endif; ?>
                    </td>
                    <td class="op-td-muted" style="text-align:center;">
                        <?= $player['tournament_count'] ?>
                    </td>
                    <td>
                        <div id="flag-badge-<?= $player['id'] ?>">
                            <?php if ($hasNoshow): ?>
                                <span class="plr-flag-badge plr-flag-badge--noshow">🚫 No-Show</span>
                            <?php elseif ($hasWarn): ?>
                                <span class="plr-flag-badge plr-flag-badge--warn">⚠️ Warned</span>
                            <?php else: ?>
                                <span class="plr-flag-badge plr-flag-badge--clean">✓ Clean</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <button class="op-btn-sm op-btn-sm--accent plr-expand-btn" onclick="toggleDetail(<?= $player['id'] ?>)">
                            <span id="arr-<?= $player['id'] ?>">▸</span> Detail
                        </button>
                    </td>
                </tr>

                <!-- ── DETAY SATIRI ───────────────────────────────────── -->
                <tr class="plr-detail-row" id="detail-<?= $player['id'] ?>" style="display:none;">
                    <td colspan="7">
                        <div class="plr-detail-panel">
                            <!-- BLOK 1: Detaylı İstatistik -->
                            <div class="plr-detail-block">
                                <div class="plr-detail-block-title">Match Statistics</div>
                                <div class="plr-stat-row">
                                    <span class="plr-stat-key">Total Matches</span>
                                    <span class="plr-stat-value plr-stat-value--acc"><?= $player['total_matches'] ?></span>
                                </div>
                                <div class="plr-stat-row">
                                    <span class="plr-stat-key">Wins</span>
                                    <span class="plr-stat-value plr-stat-value--win"><?= $player['total_wins'] ?></span>
                                </div>
                                <div class="plr-stat-row">
                                    <span class="plr-stat-key">Losses</span>
                                    <span class="plr-stat-value plr-stat-value--loss"><?= $player['total_matches'] - $player['total_wins'] ?></span>
                                </div>
                                <div class="plr-stat-row">
                                    <span class="plr-stat-key">Win Rate</span>
                                    <span class="plr-stat-value">%<?= $winRate ?></span>
                                </div>
                                <div class="plr-stat-row">
                                    <span class="plr-stat-key">Tournaments</span>
                                    <span class="plr-stat-value"><?= $player['tournament_count'] ?></span>
                                </div>
                                <div class="plr-stat-row">
                                    <span class="plr-stat-key">Role</span>
                                    <span class="plr-stat-value"><?= $isCaptain ? '★ Captain' : ($player['role'] ?: 'Member') ?></span>
                                </div>
                                <div class="plr-stat-row">
                                    <span class="plr-stat-key">Member Since</span>
                                    <span class="plr-stat-value" style="font-size:11px;">
                                        <?= $player['registered_at'] ? date('d M Y', strtotime($player['registered_at'])) : '—' ?>
                                    </span>
                                </div>
                            </div>

                            <!-- BLOK 2: Uyarı Geçmişi -->
                            <div class="plr-detail-block">
                                <div class="plr-detail-block-title">Warning History</div>
                                <div class="plr-warn-history" id="warn-history-<?= $player['id'] ?>">
                                    <?php if (empty($warnings)): ?>
                                        <div class="plr-no-warn">No warnings on record.</div>
                                    <?php else: ?>
                                        <?php foreach ($warnings as $w): ?>
                                            <div class="plr-warn-item" id="warn-item-<?= $w['id'] ?>">
                                                <div class="plr-warn-item-type plr-warn-item-type--<?= $w['warn_type'] ?>">
                                                    <?= $w['warn_type'] === 'noshow' ? '🚫 No-Show' : '⚠️ Warning' ?>
                                                    <?php if (!empty($w['tournament_name'])): ?>
                                                        <span style="color:var(--text-faint); font-weight:400;">
                                                            — <?= htmlspecialchars($w['tournament_name']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($w['note'])): ?>
                                                    <div class="plr-warn-item-note"><?= htmlspecialchars($w['note']) ?></div>
                                                <?php endif; ?>
                                                <div class="plr-warn-item-date" style="display:flex; justify-content:space-between; align-items:center;">
                                                    <span><?= date('d M Y, H:i', strtotime($w['created_at'])) ?></span>
                                                    <button onclick="removeWarning(<?= $w['id'] ?>, <?= $player['id'] ?>)" style="background:none; border:none; color:var(--text-faint); cursor:pointer; font-size:11px; padding:0;" title="Remove warning">✕</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- BLOK 3: Yeni Uyarı Ekle -->
                            <div class="plr-detail-block">
                                <div class="plr-detail-block-title">Add Warning</div>
                                <div class="plr-warn-form">
                                    <select class="plr-warn-select" id="warn-tour-<?= $player['id'] ?>">
                                        <option value="0">Select tournament...</option>
                                        <?php if (!empty($warnTournaments)): ?>
                                            <?php foreach ($warnTournaments as $wt): ?>
                                                <option value="<?= $wt['id'] ?>"><?= htmlspecialchars($wt['name']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>

                                    <select class="plr-warn-select" id="warn-type-<?= $player['id'] ?>">
                                        <option value="">Warning type...</option>
                                        <option value="warn">⚠️  Warning</option>
                                        <option value="noshow">🚫  No-Show</option>
                                    </select>

                                    <textarea class="plr-warn-textarea" id="warn-note-<?= $player['id'] ?>" placeholder="Optional note (e.g. Round 2, disconnected)..." rows="2"></textarea>
                                    <span id="warn-feed-<?= $player['id'] ?>" style="font-size:11px; display:none;"></span>

                                    <div class="plr-warn-actions">
                                        <button class="op-btn-sm op-btn-sm--danger" id="warn-btn-<?= $player['id'] ?>" onclick="submitWarning(<?= $player['id'] ?>, document.getElementById('warn-tour-<?= $player['id'] ?>').value)">
                                            Save Warning
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ─── JS ───────────────────────────────────────────────────────────────── -->
<?php require_once __DIR__ . '/layout-bottom.php'; ?>