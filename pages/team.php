<?php
require_once '../includes/session.php';

if (!isLoggedIn()) {
    header('Location: /pages/index.php?modal=login');
    exit;
}

$userId = $_SESSION['user_id'];

function getTeamMaxSize(PDO $pdo, int $teamId): int {
    try {
        $s = $pdo->prepare("SELECT max_size FROM Team WHERE id = ?");
        $s->execute([$teamId]);
        $r = $s->fetchColumn();
        return $r ? (int)$r : 6;
    } catch (Exception $e) { return 6; }
}

function fetchTeam(PDO $pdo, int $userId): array|false {
    try {
        $s = $pdo->prepare("
            SELECT t.*, p2.username AS captain_name
            FROM Team t
            JOIN Player p ON p.team_id = t.id
            LEFT JOIN Player p2 ON p2.id = t.captain_id
            WHERE p.id = ?
        ");
        $s->execute([$userId]);
        return $s->fetch();
    } catch (Exception $e) { return false; }
}

$team      = fetchTeam($pdo, $userId);
$hasTeam   = (bool)$team;
$isCaptain = $hasTeam && isset($team['captain_id']) && $team['captain_id'] == $userId;

// ── AJAX ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action    = $_POST['action'] ?? '';
    $team      = fetchTeam($pdo, $userId);
    $isCaptain = $team && $team['captain_id'] == $userId;

    if ($action === 'create_team') {
        $name   = trim($_POST['name'] ?? '');
        $tag    = strtoupper(trim($_POST['tag'] ?? ''));
        $game   = trim($_POST['game'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        if (empty($name) || empty($tag) || empty($game)) {
            echo json_encode(['status'=>'error','message'=>'Team name, tag and game are required.']); exit;
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO Team (name,tag,game,region,description,captain_id,max_size) VALUES(?,?,?,?,?,?,6)")
                ->execute([$name,$tag,$game,$region,$desc,$userId]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE Player SET team_id=? WHERE id=?")->execute([$newId,$userId]);
            $pdo->commit();
            echo json_encode(['status'=>'success','message'=>'Team created!','reload'=>true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status'=>'error','message'=>'Database error.']);
        }
        exit;
    }

    if ($action === 'update_team' && $isCaptain) {
        $name   = trim($_POST['name'] ?? '');
        $tag    = strtoupper(trim($_POST['tag'] ?? ''));
        $game   = trim($_POST['game'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        if (empty($name) || empty($tag)) {
            echo json_encode(['status'=>'error','message'=>'Name and tag are required.']); exit;
        }
        try {
            $pdo->prepare("UPDATE Team SET name=?,tag=?,game=?,region=?,description=? WHERE id=? AND captain_id=?")
                ->execute([$name,$tag,$game,$region,$desc,$team['id'],$userId]);
            echo json_encode(['status'=>'success','message'=>'Team updated!',
                'data'=>['name'=>$name,'tag'=>$tag,'game'=>$game,'region'=>$region,'desc'=>$desc]]);
        } catch (Exception $e) {
            echo json_encode(['status'=>'error','message'=>'Database error.']);
        }
        exit;
    }

    if ($action === 'invite' && $isCaptain) {
        $username = trim($_POST['invite_username'] ?? '');
        if (empty($username)) { echo json_encode(['status'=>'error','message'=>'Username cannot be empty.']); exit; }
        try {
            $maxSize = getTeamMaxSize($pdo, $team['id']);
            $stmtC   = $pdo->prepare("SELECT COUNT(*) FROM Player WHERE team_id=?");
            $stmtC->execute([$team['id']]);
            if ((int)$stmtC->fetchColumn() >= $maxSize) {
                echo json_encode(['status'=>'error','message'=>"Team is full (max {$maxSize} members)."]); exit;
            }
            $stmtF = $pdo->prepare("SELECT id,username,team_id FROM Player WHERE username=?");
            $stmtF->execute([$username]);
            $target = $stmtF->fetch();
            if (!$target)            { echo json_encode(['status'=>'error','message'=>'User not found.']); exit; }
            if ($target['team_id']) { echo json_encode(['status'=>'error','message'=>'User already has a team.']); exit; }
            $pdo->prepare("UPDATE Player SET team_id=? WHERE id=?")->execute([$team['id'],$target['id']]);
            echo json_encode(['status'=>'success','message'=>$target['username'].' added!',
                'member'=>['id'=>$target['id'],'username'=>$target['username'],'role'=>'']]);
        } catch (Exception $e) {
            echo json_encode(['status'=>'error','message'=>'Database error.']);
        }
        exit;
    }

    if ($action === 'kick' && $isCaptain) {
        $kickId = (int)($_POST['kick_id'] ?? 0);
        if (!$kickId || $kickId === $userId) { echo json_encode(['status'=>'error','message'=>'Invalid request.']); exit; }
        try {
            $pdo->prepare("UPDATE Player SET team_id=NULL WHERE id=? AND team_id=?")->execute([$kickId,$team['id']]);
            echo json_encode(['status'=>'success','message'=>'Member removed.','kick_id'=>$kickId]);
        } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>'Database error.']); }
        exit;
    }

    if ($action === 'update_role' && $isCaptain) {
        $targetId = (int)($_POST['member_id'] ?? 0);
        $role     = trim($_POST['role'] ?? '');
        if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Invalid request.']); exit; }
        try {
            $chk = $pdo->prepare("SELECT id FROM Player WHERE id=? AND team_id=?");
            $chk->execute([$targetId,$team['id']]);
            if (!$chk->fetch()) { echo json_encode(['status'=>'error','message'=>'Player not in this team.']); exit; }
            $pdo->prepare("UPDATE Player SET role=? WHERE id=?")->execute([$role,$targetId]);
            echo json_encode(['status'=>'success','message'=>'Role updated!','role'=>$role]);
        } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>'Database error.']); }
        exit;
    }

    if ($action === 'leave') {
        try {
            if ($isCaptain) {
                $stmtO = $pdo->prepare("SELECT id FROM Player WHERE team_id=? AND id!=? LIMIT 1");
                $stmtO->execute([$team['id'],$userId]);
                $next = $stmtO->fetch();
                if ($next) $pdo->prepare("UPDATE Team SET captain_id=? WHERE id=?")->execute([$next['id'],$team['id']]);
                else        $pdo->prepare("DELETE FROM Team WHERE id=?")->execute([$team['id']]);
            }
            $pdo->prepare("UPDATE Player SET team_id=NULL WHERE id=?")->execute([$userId]);
            echo json_encode(['status'=>'success','message'=>'You left the team.','reload'=>true]);
        } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>'Database error.']); }
        exit;
    }

    echo json_encode(['status'=>'error','message'=>'Unknown action.']); exit;
}

