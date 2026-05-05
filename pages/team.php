<?php
require_once '../includes/session.php';
$customTitle = 'My Team';
$extraCss = ['team'];

requireLogin();

/* ── Helpers ─────────────────────────────────────────────────────── */
function _timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return round($diff / 60) . 'm ago';
    if ($diff < 86400)  return round($diff / 3600) . 'h ago';
    return round($diff / 86400) . 'd ago';
}

function gameOptions(array $games, string $selected = ''): string {
    $out = '<option value="">Choose a game...</option>';
    foreach ($games as $g) {
        $sel = $g === $selected ? 'selected' : '';
        $out .= "<option value=\"" . htmlspecialchars($g) . "\" $sel>" . htmlspecialchars($g) . "</option>";
    }
    return $out;
}

function regionSelectorHtml(string $id, string $hiddenId, string $initial = ''): string {
    $display = htmlspecialchars($initial ?: 'Select region...');
    $val     = htmlspecialchars($initial);
    return <<<HTML
    <div class="tm-region-wrap" id="{$id}">
        <button type="button" class="tm-region-btn" aria-haspopup="listbox" aria-expanded="false">
            <span class="tm-region-display">{$display}</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="tm-region-dropdown" role="listbox">
            <div class="tm-region-search-wrap">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" class="tm-region-search" placeholder="Search regions...">
            </div>
            <div class="tm-region-list"></div>
        </div>
        <input type="hidden" name="region" id="{$hiddenId}" value="{$val}">
    </div>
    HTML;
}

/* ═══════════════════════════════════════════════════════════════════
   ALL DATA FETCHING — must happen before any HTML output
   ═══════════════════════════════════════════════════════════════════ */
$userId = (int)$_SESSION['user_id'];

