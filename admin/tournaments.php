<?php
require_once __DIR__ . '/guard.php';

// ─── POST / AJAX ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    enforceAjaxCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'change_status') {
        $id        = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $allowed   = ['draft', 'upcoming', 'registration', 'live', 'finished'];

        if (!$id || !in_array($newStatus, $allowed)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE Tournament SET status = ? WHERE id = ?")
                ->execute([$newStatus, $id]);
            echo json_encode(['status' => 'success', 'message' => 'Status updated.', 'new_status' => $newStatus]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit;
        }
        try {
            $check = $pdo->prepare("SELECT status FROM Tournament WHERE id = ? AND deleted_at IS NULL");
            $check->execute([$id]);
            $t = $check->fetch();
            if (!$t) {
                echo json_encode(['status' => 'error', 'message' => 'Tournament not found.']);
                exit;
            }
            if ($t['status'] === 'live') {
                echo json_encode(['status' => 'error', 'message' => 'Cannot delete a live tournament. Change status first.']);
                exit;
            }
            $pdo->prepare("UPDATE Tournament SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Tournament deleted.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error. Tournament may have related match records.']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    exit;
}

// ─── Filtre & Sayfalama ───────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$gameFilter   = (int)($_GET['game_id'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$allowedStatuses = ['draft', 'upcoming', 'registration', 'live', 'finished'];

// ─── WHERE yapısı ─────────────────────────────────────────────────────────
// $filterParams: sadece WHERE placeholder'ları için kullanılır.
// count query ve list query aynı $whereSql + $filterParams'ı paylaşır.
$where        = [];
$filterParams = [];

$where[] = 't.deleted_at IS NULL';

if ($search !== '') {
    $where[]        = '(t.name LIKE ? OR p.username LIKE ?)';
    $filterParams[] = '%' . $search . '%';
    $filterParams[] = '%' . $search . '%';
}
if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $where[]        = 't.status = ?';
    $filterParams[] = $statusFilter;
}
if ($gameFilter > 0) {
    $where[]        = 't.game_id = ?';
    $filterParams[] = $gameFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ─── URL builder (PHP 7.3 uyumlu, arrow function yok) ────────────────────
function buildTourUrl($search, $status, $gameId, $page = 0) {
    $params = [];
    if ($search !== '')  $params['search']  = $search;
    if ($status !== '')  $params['status']  = $status;
    if ($gameId  >  0)  $params['game_id'] = $gameId;
    if ($page    >  1)  $params['page']    = $page;   // sayfa 1 URL'de gereksiz
    return '?' . http_build_query($params);
}

// ─── Toplam kayıt ─────────────────────────────────────────────────────────
// count query, game_id filtresi çalışsın diye Game tablosunu da JOIN eder.
// Aynı $filterParams kullanılır — slot sayısı eşleşiyor.
try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.id)
        FROM Tournament t
        LEFT JOIN Player p ON p.id = t.organizer_id
        LEFT JOIN Game g   ON g.id = t.game_id
        $whereSql
    ");
    $countStmt->execute($filterParams);
    $totalTournaments = (int)$countStmt->fetchColumn();
    $totalPages       = (int)ceil($totalTournaments / $perPage);
} catch (Exception $e) {
    $totalTournaments = 0;
    $totalPages       = 1;
}