// ── Sayfa verisi ─────────────────────────────────────────────────────────
$members=[];$tournaments=[];$recentMatches=[];$stats=['matches'=>0,'wins'=>0,'tournaments'=>0];
if ($hasTeam) {
    $maxSize = getTeamMaxSize($pdo,$team['id']);
    try { $s=$pdo->prepare("SELECT id,username,role FROM Player WHERE team_id=? ORDER BY (id=?) DESC,username ASC"); $s->execute([$team['id'],$team['captain_id']]); $members=$s->fetchAll(); } catch(Exception $e){}
    try { $s=$pdo->prepare("SELECT t.id,t.name,t.status,g.name AS game_name,t.start_date FROM tournament_team tt JOIN Tournament t ON t.id=tt.tournament_id LEFT JOIN Game g ON g.id=t.game_id WHERE tt.team_id=? ORDER BY t.start_date DESC LIMIT 5"); $s->execute([$team['id']]); $tournaments=$s->fetchAll(); } catch(Exception $e){}
    try {
        $s=$pdo->prepare("SELECT m.id,m.stage,m.date,m.score_team1,m.score_team2,t1.name AS home_team,t2.name AS away_team,m.home_team_id,m.away_team_id,tour.name AS tournament_name FROM Matches m JOIN Team t1 ON t1.id=m.home_team_id JOIN Team t2 ON t2.id=m.away_team_id JOIN Tournament tour ON tour.id=m.tournament_id WHERE (m.home_team_id=:tid OR m.away_team_id=:tid) AND m.score_team1 IS NOT NULL ORDER BY m.date DESC LIMIT 5");
        $s->execute([':tid'=>$team['id']]); $recentMatches=$s->fetchAll();
    } catch(Exception $e){}
    try { $s=$pdo->prepare("SELECT COUNT(*) AS matches, SUM((home_team_id=:tid AND score_team1>score_team2) OR (away_team_id=:tid AND score_team2>score_team1)) AS wins FROM Matches WHERE (home_team_id=:tid OR away_team_id=:tid) AND score_team1 IS NOT NULL"); $s->execute([':tid'=>$team['id']]); $st=$s->fetch(); $stats['matches']=(int)$st['matches']; $stats['wins']=(int)$st['wins']; $stats['tournaments']=count($tournaments); } catch(Exception $e){}
} else { $maxSize=6; }

