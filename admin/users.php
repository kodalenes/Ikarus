<?php
    require_once __DIR__ . '/guard.php';

    //AJAX ve POST islemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');

        enforceAjaxCsrf();

        $action = $_POST['action'] ?? '';

        //Rol degistrime
        if ($action === 'change_role') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $newRole = $_POST['role'] ?? '';

            //Kullanicinin rolu yoksa veya idsi null ise
            if (!$userId || !in_array($newRole, ['player' , 'organizer' , 'admin'])) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
                exit;
            }

            //userId giris yapan kullanicinin id si ise  yani kendisiyse veya kullanici admin degilse
            if ($userId === (int)$_SESSION['user_id'] && $newRole !== 'admin') {
                echo json_encode(['status' => 'error', 'message' => 'You cannot change your own admin role.']);
                exit;
            }

            try {
                $pdo->prepare("UPDATE Player SET user_type = ? WHERE id = ?")
                    ->execute([$newRole, $userId]);
                echo json_encode(['status' => 'success', 'message' => 'Role updated successfully.']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Database error.']);
            }
            exit;
        }

        //Kullanici silme islemi
        if ($action === 'delete_user') {
            $userId = (int)($_POST['user_id'] ?? 0);

            //userId null check
            if (!$userId) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid User ID.']);
                exit;
            }
            
            //Kendini silme kontrolu
            if ($userId === (int)$_SESSION['user_id']) {
                echo json_encode(['status' => 'error', 'message' => 'You cannot delete yourself.']);
                exit;
            }

            try {
                $pdo->prepare('UPDATE Player SET deleted_at = NOW() WHERE id = ?')->execute([$userId]);
                echo json_encode(['status' => 'success', 'message' => 'User deleted.']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Database error. User may have related records.']);
            }
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
        exit;
    }

    //Filtre ve Sayfalama
    $search = trim($_GET['search'] ?? '');
    $roleFilter = $_GET['role'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    $where[] = 'p.deleted_at IS NULL';

    if ($search !== '') {
        $where[] = '(p.username LIKE ? OR p.email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($roleFilter !== '' && in_array($roleFilter, ['player', 'organizer', 'admin'])) {
        $where[] = 'p.user_type = ?';
        $params[] = $roleFilter;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    //Toplam Kayit
    try {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM Player p $whereSql");
        $countStmt->execute($params);
        $totalUsers = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($totalUsers / $perPage);
    } catch (Exception $e) {
        $totalUsers = 0;
        $totalPages = 0;
    }

    //Kullanici listesi
    try {
        $stmt = $pdo->prepare("
            SELECT
                p.id, p.username, p.email, p.user_type, p.registered_at,
                t.name AS team_name,
                (SELECT COUNT(*) FROM Tournament WHERE organizer_id = p.id) AS tournament_count
            FROM Player p 
            LEFT JOIN Team t ON t.id = p.team_id AND t.deleted_at IS NULL
            $whereSql
            ORDER BY p.registered_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll();
    } catch (Exception $e) {
        $users = [];
        die("SQL Hatası: " . $e->getMessage());
    }

    //Rol sayaclari
    try {
        $roleCounts = ['all' => 0, 'player' => 0, 'organizer' => 0, 'admin' => 0];
        foreach($pdo->query("SELECT user_type, COUNT(*) AS cnt FROM Player WHERE deleted_at IS NULL GROUP BY user_type")->fetchAll() as $c){
            $roleCounts[$c['user_type']] = (int)$c['cnt'];
        }
        $roleCounts['all'] = array_sum($roleCounts);

    } catch (Exception $e) {
        $roleCounts = ['all' => 0, 'player' => 0, 'organizer' => 0, 'admin' => 0];
    }

    $pageTitle = 'Users';
    $pageSubtitle = number_format($totalUsers) . ' users found';

    require_once __DIR__ . '/layout-top.php';
?>

<div class="admin-body">
    <div class="op-card animate-in" style="--delay: 100ms; margin-bottom: 16px;">
        <div class="adm-filter-bar">
            <div class="adm-role-tabs">
                <?php
                $tabs = [
                    ''          => ['label' => 'All'        , 'count' => $roleCounts['all']],
                    'player'    => ['label' => 'Player'     , 'count' => $roleCounts['player']],
                    'organizer' => ['label' => 'Organizer'  , 'count' => $roleCounts['organizer']],
                    'admin'     => ['label' => 'Admin'      , 'count' => $roleCounts['admin']],
                ];
                foreach ($tabs as $val => $tab):
                    $href = '?' . http_build_query(array_filter(['search' => $search, 'role' => $val]));
                ?>
                    <a href="<?= $href ?>" class="adm-role-tab <?= $roleFilter === $val ? 'active' : '' ?>">
                        <?= $tab['label'] ?>
                        <span class="adm-role-tab-count"><?= $tab['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="GET" class="adm-search-form">
                <?php if ($roleFilter): ?>
                    <input type="hidden" name="role" value="<?= htmlspecialchars($roleFilter) ?>">
                <?php endif; ?>
                <input class="adm-search-input" type="text" name="search"
                       placeholder="Search username or email..."
                       value="<?= htmlspecialchars($search) ?>">
                <button class="op-btn-sm op-btn-sm--accent" type="submit">Search</button>
                <?php if ($search): ?>
                    <a href="?<?= $roleFilter ? 'role='.$roleFilter : '' ?>" class="op-btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="admin-panel animate-in" style="--delay: 150ms;">
       <!-- ─── KULLANICI TABLOSU ───────────────────────────────────────────────── -->
        <div class="op-card">
            <?php if (empty($users)): ?>
                <div class="op-empty">No users found matching your criteria.</div>
            <?php else: ?>
                <table class="op-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Team</th>
                            <th>Tournaments</th>
                            <th>Joined</th>
                            <th>Change Role</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u):
                            $isSelf   = ((int)$u['id'] === (int)$_SESSION['user_id']);
                            $roleStyle = [
                                'admin'     => 'adm-badge--admin',
                                'organizer' => 'op-badge--open',
                                'player'    => 'op-badge--done',
                            ][$u['user_type']] ?? 'op-badge--done';
                        ?>
                        <tr data-id="<?= $u['id'] ?>">
     
                            <td>
                                <div class="adm-user-cell">
                                    <div class="adm-u-avatar <?= $u['user_type'] === 'admin' ? 'adm-u-avatar--admin' : '' ?>">
                                        <?= strtoupper(substr($u['username'], 0, 2)) ?>
                                </div>
                                <div>
                                    <div class="op-td-name">
                                        <?= htmlspecialchars($u['username']) ?>
                                        <?php if ($isSelf): ?>
                                            <span class="adm-you-badge">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="op-td-sub"><?= htmlspecialchars($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
     
                        <td>
                            <span class="op-badge <?= $roleStyle ?>">
                                <?= ucfirst($u['user_type']) ?>
                            </span>
                        </td>
     
                        <td class="op-td-muted">
                            <?= $u['team_name'] ? htmlspecialchars($u['team_name']) : '—' ?>
                        </td>
     
                        <td class="op-td-muted">
                            <?= $u['tournament_count'] > 0
                                ? '<span style="color:var(--accent)">' . $u['tournament_count'] . '</span>'
                                : '—' ?>
                        </td>
     
                        <td class="op-td-muted">
                            <?= $u['registered_at'] ? date('d M Y', strtotime($u['registered_at'])) : '—' ?>
                        </td>
     
                        <!-- Rol değiştir -->
                        <td>
                            <?php if (!$isSelf): ?>
                                <select
                                    class="adm-role-select"
                                    data-prev="<?= $u['user_type'] ?>"
                                    onchange="changeUserRole(<?= $u['id'] ?>, this.value, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', this)"
                            >
                                <option value="player"    <?= $u['user_type'] === 'player'    ? 'selected' : '' ?>>Player</option>
                                <option value="organizer" <?= $u['user_type'] === 'organizer' ? 'selected' : '' ?>>Organizer</option>
                                <option value="admin"     <?= $u['user_type'] === 'admin'     ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?php else: ?>
                                <span class="op-td-muted" style="font-size:11px">—</span>
                            <?php endif; ?>
                        </td>
     
                        <!-- Sil -->
                        <td>
                            <?php if (!$isSelf): ?>
                                <button
                                    class="adm-btn-danger"
                                    onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                                >Delete</button>
                            <?php endif; ?>
                        </td>
     
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
     
    <!-- ─── SAYFALAMA ──────────────────────────────────────────────────────── -->
    <?php if ($totalPages > 1): ?>
    <div class="adm-pagination animate-in" style="--delay: 200ms;">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $href = '?' . http_build_query(array_filter(['search' => $search, 'role' => $roleFilter, 'page' => $i]));
        ?>
            <a href="<?= $href ?>" class="adm-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <span class="adm-page-info">
            <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $totalUsers) ?>
            of <?= number_format($totalUsers) ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<?php 
    $pageJs = 'users.js';
    require_once __DIR__ . '/layout-bottom.php'; 
?>