/* ── Team & captain ──────────────────────────────────────────────── */
try {
    $s = $pdo->prepare("
        SELECT t.*, cap.username AS captain_name
        FROM   Player p
        JOIN   Team   t   ON t.id = p.team_id AND t.deleted_at IS NULL
        JOIN   Player cap ON cap.id = t.captain_id
        WHERE  p.id = ? AND p.deleted_at IS NULL
        LIMIT  1
    ");
    $s->execute([$userId]);
    $team = $s->fetch() ?: null;
} catch (Exception $e) { $team = null; }

$isCaptain = $team && (int)$team['captain_id'] === $userId;

/* ── Members ─────────────────────────────────────────────────────── */
$members = [];
if ($team) {
    try {
        $m = $pdo->prepare("
            SELECT id, username, role
            FROM   Player
            WHERE  team_id = ? AND deleted_at IS NULL
            ORDER  BY (id = ?) DESC, username ASC
        ");
        $m->execute([$team['id'], $team['captain_id']]);
        $members = $m->fetchAll();
    } catch (Exception $e) {}
}

/* ── Recent tournaments (up to 5) ───────────────────────────────── */
$tournaments = [];
if ($team) {
    try {
        $t = $pdo->prepare("
            SELECT t.id, t.name, t.status, g.name AS game_name, t.start_date
            FROM   tournament_teams tt
            JOIN   Tournament t ON t.id = tt.tournament_id AND t.deleted_at IS NULL
            LEFT   JOIN Game g  ON g.id = t.game_id AND g.deleted_at IS NULL
            WHERE  tt.team_id = ?
            ORDER  BY t.start_date DESC
            LIMIT  5
        ");
        $t->execute([$team['id']]);
        $tournaments = $t->fetchAll();
    } catch (Exception $e) {}
}

/* ── Overall stats ───────────────────────────────────────────────── */
$stats = ['matches' => 0, 'wins' => 0];
if ($team) {
    try {
        $st = $pdo->prepare("
            SELECT COUNT(*) AS matches,
                   COALESCE(SUM(
                       CASE WHEN (team1_id = :t AND score_team1 > score_team2)
                                 OR (team2_id = :t AND score_team2 > score_team1)
                            THEN 1 ELSE 0 END
                   ), 0) AS wins
            FROM   Matches
            WHERE  (team1_id = :t OR team2_id = :t)
              AND  score_team1 IS NOT NULL
              AND  deleted_at  IS NULL
        ");
        $st->execute([':t' => $team['id']]);
        $stats = $st->fetch();
    } catch (Exception $e) {}
}

/* ── Last 5 matches with scores ──────────────────────────────────── */
$recentMatches = [];
if ($team) {
    try {
        $rm = $pdo->prepare("
            SELECT m.id, m.date, m.stage, m.score_team1, m.score_team2,
                   m.team1_id AS home_team_id, m.team2_id AS away_team_id,
                   t1.name AS home_name,
                   t2.name AS away_name,
                   tour.name AS tournament_name
            FROM   Matches m
            JOIN   Team t1         ON t1.id  = m.team1_id AND t1.deleted_at IS NULL
            JOIN   Team t2         ON t2.id  = m.team2_id AND t2.deleted_at IS NULL
            JOIN   Tournament tour ON tour.id = m.tournament_id AND tour.deleted_at IS NULL
            WHERE  (m.team1_id = ? OR m.team2_id = ?)
              AND  m.score_team1 IS NOT NULL
              AND  m.deleted_at  IS NULL
            ORDER  BY m.date DESC
            LIMIT  5
        ");
        $rm->execute([$team['id'], $team['id']]);
        $recentMatches = $rm->fetchAll();
    } catch (Exception $e) {
        die("Match Query Error: " . $e->getMessage());
    }
}

/* ── Token-based invite from email link ──────────────────────────── */
$inviteResult = null;
if (!empty($_GET['inv'])) {
    [$rawId, $rawToken] = array_pad(explode('.', $_GET['inv'], 2), 2, '');
    $rawId = (int)$rawId;
    if ($rawId && $rawToken) {
        try {
            $secret = $_ENV['APP_SECRET'] ?? 'ikarus-invite-secret-2026';
            $invRow = $pdo->prepare("
                SELECT i.*, t.name AS team_name
                FROM   Invitations i
                JOIN   Team t ON t.id = i.team_id AND t.deleted_at IS NULL
                WHERE  i.id = ? AND i.receiver_id = ? AND i.status = 'pending' AND i.deleted_at IS NULL
                LIMIT  1
            ");
            $invRow->execute([$rawId, $userId]);
            $pendingInv = $invRow->fetch();

            if ($pendingInv) {
                $payload  = "{$rawId}|{$userId}|{$pendingInv['team_id']}";
                $expected = hash_hmac('sha256', $payload, $secret);
                if (hash_equals($expected, $rawToken)) {
                    $inviteResult = [
                        'valid'      => true,
                        'invite_id'  => $rawId,
                        'team_name'  => $pendingInv['team_name'],
                    ];
                }
            }
        } catch (Exception $e) {}
    }
}

/* ── Pending invitations for the current user ────────────────────── */
$pendingInvites = [];
if (!$team) {
    try {
        $pi = $pdo->prepare("
            SELECT i.id, i.sent_at,
                   t.name AS team_name, t.tag, t.logo_url,
                   p.username AS sender_name
            FROM   Invitations i
            JOIN   Team   t ON t.id = i.team_id   AND t.deleted_at IS NULL
            JOIN   Player p ON p.id = i.sender_id AND p.deleted_at IS NULL
            WHERE  i.receiver_id = ? AND i.status = 'pending' AND i.deleted_at IS NULL
            ORDER  BY i.sent_at DESC
        ");
        $pi->execute([$userId]);
        $pendingInvites = $pi->fetchAll();
    } catch (Exception $e) {}
}

/* ── Game list ───────────────────────────────────────────────────── */
try {
    $games = $pdo->query("SELECT name FROM Game WHERE deleted_at IS NULL ORDER BY name")
                 ->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $games = []; }

/* ── JS init payload ─────────────────────────────────────────────── */
$jsInit = json_encode([
    'userId'       => $userId,
    'isCaptain'    => $isCaptain,
    'hasTeam'      => (bool)$team,
    'teamId'       => $team ? (int)$team['id'] : null,
    'inviteResult' => $inviteResult,
], JSON_HEX_TAG | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once '../includes/head.php' ?>
</head>
<body>
<?php require_once '../includes/header.php'; ?>

<main class="team-main">
<div class="team-page">

<?php if (!$team): ?>
<!-- ═══════════════════ NO TEAM ═══════════════════ -->
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
        <p class="tm-empty-sub">Create a team or join one using an invite code.</p>

        <div class="tm-empty-actions">
            <button class="tm-btn-primary" onclick="TeamApp.togglePanel('createPanel')">
                + Create Team
            </button>
            <button class="tm-btn-outline" onclick="TeamApp.togglePanel('joinPanel')">
                Enter Invite Code
            </button>
        </div>

        <!-- ── Create team panel ───────────────────────────────── -->
        <div class="tm-collapsible" id="createPanel">
            <div class="tm-collapsible-inner">
                <div style="padding-top:20px; text-align:left;">
                    <form id="create-team-form" enctype="multipart/form-data" novalidate>
                        <div class="tm-field" style="margin-bottom:16px;">
                            <label class="tm-label">Team Logo</label>
                            <label class="tm-avatar-upload-area" for="create-avatar-input">
                                <div class="tm-avatar-preview" id="create-avatar-preview">🎮</div>
                                <div class="tm-avatar-upload-text">
                                    <strong>Upload team logo</strong>
                                    <span>PNG, JPG, WEBP · max 1 MB</span>
                                </div>
                            </label>
                            <input class="tm-avatar-file-input" type="file" id="create-avatar-input"
                                   name="avatar" accept="image/png,image/jpeg,image/webp"
                                   onchange="TeamApp.previewAvatar(this,'create-avatar-preview')">
                        </div>

                        <div class="tm-form-grid">
                            <div class="tm-field">
                                <label class="tm-label">Team Name *</label>
                                <input class="tm-input" type="text" name="name"
                                       placeholder="NightFall" maxlength="50" required>
                            </div>
                            <div class="tm-field">
                                <label class="tm-label">Tag * (2–6 chars)</label>
                                <input class="tm-input" type="text" name="tag"
                                       placeholder="NF" maxlength="6" minlength="2" required>
                            </div>
                            <div class="tm-field">
                                <label class="tm-label">Game *</label>
                                <select class="tm-select" name="game" required>
                                    <?= gameOptions($games) ?>
                                </select>
                            </div>
                            <div class="tm-field">
                                <label class="tm-label">Region</label>
                                <?= regionSelectorHtml('create-region-sel', 'create-region-hidden') ?>
                            </div>
                            <div class="tm-field tm-field--full">
                                <label class="tm-label">Description</label>
                                <textarea class="tm-textarea" name="description" rows="2"
                                          placeholder="Tell us about your team..."></textarea>
                            </div>
                        </div>

                        <div class="tm-form-actions">
                            <button type="button" class="tm-btn-ghost"
                                    onclick="TeamApp.closePanel('createPanel')">Cancel</button>
                            <button type="submit" class="tm-btn-primary">Create Team</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── Join by invite code panel ───────────────────────── -->
        <div class="tm-collapsible" id="joinPanel">
            <div class="tm-collapsible-inner">
                <div style="padding-top:20px; text-align:left;">
                    <div class="tm-panel tm-panel--accent">
                        <div class="tm-panel-title">Join with Invite Code</div>
                        <p style="font-size:13px;color:var(--text-muted);margin:0 0 14px;line-height:1.6;">
                            Ask your team captain for the 8-character invite code,
                            then paste it below.
                        </p>
                        <form id="join-code-form" novalidate>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input class="tm-input tm-code-input" type="text"
                                       id="join-code-input"
                                       name="code"
                                       placeholder="e.g. A3B7C9D2"
                                       maxlength="8"
                                       autocomplete="off"
                                       spellcheck="false"
                                       required
                                       style="flex:1;text-transform:uppercase;letter-spacing:3px;
                                              font-weight:700;font-size:15px;">
                                <button type="submit" class="tm-btn-primary">Join</button>
                            </div>
                            <div id="join-code-feedback"
                                 style="font-size:12px;margin-top:8px;min-height:16px;"></div>
                        </form>
                        <div style="margin-top:10px;font-size:11px;color:var(--text-faint);">
                            You can also accept invitations sent to you via the bell icon in the header.
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.tm-empty-box -->
</div><!-- /.tm-empty-wrap -->

<?php if (!empty($pendingInvites)): ?>
<!-- ── Pending Invitations ─────────────────────────────────────── -->
<div class="tm-section-header" style="margin-top:28px;">
    <span class="tm-section-title">Pending Invitations</span>
    <span class="tm-notif-badge"><?= count($pendingInvites) ?></span>
</div>
<div id="tm-invite-list" class="tm-invite-list">
    <?php foreach ($pendingInvites as $inv): ?>
    <div class="tm-invite-card" id="inv-card-<?= $inv['id'] ?>">
        <div class="tm-invite-avatar">
            <?php if ($inv['logo_url']): ?>
                <img src="<?= htmlspecialchars($inv['logo_url']) ?>"
                     alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
            <?php else: ?>
                <?= strtoupper(substr($inv['team_name'], 0, 2)) ?>
            <?php endif; ?>
        </div>
        <div class="tm-invite-body">
            <div class="tm-invite-team">
                <?= htmlspecialchars($inv['team_name']) ?>
                <span class="tm-invite-tag">#<?= htmlspecialchars($inv['tag']) ?></span>
            </div>
            <div class="tm-invite-sub">
                Invited by <strong><?= htmlspecialchars($inv['sender_name']) ?></strong>
                · <?= _timeAgo($inv['sent_at']) ?>
            </div>
        </div>
        <div class="tm-invite-actions">
            <button class="tm-btn-primary tm-btn-sm"
                    onclick="TeamApp.respondInvite(<?= $inv['id'] ?>, true)">Accept</button>
            <button class="tm-btn-ghost tm-btn-sm"
                    onclick="TeamApp.respondInvite(<?= $inv['id'] ?>, false)">Decline</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ═══════════════════ HAS TEAM ═══════════════════ -->

<!-- HEADER ──────────────────────────────────────────────────────── -->
<div class="tm-header tm-animate-in" style="--delay: 0ms;">
    <div class="tm-avatar-wrap">
        <?php if (!empty($team['logo_url'])): ?>
            <img class="tm-avatar-img" id="tm-logo-img"
                 src="<?= htmlspecialchars($team['logo_url']) ?>"
                 alt="<?= htmlspecialchars($team['name']) ?>">
        <?php else: ?>
            <div class="tm-avatar" id="tm-logo-fallback">
                <?= strtoupper(substr($team['tag'] ?? $team['name'], 0, 2)) ?>
            </div>
        <?php endif; ?>
        <?php if ($isCaptain): ?>
            <button class="tm-avatar-edit-btn" title="Change logo"
                    onclick="TeamApp.togglePanel('editPanel')">✏</button>
        <?php endif; ?>
    </div>

    <div class="tm-info">
        <div class="tm-name"><?= htmlspecialchars($team['name']) ?></div>
        <div class="tm-tag">
            #<?= htmlspecialchars($team['tag'] ?? '') ?>
            <?php if (!empty($team['game'])): ?>
                · <?= htmlspecialchars($team['game']) ?>
            <?php endif; ?>
            <?php if (!empty($team['region'])): ?>
                <span class="tm-region-chip">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10
                                 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    <?= htmlspecialchars($team['region']) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if (!empty($team['description'])): ?>
            <div class="tm-desc"><?= htmlspecialchars($team['description']) ?></div>
        <?php endif; ?>

        <!-- Invite code row -->
        <?php if (!empty($team['invitation_code'])): ?>
        <div class="tm-code-row">
            <span class="tm-code-label">Invite Code</span>
            <span id="tm-invite-code" class="tm-code-value">
                <?= htmlspecialchars($team['invitation_code']) ?>
            </span>
            <button class="tm-code-copy" onclick="TeamApp.copyCode()" title="Copy">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
            </button>
            <span class="tm-code-hint">Share this with players you want to invite</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="tm-actions">
        <?php if ($isCaptain): ?>
            <button class="tm-btn-primary" onclick="TeamApp.togglePanel('editPanel')">Edit Team</button>
            <button class="tm-btn-outline" onclick="TeamApp.togglePanel('invitePanel')">+ Invite</button>
        <?php endif; ?>
        <button class="tm-btn-danger" onclick="TeamApp.leaveTeam()">Leave Team</button>
    </div>
</div>

<!-- STATS ───────────────────────────────────────────────────────── -->
<?php
$wins   = (int)$stats['wins'];
$total  = (int)$stats['matches'];
$losses = $total - $wins;
$wr     = $total > 0 ? round($wins / $total * 100) : 0;
$wrCls  = $wr >= 60 ? 'high' : ($wr >= 40 ? 'mid' : 'low');
?>
<div class="tm-stats">
    <div class="tm-stat tm-animate-in" style="--delay: 100ms;">
        <div class="tm-stat-label">Matches</div>
        <div class="tm-stat-val"><?= $total ?></div>
    </div>
    <div class="tm-stat tm-animate-in" style="--delay: 150ms;">
        <div class="tm-stat-label">Wins</div>
        <div class="tm-stat-val" style="color:var(--accent)"><?= $wins ?></div>
    </div>
    <div class="tm-stat tm-animate-in" style="--delay: 200ms;">
        <div class="tm-stat-label">Losses</div>
        <div class="tm-stat-val" style="color:#f87171"><?= $losses ?></div>
    </div>
    <div class="tm-stat tm-animate-in" style="--delay: 250ms;">
        <div class="tm-stat-label">Win Rate</div>
        <div class="tm-stat-val"><?= $wr ?>%</div>
        <?php if ($total > 0): ?>
        <div class="tm-wr-bar-mini">
            <div class="tm-wr-fill-mini <?= $wrCls ?>" style="width:<?= $wr ?>%"></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- EDIT PANEL ──────────────────────────────────────────────────── -->
<?php if ($isCaptain): ?>
<div class="tm-collapsible" id="editPanel">
    <div class="tm-collapsible-inner">
        <div class="tm-panel">
            <div class="tm-panel-title">Edit Team</div>
            <form id="edit-team-form" enctype="multipart/form-data" novalidate>
                <div class="tm-field" style="margin-bottom:14px;">
                    <label class="tm-label">Team Logo</label>
                    <label class="tm-avatar-upload-area" for="edit-avatar-input">
                        <div class="tm-avatar-preview" id="edit-avatar-preview">
                            <?php if (!empty($team['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($team['logo_url']) ?>"
                                     alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
                            <?php else: ?>
                                <?= strtoupper(substr($team['tag'] ?? '', 0, 2)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="tm-avatar-upload-text">
                            <strong>Change team logo</strong>
                            <span>Leave empty to keep current · PNG, JPG, WEBP · max 1 MB</span>
                        </div>
                    </label>
                    <input class="tm-avatar-file-input" type="file" id="edit-avatar-input"
                           name="avatar" accept="image/png,image/jpeg,image/webp"
                           onchange="TeamApp.previewAvatar(this,'edit-avatar-preview')">
                    <?php if (!empty($team['logo_url'])): ?>
                    <button type="button" class="tm-remove-avatar-btn"
                            onclick="TeamApp.removeAvatar()">
                        ✕ Remove current logo
                    </button>
                    <?php endif; ?>
                </div>

                <div class="tm-form-grid">
                    <div class="tm-field">
                        <label class="tm-label">Team Name *</label>
                        <input class="tm-input" type="text" name="name"
                               value="<?= htmlspecialchars($team['name']) ?>" maxlength="50" required>
                    </div>
                    <div class="tm-field">
                        <label class="tm-label">Tag * (2–6)</label>
                        <input class="tm-input" type="text" name="tag"
                               value="<?= htmlspecialchars($team['tag'] ?? '') ?>" maxlength="6" required>
                    </div>
                    <div class="tm-field">
                        <label class="tm-label">Game</label>
                        <select class="tm-select" name="game">
                            <?= gameOptions($games, $team['game'] ?? '') ?>
                        </select>
                    </div>
                    <div class="tm-field">
                        <label class="tm-label">Region</label>
                        <?= regionSelectorHtml('edit-region-sel', 'edit-region-hidden', $team['region'] ?? '') ?>
                    </div>
                    <div class="tm-field tm-field--full">
                        <label class="tm-label">Description</label>
                        <textarea class="tm-textarea" name="description"
                                  rows="2"><?= htmlspecialchars($team['description'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="tm-form-actions">
                    <button type="button" class="tm-btn-ghost"
                            onclick="TeamApp.closePanel('editPanel')">Cancel</button>
                    <button type="submit" class="tm-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- INVITE PANEL ────────────────────────────────────────────────── -->
<div class="tm-collapsible" id="invitePanel">
    <div class="tm-collapsible-inner">
        <div class="tm-panel tm-panel--accent">
            <div class="tm-panel-title">Invite Member by Username</div>
            <form id="invite-form" novalidate>
                <div style="display:flex;gap:8px;">
                    <input class="tm-input" type="text" name="invite_username"
                           id="invite-username-input"
                           placeholder="Enter username..." style="flex:1;" required>
                    <button type="submit" class="tm-btn-primary">Send Invite</button>
                </div>
                <div id="invite-feedback"
                     style="font-size:12px;margin-top:6px;min-height:18px;"></div>
            </form>
            <div class="tm-panel-note" style="margin-top:10px;">
                Members: <strong id="tm-member-count"><?= count($members) ?>/6</strong>
                · They'll receive an email + in-site notification.
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MAIN GRID: Members + Tournaments ────────────────────────────── -->
<div class="tm-two-col">

    <!-- Members -->
    <div class="tm-card tm-animate-in" style="--delay: 300ms;">
        <div class="tm-card-head">
            <span class="tm-card-title">Members</span>
            <span id="tm-member-count-header" class="op-td-muted"
                  style="font-size:12px;"><?= count($members) ?>/6</span>
        </div>
        <div id="tm-member-list">
            <?php foreach ($members as $m):
                $isCap = ((int)$m['id'] === (int)$team['captain_id']);
                $isMe  = ((int)$m['id'] === $userId);
            ?>
            <div class="tm-member-row" data-member-id="<?= $m['id'] ?>">
                <div class="tm-m-avatar <?= $isCap ? 'tm-m-avatar--captain' : '' ?>">
                    <?= strtoupper(substr($m['username'], 0, 2)) ?>
                </div>
                <div class="tm-m-info">
                    <div class="tm-m-name">
                        <?= htmlspecialchars($m['username']) ?>
                        <?php if ($isMe): ?><span class="tm-you">You</span><?php endif; ?>
                    </div>
                    <?php if (!empty($m['role'])): ?>
                        <div class="tm-m-role"><?= htmlspecialchars($m['role']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($isCap): ?>
                    <span class="tm-badge tm-badge--captain">Captain</span>
                <?php else: ?>
                    <span class="tm-badge tm-badge--member">Member</span>
                    <?php if ($isCaptain): ?>
                        <button class="tm-btn-kick"
                                onclick="TeamApp.kick(<?= $m['id'] ?>,'<?= htmlspecialchars($m['username'], ENT_QUOTES) ?>')">
                            Remove
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tournaments -->
    <div class="tm-card tm-animate-in" style="--delay: 350ms;">
        <div class="tm-card-head">
            <span class="tm-card-title">Tournaments</span>
            <a href="tournaments.php" class="tm-card-link">All →</a>
        </div>
        <?php if (empty($tournaments)): ?>
            <div class="tm-empty-row">No tournaments entered yet.</div>
        <?php else: ?>
            <?php foreach ($tournaments as $t):
                $sm = [
                    'live'         => ['tm-dot--live',     'Live',         'tm-result--ongoing'],
                    'registration' => ['tm-dot--upcoming', 'Open',         'tm-result--soon'],
                    'upcoming'     => ['tm-dot--upcoming', 'Upcoming',     'tm-result--soon'],
                    'finished'     => ['tm-dot--done',     'Finished',     ''],
                ];
                [$dotCls, $stLabel, $resCls] = $sm[$t['status']] ?? ['tm-dot--done', ucfirst($t['status']), ''];
            ?>
            <div class="tm-tournament-row">
                <div class="tm-dot <?= $dotCls ?>"></div>
                <div class="tm-t-info">
                    <div class="tm-t-name"><?= htmlspecialchars($t['name']) ?></div>
                    <div class="tm-t-meta">
                        <?= htmlspecialchars($t['game_name'] ?? '') ?>
                        · <?= date('M Y', strtotime($t['start_date'])) ?>
                    </div>
                </div>
                <span class="tm-result <?= $resCls ?>"><?= $stLabel ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- /.tm-two-col -->

<!-- ── Last 5 Matches ────────────────────────────────────────────── -->
<?php if (!empty($recentMatches)): ?>
<div class="tm-section-header tm-animate-in" style="--delay: 400ms;">
    <span class="tm-section-title">Recent Matches</span>
    <span class="op-td-muted" style="font-size:12px;">Last <?= count($recentMatches) ?></span>
</div>
<div class="tm-matches-grid">
    <?php foreach ($recentMatches as $idx => $m):
        $isHome   = (int)$m['home_team_id'] === (int)$team['id'];
        $myScore  = $isHome ? (int)$m['score_team1'] : (int)$m['score_team2'];
        $oppScore = $isHome ? (int)$m['score_team2'] : (int)$m['score_team1'];
        $oppName  = $isHome ? $m['away_name']         : $m['home_name'];
        $won      = $myScore > $oppScore;
        $resCls   = $won ? 'win' : 'loss';
        $resLbl   = $won ? 'W'  : 'L';
    ?>
    <div class="tm-match-card" style="--delay:<?= 450 + ($idx * 60) ?>ms">
        <div class="tm-match-result-badge tm-match-result-badge--<?= $resCls ?>">
            <?= $resLbl ?>
        </div>
        <div class="tm-match-body">
            <div class="tm-match-vs">vs
                <span class="tm-match-opp"><?= htmlspecialchars($oppName) ?></span>
            </div>
            <div class="tm-match-score-row">
                <span class="tm-match-score tm-match-score--<?= $resCls ?>"><?= $myScore ?></span>
                <span class="tm-match-score-sep">:</span>
                <span class="tm-match-score tm-match-score--opp"><?= $oppScore ?></span>
            </div>
            <div class="tm-match-meta">
                <?= htmlspecialchars($m['tournament_name']) ?>
                <?php if ($m['stage']): ?> · <?= htmlspecialchars($m['stage']) ?><?php endif; ?>
            </div>
        </div>
        <?php if ($m['date']): ?>
        <div class="tm-match-date"><?= date('d M', strtotime($m['date'])) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="tm-section-header tm-animate-in" style="--delay: 400ms;">
    <span class="tm-section-title">Recent Matches</span>
</div>
<div class="tm-matches-empty tm-animate-in" style="--delay: 450ms;">
    No matches played yet. Enter a tournament to get started.
</div>
<?php endif; ?>

<?php endif; ?><!-- end has-team -->

</div><!-- /.team-page -->
</main>

<!-- Toast -->
<div id="tm-toast" class="tm-toast" role="alert" aria-live="polite"></div>

<!-- Custom Confirm -->
<div id="tm-confirm-overlay" class="tm-confirm-overlay" style="display:none;">
    <div class="tm-confirm-box">
        <div id="tm-confirm-title" class="tm-confirm-title">Confirm</div>
        <div id="tm-confirm-message" class="tm-confirm-message"></div>
        <div class="tm-confirm-actions">
            <button id="tm-confirm-cancel" class="tm-btn-ghost">Cancel</button>
            <button id="tm-confirm-ok" class="tm-btn-danger">Confirm</button>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>window.TEAM_INIT = <?= $jsInit ?>;</script>
<script src="../assets/js/team.js"></script>
</body>
</html>
