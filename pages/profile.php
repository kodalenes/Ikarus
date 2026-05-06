<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/FileUploader.php';
$extraCss = ['profile'];

requireLogin();

$viewerId = (int)$_SESSION['user_id'];
$profileId = max(1, (int)($_GET['id'] ?? $viewerId));
$isSelf = $profileId === $viewerId;
$flashError = '';
$flashSuccess = isset($_GET['updated']) ? 'Profile updated successfully.' : '';
$shouldOpenEditPanel = false;

function profileInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function profileUploader(): FileUploader {
    return new FileUploader(
        dirname(__DIR__) . '/assets/uploads',
        '/assets/uploads',
        'avatar'
    );
}

$avatarSelect = playerAvatarColumnExists() ? 'p.avatar_url' : 'NULL AS avatar_url';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSelf) {
    $shouldOpenEditPanel = true;
    $username = trim($_POST['username'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');
    $removeAvatar = !empty($_POST['remove_avatar']);

    if ($username === '') {
        $flashError = 'Username is required.';
    } elseif (mb_strlen($username) > 50) {
        $flashError = 'Username is too long.';
    } else {
        try {
            $currentStmt = $pdo->prepare("
                SELECT " . (playerAvatarColumnExists() ? "avatar_url" : "NULL AS avatar_url") . "
                FROM Player
                WHERE id = ? AND deleted_at IS NULL
                LIMIT 1
            ");
            $currentStmt->execute([$viewerId]);
            $currentPlayer = $currentStmt->fetch();

            if (!$currentPlayer) {
                $flashError = 'Player not found.';
            } else {
                $dupStmt = $pdo->prepare("SELECT id FROM Player WHERE username = ? AND id != ? AND deleted_at IS NULL LIMIT 1");
                $dupStmt->execute([$username, $viewerId]);
                if ($dupStmt->fetch()) {
                    $flashError = 'That username is already taken.';
                } else {
                    $avatarUrl = $currentPlayer['avatar_url'];
                    $uploader = profileUploader();

                    if ($removeAvatar && $avatarUrl) {
                        $uploader->delete($avatarUrl);
                        $avatarUrl = null;
                    }

                    if (!empty($_FILES['avatar']['name']) && !playerAvatarColumnExists()) {
                        $flashError = 'Avatar storage requires the avatar_url column to be added to Player.';
                    } elseif (!empty($_FILES['avatar']['name'])) {
                        $newAvatar = $uploader->upload($_FILES['avatar'], 'players', "player_{$viewerId}");
                        if ($uploader->hasErrors()) {
                            $flashError = $uploader->firstError();
                        } elseif ($newAvatar) {
                            if ($avatarUrl) {
                                $uploader->delete($avatarUrl);
                            }
                            $avatarUrl = $newAvatar;
                        }
                    }

                    if ($flashError === '') {
                        $birthDateSql = $birthDate !== '' ? $birthDate : null;
                        if (playerAvatarColumnExists()) {
                            $updateStmt = $pdo->prepare("
                                UPDATE Player
                                SET username = ?, birth_date = ?, avatar_url = ?
                                WHERE id = ? AND deleted_at IS NULL
                            ");
                            $updateStmt->execute([$username, $birthDateSql, $avatarUrl, $viewerId]);
                        } else {
                            $updateStmt = $pdo->prepare("
                                UPDATE Player
                                SET username = ?, birth_date = ?
                                WHERE id = ? AND deleted_at IS NULL
                            ");
                            $updateStmt->execute([$username, $birthDateSql, $viewerId]);
                        }
                        $_SESSION['username'] = $username;
                        header('Location: profile.php?updated=1');
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            $flashError = 'Could not update profile.';
        }
    }
}

$playerStmt = $pdo->prepare("
    SELECT
        p.id,
        p.username,
        p.email,
        p.birth_date,
        p.registered_at,
        p.role,
        p.user_type,
        {$avatarSelect},
        t.id AS team_id,
        t.name AS team_name,
        t.tag AS team_tag,
        t.game AS team_game,
        t.region AS team_region,
        t.logo_url AS team_logo_url,
        cap.username AS captain_name
    FROM Player p
    LEFT JOIN Team t ON t.id = p.team_id AND t.deleted_at IS NULL
    LEFT JOIN Player cap ON cap.id = t.captain_id
    WHERE p.id = ? AND p.deleted_at IS NULL
    LIMIT 1
");
$playerStmt->execute([$profileId]);
$player = $playerStmt->fetch();

if (!$player) {
    http_response_code(404);
    echo 'Profile not found.';
    exit;
}

$customTitle = htmlspecialchars($player['username']);

$teamId = !empty($player['team_id']) ? (int)$player['team_id'] : 0;

$stats = ['matches' => 0, 'wins' => 0, 'active_tournaments' => 0, 'win_rate' => 0];
$matches = [];
$activeTournaments = [];

if ($teamId > 0) {
    try {
        $statsStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS matches,
                COALESCE(SUM(
                    CASE
                        WHEN (team1_id = :team AND score_team1 > score_team2)
                          OR (team2_id = :team AND score_team2 > score_team1)
                        THEN 1 ELSE 0
                    END
                ), 0) AS wins
            FROM Matches
            WHERE (team1_id = :team OR team2_id = :team)
              AND score_team1 IS NOT NULL
              AND deleted_at IS NULL
        ");
        $statsStmt->execute([':team' => $teamId]);
        $stats = $statsStmt->fetch() ?: $stats;
        $stats['win_rate'] = (int)$stats['matches'] > 0
            ? (int)round(((int)$stats['wins'] / (int)$stats['matches']) * 100)
            : 0;

        $tourStmt = $pdo->prepare("
            SELECT
                t.id,
                t.name,
                t.status,
                t.start_date,
                g.name AS game_name
            FROM tournament_teams tt
            JOIN Tournament t ON t.id = tt.tournament_id AND t.deleted_at IS NULL
            LEFT JOIN Game g ON g.id = t.game_id
            WHERE tt.team_id = ?
              AND t.status IN ('live', 'registration', 'upcoming', 'open', 'ongoing')
            ORDER BY t.start_date ASC
            LIMIT 5
        ");
        $tourStmt->execute([$teamId]);
        $activeTournaments = $tourStmt->fetchAll();
        $stats['active_tournaments'] = count($activeTournaments);

        $matchStmt = $pdo->prepare("
            SELECT
                m.id,
                m.date,
                m.stage,
                m.score_team1,
                m.score_team2,
                m.team1_id,
                m.team2_id,
                t1.name AS team1_name,
                t2.name AS team2_name,
                tr.name AS tournament_name
            FROM Matches m
            JOIN Team t1 ON t1.id = m.team1_id
            JOIN Team t2 ON t2.id = m.team2_id
            JOIN Tournament tr ON tr.id = m.tournament_id AND tr.deleted_at IS NULL
            WHERE (m.team1_id = ? OR m.team2_id = ?)
              AND m.score_team1 IS NOT NULL
              AND m.deleted_at IS NULL
            ORDER BY COALESCE(m.date, tr.start_date) DESC
            LIMIT 5
        ");
        $matchStmt->execute([$teamId, $teamId]);
        $matches = $matchStmt->fetchAll();
    } catch (Exception $e) {
        $activeTournaments = [];
        $matches = [];
    }
}

$losses = max(0, (int)$stats['matches'] - (int)$stats['wins']);
$statusMap = [
    'live' => ['Live', 'profile-badge--live'],
    'ongoing' => ['Live', 'profile-badge--live'],
    'registration' => ['Open', 'profile-badge--open'],
    'open' => ['Open', 'profile-badge--open'],
    'upcoming' => ['Upcoming', 'profile-badge--soon'],
    'finished' => ['Finished', 'profile-badge--done'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <title><?= htmlspecialchars($player['username']) ?> — Profile</title>
    <?php require_once '../includes/head.php' ?>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="profile-main">
    <div class="profile-stack">
        <?php if ($flashSuccess): ?>
            <div class="profile-alert profile-alert--success animate-in" style="--delay: 40ms;"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="profile-alert profile-alert--error animate-in" style="--delay: 40ms;"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <section class="profile-hero animate-in" style="--delay: 80ms;">
            <div class="profile-avatar-wrap">
                <?php if (!empty($player['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($player['avatar_url']) ?>" alt="<?= htmlspecialchars($player['username']) ?>" class="profile-avatar-img">
                <?php else: ?>
                    <div class="profile-avatar"><?= htmlspecialchars(profileInitials($player['username'])) ?></div>
                <?php endif; ?>
            </div>

            <div>
                <h1 class="profile-name"><?= htmlspecialchars($player['username']) ?></h1>
                <div class="profile-meta">
                    <span class="profile-chip"><?= htmlspecialchars(ucfirst($player['user_type'])) ?></span>
                    <?php if (!empty($player['role'])): ?>
                        <span class="profile-chip"><?= htmlspecialchars($player['role']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($player['birth_date'])): ?>
                        <span class="profile-chip">Born <?= htmlspecialchars(date('d M Y', strtotime($player['birth_date']))) ?></span>
                    <?php endif; ?>
                </div>
                <p class="profile-sub">
                    <?= $player['team_name']
                        ? 'Currently competing with ' . htmlspecialchars($player['team_name']) . '.'
                        : 'Not currently assigned to a team.' ?>
                </p>
            </div>

            <?php if ($isSelf): ?>
                <div class="profile-hero-actions">
                    <button type="button" class="profile-button" id="profile-edit-toggle" aria-expanded="<?= $shouldOpenEditPanel ? 'true' : 'false' ?>">Edit Profile</button>
                </div>
            <?php endif; ?>
        </section>

        <section class="profile-stats">
            <div class="profile-stat animate-in" style="--delay: 140ms;">
                <div class="profile-stat-label">Team</div>
                <div class="profile-stat-value"><?= $player['team_name'] ? '1' : '0' ?></div>
            </div>
            <div class="profile-stat animate-in" style="--delay: 180ms;">
                <div class="profile-stat-label">Matches</div>
                <div class="profile-stat-value"><?= number_format((int)$stats['matches']) ?></div>
            </div>
            <div class="profile-stat animate-in" style="--delay: 220ms;">
                <div class="profile-stat-label">Wins</div>
                <div class="profile-stat-value"><?= number_format((int)$stats['wins']) ?></div>
            </div>
            <div class="profile-stat animate-in" style="--delay: 260ms;">
                <div class="profile-stat-label">Win Rate</div>
                <div class="profile-stat-value"><?= (int)$stats['win_rate'] ?>%</div>
            </div>
        </section>

        <section class="profile-top-grid">
            <div class="profile-card profile-card--feature animate-in" style="--delay: 300ms;">
                <div class="profile-card-head">
                    <div>
                        <div class="profile-card-title">Player Team Info</div>
                        <div class="profile-card-sub">Current team snapshot</div>
                    </div>
                </div>
                <div class="profile-card-body">
                    <?php if ($player['team_name']): ?>
                        <div class="profile-team">
                            <?php if (!empty($player['team_logo_url'])): ?>
                                <img src="<?= htmlspecialchars($player['team_logo_url']) ?>" alt="<?= htmlspecialchars($player['team_name']) ?>" class="profile-team-logo-img">
                            <?php else: ?>
                                <div class="profile-team-logo"><?= htmlspecialchars(strtoupper(substr($player['team_tag'] ?: $player['team_name'], 0, 2))) ?></div>
                            <?php endif; ?>
                            <div>
                                <div class="profile-team-name"><?= htmlspecialchars($player['team_name']) ?><?php if (!empty($player['team_tag'])): ?> #<?= htmlspecialchars($player['team_tag']) ?><?php endif; ?></div>
                                <div class="profile-team-meta">
                                    <?= htmlspecialchars($player['team_game'] ?? 'Unknown Game') ?>
                                    <?php if (!empty($player['team_region'])): ?> · <?= htmlspecialchars($player['team_region']) ?><?php endif; ?>
                                </div>
                                <div class="profile-team-meta">Captain: <?= htmlspecialchars($player['captain_name'] ?? 'Unknown') ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="profile-empty">This player is not in a team right now.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-card animate-in" style="--delay: 340ms;">
                <div class="profile-card-head">
                    <div>
                        <div class="profile-card-title">Player Snapshot</div>
                        <div class="profile-card-sub">Quick profile summary</div>
                    </div>
                </div>
                <div class="profile-card-body">
                    <div class="profile-summary-list">
                        <div class="profile-summary-row">
                            <span class="profile-summary-label">Profile Type</span>
                            <strong><?= htmlspecialchars(ucfirst($player['user_type'])) ?></strong>
                        </div>
                        <div class="profile-summary-row">
                            <span class="profile-summary-label">Role</span>
                            <strong><?= htmlspecialchars($player['role'] ?: 'Not set') ?></strong>
                        </div>
                        <div class="profile-summary-row">
                            <span class="profile-summary-label">Birth Date</span>
                            <strong><?= !empty($player['birth_date']) ? htmlspecialchars(date('d M Y', strtotime($player['birth_date']))) : 'Not set' ?></strong>
                        </div>
                        <div class="profile-summary-row">
                            <span class="profile-summary-label">Current Team</span>
                            <strong><?= htmlspecialchars($player['team_name'] ?: 'Free Agent') ?></strong>
                        </div>
                        <div class="profile-summary-row">
                            <span class="profile-summary-label">Recent Form</span>
                            <strong><?= (int)$stats['wins'] ?> wins in <?= (int)$stats['matches'] ?> matches</strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="profile-grid">
            <div class="profile-card animate-in" style="--delay: 380ms;">
                <div class="profile-card-head">
                    <div>
                        <div class="profile-card-title">Last 5 Matches Info</div>
                        <div class="profile-card-sub">Most recent team matches</div>
                    </div>
                </div>
                <div class="profile-match-list">
                    <?php if (empty($matches)): ?>
                        <div class="profile-empty">No recent matches found.</div>
                    <?php else: ?>
                        <?php foreach ($matches as $match): ?>
                            <?php
                                $isTeamOne = (int)$match['team1_id'] === $teamId;
                                $myScore = $isTeamOne ? (int)$match['score_team1'] : (int)$match['score_team2'];
                                $oppScore = $isTeamOne ? (int)$match['score_team2'] : (int)$match['score_team1'];
                                $oppName = $isTeamOne ? $match['team2_name'] : $match['team1_name'];
                                $won = $myScore >= $oppScore;
                            ?>
                            <div class="profile-match-row">
                                <div>
                                    <div class="profile-match-title">vs <?= htmlspecialchars($oppName) ?></div>
                                    <div class="profile-match-meta">
                                        <?= htmlspecialchars($match['tournament_name']) ?>
                                        <?php if (!empty($match['stage'])): ?> · <?= htmlspecialchars($match['stage']) ?><?php endif; ?>
                                        <?php if (!empty($match['date'])): ?> · <?= htmlspecialchars(date('d M Y', strtotime($match['date']))) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="profile-score <?= $won ? '' : 'profile-score--loss' ?>"><?= $myScore ?> : <?= $oppScore ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-card animate-in" style="--delay: 420ms;">
                <div class="profile-card-head">
                    <div>
                        <div class="profile-card-title">Active Tournaments</div>
                        <div class="profile-card-sub">Current team tournament activity</div>
                    </div>
                </div>
                <div class="profile-tournament-list">
                    <?php if (empty($activeTournaments)): ?>
                        <div class="profile-empty">No active tournaments right now.</div>
                    <?php else: ?>
                        <?php foreach ($activeTournaments as $tournament): ?>
                            <?php [$label, $badgeClass] = $statusMap[$tournament['status']] ?? [ucfirst($tournament['status']), 'profile-badge--done']; ?>
                            <div class="profile-tournament-row">
                                <div>
                                    <div class="profile-tournament-name"><?= htmlspecialchars($tournament['name']) ?></div>
                                    <div class="profile-tournament-meta">
                                        <?= htmlspecialchars($tournament['game_name'] ?? 'Unknown Game') ?>
                                        <?php if (!empty($tournament['start_date'])): ?> · <?= htmlspecialchars(date('d M Y', strtotime($tournament['start_date']))) ?><?php endif; ?>
                                    </div>
                                </div>
                                <span class="profile-badge <?= $badgeClass ?>"><?= htmlspecialchars($label) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</main>

<?php if ($isSelf): ?>
<div class="profile-modal-overlay <?= $shouldOpenEditPanel ? 'is-open' : '' ?>" id="profile-edit-modal" style="<?= $shouldOpenEditPanel ? 'display:flex;' : 'display:none;' ?>">
    <div class="profile-modal">
        <div class="profile-modal-head">
            <div>
                <div class="profile-card-title">Edit Player Info</div>
                <div class="profile-card-sub">Avatar, username and birth date</div>
            </div>
            <button type="button" class="profile-modal-close" id="profile-modal-close" aria-label="Close">×</button>
        </div>
        <div class="profile-modal-body">
            <form method="POST" enctype="multipart/form-data" class="profile-form">
                <input type="hidden" name="remove_avatar" id="profile-remove-avatar-input" value="0">
                <div class="profile-field">
                    <label class="profile-label">Username</label>
                    <input class="profile-input" type="text" name="username" maxlength="50" value="<?= htmlspecialchars($_POST['username'] ?? $player['username']) ?>" required>
                </div>
                <div class="profile-field">
                    <label class="profile-label">Birth Date</label>
                    <input class="profile-input" type="date" name="birth_date" value="<?= htmlspecialchars($_POST['birth_date'] ?? (!empty($player['birth_date']) ? date('Y-m-d', strtotime($player['birth_date'])) : '')) ?>">
                </div>
                <div class="profile-field">
                    <label class="profile-label">Avatar</label>
                    <div class="profile-avatar-upload">
                        <div class="profile-avatar-preview" id="profile-avatar-preview">
                            <?php if (!empty($player['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($player['avatar_url']) ?>" alt="<?= htmlspecialchars($player['username']) ?>" class="profile-avatar-preview-img">
                            <?php else: ?>
                                <span><?= htmlspecialchars(profileInitials($player['username'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="profile-avatar-upload-copy">
                            <strong>Upload new avatar</strong>
                            <span>PNG, JPG, WEBP · max 1 MB</span>
                        </div>
                    </div>
                    <input class="profile-file" type="file" name="avatar" id="profile-avatar-input" accept="image/png,image/jpeg,image/webp">
                </div>
                <?php if (!empty($player['avatar_url'])): ?>
                    <div class="profile-form-secondary">
                        <button type="button" class="profile-remove-btn" id="profile-remove-avatar-btn">Remove Avatar</button>
                    </div>
                <?php endif; ?>
                <div class="profile-form-actions">
                    <button type="submit" class="profile-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="profile-confirm-overlay" id="profile-confirm-overlay" style="display:none;">
    <div class="profile-confirm-box">
        <div class="profile-confirm-title">Remove Avatar?</div>
        <div class="profile-confirm-message">Your current avatar will be removed when you save the profile changes.</div>
        <div class="profile-confirm-actions">
            <button type="button" class="profile-confirm-cancel" id="profile-confirm-cancel">Cancel</button>
            <button type="button" class="profile-confirm-ok" id="profile-confirm-ok">Remove Avatar</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
(() => {
    const alerts = document.querySelectorAll('.profile-alert');
    alerts.forEach((alert) => {
        setTimeout(() => {
            alert.classList.add('profile-alert--hide');
            setTimeout(() => alert.remove(), 460);
        }, 2000);
    });

    const editToggle = document.getElementById('profile-edit-toggle');
    const editModal = document.getElementById('profile-edit-modal');
    const modalClose = document.getElementById('profile-modal-close');
    const closeEditModal = () => {
        if (!editModal) return;
        editModal.classList.remove('is-open');
        if (!editToggle) return;
        editToggle.setAttribute('aria-expanded', 'false');
        setTimeout(() => {
            editModal.style.display = 'none';
        }, 220);
    };

    if (editToggle && editModal) {
        editToggle.addEventListener('click', () => {
            editModal.style.display = 'flex';
            requestAnimationFrame(() => editModal.classList.add('is-open'));
            editToggle.setAttribute('aria-expanded', 'true');
        });
        if (modalClose) modalClose.addEventListener('click', closeEditModal);
        editModal.addEventListener('click', (event) => {
            if (event.target === editModal) closeEditModal();
        });
    }

    const avatarInput = document.getElementById('profile-avatar-input');
    const avatarPreview = document.getElementById('profile-avatar-preview');
    if (avatarInput && avatarPreview) {
        const defaultInitials = <?= json_encode(profileInitials($player['username'])) ?>;
        avatarInput.addEventListener('change', () => {
            const file = avatarInput.files && avatarInput.files[0];
            if (!file) {
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => {
                avatarPreview.innerHTML = `<img src="${e.target.result}" alt="Avatar Preview" class="profile-avatar-preview-img">`;
                const removeInput = document.getElementById('profile-remove-avatar-input');
                if (removeInput) removeInput.value = '0';
            };
            reader.readAsDataURL(file);
        });

        const removeBtn = document.getElementById('profile-remove-avatar-btn');
        const confirmOverlay = document.getElementById('profile-confirm-overlay');
        const confirmCancel = document.getElementById('profile-confirm-cancel');
        const confirmOk = document.getElementById('profile-confirm-ok');
        const removeInput = document.getElementById('profile-remove-avatar-input');

        const closeConfirm = () => {
            if (!confirmOverlay) return;
            confirmOverlay.classList.remove('is-open');
            setTimeout(() => {
                confirmOverlay.style.display = 'none';
            }, 200);
        };

        if (removeBtn && confirmOverlay && confirmCancel && confirmOk && removeInput) {
            removeBtn.addEventListener('click', () => {
                confirmOverlay.style.display = 'flex';
                requestAnimationFrame(() => confirmOverlay.classList.add('is-open'));
            });
            confirmCancel.addEventListener('click', closeConfirm);
            confirmOverlay.addEventListener('click', (event) => {
                if (event.target === confirmOverlay) closeConfirm();
            });
            confirmOk.addEventListener('click', () => {
                removeInput.value = '1';
                avatarInput.value = '';
                avatarPreview.innerHTML = `<span>${defaultInitials}</span>`;
                closeConfirm();
            });
        }
    }
})();
</script>
</body>
</html>