$gamesList=[];
try { $gamesList=$pdo->query("SELECT name FROM Game ORDER BY name")->fetchAll(); } catch(Exception $e){}
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

<div id="tm-toast" class="tm-toast" aria-live="polite"></div>

<?php if (!$hasTeam): ?>
<!-- ══════ NO TEAM ══════ -->
<div class="tm-empty-wrap">
    <div class="tm-empty-box">
        <div class="tm-empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <h2 class="tm-empty-title">You don't have a team yet</h2>
        <p class="tm-empty-sub">Create a team and start competing in tournaments.</p>
        <button class="tm-btn-primary" id="showCreateBtn">+ Create Team</button>
        <div id="createPanel" class="tm-slide-panel" style="display:none;margin-top:24px;text-align:left">
            <div class="tm-form-grid">
                <div class="tm-field"><label class="tm-label">Team Name *</label><input class="tm-input" id="c_name" type="text" placeholder="NightFall"></div>
                <div class="tm-field"><label class="tm-label">Tag *</label><input class="tm-input" id="c_tag" type="text" placeholder="NX" maxlength="4"></div>
                <div class="tm-field">
                    <label class="tm-label">Game *</label>
                    <select class="tm-select" id="c_game">
                        <option value="">Select...</option>
                        <?php foreach ($gamesList as $g): ?><option value="<?=htmlspecialchars($g['name'])?>"><?=htmlspecialchars($g['name'])?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="tm-field"><label class="tm-label">Region</label><select class="tm-select" id="c_region"><option value="">Loading countries...</option></select></div>
                <div class="tm-field tm-field--full"><label class="tm-label">Description</label><textarea class="tm-textarea" id="c_desc" rows="2" placeholder="Tell us about your team..."></textarea></div>
            </div>
            <div class="tm-form-actions">
                <button class="tm-btn-ghost" id="cancelCreateBtn">Cancel</button>
                <button class="tm-btn-primary" id="submitCreateBtn">Create Team</button>
            </div>
        </div>
    </div>
</div>

<?php else:
    $winRate = $stats['matches'] > 0 ? round($stats['wins']/$stats['matches']*100) : 0;
?>
<!-- ══════ HAS TEAM ══════ -->
<div class="tm-header">
    <div class="tm-avatar" id="tm-avatar-text"><?=htmlspecialchars(strtoupper(substr($team['tag']??'T',0,2)))?></div>
    <div class="tm-info">
        <div class="tm-name" id="tm-display-name"><?=htmlspecialchars($team['name']??'')?></div>
        <div class="tm-tag">#<span id="tm-display-tag"><?=htmlspecialchars($team['tag']??'')?></span> · <span id="tm-display-game"><?=htmlspecialchars($team['game']??'')?></span></div>
        <div class="tm-desc" id="tm-display-desc"><?=htmlspecialchars($team['description']??'')?></div>
        <div class="tm-meta">
            <div class="tm-meta-item">Region <span id="tm-display-region"><?=htmlspecialchars($team['region']??'—')?></span></div>
            <div class="tm-meta-item">Members <span id="tm-member-count"><?=count($members)?></span> / <span id="tm-max-size"><?=$maxSize?></span></div>
        </div>
    </div>
    <div class="tm-actions">
        <?php if ($isCaptain): ?>
        <button class="tm-btn-primary" onclick="togglePanel('editPanel')">Edit Team</button>
        <button class="tm-btn-outline" onclick="togglePanel('invitePanel')">Invite Member</button>
        <?php endif; ?>
        <button class="tm-btn-danger" id="leaveBtn">Leave Team</button>
    </div>
