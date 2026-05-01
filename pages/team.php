<?php
/**
 * Team Management Page
 * Handles team creation, editing, member management, and stats display.
 */

require_once '../includes/session.php';

if (!isLoggedIn()) {
    header('Location: ../pages/index.php?modal=login');
    exit;
}

$userId       = $_SESSION['user_id'];
$feedback     = '';
$feedbackType = '';

// ─── Helper: Fetch the current user's team ───────────────────────────────────
function fetchMyTeam(PDO $pdo, int $userId): array|false
{
    $stmt = $pdo->prepare("
        SELECT t.*, p2.username AS captain_name, p2.id AS captain_id
        FROM Team t
        JOIN Player p ON p.team_id = t.id
        LEFT JOIN Player p2 ON p2.id = t.captain_id
        WHERE p.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// ─── Helper: Fetch team members ──────────────────────────────────────────────
function fetchMembers(PDO $pdo, int $teamId, int $captainId): array
{
    $stmt = $pdo->prepare("
        SELECT id, username, role
        FROM Player
        WHERE team_id = ?
        ORDER BY (id = ?) DESC, username ASC
    ");
    $stmt->execute([$teamId, $captainId]);
    return $stmt->fetchAll();
}

// ─── Helper: Fetch team tournaments ──────────────────────────────────────────
function fetchTournaments(PDO $pdo, int $teamId): array
{
    $stmt = $pdo->prepare("
        SELECT t.id, t.name, t.status, g.name AS game_name, t.start_date
        FROM tournament_team tt
        JOIN Tournament t ON t.id = tt.tournament_id
        LEFT JOIN Game g ON g.id = t.game_id
        WHERE tt.team_id = ?
        ORDER BY t.start_date DESC
        LIMIT 5
    ");
    $stmt->execute([$teamId]);
    return $stmt->fetchAll();
}

// ─── Helper: Fetch team statistics ───────────────────────────────────────────
// FIX: Named parameters cannot be reused when ATTR_EMULATE_PREPARES is false.
//      Using positional (?) parameters with repeated values instead.
function fetchStats(PDO $pdo, int $teamId, int $tournamentCount): array
{
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS matches,
            SUM(
                (home_team_id = ? AND score_team1 > score_team2) OR
                (away_team_id = ? AND score_team2 > score_team1)
            ) AS wins
        FROM Matches
        WHERE (home_team_id = ? OR away_team_id = ?)
          AND score_team1 IS NOT NULL
    ");
    $stmt->execute([$teamId, $teamId, $teamId, $teamId]);
    $row = $stmt->fetch();

    return [
        'matches'     => (int) $row['matches'],
        'wins'        => (int) ($row['wins'] ?? 0),
        'tournaments' => $tournamentCount,
    ];
}

// ─── Helper: Set feedback message ────────────────────────────────────────────
function setFeedback(string &$msg, string &$type, string $message, string $msgType): void
{
    $msg  = $message;
    $type = $msgType;
}

// ─── POST: Handle form actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $team   = fetchMyTeam($pdo, $userId);
    $isCaptain = $team && (int)$team['captain_id'] === $userId;

    // ── Create Team ──────────────────────────────────────────────────────────
    if ($action === 'create_team' && !$team) {
        $name   = trim($_POST['name']        ?? '');
        $tag    = strtoupper(trim($_POST['tag'] ?? ''));
        $game   = trim($_POST['game']        ?? '');
        $region = trim($_POST['region']      ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if (empty($name) || empty($tag) || empty($game)) {
            setFeedback($feedback, $feedbackType, 'Team name, tag and game are required.', 'error');
        } elseif (strlen($tag) > 4) {
            setFeedback($feedback, $feedbackType, 'Tag must be 4 characters or less.', 'error');
        } else {
            try {
                $pdo->beginTransaction();

                $pdo->prepare("
                    INSERT INTO Team (name, tag, game, region, description, captain_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$name, $tag, $game, $region, $desc, $userId]);

                $newTeamId = (int) $pdo->lastInsertId();

                $pdo->prepare("UPDATE Player SET team_id = ? WHERE id = ?")
                    ->execute([$newTeamId, $userId]);

                $pdo->commit();
                header('Location: team.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                setFeedback($feedback, $feedbackType, 'An error occurred while creating the team.', 'error');
            }
        }
    }

    // ── Update Team ──────────────────────────────────────────────────────────
    elseif ($action === 'update_team' && $isCaptain) {
        $name   = trim($_POST['name']        ?? '');
        $tag    = strtoupper(trim($_POST['tag'] ?? ''));
        $game   = trim($_POST['game']        ?? '');
        $region = trim($_POST['region']      ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if (empty($name) || empty($tag)) {
            setFeedback($feedback, $feedbackType, 'Team name and tag are required.', 'error');
        } else {
            try {
                $pdo->prepare("
                    UPDATE Team
                    SET name = ?, tag = ?, game = ?, region = ?, description = ?
                    WHERE id = ? AND captain_id = ?
                ")->execute([$name, $tag, $game, $region, $desc, $team['id'], $userId]);

                header('Location: team.php?updated=1');
                exit;
            } catch (Exception $e) {
                setFeedback($feedback, $feedbackType, 'An error occurred while updating.', 'error');
            }
        }
    }

    // ── Invite Member ────────────────────────────────────────────────────────
    elseif ($action === 'invite' && $isCaptain) {
        $inviteUsername = trim($_POST['invite_username'] ?? '');

        if (empty($inviteUsername)) {
            setFeedback($feedback, $feedbackType, 'Username cannot be empty.', 'error');
        } else {
            try {
                $stmtFind = $pdo->prepare("SELECT id, team_id FROM Player WHERE username = ?");
                $stmtFind->execute([$inviteUsername]);
                $target = $stmtFind->fetch();

                if (!$target) {
                    setFeedback($feedback, $feedbackType, 'User not found.', 'error');
                } elseif ($target['team_id']) {
                    setFeedback($feedback, $feedbackType, 'This user is already in a team.', 'error');
                } else {
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM Player WHERE team_id = ?");
                    $countStmt->execute([$team['id']]);
                    $memberCount = (int) $countStmt->fetchColumn();

                    if ($memberCount >= 6) {
                        setFeedback($feedback, $feedbackType, 'Team is full (max 6 members).', 'error');
                    } else {
                        $pdo->prepare("UPDATE Player SET team_id = ? WHERE id = ?")
                            ->execute([$team['id'], $target['id']]);
                        setFeedback($feedback, $feedbackType, htmlspecialchars($inviteUsername) . ' added to team!', 'success');
                    }
                }
            } catch (Exception $e) {
                setFeedback($feedback, $feedbackType, 'An error occurred during the operation.', 'error');
            }
        }
    }

    // ── Kick Member ──────────────────────────────────────────────────────────
    elseif ($action === 'kick' && $isCaptain) {
        $kickId = (int) ($_POST['kick_id'] ?? 0);

        if ($kickId && $kickId !== $userId) {
            try {
                $pdo->prepare("UPDATE Player SET team_id = NULL WHERE id = ? AND team_id = ?")
                    ->execute([$kickId, $team['id']]);
                header('Location: team.php');
                exit;
            } catch (Exception $e) {
                setFeedback($feedback, $feedbackType, 'An error occurred during the operation.', 'error');
            }
        }
    }

    // ── Leave Team ───────────────────────────────────────────────────────────
    elseif ($action === 'leave') {
        try {
            if ($isCaptain) {
                $stmtNext = $pdo->prepare("SELECT id FROM Player WHERE team_id = ? AND id != ? LIMIT 1");
                $stmtNext->execute([$team['id'], $userId]);
                $nextCaptain = $stmtNext->fetch();

                if ($nextCaptain) {
                    $pdo->prepare("UPDATE Team SET captain_id = ? WHERE id = ?")
                        ->execute([$nextCaptain['id'], $team['id']]);
                } else {
                    // Last member — delete the team
                    $pdo->prepare("DELETE FROM Team WHERE id = ?")->execute([$team['id']]);
                }
            }

            $pdo->prepare("UPDATE Player SET team_id = NULL WHERE id = ?")->execute([$userId]);
            header('Location: team.php');
            exit;
        } catch (Exception $e) {
            setFeedback($feedback, $feedbackType, 'An error occurred while leaving the team.', 'error');
        }
    }
}

// ─── URL notification (after redirect) ───────────────────────────────────────
if (isset($_GET['updated'])) {
    setFeedback($feedback, $feedbackType, 'Team information updated successfully.', 'success');
}

// ─── Load data ────────────────────────────────────────────────────────────────
try {
    $team = fetchMyTeam($pdo, $userId);
} catch (Exception $e) {
    $team = null;
}

$hasTeam   = (bool) $team;
$isCaptain = $hasTeam && (int)$team['captain_id'] === $userId;

$members     = [];
$teamTournaments = [];
$stats       = ['matches' => 0, 'wins' => 0, 'tournaments' => 0];
$games       = [];

// Load game list for forms
try {
    $games = $pdo->query("SELECT name FROM Game ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $games = [];
}

if ($hasTeam) {
    try {
        $members = fetchMembers($pdo, $team['id'], $team['captain_id']);
    } catch (Exception $e) {
        $members = [];
    }

    try {
        $teamTournaments = fetchTournaments($pdo, $team['id']);
    } catch (Exception $e) {
        $teamTournaments = [];
    }

    try {
        $stats = fetchStats($pdo, $team['id'], count($teamTournaments));
    } catch (Exception $e) {
        $stats = ['matches' => 0, 'wins' => 0, 'tournaments' => count($teamTournaments)];
    }
}

$winRate = $stats['matches'] > 0 ? round($stats['wins'] / $stats['matches'] * 100) : 0;
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
</head>
<body>

<?php require_once '../includes/header.php'; ?>

<main>
<div class="team-page">

    <?php if ($feedback): ?>
        <div class="tm-alert tm-alert--<?= htmlspecialchars($feedbackType) ?>">
            <?= htmlspecialchars($feedback) ?>
        </div>
    <?php endif; ?>

    <?php if (!$hasTeam): ?>
    <!-- ═══════════════════════ NO TEAM STATE ══════════════════════════════ -->
    <div class="tm-empty-wrap">
        <div class="tm-empty-box">
            <div class="tm-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <h2 class="tm-empty-title">You don't have a team yet</h2>
            <p class="tm-empty-sub">Create a team and start competing in tournaments.</p>
            <button class="tm-btn-primary"
                    onclick="document.getElementById('createPanel').style.display='block';this.style.display='none'">
                + Create Team
            </button>

            <div id="createPanel" style="display:none; margin-top:28px; text-align:left;">
                <form method="POST">
                    <input type="hidden" name="action" value="create_team">
                    <div class="tm-form-grid">
                        <div class="tm-field">
                            <label class="tm-label">Team Name *</label>
                            <input class="tm-input" type="text" name="name" placeholder="NightFall" required>
                        </div>
                        <div class="tm-field">
                            <label class="tm-label">Tag * (max 4)</label>
                            <input class="tm-input" type="text" name="tag" placeholder="NX" maxlength="4" required>
                        </div>
                        <div class="tm-field">
                            <label class="tm-label">Game *</label>
                            <select class="tm-select" name="game" required>
                                <option value="">Select...</option>
                                <?php foreach ($games as $g): ?>
                                    <option value="<?= htmlspecialchars($g['name']) ?>">
                                        <?= htmlspecialchars($g['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tm-field">
                            <label class="tm-label">Region</label>
                            <input class="tm-input" type="text" name="region" placeholder="Turkey">
                        </div>
                        <div class="tm-field tm-field--full">
                            <label class="tm-label">Description</label>
                            <textarea class="tm-textarea" name="description" rows="2"
                                      placeholder="Introduce your team..."></textarea>
                        </div>
                    </div>
                    <div class="tm-form-actions">
                        <button type="button" class="tm-btn-ghost"
                                onclick="document.getElementById('createPanel').style.display='none'">
                            Cancel
                        </button>
                        <button type="submit" class="tm-btn-primary">Create Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ═══════════════════════ HAS TEAM ══════════════════════════════════ -->

    <!-- HEADER CARD -->
    <div class="tm-header">
        <div class="tm-avatar">
            <?= htmlspecialchars(strtoupper(substr($team['tag'] ?? 'T', 0, 2))) ?>
        </div>
        <div class="tm-info">
            <div class="tm-name"><?= htmlspecialchars($team['name'] ?? '') ?></div>
            <div class="tm-tag">
                #<?= htmlspecialchars($team['tag'] ?? '') ?>
                <?php if (!empty($team['game'])): ?>
                    · <?= htmlspecialchars($team['game']) ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($team['description'])): ?>
                <div class="tm-desc"><?= htmlspecialchars($team['description']) ?></div>
            <?php endif; ?>
            <div class="tm-meta">
                <?php if (!empty($team['region'])): ?>
                    <div class="tm-meta-item">Region <span><?= htmlspecialchars($team['region']) ?></span></div>
                <?php endif; ?>
                <div class="tm-meta-item">Members <span><?= count($members) ?> / 6</span></div>
                <div class="tm-meta-item">
                    Captain <span><?= htmlspecialchars($team['captain_name'] ?? '—') ?></span>
                </div>
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

    <!-- STATS BAR -->
    <div class="tm-stats">
        <div class="tm-stat">
            <div class="tm-stat-label">Total Matches</div>
            <div class="tm-stat-val"><?= $stats['matches'] ?></div>
        </div>
        <div class="tm-stat">
            <div class="tm-stat-label">Wins</div>
            <div class="tm-stat-val"><?= $stats['wins'] ?></div>
            <?php if ($stats['matches'] > 0): ?>
                <div class="tm-stat-sub"><?= $winRate ?>% win rate</div>
            <?php endif; ?>
        </div>
        <div class="tm-stat">
            <div class="tm-stat-label">Tournaments</div>
            <div class="tm-stat-val"><?= $stats['tournaments'] ?></div>
        </div>
        <div class="tm-stat">
            <div class="tm-stat-label">Losses</div>
            <div class="tm-stat-val"><?= $stats['matches'] - $stats['wins'] ?></div>
            <div class="tm-stat-sub">Max <?= 6 - count($members) ?> slots left</div>
        </div>
    </div>

    <!-- INVITE PANEL -->
    <?php if ($isCaptain): ?>
    <div id="invitePanel" class="tm-panel tm-panel--accent" style="display:none">
        <div class="tm-panel-title">Invite Member</div>
        <form method="POST" class="tm-invite-form">
            <input type="hidden" name="action" value="invite">
            <input class="tm-input" type="text" name="invite_username"
                   placeholder="Username" required autocomplete="off">
            <button type="submit" class="tm-btn-primary">Add</button>
        </form>
        <div class="tm-panel-note">Current members: <?= count($members) ?> / 6</div>
    </div>

    <!-- EDIT PANEL -->
    <div id="editPanel" class="tm-panel" style="display:none">
        <div class="tm-panel-title">Edit Team</div>
        <form method="POST">
            <input type="hidden" name="action" value="update_team">
            <div class="tm-form-grid">
                <div class="tm-field">
                    <label class="tm-label">Team Name</label>
                    <input class="tm-input" type="text" name="name"
                           value="<?= htmlspecialchars($team['name'] ?? '') ?>" required>
                </div>
                <div class="tm-field">
                    <label class="tm-label">Tag (max 4)</label>
                    <input class="tm-input" type="text" name="tag" maxlength="4"
                           value="<?= htmlspecialchars($team['tag'] ?? '') ?>" required>
                </div>
                <div class="tm-field">
                    <label class="tm-label">Game</label>
                    <select class="tm-select" name="game">
                        <?php foreach ($games as $g):
                            $selected = ($g['name'] === ($team['game'] ?? '')) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($g['name']) ?>" <?= $selected ?>>
                                <?= htmlspecialchars($g['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tm-field">
                    <label class="tm-label">Region</label>
                    <input class="tm-input" type="text" name="region"
                           value="<?= htmlspecialchars($team['region'] ?? '') ?>">
                </div>
                <div class="tm-field tm-field--full">
                    <label class="tm-label">Description</label>
                    <textarea class="tm-textarea" name="description" rows="2"><?=
                        htmlspecialchars($team['description'] ?? '')
                    ?></textarea>
                </div>
            </div>
            <div class="tm-form-actions">
                <button type="button" class="tm-btn-ghost" onclick="togglePanel('editPanel')">Cancel</button>
                <button type="submit" class="tm-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- MEMBERS + TOURNAMENTS -->
    <div class="tm-two-col">

        <!-- MEMBERS -->
        <div class="tm-card">
            <div class="tm-card-head">
                <span class="tm-card-title">Members</span>
                <span class="tm-card-count"><?= count($members) ?> / 6</span>
            </div>

            <?php foreach ($members as $member):
                $memberIsCaptain = ((int)$member['id'] === (int)$team['captain_id']);
                $isCurrentUser   = ((int)$member['id'] === $userId);
            ?>
                <div class="tm-member-row">
                    <div class="tm-m-avatar <?= $memberIsCaptain ? 'tm-m-avatar--captain' : '' ?>">
                        <?= strtoupper(substr($member['username'], 0, 2)) ?>
                    </div>
                    <div class="tm-m-info">
                        <div class="tm-m-name">
                            <?= htmlspecialchars($member['username']) ?>
                            <?php if ($isCurrentUser): ?>
                                <span class="tm-you">You</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($member['role'])): ?>
                            <div class="tm-m-role"><?= htmlspecialchars($member['role']) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($memberIsCaptain): ?>
                        <span class="tm-badge tm-badge--captain">Captain</span>
                    <?php else: ?>
                        <span class="tm-badge tm-badge--member">Member</span>
                        <?php if ($isCaptain): ?>
                            <form method="POST" class="tm-kick-form"
                                  onsubmit="return confirm('Remove <?= htmlspecialchars($member['username']) ?> from team?')">
                                <input type="hidden" name="action" value="kick">
                                <input type="hidden" name="kick_id" value="<?= $member['id'] ?>">
                                <button type="submit" class="tm-btn-kick">Kick</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- TOURNAMENTS -->
        <div class="tm-card">
            <div class="tm-card-head">
                <span class="tm-card-title">Tournaments</span>
                <a href="tournaments.php" class="tm-card-link">See All →</a>
            </div>

            <?php if (empty($teamTournaments)): ?>
                <div class="tm-empty-row">No tournaments joined yet.</div>
            <?php else: ?>
                <?php
                $statusMap = [
                    'live'         => ['dot' => 'tm-dot--live',     'label' => 'Live',         'cls' => 'tm-result--ongoing'],
                    'registration' => ['dot' => 'tm-dot--upcoming', 'label' => 'Registration', 'cls' => 'tm-result--soon'],
                    'upcoming'     => ['dot' => 'tm-dot--upcoming', 'label' => 'Upcoming',     'cls' => 'tm-result--soon'],
                    'finished'     => ['dot' => 'tm-dot--done',     'label' => 'Finished',     'cls' => ''],
                ];
                foreach ($teamTournaments as $t):
                    $st = $statusMap[$t['status']] ?? ['dot' => 'tm-dot--done', 'label' => $t['status'], 'cls' => ''];
                ?>
                    <div class="tm-tournament-row">
                        <div class="tm-dot <?= $st['dot'] ?>"></div>
                        <div class="tm-t-info">
                            <div class="tm-t-name"><?= htmlspecialchars($t['name']) ?></div>
                            <div class="tm-t-meta">
                                <?= htmlspecialchars($t['game_name'] ?? '') ?>
                                · <?= date('M Y', strtotime($t['start_date'])) ?>
                            </div>
                        </div>
                        <span class="tm-result <?= $st['cls'] ?>"><?= $st['label'] ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /.tm-two-col -->

    <!-- Hidden leave form -->
    <form id="leaveForm" method="POST" style="display:none">
        <input type="hidden" name="action" value="leave">
    </form>

    <?php endif; // $hasTeam ?>

</div><!-- /.team-page -->
</main>

<?php require_once '../includes/footer.php'; ?>

<script>
function togglePanel(id) {
    const panels = ['editPanel', 'invitePanel'];
    panels.forEach(panelId => {
        const el = document.getElementById(panelId);
        if (!el) return;
        el.style.display = (panelId === id && el.style.display === 'none') ? 'block' : 'none';
    });
}

function confirmLeave() {
    if (confirm('Are you sure you want to leave the team?')) {
        document.getElementById('leaveForm').submit();
    }
}

// Auto-dismiss success alerts
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tm-alert--success').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });
});
</script>

</body>
</html>