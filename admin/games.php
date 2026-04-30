<?php
    require_once __DIR__ . '/guard.php';

    //AJAX ve POST islemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');

        enforceAjaxCsrf();

        $action = $_POST['action'] ?? '';

        //Oyun ekle
        if ($action === 'add_game') {
            $name = trim($_POST['name'] ?? '');
            $genre = trim($_POST['genre'] ?? '');
            $maxTeamSize = (int)($_POST['max_team_size'] ?? 5);

            if (empty($name)) {
                echo json_encode(['status' => 'error', 'message' => 'Game name is required.']);
                exit;
            }
            if ($maxTeamSize < 1 || $maxTeamSize > 20) {
                echo json_encode(['status' => 'error', 'message' => 'Max team size must be between 1 and 20.']);
                exit;
            }

            try {
                //Ayni isimde oyun varmi kontrolu
                $check = $pdo->prepare("SELECT id FROM Game WHERE name = ?");
                $check->execute([$name]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'A game with this name already exist.']);
                    exit;
                }

                $pdo->prepare("
                    INSERT INTO Game (name, genre, max_team_size)
                    VALUES (?, ?, ?)
                ")->execute([$name, $genre, $maxTeamSize]);

                $newId = (int)$pdo->lastInsertId();
                echo json_encode([
                    'status' => 'success',
                    'message' => "\"$name\" added successfully.",
                    'id' => $newId,
                    'genre' => $genre ?: '-',
                    'max_team_size' => $maxTeamSize,
                ]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Database error.']);
            }
            exit;
        }

        //Oyun duzenleme
        if ($action === 'edit_game') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $genre = trim($_POST['genre'] ?? '');
            $maxTeamSize = (int)($_POST['max_team_size'] ?? 5);

            if (!$id || empty($name)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
                exit;
            }
            if ($maxTeamSize < 1 || $maxTeamSize > 20) {
                echo json_encode(['status' => 'error', 'message' => 'Max team size must be between 1 and 20']);
                exit;
            }

            try {
                //Baska oyun bu isimdemi kontrolu
                $check = $pdo->prepare("SELECT id FROM Game WHERE name = ? AND id != ?");
                $check->execute([$name, $id]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Another game with this name already exists.']);
                    exit;
                }
                $pdo->prepare("
                    UPDATE Game SET name = ?, genre = ?, max_team_size = ? WHERE id = ?
                ")->execute([$name, $genre, $maxTeamSize, $id]);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Game updated successfully',
                    'name' => $name,
                    'genre' => $genre ?: '-',
                    'max_team_size' => $maxTeamSize,
                ]);
            } catch (Exception $e) {
                echo json_encode(['stauts' => 'error', 'message' => 'Database error']);
            }
            exit;
        }

        //Oyun sil
        if ($action === 'delete_game') {
            $id = (int)($_POST['id'] ?? 0);
            
            if (!$id) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid game ID.']);
                exit;
            }
        
            try {
                // Bu oyuna bağlı aktif turnuva var mı?
                $linked = $pdo->prepare("
                    SELECT COUNT(*) FROM Tournament
                    WHERE game_id = ? AND status IN ('live', 'registration', 'upcoming')
                ");
                $linked->execute([$id]);
                if ((int)$linked->fetchColumn() > 0) {
                    echo json_encode([
                        'status'  => 'error',
                        'message' => 'Cannot delete: this game has active tournaments linked to it.',
                    ]);
                    exit;
                }
            
                $pdo->prepare("UPDATE Game SET deleted_at WHERE id = ?")->execute([$id]);
                echo json_encode(['status' => 'success', 'message' => 'Game deleted.']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Database error.']);
            }
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
        exit;
    }

    // ─── Oyun Listesi ─────────────────────────────────────────────────────────
    try {
        $games = $pdo->query("
            SELECT
                g.id,
                g.name,
                g.genre,
                g.max_team_size,
                g.created_at,
                COUNT(DISTINCT t.id)                                            AS total_tournaments,
                COUNT(DISTINCT CASE WHEN t.status IN ('live','registration','upcoming') THEN t.id END) AS active_tournaments
            FROM Game g
            LEFT JOIN Tournament t ON t.game_id = g.id AND t.deleted_at IS NULL
            GROUP BY g.id
            ORDER BY g.name ASC
        ")->fetchAll();
    } catch (Exception $e) {
        $games = [];
    }
    
    $pageTitle    = 'Games';
    $pageSubtitle = count($games) . ' games in the system';
    $pageAction   = ['href' => '#', 'label' => '+ Add Game'];
    
    require_once __DIR__ . '/layout-top.php';
?>

<!-- ─── ADD GAME FORMU ──────────────────────────────────────────────────── -->
<div class="op-card adm-game-form-card" id="addGameCard" style="display:none; margin-bottom:16px;">
    <div class="op-card-head">
        <span class="op-card-title">Add New Game</span>
        <button class="op-btn-sm" onclick="toggleAddForm()">✕ Cancel</button>
    </div>
    <div class="adm-game-form">
        <div class="adm-gf-field">
            <label class="op-label">Game Name *</label>
            <input class="op-input" type="text" id="gf-name" placeholder="e.g. Valorant" maxlength="50">
            <span class="adm-gf-error" id="gf-name-error"></span>
        </div>
        <div class="adm-gf-field">
            <label class="op-label">Genre</label>
            <input class="op-input" type="text" id="gf-genre" placeholder="e.g. FPS, MOBA, Battle Royale" maxlength="50">
        </div>
        <div class="adm-gf-field adm-gf-field--sm">
            <label class="op-label">Max Team Size *</label>
            <input class="op-input" type="number" id="gf-size" value="5" min="1" max="20">
        </div>
        <div class="adm-gf-actions">
            <button class="op-btn adm-btn-primary" onclick="submitAddGame()">Add Game</button>
        </div>
    </div>
</div>
 
<!-- ─── OYUN TABLOSU ────────────────────────────────────────────────────── -->
<div class="op-card" id="gamesCard">
    <?php if (empty($games)): ?>
        <div class="op-empty">
            No games found.
            <button class="op-btn adm-btn-primary" style="margin-top:12px" onclick="toggleAddForm()">
                + Add First Game
            </button>
        </div>
    <?php else: ?>
        <table class="op-table" id="gamesTable">
            <thead>
                <tr>
                    <th>Game</th>
                    <th>Genre</th>
                    <th>Max Team</th>
                    <th>Tournaments</th>
                    <th>Active</th>
                    <th>Added</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($games as $g): ?>
                <tr data-id="<?= $g['id'] ?>">
 
                    <!-- İsim -->
                    <td>
                        <div class="adm-game-cell">
                            <div class="adm-game-icon">
                                <?= strtoupper(substr($g['name'], 0, 2)) ?>
                            </div>
                            <span class="op-td-name adm-game-name"><?= htmlspecialchars($g['name']) ?></span>
                        </div>
                    </td>
 
                    <!-- Genre -->
                    <td class="op-td-muted adm-game-genre">
                        <?= $g['genre'] ? htmlspecialchars($g['genre']) : '—' ?>
                    </td>
 
                    <!-- Max team size -->
                    <td>
                        <span class="adm-team-size-badge adm-game-size">
                            <?= $g['max_team_size'] ?>v<?= $g['max_team_size'] ?>
                        </span>
                    </td>
 
                    <!-- Toplam turnuva -->
                    <td class="op-td-muted">
                        <?= $g['total_tournaments'] > 0
                            ? '<span style="color:var(--text)">' . $g['total_tournaments'] . '</span>'
                            : '—' ?>
                    </td>
 
                    <!-- Aktif turnuva -->
                    <td>
                        <?php if ($g['active_tournaments'] > 0): ?>
                            <span class="op-badge op-badge--live"><?= $g['active_tournaments'] ?> Active</span>
                        <?php else: ?>
                            <span class="op-td-muted">—</span>
                        <?php endif; ?>
                    </td>
 
                    <!-- Eklenme tarihi -->
                    <td class="op-td-muted">
                        <?= $g['created_at'] ? date('d M Y', strtotime($g['created_at'])) : '—' ?>
                    </td>
 
                    <!-- Aksiyonlar -->
                    <td>
                        <div class="op-row-actions" style="opacity:1; gap:6px;">
                            <button
                                class="op-btn-sm op-btn-sm--accent"
                                onclick="openEditRow(<?= $g['id'] ?>)"
                            >Edit</button>
 
                            <?php if ($g['active_tournaments'] == 0): ?>
                                <button
                                    class="adm-btn-danger"
                                    onclick="deleteGame(<?= $g['id'] ?>, '<?= htmlspecialchars($g['name'], ENT_QUOTES) ?>')"
                                >Delete</button>
                            <?php else: ?>
                                <span class="op-td-muted" style="font-size:11px" title="Has active tournaments">Protected</span>
                            <?php endif; ?>
                        </div>
                    </td>
 
                </tr>
 
                <!-- Inline düzenleme satırı (gizli) -->
                <tr class="adm-edit-row" id="edit-row-<?= $g['id'] ?>" style="display:none;">
                    <td colspan="7">
                        <div class="adm-inline-edit">
                            <div class="adm-gf-field">
                                <label class="op-label">Name</label>
                                <input class="op-input" type="text" id="ef-name-<?= $g['id'] ?>"
                                       value="<?= htmlspecialchars($g['name']) ?>" maxlength="50">
                            </div>
                            <div class="adm-gf-field">
                                <label class="op-label">Genre</label>
                                <input class="op-input" type="text" id="ef-genre-<?= $g['id'] ?>"
                                       value="<?= htmlspecialchars($g['genre'] ?? '') ?>" maxlength="50">
                            </div>
                            <div class="adm-gf-field adm-gf-field--sm">
                                <label class="op-label">Max Team</label>
                                <input class="op-input" type="number" id="ef-size-<?= $g['id'] ?>"
                                       value="<?= $g['max_team_size'] ?>" min="1" max="20">
                            </div>
                            <div class="adm-gf-actions">
                                <button class="op-btn-sm" onclick="closeEditRow(<?= $g['id'] ?>)">Cancel</button>
                                <button class="op-btn adm-btn-primary" onclick="submitEditGame(<?= $g['id'] ?>)">Save</button>
                            </div>
                        </div>
                    </td>
                </tr>
 
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
 
<?php
    $pageJs = 'games.js'
?>
 
<?php require_once __DIR__ . '/layout-bottom.php'; ?>