</div>

<div class="tm-stats">
    <div class="tm-stat"><div class="tm-stat-label">Total Matches</div><div class="tm-stat-val"><?=$stats['matches']?></div></div>
    <div class="tm-stat"><div class="tm-stat-label">Wins</div><div class="tm-stat-val"><?=$stats['wins']?></div><div class="tm-stat-sub"><?=$winRate?>% win rate</div></div>
    <div class="tm-stat"><div class="tm-stat-label">Tournaments</div><div class="tm-stat-val"><?=$stats['tournaments']?></div></div>
    <div class="tm-stat"><div class="tm-stat-label">Members</div><div class="tm-stat-val"><?=count($members)?></div><div class="tm-stat-sub">Max <?=$maxSize?></div></div>
</div>

<?php if ($isCaptain): ?>
<div id="invitePanel" class="tm-panel tm-panel--accent tm-slide-panel" style="display:none">
    <div class="tm-panel-title">Invite Member</div>
    <div class="tm-invite-form">
        <input class="tm-input" id="inviteInput" type="text" placeholder="Username">
        <button class="tm-btn-primary" id="inviteBtn">Add</button>
    </div>
    <div class="tm-panel-note" id="inviteNote">Current members: <?=count($members)?>/<?=$maxSize?></div>
</div>

<div id="editPanel" class="tm-panel tm-slide-panel" style="display:none">
    <div class="tm-panel-title">Edit Team Info</div>
    <div class="tm-form-grid">
        <div class="tm-field"><label class="tm-label">Team Name</label><input class="tm-input" id="e_name" type="text" value="<?=htmlspecialchars($team['name']??'')?>"></div>
        <div class="tm-field"><label class="tm-label">Tag</label><input class="tm-input" id="e_tag" type="text" maxlength="4" value="<?=htmlspecialchars($team['tag']??'')?>"></div>
        <div class="tm-field">
            <label class="tm-label">Game</label>
            <select class="tm-select" id="e_game">
    <?php foreach ($gamesList as $g): ?>
            <option value="<?= htmlspecialchars($g['name']) ?>" <?= ($g['name'] === ($team['game'] ?? '')) ? 'selected' : '' ?>>
              <?= htmlspecialchars($g['name']) ?>
             </option>
        <?php endforeach; ?>
    </select>
        </div>
        <div class="tm-field"><label class="tm-label">Region</label><select class="tm-select" id="e_region"><option value="">Loading...</option></select></div>
        <div class="tm-field tm-field--full"><label class="tm-label">Description</label><textarea class="tm-textarea" id="e_desc" rows="2"><?=htmlspecialchars($team['description']??'')?></textarea></div>
    </div>
    <div class="tm-form-actions">
        <button class="tm-btn-ghost" onclick="togglePanel('editPanel')">Cancel</button>
        <button class="tm-btn-primary" id="saveEditBtn">Save</button>
    </div>
</div>
<?php endif; ?>