// ─── Turnuva listesi ──────────────────────────────────────────────────────
// GROUP BY tüm non-aggregate kolonları listeler → ONLY_FULL_GROUP_BY uyumlu.
try {
    $stmt = $pdo->prepare("
        SELECT
            t.id, t.name, t.status, t.prize_pool,
            t.max_teams, t.start_date, t.end_date, t.created_at,
            p.id       AS organizer_id,
            p.username AS organizer_name,
            g.name     AS game_name,
            g.id       AS game_id,
            COUNT(DISTINCT tt.team_id)                                     AS registered_teams,
            COUNT(DISTINCT m.id)                                           AS total_matches,
            COUNT(DISTINCT CASE WHEN m.score_team1 IS NULL THEN m.id END) AS pending_matches
        FROM Tournament t
        LEFT JOIN Player p           ON p.id  = t.organizer_id
        LEFT JOIN Game g             ON g.id  = t.game_id
        LEFT JOIN tournament_teams tt ON tt.tournament_id = t.id
        LEFT JOIN Matches m          ON m.tournament_id  = t.id
        $whereSql
        GROUP BY
            t.id, t.name, t.status, t.prize_pool,
            t.max_teams, t.start_date, t.end_date, t.created_at,
            p.id, p.username,
            g.id, g.name
        ORDER BY
            FIELD(t.status, 'live', 'registration', 'upcoming', 'draft', 'finished'),
            t.start_date DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($filterParams);
    $tournaments = $stmt->fetchAll();
} catch (Exception $e) {
    $tournaments = [];
}

// ─── Durum sayaçları ──────────────────────────────────────────────────────
try {
    $statusCounts = ['all' => 0, 'live' => 0, 'registration' => 0, 'upcoming' => 0, 'draft' => 0, 'finished' => 0];
    foreach ($pdo->query("SELECT status, COUNT(*) AS cnt FROM Tournament WHERE deleted_at IS NULL GROUP BY status")->fetchAll() as $r) {
        $statusCounts[$r['status']] = (int)$r['cnt'];
    }
    $statusCounts['all'] = array_sum(array_diff_key($statusCounts, ['all' => 0]));
} catch (Exception $e) {
    $statusCounts = array_fill_keys(['all','live','registration','upcoming','draft','finished'], 0);
}

// ─── Oyun listesi (dropdown için) ─────────────────────────────────────────
try {
    $allGames = $pdo->query("SELECT id, name FROM Game WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $allGames = [];
}

$pageTitle    = 'Tournaments';
$pageSubtitle = number_format($totalTournaments) . ' tournaments found';

require_once __DIR__ . '/layout-top.php';

$statusMap = [
    'live'         => ['label' => 'Live',         'badge' => 'op-badge--live'],
    'registration' => ['label' => 'Registration', 'badge' => 'op-badge--open'],
    'upcoming'     => ['label' => 'Upcoming',     'badge' => 'op-badge--soon'],
    'draft'        => ['label' => 'Draft',        'badge' => 'op-badge--draft'],
    'finished'     => ['label' => 'Finished',     'badge' => 'op-badge--done'],
];
?>

<!-- ─── FİLTRE ÇUBUĞU ──────────────────────────────────────────────────── -->
<div class="adm-filter-bar animate-in" style="flex-wrap:wrap; gap:12px; --delay:100ms;">

    <!-- Durum sekmeleri -->
    <div class="adm-role-tabs">
        <?php
        $tabs = [
            ''             => 'All',
            'live'         => 'Live',
            'registration' => 'Open',
            'upcoming'     => 'Upcoming',
            'draft'        => 'Draft',
            'finished'     => 'Finished',
        ];
        foreach ($tabs as $val => $label):
            $cnt  = ($val === '') ? $statusCounts['all'] : ($statusCounts[$val] ?? 0);
            // buildTourUrl: arama ve game filtresi korunur, sadece status değişir
            $href = buildTourUrl($search, $val, $gameFilter);
        ?>
            <a href="<?= $href ?>" class="adm-role-tab <?= ($statusFilter === $val) ? 'active' : '' ?>">
                <?= $label ?>
                <span class="adm-role-tab-count"><?= $cnt ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Arama + Oyun Filtresi -->
    <form method="GET" class="adm-search-form">
        <!-- Status her zaman hidden input olarak taşınır (boş string dahil değil) -->
        <?php if ($statusFilter !== ''): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <?php endif; ?>

        <select class="adm-role-select" name="game_id" onchange="this.form.submit()">
            <option value="0">All Games</option>
            <?php foreach ($allGames as $g): ?>
                <option value="<?= (int)$g['id'] ?>" <?= ($gameFilter === (int)$g['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input class="adm-search-input" type="text" name="search"
               placeholder="Search name or organizer..."
               value="<?= htmlspecialchars($search) ?>">
        <button class="op-btn-sm op-btn-sm--accent" type="submit">Search</button>
        <?php if ($search !== '' || $gameFilter > 0): ?>
            <a href="<?= buildTourUrl('', $statusFilter, 0) ?>" class="op-btn-sm">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- ─── TURNUVA TABLOSU ─────────────────────────────────────────────────── -->
<div class="op-card animate-in" style="--delay:150ms;">
    <?php if (empty($tournaments)): ?>
        <div class="op-empty">No tournaments found matching your criteria.</div>
    <?php else: ?>
        <table class="op-table adm-tour-table">
            <thead>
                <tr>
                    <th>Tournament</th>
                    <th>Organizer</th>
                    <th>Game</th>
                    <th>Teams</th>
                    <th>Matches</th>
                    <th>Prize</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tournaments as $t):
                    $s       = $statusMap[$t['status']] ?? ['label' => $t['status'], 'badge' => 'op-badge--done'];
                    $fillPct = $t['max_teams'] > 0
                        ? min(100, round($t['registered_teams'] / $t['max_teams'] * 100))
                        : 0;
                ?>
                <tr data-id="<?= $t['id'] ?>">

                    <td>
                        <div class="op-td-name"><?= htmlspecialchars($t['name']) ?></div>
                        <div class="op-td-sub">#<?= $t['id'] ?></div>
                    </td>

                    <td>
                        <div class="adm-org-cell">
                            <div class="adm-org-avatar">
                                <?= strtoupper(substr($t['organizer_name'] ?? '?', 0, 2)) ?>
                            </div>
                            <span class="op-td-muted"><?= htmlspecialchars($t['organizer_name'] ?? '—') ?></span>
                        </div>
                    </td>

                    <td class="op-td-muted"><?= htmlspecialchars($t['game_name'] ?? '—') ?></td>

                    <td>
                        <div class="adm-slot-wrap">
                            <div class="adm-slot-bar">
                                <div class="adm-slot-fill" style="width:<?= $fillPct ?>%"></div>
                            </div>
                            <span class="adm-slot-text"><?= $t['registered_teams'] ?>/<?= $t['max_teams'] ?></span>
                        </div>
                    </td>

                    <td>
                        <span class="op-td-muted"><?= $t['total_matches'] ?></span>
                        <?php if ($t['pending_matches'] > 0): ?>
                            <span class="adm-pending-dot" title="<?= $t['pending_matches'] ?> pending">
                                <?= $t['pending_matches'] ?>⏳
                            </span>
                        <?php endif; ?>
                    </td>

                    <td class="op-td-prize">
                        <?= $t['prize_pool'] > 0
                            ? '₺' . number_format($t['prize_pool'], 0, ',', '.')
                            : '<span class="op-td-muted">—</span>' ?>
                    </td>

                    <td class="op-td-muted" style="font-size:11px; white-space:nowrap;">
                        <?= date('d M Y', strtotime($t['start_date'])) ?>
                        <br>→ <?= date('d M Y', strtotime($t['end_date'])) ?>
                    </td>

                    <td>
                        <span class="op-badge <?= $s['badge'] ?> adm-status-badge"
                              data-id="<?= $t['id'] ?>">
                            <?= $s['label'] ?>
                        </span>
                    </td>

                    <td>
                        <div class="adm-tour-actions">
                            <button class="op-btn-sm op-btn-sm--accent"
                                    onclick="openStatusPanel(<?= $t['id'] ?>, '<?= $t['status'] ?>')">
                                Status
                            </button>
                            <a href="../organizer/tournament-create.php?id=<?= $t['id'] ?>"
                               class="op-btn-sm" target="_blank">
                                Edit
                            </a>
                            <?php if ($t['status'] !== 'live'): ?>
                                <button class="adm-btn-danger"
                                        onclick="adminDeleteTournament(<?= $t['id'] ?>, '<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')">
                                    Delete
                                </button>
                            <?php else: ?>
                                <span class="op-td-muted" style="font-size:10px">Live</span>
                            <?php endif; ?>
                        </div>
                    </td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ─── SAYFALAMA ──────────────────────────────────────────────────────── -->
<?php if ($totalPages > 1): ?>
<div class="adm-pagination animate-in" style="--delay:200ms;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="<?= buildTourUrl($search, $statusFilter, $gameFilter, $i) ?>"
           class="adm-page-btn <?= ($i === $page) ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
    <span class="adm-page-info">
        <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $totalTournaments) ?>
        of <?= number_format($totalTournaments) ?>
    </span>
</div>
<?php endif; ?>

<!-- ─── STATUS PANEL (floating) ─────────────────────────────────────────── -->
<div id="statusPanel" class="adm-status-panel" style="display:none;">
    <div class="adm-sp-title">Change Status</div>
    <input type="hidden" id="sp-tournament-id">
    <div class="adm-sp-options" id="spOptions"></div>
    <button class="op-btn-sm" style="margin-top:10px; width:100%;" onclick="closeStatusPanel()">Cancel</button>
</div>
<div id="statusPanelOverlay" class="adm-sp-overlay" style="display:none;" onclick="closeStatusPanel()"></div>



<?php 
    $pageJs = 'tournaments.js';
    require_once __DIR__ . '/layout-bottom.php';     
?>
