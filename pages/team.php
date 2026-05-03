<?php

require_once '../includes/session.php';

if (!isLoggedIn()) {
    header('Location: ../pages/index.php?modal=login');
    exit;
}

$userId = $_SESSION['user_id'];
$feedback = '';
$feedbackType = '';

// ─── Kullanıcının takımını çek ───────────────────────────────────
try {
    $stmtMyTeam = $pdo->prepare("
        SELECT t.*, p2.username AS captain_name, p2.id AS captain_id_val
        FROM Team t
        JOIN Player p ON p.team_id = t.id
        LEFT JOIN Player p2 ON p2.id = t.captain_id
        WHERE p.id = ? AND t.deleted_at IS NULL
    ");
    $stmtMyTeam->execute([$userId]);
    $team = $stmtMyTeam->fetch();
} catch (Exception $e) {
    $team = null;
}

$isCaptain = $team && isset($team['captain_id']) && $team['captain_id'] == $userId;
$hasTeam   = (bool) $team;

// ─── Avatar upload yardımcı fonksiyon ───────────────────────────
function handleAvatarUpload(array $file, int $teamId): ?string {
    if (empty($file['name'])) return null;

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowedTypes)) return null;
    if ($file['size'] > $maxSize) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $uploadDir = __DIR__ . '/../assets/uploads/teams/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'team_' . $teamId . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return '../assets/uploads/teams/' . $filename;
    }
    return null;
}