<div class="tm-two-col">
    <div class="tm-card">
        <div class="tm-card-head"><span class="tm-card-title">Members</span></div>
        <div id="membersList">
        <?php foreach ($members as $m):
            $isMC = ($m['id'] == $team['captain_id']);
        ?>
        <div class="tm-member-row" data-member-id="<?=$m['id']?>">
            <div class="tm-m-avatar <?=$isMC?'tm-m-avatar--captain':''?>"><?=strtoupper(substr($m['username'],0,2))?></div>
            <div class="tm-m-info">
                <div class="tm-m-name">
                    <?=htmlspecialchars($m['username'])?>
                    <?php if ($m['id']==$userId):?><span class="tm-you">You</span><?php endif;?>
                </div>
                <div class="tm-m-role">
                    <?php if ($isCaptain && !$isMC):?>
                        <input class="tm-role-input" type="text" value="<?=htmlspecialchars($m['role']??'')?>" placeholder="Role (e.g. Duelist)" data-member-id="<?=$m['id']?>">
                    <?php else:?>
                        <span><?=htmlspecialchars($m['role']??($isMC?'Captain':'—'))?></span>
                    <?php endif;?>
                </div>
            </div>
            <?php if ($isMC):?>
                <span class="tm-badge tm-badge--captain">Captain</span>
            <?php else:?>
                <span class="tm-badge tm-badge--member">Member</span>
                <?php if ($isCaptain):?>
                    <button class="tm-btn-kick" data-kick-id="<?=$m['id']?>" data-name="<?=htmlspecialchars($m['username'])?>">Kick</button>
                <?php endif;?>
            <?php endif;?>
        </div>
        <?php endforeach;?>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        <!-- Tournaments -->
        <div class="tm-card">
            <div class="tm-card-head"><span class="tm-card-title">Tournaments</span><a href="tournaments.php" class="tm-card-link">All →</a></div>
            <?php if (empty($tournaments)):?>
                <div class="tm-empty-row">No tournaments yet.</div>
            <?php else:
                $sm=['live'=>['tm-dot--live','Ongoing','tm-result--ongoing'],'registration'=>['tm-dot--upcoming','Registration','tm-result--soon'],'upcoming'=>['tm-dot--upcoming','Upcoming','tm-result--soon'],'finished'=>['tm-dot--done','Finished','']];
                foreach ($tournaments as $t): $st=$sm[$t['status']]??['tm-dot--done',$t['status'],''];?>
            <div class="tm-tournament-row">
                <div class="tm-dot <?=$st[0]?>"></div>
                <div class="tm-t-info"><div class="tm-t-name"><?=htmlspecialchars($t['name'])?></div><div class="tm-t-meta"><?=htmlspecialchars($t['game_name']??'')?> · <?=date('M Y',strtotime($t['start_date']))?></div></div>
                <span class="tm-result <?=$st[2]?>"><?=$st[1]?></span>
            </div>
            <?php endforeach; endif;?>
        </div>

        <!-- Last 5 Matches -->
        <div class="tm-card">
            <div class="tm-card-head"><span class="tm-card-title">Last 5 Matches</span></div>
            <?php if (empty($recentMatches)):?>
                <div class="tm-empty-row">No matches played yet.</div>
            <?php else: foreach ($recentMatches as $m):
                $isHome=$m['home_team_id']==$team['id'];
                $myScore=$isHome?$m['score_team1']:$m['score_team2'];
                $oppScore=$isHome?$m['score_team2']:$m['score_team1'];
                $opponent=$isHome?$m['away_team']:$m['home_team'];
                $won=$myScore>$oppScore;?>
            <div class="tm-match-row">
                <span class="tm-match-result <?=$won?'tm-match-win':'tm-match-loss'?>"><?=$won?'W':'L'?></span>
                <div class="tm-match-info">
                    <div class="tm-match-vs">vs <?=htmlspecialchars($opponent)?></div>
                    <div class="tm-match-meta"><?=htmlspecialchars($m['tournament_name'])?> · <?=htmlspecialchars($m['stage']??'')?></div>
                </div>
                <div class="tm-match-score"><?=$myScore?> — <?=$oppScore?></div>
            </div>
            <?php endforeach; endif;?>
        </div>
    </div>
</div>

<?php endif; ?>
</div>
</main>
<?php require_once '../includes/footer.php'; ?>

<script>
const IS_CAPTAIN     = <?=$isCaptain?'true':'false'?>;
const MAX_SIZE       = <?=$maxSize?>;
const CURRENT_REGION = <?=json_encode($team['region']??'')?>;

function showToast(msg, type='success') {
    const t = document.getElementById('tm-toast');
    t.textContent = msg;
    t.className = `tm-toast tm-toast--${type} tm-toast--show`;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('tm-toast--show'), 3500);
}

