<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/FileUploader.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    exit(json_encode(['ok' => false, 'message' => 'Authentication required.']));
}

$userId = (int)$_SESSION['user_id'];
$action = trim($_REQUEST['action'] ?? '');

/* ── Helpers ──────────────────────────────────────────────────────── */
function ok(mixed $data = null, string $msg = ''): never
{
    echo json_encode(['ok' => true, 'message' => $msg, 'data' => $data]);
    exit;
}

function err(string $msg, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

function myTeam(PDO $pdo, int $uid): ?array
{
    $s = $pdo->prepare("
        SELECT t.*, cap.username AS captain_name
        FROM   Player p
        JOIN   Team   t   ON t.id  = p.team_id  AND t.deleted_at IS NULL
        JOIN   Player cap ON cap.id = t.captain_id AND cap.deleted_at IS NULL
        WHERE  p.id = ? AND p.deleted_at IS NULL
        LIMIT  1
    ");
    $s->execute([$uid]);
    return $s->fetch() ?: null;
}

function makeUploader(): FileUploader
{
    return new FileUploader(
        dirname(__DIR__) . '/assets/uploads',
        '../assets/uploads',
        'avatar'
    );
}

/* ── Router ───────────────────────────────────────────────────────── */
switch ($action) {

    /* ── GET TEAM ─────────────────────────────────────────────────── */
    case 'get_team': {
        $team = myTeam($pdo, $userId);
        if (!$team) { ok(['team' => null]); }

        $members = $pdo->prepare("
            SELECT id, username, role
            FROM   Player
            WHERE  team_id = ? AND deleted_at IS NULL
            ORDER BY (id = ?) DESC, username ASC
        ");
        $members->execute([$team['id'], $team['captain_id']]);

        $tours = $pdo->prepare("
            SELECT t.id, t.name, t.status, g.name AS game_name, t.start_date
            FROM   tournament_teams tt
            JOIN   Tournament t ON t.id = tt.tournament_id AND t.deleted_at IS NULL
            LEFT   JOIN Game g  ON g.id = t.game_id AND g.deleted_at IS NULL
            WHERE  tt.team_id = ?
            ORDER  BY t.start_date DESC
            LIMIT  5
        ");
        $tours->execute([$team['id']]);

        $stats = $pdo->prepare("
            SELECT COUNT(*) AS matches,
                   COALESCE(SUM(
                       CASE WHEN (home_team_id = :t AND score_team1 > score_team2)
                                 OR (away_team_id = :t AND score_team2 > score_team1)
                            THEN 1 ELSE 0 END
                   ), 0) AS wins
            FROM   Matches
            WHERE  (home_team_id = :t OR away_team_id = :t)
              AND  score_team1 IS NOT NULL
              AND  deleted_at  IS NULL
        ");
        $stats->execute([':t' => $team['id']]);

        ok([
            'team'        => $team,
            'members'     => $members->fetchAll(),
            'tournaments' => $tours->fetchAll(),
            'stats'       => $stats->fetch(),
        ]);
    }

    /* ── CREATE TEAM ──────────────────────────────────────────────── */
    case 'create_team': {
        if (myTeam($pdo, $userId)) err('You already belong to a team.');

        $name   = trim($_POST['name']        ?? '');
        $tag    = strtoupper(trim($_POST['tag']  ?? ''));
        $game   = trim($_POST['game']        ?? '');
        $region = trim($_POST['region']      ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if (!$name || !$tag || !$game) err('Name, tag and game are required.');
        if (mb_strlen($tag) < 2 || mb_strlen($tag) > 6) err('Tag must be 2–6 characters.');
        if (mb_strlen($name) > 50) err('Team name is too long (max 50).');

        try {
            $pdo->beginTransaction();

            $dup = $pdo->prepare("SELECT id FROM Team WHERE name = ? AND deleted_at IS NULL");
            $dup->execute([$name]);
            if ($dup->fetch()) { $pdo->rollBack(); err('A team with that name already exists.'); }

            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $pdo->prepare("
                INSERT INTO Team (name, tag, game, region, description, captain_id, invitation_code)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$name, $tag, $game, $region, $desc, $userId, $code]);
            $teamId = (int)$pdo->lastInsertId();

            $logoUrl = null;
            if (!empty($_FILES['avatar']['name'])) {
                $up = makeUploader();
                $logoUrl = $up->upload($_FILES['avatar'], 'teams', "team_{$teamId}");
                if ($up->hasErrors()) { error_log('Avatar: ' . $up->firstError()); $logoUrl = null; }
                if ($logoUrl) $pdo->prepare("UPDATE Team SET logo_url = ? WHERE id = ?")->execute([$logoUrl, $teamId]);
            }

            $pdo->prepare("UPDATE Player SET team_id = ? WHERE id = ?")->execute([$teamId, $userId]);
            $pdo->commit();

            ok(['team_id' => $teamId, 'logo_url' => $logoUrl], 'Team created successfully!');
        } catch (Exception $e) {
            $pdo->rollBack();
            err('Database error: ' . $e->getMessage());
        }
    }

    /* ── UPDATE TEAM ──────────────────────────────────────────────── */
    case 'update_team': {
        $team = myTeam($pdo, $userId);
        if (!$team || (int)$team['captain_id'] !== $userId) err('Only the captain can edit the team.');

        $name   = trim($_POST['name']        ?? '');
        $tag    = strtoupper(trim($_POST['tag']  ?? ''));
        $game   = trim($_POST['game']        ?? '');
        $region = trim($_POST['region']      ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if (!$name || !$tag) err('Name and tag are required.');
        if (mb_strlen($tag) < 2 || mb_strlen($tag) > 6) err('Tag must be 2–6 characters.');

        $logoUrl = $team['logo_url'];

        if (!empty($_FILES['avatar']['name'])) {
            $up  = makeUploader();
            $new = $up->upload($_FILES['avatar'], 'teams', "team_{$team['id']}");
            if ($up->hasErrors()) err($up->firstError());
            if ($new) {
                if ($logoUrl) $up->delete($logoUrl);
                $logoUrl = $new;
            }
        }

        try {
            $pdo->prepare("
                UPDATE Team SET name=?, tag=?, game=?, region=?, description=?, logo_url=?
                WHERE id = ? AND captain_id = ? AND deleted_at IS NULL
            ")->execute([$name, $tag, $game, $region, $desc, $logoUrl, $team['id'], $userId]);

            ok(['logo_url' => $logoUrl], 'Team updated!');
        } catch (Exception $e) { err('Database error.'); }
    }

    /* ── REMOVE AVATAR ────────────────────────────────────────────── */
    case 'remove_avatar': {
        $team = myTeam($pdo, $userId);
        if (!$team || (int)$team['captain_id'] !== $userId) err('Permission denied.');
        if ($team['logo_url']) makeUploader()->delete($team['logo_url']);
        $pdo->prepare("UPDATE Team SET logo_url = NULL WHERE id = ?")->execute([$team['id']]);
        ok([], 'Avatar removed.');
    }

    /* ── KICK MEMBER ──────────────────────────────────────────────── */
    case 'kick_member': {
        $team = myTeam($pdo, $userId);
        if (!$team || (int)$team['captain_id'] !== $userId) err('Permission denied.');

        $kickId = (int)($_POST['kick_id'] ?? 0);
        if (!$kickId || $kickId === $userId) err('Invalid target.');

        $r = $pdo->prepare("UPDATE Player SET team_id = NULL WHERE id = ? AND team_id = ?");
        $r->execute([$kickId, $team['id']]);
        if ($r->rowCount() === 0) err('Member not found in this team.');

        ok(['kicked_id' => $kickId], 'Member removed from team.');
    }

    /* ── LEAVE TEAM ───────────────────────────────────────────────── */
    case 'leave_team': {
        $team = myTeam($pdo, $userId);
        if (!$team) err('You are not in a team.');

        try {
            $pdo->beginTransaction();

            if ((int)$team['captain_id'] === $userId) {
                $nxt = $pdo->prepare("SELECT id FROM Player WHERE team_id = ? AND id != ? AND deleted_at IS NULL LIMIT 1");
                $nxt->execute([$team['id'], $userId]);
                $nc  = $nxt->fetchColumn();
                if ($nc) {
                    $pdo->prepare("UPDATE Team SET captain_id = ? WHERE id = ?")->execute([$nc, $team['id']]);
                } else {
                    $pdo->prepare("UPDATE Team SET deleted_at = NOW() WHERE id = ?")->execute([$team['id']]);
                }
            }

            $pdo->prepare("UPDATE Player SET team_id = NULL WHERE id = ?")->execute([$userId]);
            $pdo->commit();
            ok([], 'You left the team.');
        } catch (Exception $e) {
            $pdo->rollBack();
            err('Database error.');
        }
    }

    /* ── SEND INVITE ──────────────────────────────────────────────────── */
    case 'send_invite': {
        $team = myTeam($pdo, $userId);
        if (!$team || (int)$team['captain_id'] !== $userId) err('Only the captain can send invites.');

        // Team size guard
        $memberCount = (int)$pdo->prepare("SELECT COUNT(*) FROM Player WHERE team_id = ? AND deleted_at IS NULL")
            ->execute([$team['id']]) ? $pdo->query("SELECT COUNT(*) FROM Player WHERE team_id = {$team['id']} AND deleted_at IS NULL")->fetchColumn() : 0;

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM Player WHERE team_id = ? AND deleted_at IS NULL");
        $cntStmt->execute([$team['id']]);
        if ((int)$cntStmt->fetchColumn() >= 6) err('Your team is full (max 6 members).');

        $username = trim($_POST['username'] ?? '');
        if (!$username) err('Username is required.');

        // Find target player
        $targetStmt = $pdo->prepare("SELECT id, email, username, team_id FROM Player WHERE username = ? AND deleted_at IS NULL LIMIT 1");
        $targetStmt->execute([$username]);
        $target = $targetStmt->fetch();

        if (!$target)              err('Player not found.');
        if ($target['id'] == $userId) err('You cannot invite yourself.');
        if ($target['team_id'])    err("{$target['username']} is already in a team.");

        // Duplicate pending invite check
        $dupStmt = $pdo->prepare("
            SELECT id FROM Invitations
            WHERE team_id = ? AND receiver_id = ? AND status = 'pending' AND deleted_at IS NULL
        ");
        $dupStmt->execute([$team['id'], $target['id']]);
        if ($dupStmt->fetch()) err('An invitation is already pending for this player.');

        try {
            $pdo->beginTransaction();

            // Create invitation
            $insStmt = $pdo->prepare("
                INSERT INTO Invitations (team_id, sender_id, receiver_id, status, sent_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $insStmt->execute([$team['id'], $userId, $target['id']]);
            $invId = (int)$pdo->lastInsertId();

            // Generate signed token — HMAC-SHA256(invId|receiverId|teamId, APP_SECRET)
            $secret = $_ENV['APP_SECRET'] ?? 'ikarus-invite-secret-2026';
            $payload = "{$invId}|{$target['id']}|{$team['id']}";
            $token   = hash_hmac('sha256', $payload, $secret);

            $pdo->prepare("UPDATE Invitations SET token = ? WHERE id = ?")->execute([$token, $invId]);
            $pdo->commit();

            // Send email (non-blocking — failure doesn't roll back)
            try {
                require_once __DIR__ . '/../includes/mailer.php';
                $appUrl  = rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/');
                $invUrl  = "{$appUrl}/Ikarus/pages/team.php?inv={$invId}.{$token}";
                $html    = _buildInviteEmail($team['name'], getUsername(), $target['username'], $invUrl);
                sendMail($target['email'], $target['username'], "You've been invited to join {$team['name']} — Ikarus", $html);
            } catch (Throwable $mailErr) {
                error_log('Invite mailer: ' . $mailErr->getMessage());
            }

            ok(['invite_id' => $invId], "Invitation sent to {$target['username']}!");
        } catch (Exception $e) {
            $pdo->rollBack();
            err('Database error: ' . $e->getMessage());
        }
    }

    /* ── RESPOND TO INVITE ────────────────────────────────────────────── */
    case 'respond_invite': {
        if (myTeam($pdo, $userId)) err('You are already in a team. Leave it first.');

        $invId  = (int)($_POST['invite_id'] ?? 0);
        $accept = ($_POST['response'] ?? '') === 'accept';

        if (!$invId) err('Invalid invitation.');

        // Load and verify
        $invStmt = $pdo->prepare("
            SELECT i.*, t.name AS team_name, t.id AS t_id
            FROM Invitations i
            JOIN Team t ON t.id = i.team_id AND t.deleted_at IS NULL
            WHERE i.id = ? AND i.receiver_id = ? AND i.status = 'pending' AND i.deleted_at IS NULL
            LIMIT 1
        ");
        $invStmt->execute([$invId, $userId]);
        $inv = $invStmt->fetch();

        if (!$inv) err('Invitation not found or already responded.');

        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                UPDATE Invitations SET status = ?, responded_at = NOW() WHERE id = ?
            ")->execute([$accept ? 'accepted' : 'declined', $invId]);

            if ($accept) {
                // Re-check team size
                $cntS = $pdo->prepare("SELECT COUNT(*) FROM Player WHERE team_id = ? AND deleted_at IS NULL");
                $cntS->execute([$inv['t_id']]);
                if ((int)$cntS->fetchColumn() >= 6) {
                    $pdo->rollBack();
                    err('The team is now full.');
                }
                $pdo->prepare("UPDATE Player SET team_id = ? WHERE id = ?")->execute([$inv['t_id'], $userId]);
            }

            $pdo->commit();
            ok(['accepted' => $accept, 'team_name' => $inv['team_name']],
               $accept ? "You joined {$inv['team_name']}!" : 'Invitation declined.');
        } catch (Exception $e) {
            $pdo->rollBack();
            err('Database error.');
        }
    }

    /* ── GET PENDING INVITES (for current user) ───────────────────────── */
    case 'get_invites': {
    $stmt = $pdo->prepare("
        SELECT i.id, i.team_id, i.sent_at,
               t.name AS team_name, t.tag, t.logo_url,
               p.username AS sender_name
        FROM   Invitations i
        JOIN   Team   t ON t.id = i.team_id   AND t.deleted_at IS NULL
        JOIN   Player p ON p.id = i.sender_id AND p.deleted_at IS NULL
        WHERE  i.receiver_id = ? AND i.status = 'pending' AND i.deleted_at IS NULL
        ORDER  BY i.sent_at DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    ok(['invites' => $rows, 'count' => count($rows)]);
    }

    /* ── GLOBAL NOTIFICATION COUNT (header badge) ─────────────────────── */
    case 'notif_count': {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM Invitations
            WHERE receiver_id = ? AND status = 'pending' AND deleted_at IS NULL
        ");
        $stmt->execute([$userId]);
        ok(['count' => (int)$stmt->fetchColumn()]);
    }

    default: err('Unknown action.', 400);

}

/* ── Email Template ───────────────────────────────────────────────── */
function _buildInviteEmail(string $teamName, string $senderName, string $recipientName, string $invUrl): string {
    $app = $_ENV['APP_NAME'] ?? 'Ikarus';
    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#1e1e1e;font-family:Arial,sans-serif;">
      <div style="max-width:480px;margin:40px auto;background:#2f2f2f;border-radius:12px;
                  border:1px solid #3a3a3a;overflow:hidden;">
        <div style="background:#272727;padding:22px 30px;border-bottom:1px solid #3a3a3a;
                    display:flex;align-items:center;gap:10px;">
          <span style="color:#82c0cc;font-size:17px;font-weight:700;letter-spacing:4px;">{$app}</span>
        </div>
        <div style="padding:30px;">
          <div style="font-size:28px;margin-bottom:14px;">🎮</div>
          <h2 style="color:#ede7e3;margin:0 0 10px;font-size:18px;">Team Invitation</h2>
          <p style="color:#a09d99;line-height:1.7;margin:0 0 24px;">
            Hi <strong style="color:#ede7e3;">{$recipientName}</strong>,<br>
            <strong style="color:#489fb5;">{$senderName}</strong> has invited you to join
            the team <strong style="color:#ede7e3;">{$teamName}</strong> on {$app}.<br>
            Click the button below to view and respond to the invitation.
          </p>
          <a href="{$invUrl}"
             style="display:inline-block;background:#489fb5;color:#272727;padding:12px 28px;
                    border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:1px;">
            View Invitation
          </a>
          <p style="color:#6b6880;font-size:12px;margin:22px 0 0;line-height:1.6;">
            This invitation expires in <strong>7 days</strong>.<br>
            If you don't recognize this, you can safely ignore it.
          </p>
        </div>
        <div style="padding:14px 30px;border-top:1px solid #3a3a3a;text-align:center;">
          <span style="color:#6b6880;font-size:11px;">&copy; 2026 {$app} Tournament Platform</span>
        </div>
      </div>
    </body>
    </html>
    HTML;
}