// ─── POST İşlemleri ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Takım oluştur ─────────────────────────────────────────
    if ($action === 'create_team') {
        $name   = trim($_POST['name'] ?? '');
        $tag    = strtoupper(trim($_POST['tag'] ?? ''));
        $game   = trim($_POST['game'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if (empty($name) || empty($tag) || empty($game)) {
            $feedback = 'Team name, tag and game are required.';
            $feedbackType = 'error';
        } elseif (strlen($tag) < 2 || strlen($tag) > 6) {
            $feedback = 'Tag must be between 2-6 characters.';
            $feedbackType = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                // Aynı takım adı var mı?
                $dupCheck = $pdo->prepare("SELECT id FROM Team WHERE name = ? AND deleted_at IS NULL");
                $dupCheck->execute([$name]);
                if ($dupCheck->fetch()) {
                    $pdo->rollBack();
                    $feedback = 'A team with this name already exists.';
                    $feedbackType = 'error';
                } else {
                    // Önce takımı oluştur (avatar için ID lazım)
                    $invCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

                    $pdo->prepare("
                        INSERT INTO Team (name, tag, game, region, description, captain_id, invitation_code)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$name, $tag, $game, $region, $desc, $userId, $invCode]);

                    $newTeamId = (int) $pdo->lastInsertId();

                    // Avatar varsa yükle
                    if (!empty($_FILES['avatar']['name'])) {
                        $logoUrl = handleAvatarUpload($_FILES['avatar'], $newTeamId);
                        if ($logoUrl) {
                            $pdo->prepare("UPDATE Team SET logo_url = ? WHERE id = ?")
                                ->execute([$logoUrl, $newTeamId]);
                        }
                    }

                    $pdo->prepare("UPDATE Player SET team_id = ? WHERE id = ?")->execute([$newTeamId, $userId]);
                    $pdo->commit();
                    header('Location: team.php?created=1');
                    exit;
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback = 'An error occurred while creating the team: ' . $e->getMessage();
                $feedbackType = 'error';
            }
        }
    }

    // ── Takım düzenle ─────────────────────────────────────────
    if ($action === 'update_team' && $isCaptain) {
        $name   = trim($_POST['name'] ?? '');
        $tag    = strtoupper(trim($_POST['tag'] ?? ''));
        $game   = trim($_POST['game'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if (empty($name) || empty($tag)) {
            $feedback = 'Team name and tag are required.';
            $feedbackType = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                // Avatar yükle
                $logoUrl = null;
                if (!empty($_FILES['avatar']['name'])) {
                    $logoUrl = handleAvatarUpload($_FILES['avatar'], $team['id']);
                }

                if ($logoUrl) {
                    $pdo->prepare("
                        UPDATE Team SET name=?, tag=?, game=?, region=?, description=?, logo_url=?
                        WHERE id=? AND captain_id=?
                    ")->execute([$name, $tag, $game, $region, $desc, $logoUrl, $team['id'], $userId]);
                } else {
                    $pdo->prepare("
                        UPDATE Team SET name=?, tag=?, game=?, region=?, description=?
                        WHERE id=? AND captain_id=?
                    ")->execute([$name, $tag, $game, $region, $desc, $team['id'], $userId]);
                }

                $pdo->commit();
                header('Location: team.php?updated=1');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback = 'An error occurred while updating the team.';
                $feedbackType = 'error';
            }
        }
    }

    // ── Avatar sil ────────────────────────────────────────────
    if ($action === 'remove_avatar' && $isCaptain) {
        try {
            if (!empty($team['logo_url'])) {
                $filePath = __DIR__ . '/../' . ltrim($team['logo_url'], './');
                if (file_exists($filePath)) unlink($filePath);
            }
            $pdo->prepare("UPDATE Team SET logo_url = NULL WHERE id = ? AND captain_id = ?")
                ->execute([$team['id'], $userId]);
            header('Location: team.php?updated=1');
            exit;
        } catch (Exception $e) {
            $feedback = 'Could not remove avatar.';
            $feedbackType = 'error';
        }
    }

    // ── Üye davet et ──────────────────────────────────────────
    if ($action === 'invite' && $isCaptain) {
        $inviteUser = trim($_POST['invite_username'] ?? '');
        if (empty($inviteUser)) {
            $feedback = 'Username cannot be empty.';
            $feedbackType = 'error';
        } else {
            try {
                $stmtFind = $pdo->prepare("SELECT id, team_id FROM Player WHERE username = ? AND deleted_at IS NULL");
                $stmtFind->execute([$inviteUser]);
                $target = $stmtFind->fetch();

                if (!$target) {
                    $feedback = 'User not found.';
                    $feedbackType = 'error';
                } elseif ($target['team_id']) {
                    $feedback = 'This user is already in a team.';
                    $feedbackType = 'error';
                } else {
                    $count = $pdo->prepare("SELECT COUNT(*) FROM Player WHERE team_id = ?");
                    $count->execute([$team['id']]);
                    if ((int)$count->fetchColumn() >= 6) {
                        $feedback = 'Team is full (max 6 members).';
                        $feedbackType = 'error';
                    } else {
                        $pdo->prepare("UPDATE Player SET team_id = ? WHERE id = ?")->execute([$team['id'], $target['id']]);
                        $feedback = htmlspecialchars($inviteUser) . ' added to the team!';
                        $feedbackType = 'success';
                        $stmtMyTeam->execute([$userId]);
                        $team = $stmtMyTeam->fetch();
                        $isCaptain = $team && $team['captain_id'] == $userId;
                    }
                }
            } catch (Exception $e) {
                $feedback = 'An error occurred.';
                $feedbackType = 'error';
            }
        }
    }

    // ── Üye çıkar ─────────────────────────────────────────────
    if ($action === 'kick' && $isCaptain) {
        $kickId = (int)($_POST['kick_id'] ?? 0);
        if ($kickId && $kickId !== $userId) {
            try {
                $pdo->prepare("UPDATE Player SET team_id = NULL WHERE id = ? AND team_id = ?")
                    ->execute([$kickId, $team['id']]);
                header('Location: team.php');
                exit;
            } catch (Exception $e) {
                $feedback = 'An error occurred.';
                $feedbackType = 'error';
            }
        }
    }

    // ── Takımdan ayrıl ────────────────────────────────────────
    if ($action === 'leave') {
        try {
            if ($isCaptain) {
                $stmtOther = $pdo->prepare("SELECT id FROM Player WHERE team_id = ? AND id != ? LIMIT 1");
                $stmtOther->execute([$team['id'], $userId]);
                $nextCaptain = $stmtOther->fetch();
                if ($nextCaptain) {
                    $pdo->prepare("UPDATE Team SET captain_id = ? WHERE id = ?")->execute([$nextCaptain['id'], $team['id']]);
                } else {
                    $pdo->prepare("UPDATE Team SET deleted_at = NOW() WHERE id = ?")->execute([$team['id']]);
                }
            }
            $pdo->prepare("UPDATE Player SET team_id = NULL WHERE id = ?")->execute([$userId]);
            header('Location: team.php');
            exit;
        } catch (Exception $e) {
            $feedback = 'An error occurred while leaving.';
            $feedbackType = 'error';
        }
    }
}

// URL bildirimleri
if (isset($_GET['updated'])) {
    $feedback = 'Team information updated.';
    $feedbackType = 'success';
    $stmtMyTeam->execute([$userId]);
    $team = $stmtMyTeam->fetch();
    $isCaptain = $team && $team['captain_id'] == $userId;
}
if (isset($_GET['created'])) {
    $feedback = 'Team created successfully!';
    $feedbackType = 'success';
    $stmtMyTeam->execute([$userId]);
    $team = $stmtMyTeam->fetch();
    $isCaptain = $team && $team['captain_id'] == $userId;
    $hasTeam = (bool) $team;
}

// ─── Ekstra veriler ─────────────────────────────────────────────
$members     = [];
$Tournaments = [];
$stats       = ['matches' => 0, 'wins' => 0, 'Tournaments' => 0];

if ($hasTeam) {
    try {
        $stmtMembers = $pdo->prepare("
            SELECT id, username, role
            FROM Player
            WHERE team_id = ? AND deleted_at IS NULL
            ORDER BY (id = ?) DESC, username ASC
        ");
        $stmtMembers->execute([$team['id'], $team['captain_id']]);
        $members = $stmtMembers->fetchAll();
    } catch (Exception $e) { $members = []; }

    try {
        $stmtT = $pdo->prepare("
            SELECT t.id, t.name, t.status, g.name AS game_name, t.start_date
            FROM tournament_teams tt
            JOIN Tournament t ON t.id = tt.tournament_id AND t.deleted_at IS NULL
            LEFT JOIN Game g ON g.id = t.game_id
            WHERE tt.team_id = ?
            ORDER BY t.start_date DESC
            LIMIT 5
        ");
        $stmtT->execute([$team['id']]);
        $Tournaments = $stmtT->fetchAll();
    } catch (Exception $e) { $Tournaments = []; }

    try {
        $stmtStats = $pdo->prepare("
            SELECT
                COUNT(*) AS matches,
                SUM(
                    (home_team_id = :tid AND score_team1 > score_team2) OR
                    (away_team_id = :tid AND score_team2 > score_team1)
                ) AS wins
            FROM Matches
            WHERE (home_team_id = :tid OR away_team_id = :tid)
              AND score_team1 IS NOT NULL
              AND deleted_at IS NULL
        ");
        $stmtStats->execute([':tid' => $team['id']]);
        $s = $stmtStats->fetch();
        $stats['matches']     = (int)$s['matches'];
        $stats['wins']        = (int)$s['wins'];
        $stats['Tournaments'] = count($Tournaments);
    } catch (Exception $e) {}
}

// Oyun listesi
$games = [];
try {
    $games = $pdo->query("SELECT name FROM Game ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {die("A database error occurred while downloading the games: " . $e->getMessage());}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team — Ikarus</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/utils.css">
    <link rel="stylesheet" href="../assets/css/team.css">
    <style>
        /* ── Avatar Upload Styles ── */
        .tm-avatar-wrap {
            position: relative;
            width: 72px;
            height: 72px;
            flex-shrink: 0;
        }
        .tm-avatar-img {
            width: 72px;
            height: 72px;
            border-radius: 14px;
            object-fit: cover;
            border: 1px solid rgba(72,159,181,0.25);
        }
        .tm-avatar-edit-btn {
            position: absolute;
            bottom: -6px;
            right: -6px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--accent);
            color: var(--bg);
            border: 2px solid var(--surface);
            font-size: 11px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background var(--transition);
        }
        .tm-avatar-edit-btn:hover { background: var(--accent-soft); }

        /* Avatar upload field in form */
        .tm-avatar-upload-area {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px;
            border: 1px dashed var(--border);
            border-radius: 10px;
            background: var(--bg);
            cursor: pointer;
            transition: border-color var(--transition);
        }
        .tm-avatar-upload-area:hover { border-color: var(--accent); }
        .tm-avatar-preview {
            width: 56px;
            height: 56px;
            border-radius: 10px;
            background: rgba(72,159,181,0.12);
            border: 1px solid rgba(72,159,181,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
            overflow: hidden;
            flex-shrink: 0;
        }
        .tm-avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .tm-avatar-upload-text {
            flex: 1;
        }
        .tm-avatar-upload-text strong {
            display: block;
            font-size: 13px;
            color: var(--text);
            margin-bottom: 3px;
        }
        .tm-avatar-upload-text span {
            font-size: 11px;
            color: var(--text-muted);
        }
        .tm-avatar-file-input {
            display: none;
        }
        .tm-remove-avatar-btn {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 5px;
            border: 1px solid rgba(220,38,38,0.3);
            background: transparent;
            color: #f87171;
            cursor: pointer;
            transition: background var(--transition);
        }
        .tm-remove-avatar-btn:hover { background: rgba(220,38,38,0.08); }
    </style>
</head>

<body>
<?php require_once '../includes/header.php'; ?>


<main>
<div class="team-page">

<?php if (!empty($feedback)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // PHP'den gelen mesajı ve tipi direkt Toast'a yolla
            showToast(<?= json_encode($feedback) ?>, <?= json_encode($feedbackType) ?>);
            
            // URL'deki ?updated=1 veya ?created=1 gibi takılı kalan parametreleri temizle
            window.history.replaceState({}, document.title, window.location.pathname);
        });
    </script>
<?php endif; ?>

<?php if (!$hasTeam): ?>
<!-- ═══════════════ TAKİM YOK ═══════════════ -->
<div class="tm-empty-wrap">
    <div class="tm-empty-box">
        <div class="tm-empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <h2 class="tm-empty-title">You don't have a team yet</h2>
        <p class="tm-empty-sub">Create a team and start competing in Tournaments.</p>
        <button class="tm-btn-primary" onclick="document.getElementById('createPanel').style.display='block';this.style.display='none'">
            + Create Team
        </button>

        <div id="createPanel" style="display:none; margin-top:28px; text-align:left;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_team">

                <!-- Avatar Upload -->
                <div style="margin-bottom:16px;">
                    <label class="tm-label" style="display:block; margin-bottom:8px;">Team Avatar</label>
                    <label class="tm-avatar-upload-area" for="create-avatar-input">
                        <div class="tm-avatar-preview" id="create-avatar-preview">🎮</div>
                        <div class="tm-avatar-upload-text">
                            <strong>Upload team logo</strong>
                            <span>PNG, JPG, WEBP — max 2MB</span>
                        </div>
                    </label>
                    <input class="tm-avatar-file-input" type="file" id="create-avatar-input" name="avatar"
                           accept="image/png,image/jpeg,image/webp,image/gif"
                           onchange="previewAvatar(this, 'create-avatar-preview')">
                </div>

                <div class="tm-form-grid">
                    <div class="tm-field">
                        <label class="tm-label">Team Name *</label>
                        <input class="tm-input" type="text" name="name" placeholder="NightFall" required>
                    </div>
                    <div class="tm-field">
                        <label class="tm-label">Tag * (2-6 chars)</label>
                        <input class="tm-input" type="text" name="tag" placeholder="NF" maxlength="6" minlength="2" required style="text-transform:uppercase">
                    </div>
                    <div class="tm-field">
                        <label class="tm-label">Game *</label>
                        <select class="tm-select" name="game" required>
                            <option value="">Choose...</option>
                            <?php foreach ($games as $g): ?>
                                <option value="<?= htmlspecialchars($g['name']) ?>"><?= htmlspecialchars($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tm-field">
                        <label class="tm-label">Region</label>
                        <input class="tm-input" type="text" name="region" placeholder="Turkey">
                    </div>
                    <div class="tm-field tm-field--full">
                        <label class="tm-label">Description</label>
                        <textarea class="tm-textarea" name="description" rows="2" placeholder="Tell us about your team..."></textarea>
                    </div>
                </div>
                <div class="tm-form-actions">
                    <button type="button" class="tm-btn-ghost" onclick="document.getElementById('createPanel').style.display='none'">Cancel</button>
                    <button type="submit" class="tm-btn-primary">Create Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════ TAKİM VAR ═══════════════ -->

<!-- HEADER -->
<div class="tm-header">
    <!-- Avatar -->
    <div class="tm-avatar-wrap">
        <?php if (!empty($team['logo_url'])): ?>
            <img class="tm-avatar-img" src="<?= htmlspecialchars($team['logo_url']) ?>"
                 alt="<?= htmlspecialchars($team['name']) ?>">
        <?php else: ?>
            <div class="tm-avatar">
                <?= htmlspecialchars(strtoupper(substr($team['tag'] ?? $team['name'], 0, 2))) ?>
            </div>
        <?php endif; ?>
        <?php if ($isCaptain): ?>
            <button class="tm-avatar-edit-btn" title="Change avatar" onclick="togglePanel('editPanel')">✏</button>
        <?php endif; ?>
    </div>

    <div class="tm-info">
        <div class="tm-name"><?= htmlspecialchars($team['name'] ?? '') ?></div>
        <div class="tm-tag">
            #<?= htmlspecialchars($team['tag'] ?? '') ?>
            <?php if (!empty($team['game'])): ?> · <?= htmlspecialchars($team['game']) ?><?php endif; ?>
        </div>
        <?php if (!empty($team['description'])): ?>
            <div class="tm-desc"><?= htmlspecialchars($team['description']) ?></div>
        <?php endif; ?>
        <div class="tm-meta">
            <?php if (!empty($team['region'])): ?>
                <div class="tm-meta-item">Region <span><?= htmlspecialchars($team['region']) ?></span></div>
            <?php endif; ?>
            <div class="tm-meta-item">Members <span><?= count($members) ?> / 6</span></div>
            
            <!-- DAVET KODU VE KOPYALA BUTONU BURADA -->
            <?php if (!empty($team['invitation_code'])): ?>
                <div class="tm-meta-item" style="display: flex; align-items: center; gap: 8px;">
                    Invite Code 
                    <span id="inviteCodeText" style="color:var(--accent); letter-spacing:2px; font-weight:bold;">
                        <?= htmlspecialchars($team['invitation_code']) ?>
                    </span>
                    <button type="button" onclick="copyInviteCode()" title="Copy Code" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; padding:2px; display:flex;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
            <!-- DAVET KODU SONU -->

        </div>
    </div>

    <div class="tm-actions">
        <?php if ($isCaptain): ?>
            <button class="tm-btn-primary" onclick="togglePanel('editPanel')">Edit Team</button>
            <button class="tm-btn-outline" onclick="togglePanel('invitePanel')">Invite Member</button>
        <?php endif; ?>
        <button class="tm-btn-danger" onclick="confirmLeave()">Leave Team</button>
    </div>
</div>

<!-- STATS -->
<div class="tm-stats">
    <div class="tm-stat">
        <div class="tm-stat-label">Total Matches</div>
        <div class="tm-stat-val"><?= $stats['matches'] ?></div>
    </div>
    <div class="tm-stat">
        <div class="tm-stat-label">Wins</div>
        <div class="tm-stat-val"><?= $stats['wins'] ?></div>
        <?php if ($stats['matches'] > 0): ?>
            <div class="tm-stat-sub">%<?= round($stats['wins'] / $stats['matches'] * 100) ?> win rate</div>
        <?php endif; ?>
    </div>
    <div class="tm-stat">
        <div class="tm-stat-label">Tournaments</div>
        <div class="tm-stat-val"><?= $stats['Tournaments'] ?></div>
    </div>
    <div class="tm-stat">
        <div class="tm-stat-label">Members</div>
        <div class="tm-stat-val"><?= count($members) ?></div>
        <div class="tm-stat-sub">Max 6</div>
    </div>
</div>

<!-- DAVET PANELİ -->
<?php if ($isCaptain): ?>
<div id="invitePanel" class="tm-panel tm-panel--accent" style="display:none">
    <div class="tm-panel-title">Invite Member</div>
    <form id="inviteTeamForm" method="POST" action="team.php" class="tm-invite-form">
    <input type="hidden" name="action" value="invite">
        <input class="tm-input" type="text" name="invite_username" placeholder="Username" required>
        <button type="submit" class="tm-btn-primary">Add</button>
    </form>
    <div class="tm-panel-note">Current members: <?= count($members) ?>/6</div>
</div>

<!-- DÜZENLEME PANELİ -->
<div id="editPanel" class="tm-panel" style="display:none">
    <div class="tm-panel-title">Edit Team</div>
    <form id="editTeamForm" method="POST" action="team.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_team">

        <!-- Avatar Upload -->
        <div style="margin-bottom:16px;">
            <label class="tm-label" style="display:block; margin-bottom:8px;">Team Avatar</label>
            <label class="tm-avatar-upload-area" for="edit-avatar-input">
                <div class="tm-avatar-preview" id="edit-avatar-preview">
                    <?php if (!empty($team['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($team['logo_url']) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars(strtoupper(substr($team['tag'] ?? $team['name'], 0, 2))) ?>
                    <?php endif; ?>
                </div>
                <div class="tm-avatar-upload-text">
                    <strong>Change team logo</strong>
                    <span>PNG, JPG, WEBP — max 2MB. Leave empty to keep current.</span>
                </div>
            </label>
            <input class="tm-avatar-file-input" type="file" id="edit-avatar-input" name="avatar"
                   accept="image/png,image/jpeg,image/webp,image/gif"
                   onchange="previewAvatar(this, 'edit-avatar-preview')">
            <?php if (!empty($team['logo_url'])): ?>
                <div style="margin-top:8px;">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="remove_avatar">
                        <button type="submit" class="tm-remove-avatar-btn" onclick="return confirm('Remove team avatar?')">✕ Remove current avatar</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="tm-form-grid">
            <div class="tm-field">
                <label class="tm-label">Team Name</label>
                <input class="tm-input" type="text" name="name" value="<?= htmlspecialchars($team['name'] ?? '') ?>" required>
            </div>
            <div class="tm-field">
                <label class="tm-label">Tag</label>
                <input class="tm-input" type="text" name="tag" maxlength="6" value="<?= htmlspecialchars($team['tag'] ?? '') ?>" required style="text-transform:uppercase">
            </div>
            <div class="tm-field">
                <label class="tm-label">Game</label>
                <select class="tm-select" name="game">
                    <option value="">Choose...</option>
                    <?php foreach ($games as $g): ?>
                        <option value="<?= htmlspecialchars($g['name']) ?>"
                            <?= ($g['name'] === ($team['game'] ?? '')) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tm-field">
                <label class="tm-label">Region</label>
                <input class="tm-input" type="text" name="region" value="<?= htmlspecialchars($team['region'] ?? '') ?>">
            </div>
            <div class="tm-field tm-field--full">
                <label class="tm-label">Description</label>
                <textarea class="tm-textarea" name="description" rows="2"><?= htmlspecialchars($team['description'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="tm-form-actions">
            <button type="button" class="tm-btn-ghost" onclick="togglePanel('editPanel')">Cancel</button>
            <button type="submit" class="tm-btn-primary">Save Changes</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- İKİ KOLON: ÜYELER + TURNUVALAR -->
<div class="tm-two-col">

    <!-- ÜYELER -->
    <div class="tm-card">
        <div class="tm-card-head">
            <span class="tm-card-title">Members</span>
            <span class="op-td-muted" style="font-size:12px;"><?= count($members) ?>/6</span>
        </div>

        <?php foreach ($members as $m):
            $isMemberCaptain = ($m['id'] == $team['captain_id']);
        ?>
            <div class="tm-member-row">
                <div class="tm-m-avatar <?= $isMemberCaptain ? 'tm-m-avatar--captain' : '' ?>">
                    <?= strtoupper(substr($m['username'], 0, 2)) ?>
                </div>
                <div class="tm-m-info">
                    <div class="tm-m-name">
                        <?= htmlspecialchars($m['username']) ?>
                        <?php if ($m['id'] == $userId): ?><span class="tm-you">You</span><?php endif; ?>
                    </div>
                    <?php if (!empty($m['role'])): ?>
                        <div class="tm-m-role"><?= htmlspecialchars($m['role']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($isMemberCaptain): ?>
                    <span class="tm-badge tm-badge--captain">Captain</span>
                <?php else: ?>
                    <span class="tm-badge tm-badge--member">Member</span>
                    <?php if ($isCaptain): ?>
                        <form method="POST" class="tm-kick-form"
                              onsubmit="return confirm('Remove <?= htmlspecialchars($m['username']) ?> from team?')">
                            <input type="hidden" name="action" value="kick">
                            <input type="hidden" name="kick_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="tm-btn-kick">Remove</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TURNUVALAR -->
    <div class="tm-card">
        <div class="tm-card-head">
            <span class="tm-card-title">Tournaments</span>
            <a href="Tournaments.php" class="tm-card-link">All →</a>
        </div>

        <?php if (empty($Tournaments)): ?>
            <div class="tm-empty-row">You haven't entered any Tournaments yet.</div>
        <?php else: ?>
            <?php foreach ($Tournaments as $t):
                $statusMap = [
                    'live'         => ['dot' => 'tm-dot--live',     'label' => 'Live',         'cls' => 'tm-result--ongoing'],
                    'registration' => ['dot' => 'tm-dot--upcoming', 'label' => 'Registration', 'cls' => 'tm-result--soon'],
                    'upcoming'     => ['dot' => 'tm-dot--upcoming', 'label' => 'Upcoming',     'cls' => 'tm-result--soon'],
                    'finished'     => ['dot' => 'tm-dot--done',     'label' => 'Finished',     'cls' => ''],
                ];
                $st = $statusMap[$t['status']] ?? ['dot' => 'tm-dot--done', 'label' => $t['status'], 'cls' => ''];
            ?>
            <div class="tm-tournament-row">
                <div class="tm-dot <?= $st['dot'] ?>"></div>
                <div class="tm-t-info">
                    <div class="tm-t-name"><?= htmlspecialchars($t['name']) ?></div>
                    <div class="tm-t-meta"><?= htmlspecialchars($t['game_name'] ?? '') ?> · <?= date('M Y', strtotime($t['start_date'])) ?></div>
                </div>
                <span class="tm-result <?= $st['cls'] ?>"><?= $st['label'] ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Gizli ayrılma formu -->
<form id="leaveForm" method="POST" style="display:none">
    <input type="hidden" name="action" value="leave">
</form>

<?php endif; ?>
</div>
</main>

  <div id="toast" class="tm-toast"></div>

<?php require_once '../includes/footer.php'; ?>
<!-- ÖZEL ONAY (CONFIRM) KUTUSU -->
<div id="customConfirmOverlay" class="tm-modal-overlay" style="display: none;">
    <div class="tm-modal-box">
        <div class="tm-modal-title" id="customConfirmTitle">Confirm Action</div>
        <div id="customConfirmMessage" class="tm-modal-message">Are you sure?</div>
        <div class="tm-modal-actions">
            <button type="button" class="tm-btn-ghost" onclick="closeCustomConfirm()">Cancel</button>
            <button type="button" id="customConfirmBtn" class="tm-btn-danger">Confirm</button>
        </div>
    </div>
</div>

<script>
function togglePanel(id) {
    ['editPanel','invitePanel'].forEach(p => {
        if (p !== id) {
            const el = document.getElementById(p);
            if (el) el.style.display = 'none';
        }
    });
    const target = document.getElementById(id);
    if (target) {
        target.style.display = target.style.display === 'none' ? 'block' : 'none';
        if (target.style.display === 'block') {
            target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

// --- ÖZEL ONAY KUTUSU (CUSTOM CONFIRM) SİSTEMİ ---
let currentConfirmAction = null;
let currentConfirmBtn = null;

function showCustomConfirm(title, message, btnText, actionCallback, sourceBtn = null) {
    document.getElementById('customConfirmTitle').innerText = title;
    document.getElementById('customConfirmMessage').innerText = message;
    
    const confirmBtn = document.getElementById('customConfirmBtn');
    confirmBtn.innerText = btnText;
    
    // İşlemi hafızaya al
    currentConfirmAction = actionCallback;
    currentConfirmBtn = sourceBtn;
    
    // Kutuyu göster
    const overlay = document.getElementById('customConfirmOverlay');
    overlay.style.display = 'flex';
    setTimeout(() => overlay.classList.add('show'), 10); // Animasyon için ufak gecikme
}

function closeCustomConfirm() {
    const overlay = document.getElementById('customConfirmOverlay');
    overlay.classList.remove('show');
    setTimeout(() => overlay.style.display = 'none', 300);
}

// Modal içindeki onay butonuna tıklanınca
document.getElementById('customConfirmBtn').addEventListener('click', function() {
    if (currentConfirmAction) {
        currentConfirmAction(currentConfirmBtn, this);
    }
});

// --- TAKIMDAN AYRILMA (LEAVE TEAM) İŞLEMİ ---
function confirmLeave(btn) {
    showCustomConfirm(
        'Leave Team', 
        'Are you sure you want to leave this team? This action cannot be undone.', 
        'Leave', 
        function(originalBtn, modalBtn) {
            
            // Modal içindeki butonu "Leaving..." yapıp dondur
            modalBtn.innerText = 'Leaving...';
            modalBtn.disabled = true;
            modalBtn.style.opacity = '0.7';
            modalBtn.style.cursor = 'not-allowed';
            
            // 1 saniyelik şık bekleme süresinden sonra formu gönder
            setTimeout(() => {
                document.getElementById('leaveForm').submit();
            }, 1000);
        },
        btn
    );
}

function previewAvatar(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview || !input.files || !input.files[0]) return;

    const file = input.files[0];
    if (!file.type.startsWith('image/')) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
    };
    reader.readAsDataURL(file);
}

// Auto-hide success alerts
document.addEventListener('DOMContentLoaded', () => {
    
    // Tag input auto-uppercase
    document.querySelectorAll('input[name="tag"]').forEach(inp => {
        inp.addEventListener('input', () => {
            inp.value = inp.value.toUpperCase();
        });
    });

    // --- "Save Changes" Fake Delay Animasyonu ---
    const editForm = document.getElementById('editTeamForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            const submitBtn = editForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerText = 'Saving...';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'not-allowed';
            }
            setTimeout(() => { editForm.submit(); }, 1500);
        });
    }

    // --- "Invite Member" Fake Delay Animasyonu ---
    const inviteForm = document.getElementById('inviteTeamForm');
    if (inviteForm) {
        inviteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = inviteForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerText = 'Inviting...'; // Davet ediliyor...
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'not-allowed';
            }
            setTimeout(() => { inviteForm.submit(); }, 500); // 0.5 saniye gecikme
        });
    }
});

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    // Önce varsa eski sınıfları temizle
    toast.className = 'tm-toast';
    
    // Mesajı ve tipi ayarla
    toast.innerText = message;
    toast.classList.add('tm-toast--' + type);

    // Görünür yap (Küçük bir delay tarayıcının render etmesini sağlar)
    setTimeout(() => {
        toast.classList.add('tm-toast--show');
    }, 50);

    // 3 saniye sonra kapat
    setTimeout(() => {
        toast.classList.remove('tm-toast--show');
    }, 3000);
}
function copyInviteCode() {
    const codeEl = document.getElementById('inviteCodeText');
    if (!codeEl) return;
    
    const code = codeEl.innerText.trim();
    
    navigator.clipboard.writeText(code).then(() => {
        showToast('Code copied: ' + code, 'success');
    }).catch(err => {
        console.error('Copy error:', err);
        alert('Code: ' + code); 
    });
}
const editForm = document.getElementById('editTeamForm');
if (editForm) {
    editForm.addEventListener('submit', function(e) {
        e.preventDefault(); // Formun hemen gitmesini engelle

        // Gönder butonunu bul
        const submitBtn = editForm.querySelector('button[type="submit"]');

        // Butonu pasifleştir ve yazısını değiştir
        submitBtn.innerText = 'Saving...'; // İstersen 'Kaydediliyor...' yap
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        submitBtn.style.cursor = 'not-allowed';

        // 1.5 saniye (1500ms) bekleyip formu manuel olarak gönder
        setTimeout(() => {
            editForm.submit();
        }, 1500);
    });
}

</script>
</body>
</html>