async function tmAjax(data) {
    const fd = new FormData();
    for (const [k,v] of Object.entries(data)) fd.append(k,v);
    const res = await fetch(window.location.href, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
    return res.json();
}

function slideDown(el) {
    el.style.display='block'; el.style.overflow='hidden'; el.style.height='0'; el.style.opacity='0';
    const h=el.scrollHeight;
    el.style.transition='height 0.3s ease,opacity 0.3s ease';
    requestAnimationFrame(()=>{ el.style.height=h+'px'; el.style.opacity='1'; });
    setTimeout(()=>{ el.style.height=''; el.style.overflow=''; },320);
}
function slideUp(el) {
    el.style.height=el.scrollHeight+'px'; el.style.overflow='hidden';
    el.style.transition='height 0.28s ease,opacity 0.28s ease';
    requestAnimationFrame(()=>{ el.style.height='0'; el.style.opacity='0'; });
    setTimeout(()=>{ el.style.display='none'; el.style.height=''; el.style.opacity=''; el.style.overflow=''; },300);
}
function togglePanel(id) {
    ['editPanel','invitePanel'].forEach(p=>{ if(p!==id){ const e=document.getElementById(p); if(e) slideUp(e); } });
    const t=document.getElementById(id); if(!t) return;
    t.style.display==='none' ? slideDown(t) : slideUp(t);
}

async function loadCountries(selId, current) {
    const sel=document.getElementById(selId); if(!sel) return;
    try {
        const res=await fetch('https://restcountries.com/v3.1/all?fields=name');
        const data=await res.json();
        const names=data.map(c=>c.name.common).sort();
        sel.innerHTML='<option value="">Select region...</option>';
        names.forEach(n=>{ const o=document.createElement('option'); o.value=n; o.textContent=n; if(n===current) o.selected=true; sel.appendChild(o); });
    } catch(e){ sel.innerHTML='<option value="">Could not load</option>'; }
}

function updateMemberCount() {
    const rows=document.querySelectorAll('#membersList .tm-member-row');
    const c=document.getElementById('tm-member-count');
    const n=document.getElementById('inviteNote');
    if(c) c.textContent=rows.length;
    if(n) n.textContent=`Current members: ${rows.length}/${MAX_SIZE}`;
}

function appendMemberRow(member) {
    const initials=member.username.substring(0,2).toUpperCase();
    const html=`
    <div class="tm-member-row" data-member-id="${member.id}">
        <div class="tm-m-avatar">${initials}</div>
        <div class="tm-m-info">
            <div class="tm-m-name">${member.username}</div>
            <div class="tm-m-role">${IS_CAPTAIN?`<input class="tm-role-input" type="text" value="" placeholder="Role (e.g. Duelist)" data-member-id="${member.id}">`:'<span>—</span>'}</div>
        </div>
        <span class="tm-badge tm-badge--member">Member</span>
        ${IS_CAPTAIN?`<button class="tm-btn-kick" data-kick-id="${member.id}" data-name="${member.username}">Kick</button>`:''}
    </div>`;
    document.getElementById('membersList').insertAdjacentHTML('beforeend',html);
    bindRoleInputs(); bindKickButtons(); updateMemberCount();
}

function bindRoleInputs() {
    document.querySelectorAll('.tm-role-input').forEach(input=>{
        if(input.dataset.bound) return; input.dataset.bound='1';
        let timer;
        input.addEventListener('input',()=>{
            clearTimeout(timer);
            timer=setTimeout(async()=>{
                const data=await tmAjax({action:'update_role',member_id:input.dataset.memberId,role:input.value});
                showToast(data.status==='success'?'Role updated!':data.message, data.status==='success'?'success':'error');
            },700);
        });
    });
}

function bindKickButtons() {
    document.querySelectorAll('.tm-btn-kick').forEach(btn=>{
        if(btn.dataset.bound) return; btn.dataset.bound='1';
        btn.addEventListener('click',async()=>{
            if(!confirm(`Remove ${btn.dataset.name} from team?`)) return;
            const data=await tmAjax({action:'kick',kick_id:btn.dataset.kickId});
            if(data.status==='success'){
                const row=document.querySelector(`.tm-member-row[data-member-id="${data.kick_id}"]`);
                if(row){ row.style.transition='opacity 0.3s'; row.style.opacity='0'; setTimeout(()=>row.remove(),300); }
                updateMemberCount(); showToast(data.message);
            } else showToast(data.message,'error');
        });
    });
}

document.addEventListener('DOMContentLoaded',()=>{
    bindRoleInputs(); bindKickButtons();

    // Create panel
    const showCreateBtn=document.getElementById('showCreateBtn');
    const cancelCreateBtn=document.getElementById('cancelCreateBtn');
    const createPanel=document.getElementById('createPanel');
    if(showCreateBtn){
        showCreateBtn.addEventListener('click',()=>{ slideDown(createPanel); showCreateBtn.style.display='none'; loadCountries('c_region',''); });
    }
    if(cancelCreateBtn){
        cancelCreateBtn.addEventListener('click',()=>{ slideUp(createPanel); setTimeout(()=>{ if(showCreateBtn) showCreateBtn.style.display=''; },300); });
    }

    const submitCreateBtn=document.getElementById('submitCreateBtn');
    if(submitCreateBtn){
        submitCreateBtn.addEventListener('click',async()=>{
            const name=document.getElementById('c_name').value.trim();
            const tag=document.getElementById('c_tag').value.trim();
            const game=document.getElementById('c_game').value;
            if(!name||!tag||!game){ showToast('Name, tag and game are required.','error'); return; }
            submitCreateBtn.disabled=true; submitCreateBtn.textContent='Creating...';
            const data=await tmAjax({action:'create_team',name,tag,game,region:document.getElementById('c_region').value,description:document.getElementById('c_desc').value});
            if(data.status==='success'){ showToast(data.message); setTimeout(()=>location.reload(),800); }
            else{ showToast(data.message,'error'); submitCreateBtn.disabled=false; submitCreateBtn.textContent='Create Team'; }
        });
    }

    // Edit panel — countries lazy load
    const editPanel=document.getElementById('editPanel');
    if(editPanel){
        let loaded=false;
        const obs=new MutationObserver(()=>{
            if(editPanel.style.display!=='none'&&!loaded){ loaded=true; loadCountries('e_region',CURRENT_REGION); }
        });
        obs.observe(editPanel,{attributes:true,attributeFilter:['style']});
    }

    const saveEditBtn=document.getElementById('saveEditBtn');
    if(saveEditBtn){
        saveEditBtn.addEventListener('click',async()=>{
            const name=document.getElementById('e_name').value.trim();
            const tag=document.getElementById('e_tag').value.trim();
            if(!name||!tag){ showToast('Name and tag are required.','error'); return; }
            saveEditBtn.disabled=true; saveEditBtn.textContent='Saving...';
            const data=await tmAjax({action:'update_team',name,tag,game:document.getElementById('e_game').value,region:document.getElementById('e_region').value,description:document.getElementById('e_desc').value});
            if(data.status==='success'){
                document.getElementById('tm-display-name').textContent=data.data.name;
                document.getElementById('tm-display-tag').textContent=data.data.tag;
                document.getElementById('tm-display-game').textContent=data.data.game;
                document.getElementById('tm-display-desc').textContent=data.data.desc;
                document.getElementById('tm-display-region').textContent=data.data.region||'—';
                document.getElementById('tm-avatar-text').textContent=data.data.tag.substring(0,2).toUpperCase();
                togglePanel('editPanel'); showToast(data.message);
            } else showToast(data.message,'error');
            saveEditBtn.disabled=false; saveEditBtn.textContent='Save';
        });
    }

    const inviteBtn=document.getElementById('inviteBtn');
    const inviteInput=document.getElementById('inviteInput');
    if(inviteBtn&&inviteInput){
        async function doInvite(){
            const username=inviteInput.value.trim();
            if(!username){ showToast('Enter a username.','error'); return; }
            inviteBtn.disabled=true;
            const data=await tmAjax({action:'invite',invite_username:username});
            if(data.status==='success'){ appendMemberRow(data.member); inviteInput.value=''; showToast(data.message); }
            else showToast(data.message,'error');
            inviteBtn.disabled=false;
        }
        inviteBtn.addEventListener('click',doInvite);
        inviteInput.addEventListener('keydown',e=>{ if(e.key==='Enter') doInvite(); });
    }

    const leaveBtn=document.getElementById('leaveBtn');
    if(leaveBtn){
        leaveBtn.addEventListener('click',async()=>{
            if(!confirm('Are you sure you want to leave the team?')) return;
            const data=await tmAjax({action:'leave'});
            if(data.status==='success'){ showToast(data.message); setTimeout(()=>location.reload(),900); }
            else showToast(data.message,'error');
        });
    }
});
</script>
</body>
</html>