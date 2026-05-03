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
        JOIN   Player cap ON cap.id = t.captain_id
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
            LEFT   JOIN Game g  ON g.id = t.game_id
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

    default: err('Unknown action.', 400);
}