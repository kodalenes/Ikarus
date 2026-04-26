<?php
require_once '../includes/session.php';

// Giriş zorunlu
if (!isLoggedIn()) {
    header('Location: index.php?modal=login');
    exit;
}

$userId   = $_SESSION['user_id'];
$feedback = '';
$feedbackType = '';

// ─── Kullanıcının takımını çek ───────────────────────────────────────────────
try {
    $stmtMyTeam = $pdo->prepare("
        SELECT t.*, p2.username AS captain_name, p2.id AS captain_id
        FROM Team t
        JOIN Player p ON p.team_id = t.id
        LEFT JOIN Player p2 ON p2.id = t.captain_id
        WHERE p.id = ?
    ");
    $stmtMyTeam->execute([$userId]);
    $team = $stmtMyTeam->fetch();
} catch (Exception $e) {
    $team = null;
}

$isCaptain = $team && isset($team['captain_id']) && $team['captain_id'] == $userId;
$hasTeam   = (bool) $team;

// ─── POST İşlemleri ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Takım oluştur
    if ($action === 'create_team') {
        $name   = trim($_POST['name'] ?? '');
        $tag    = strtoupper(trim($_POST['tag'] ?? ''));
        $game   = trim($_POST['game'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if (empty($name) || empty($tag) || empty($game)) {
            $feedback = 'Takım adı, etiket ve oyun zorunludur.';
            $feedbackType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("
                    INSERT INTO Team (name, tag, game, region, description, captain_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$name, $tag, $game, $region, $desc, $userId]);
                $newTeamId = (int) $pdo->lastInsertId();
                $pdo->prepare("UPDATE Player SET team_id = ? WHERE id = ?")->execute([$newTeamId, $userId]);
                $pdo->commit();
                header('Location: team.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback = 'Takım oluşturulurken hata oluştu.';
                $feedbackType = 'error';
            }
        }
    }

    // Takım düzenle
    if ($action === 'update_team' && $isCaptain) {
        $name   = trim($_POST['name'] ?? '');
        $tag    = strtoupper(trim($_POST['tag'] ?? ''));
        $game   = trim($_POST['game'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if (empty($name) || empty($tag)) {
            $feedback = 'Takım adı ve etiket zorunludur.';
            $feedbackType = 'error';
        } else {
            try {
                $pdo->prepare("
                    UPDATE Team SET name=?, tag=?, game=?, region=?, description=?
                    WHERE id=? AND captain_id=?
                ")->execute([$name, $tag, $game, $region, $desc, $team['id'], $userId]);
                header('Location: team.php?updated=1');
                exit;
            } catch (Exception $e) {
                $feedback = 'Güncelleme sırasında hata oluştu.';
                $feedbackType = 'error';
            }
        }
    }

    // Üye davet et
    if ($action === 'invite' && $isCaptain) {
        $inviteUser = trim($_POST['invite_username'] ?? '');
        if (empty($inviteUser)) {
            $feedback = 'Kullanıcı adı boş olamaz.';
            $feedbackType = 'error';
        } else {
            try {
                $stmtFind = $pdo->prepare("SELECT id, team_id FROM Player WHERE username = ?");
                $stmtFind->execute([$inviteUser]);
                $target = $stmtFind->fetch();

                if (!$target) {
                    $feedback = 'Kullanıcı bulunamadı.';
                    $feedbackType = 'error';
                } elseif ($target['team_id']) {
                    $feedback = 'Bu kullanıcı zaten bir takımda.';
                    $feedbackType = 'error';
                } else {
                    // Üye sayısını kontrol et
                    $count = $pdo->prepare("SELECT COUNT(*) FROM Player WHERE team_id = ?");
                    $count->execute([$team['id']]);
                    if ((int)$count->fetchColumn() >= 6) {
                        $feedback = 'Takım dolu (max 6 üye).';
                        $feedbackType = 'error';
                    } else {
                        $pdo->prepare("UPDATE Player SET team_id = ? WHERE id = ?")->execute([$team['id'], $target['id']]);
                        $feedback = htmlspecialchars($inviteUser) . ' takıma eklendi!';
                        $feedbackType = 'success';
                        // Takımı yenile
                        $stmtMyTeam->execute([$userId]);
                        $team = $stmtMyTeam->fetch();
                    }
                }
            } catch (Exception $e) {
                $feedback = 'İşlem sırasında hata oluştu.';
                $feedbackType = 'error';
            }
        }
    }

    // Üye çıkar
    if ($action === 'kick' && $isCaptain) {
        $kickId = (int)($_POST['kick_id'] ?? 0);
        if ($kickId && $kickId !== $userId) {
            try {
                $pdo->prepare("UPDATE Player SET team_id = NULL WHERE id = ? AND team_id = ?")
                    ->execute([$kickId, $team['id']]);
                header('Location: team.php');
                exit;
            } catch (Exception $e) {
                $feedback = 'İşlem sırasında hata oluştu.';
                $feedbackType = 'error';
            }
        }
    }

    // Takımdan ayrıl
    if ($action === 'leave') {
        try {
            if ($isCaptain) {
                // Başka üye var mı?
                $stmtOther = $pdo->prepare("SELECT id FROM Player WHERE team_id = ? AND id != ? LIMIT 1");
                $stmtOther->execute([$team['id'], $userId]);
                $nextCaptain = $stmtOther->fetch();
                if ($nextCaptain) {
                    $pdo->prepare("UPDATE Team SET captain_id = ? WHERE id = ?")->execute([$nextCaptain['id'], $team['id']]);
                } else {
                    // Son kişiyse takımı sil
                    $pdo->prepare("DELETE FROM Team WHERE id = ?")->execute([$team['id']]);
                }
            }
            $pdo->prepare("UPDATE Player SET team_id = NULL WHERE id = ?")->execute([$userId]);
            header('Location: team.php');
            exit;
        } catch (Exception $e) {
            $feedback = 'Ayrılma işleminde hata oluştu.';
            $feedbackType = 'error';
        }
    }
}

// URL ile bildirim
if (isset($_GET['updated'])) {
    $feedback = 'Takım bilgileri güncellendi.';
    $feedbackType = 'success';
    // Güncel takım verisini yenile
    $stmtMyTeam->execute([$userId]);
    $team = $stmtMyTeam->fetch();
}

// ─── Takım varsa ekstra verileri çek ────────────────────────────────────────
$members     = [];
$tournaments = [];
$stats       = ['matches' => 0, 'wins' => 0, 'tournaments' => 0, 'rank' => '—'];

if ($hasTeam) {
    // Üyeler
    try {
        $stmtMembers = $pdo->prepare("
            SELECT id, username, role
            FROM Player
            WHERE team_id = ?
            ORDER BY (id = ?) DESC, username ASC
        ");
        $stmtMembers->execute([$team['id'], $team['captain_id']]);
        $members = $stmtMembers->fetchAll();
    } catch (Exception $e) { $members = []; }

    // Turnuvalar
    try {
        $stmtT = $pdo->prepare("
            SELECT t.id, t.name, t.status, g.name AS game_name, t.start_date
            FROM tournament_team tt
            JOIN Tournament t ON t.id = tt.tournament_id
            LEFT JOIN Game g ON g.id = t.game_id
            WHERE tt.team_id = ?
            ORDER BY t.start_date DESC
            LIMIT 5
        ");
        $stmtT->execute([$team['id']]);
        $tournaments = $stmtT->fetchAll();
    } catch (Exception $e) { $tournaments = []; }

    // İstatistikler
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
        ");
        $stmtStats->execute([':tid' => $team['id']]);
        $s = $stmtStats->fetch();
        $stats['matches'] = (int)$s['matches'];
        $stats['wins']    = (int)$s['wins'];
        $stats['tournaments'] = count($tournaments);
    } catch (Exception $e) {}
}

// Aktif sayfa için header nav-link vurgusu
$currentPage = 'team.php';
?>
<!DOCTYPE html>
<html lang="tr">
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
    <div class="tm-alert tm-alert--<?= $feedbackType ?>">
        <?= htmlspecialchars($feedback) ?>
    </div>
<?php endif; ?>

<?php if (!$hasTeam): ?>
<!-- ═══════════════════════════════════════════════════════════ TAKİM YOK -->
<div class="tm-empty-wrap">
    <div class="tm-empty-box">
        <div class="tm-empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <h2 class="tm-empty-title">Henüz bir takımın yok</h2>
        <p class="tm-empty-sub">Bir takım kur ve turnuvalara katılmaya başla.</p>
        <button class="tm-btn-primary" onclick="document.getElementById('createPanel').style.display='block';this.style.display='none'">
            + Takım Oluştur
        </button>

        <div id="createPanel" style="display:none; margin-top:28px; text-align:left;">
            <form method="POST">
                <input type="hidden" name="action" value="create_team">
                <div class="tm-form-grid">
                    <div class="tm-field">
                        <label class="tm-label">Takım Adı *</label>
                        <input class="tm-input" type="text" name="name" placeholder="NightFall" required>
                    </div>
                    <div class="tm-field">
                        <label class="tm-label">Etiket *</label>
                        <input class="tm-input" type="text" name="tag" placeholder="NX" maxlength="4" required>
                    </div>
                    <div class="tm-field">
                        <label class="tm-label">Oyun *</label>
                        <select class="tm-select" name="game" required>
                            <option value="">Seç...</option>
                            <?php
                            try {
                                $games = $pdo->query("SELECT name FROM Game ORDER BY name")->fetchAll();
                                foreach ($games as $g) echo '<option value="'.htmlspecialchars($g['name']).'">'.htmlspecialchars($g['name']).'</option>';
                            } catch (Exception $e) {}
                            ?>
                        </select>
                    </div>
                    <div class="tm-field">
                        <label class="tm-label">Bölge</label>
                        <input class="tm-input" type="text" name="region" placeholder="Türkiye">
                    </div>
                    <div class="tm-field tm-field--full">
                        <label class="tm-label">Açıklama</label>
                        <textarea class="tm-textarea" name="description" rows="2" placeholder="Takımınızı tanıtın..."></textarea>
                    </div>
                </div>
                <div class="tm-form-actions">
                    <button type="button" class="tm-btn-ghost" onclick="document.getElementById('createPanel').style.display='none'">İptal</button>
                    <button type="submit" class="tm-btn-primary">Takımı Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════ TAKİM VAR -->

<!-- HEADER -->
<div class="tm-header">
    <div class="tm-avatar"><?= htmlspecialchars(strtoupper(substr($team['tag'] ?? 'T', 0, 2))) ?></div>
    <div class="tm-info">
        <div class="tm-name"><?= htmlspecialchars($team['name'] ?? '') ?></div>
        <div class="tm-tag">#<?= htmlspecialchars($team['tag'] ?? '') ?> · <?= htmlspecialchars($team['game'] ?? '') ?></div>
        <div class="tm-desc"><?= htmlspecialchars($team['description'] ?? '') ?></div>
        <div class="tm-meta">
            <?php if (!empty($team['region'])): ?>
            <div class="tm-meta-item">Bölge <span><?= htmlspecialchars($team['region']) ?></span></div>
            <?php endif; ?>
            <div class="tm-meta-item">Üyeler <span><?= count($members) ?> / 6</span></div>
        </div>
    </div>
    <div class="tm-actions">
        <?php if ($isCaptain): ?>
            <button class="tm-btn-primary" onclick="togglePanel('editPanel')">Takımı Düzenle</button>
            <button class="tm-btn-outline" onclick="togglePanel('invitePanel')">Üye Davet Et</button>
        <?php endif; ?>
        <button class="tm-btn-danger" onclick="confirmLeave()">Takımdan Ayrıl</button>
    </div>
</div>

<!-- STATS -->
<div class="tm-stats">
    <div class="tm-stat">
        <div class="tm-stat-label">Toplam Maç</div>
        <div class="tm-stat-val"><?= $stats['matches'] ?></div>
    </div>
    <div class="tm-stat">
        <div class="tm-stat-label">Galibiyet</div>
        <div class="tm-stat-val"><?= $stats['wins'] ?></div>
        <?php if ($stats['matches'] > 0): ?>
        <div class="tm-stat-sub">%<?= round($stats['wins'] / $stats['matches'] * 100) ?> win rate</div>
        <?php endif; ?>
    </div>
    <div class="tm-stat">
        <div class="tm-stat-label">Turnuva</div>
        <div class="tm-stat-val"><?= $stats['tournaments'] ?></div>
    </div>
    <div class="tm-stat">
        <div class="tm-stat-label">Üye</div>
        <div class="tm-stat-val"><?= count($members) ?></div>
        <div class="tm-stat-sub">Maks. 6</div>
    </div>
</div>

<!-- DAVET PANELİ -->
<?php if ($isCaptain): ?>
<div id="invitePanel" class="tm-panel tm-panel--accent" style="display:none">
    <div class="tm-panel-title">Üye Davet Et</div>
    <form method="POST" class="tm-invite-form">
        <input type="hidden" name="action" value="invite">
        <input class="tm-input" type="text" name="invite_username" placeholder="Kullanıcı adı" required>
        <button type="submit" class="tm-btn-primary">Ekle</button>
    </form>
    <div class="tm-panel-note">Mevcut üye sayısı: <?= count($members) ?>/6</div>
</div>

<!-- DÜZENLEME PANELİ -->
<div id="editPanel" class="tm-panel" style="display:none">
    <div class="tm-panel-title">Takım Bilgilerini Düzenle</div>
    <form method="POST">
        <input type="hidden" name="action" value="update_team">
        <div class="tm-form-grid">
            <div class="tm-field">
                <label class="tm-label">Takım Adı</label>
                <input class="tm-input" type="text" name="name" value="<?= htmlspecialchars($team['name'] ?? '') ?>" required>
            </div>
            <div class="tm-field">
                <label class="tm-label">Etiket</label>
                <input class="tm-input" type="text" name="tag" maxlength="4" value="<?= htmlspecialchars($team['tag'] ?? '') ?>" required>
            </div>
            <div class="tm-field">
                <label class="tm-label">Oyun</label>
                <select class="tm-select" name="game">
                    <?php
                    try {
                        $games = $pdo->query("SELECT name FROM Game ORDER BY name")->fetchAll();
                        foreach ($games as $g) {
                            $sel = ($g['name'] === ($team['game'] ?? '')) ? 'selected' : '';
                            echo '<option value="'.htmlspecialchars($g['name']).'" '.$sel.'>'.htmlspecialchars($g['name']).'</option>';
                        }
                    } catch (Exception $e) {}
                    ?>
                </select>
            </div>
            <div class="tm-field">
                <label class="tm-label">Bölge</label>
                <input class="tm-input" type="text" name="region" value="<?= htmlspecialchars($team['region'] ?? '') ?>">
            </div>
            <div class="tm-field tm-field--full">
                <label class="tm-label">Açıklama</label>
                <textarea class="tm-textarea" name="description" rows="2"><?= htmlspecialchars($team['description'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="tm-form-actions">
            <button type="button" class="tm-btn-ghost" onclick="togglePanel('editPanel')">İptal</button>
            <button type="submit" class="tm-btn-primary">Kaydet</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- İKİ KOLON: ÜYELER + TURNUVALAR -->
<div class="tm-two-col">

    <!-- ÜYELER -->
    <div class="tm-card">
        <div class="tm-card-head">
            <span class="tm-card-title">Üyeler</span>
        </div>

        <?php foreach ($members as $m): ?>
            <?php $isMemberCaptain = ($m['id'] == $team['captain_id']); ?>
            <div class="tm-member-row">
                <div class="tm-m-avatar <?= $isMemberCaptain ? 'tm-m-avatar--captain' : '' ?>">
                    <?= strtoupper(substr($m['username'], 0, 2)) ?>
                </div>
                <div class="tm-m-info">
                    <div class="tm-m-name">
                        <?= htmlspecialchars($m['username']) ?>
                        <?php if ($m['id'] == $userId): ?><span class="tm-you">Sen</span><?php endif; ?>
                    </div>
                    <?php if (!empty($m['role'])): ?>
                        <div class="tm-m-role"><?= htmlspecialchars($m['role']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($isMemberCaptain): ?>
                    <span class="tm-badge tm-badge--captain">Kaptan</span>
                <?php else: ?>
                    <span class="tm-badge tm-badge--member">Üye</span>
                    <?php if ($isCaptain): ?>
                        <form method="POST" class="tm-kick-form" onsubmit="return confirm('<?= htmlspecialchars($m['username']) ?> takımdan çıkarılsın mı?')">
                            <input type="hidden" name="action" value="kick">
                            <input type="hidden" name="kick_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="tm-btn-kick">Çıkar</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TURNUVALAR -->
    <div class="tm-card">
        <div class="tm-card-head">
            <span class="tm-card-title">Turnuvalar</span>
            <a href="tournaments.php" class="tm-card-link">Tümü →</a>
        </div>

        <?php if (empty($tournaments)): ?>
            <div class="tm-empty-row">Henüz hiç turnuvaya katılmadınız.</div>
        <?php else: ?>
            <?php foreach ($tournaments as $t):
                $statusMap = [
                    'live'         => ['dot' => 'tm-dot--live',     'label' => 'Devam ediyor',  'cls' => 'tm-result--ongoing'],
                    'registration' => ['dot' => 'tm-dot--upcoming', 'label' => 'Kayıt açık',    'cls' => 'tm-result--soon'],
                    'upcoming'     => ['dot' => 'tm-dot--upcoming', 'label' => 'Yaklaşıyor',    'cls' => 'tm-result--soon'],
                    'finished'     => ['dot' => 'tm-dot--done',     'label' => 'Tamamlandı',    'cls' => ''],
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

</div><!-- /.tm-two-col -->

<!-- Gizli ayrılma formu -->
<form id="leaveForm" method="POST" style="display:none">
    <input type="hidden" name="action" value="leave">
</form>

<?php endif; // hasTeam ?>
</div><!-- /.team-page -->
</main>

<?php require_once '../includes/footer.php'; ?>

<script>
function togglePanel(id) {
    const el = document.getElementById(id);
    if (!el) return;
    // Diğer açık paneli kapat
    ['editPanel','invitePanel'].forEach(p => {
        if (p !== id) {
            const other = document.getElementById(p);
            if (other) other.style.display = 'none';
        }
    });
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function confirmLeave() {
    if (confirm('Takımdan ayrılmak istediğinden emin misin?')) {
        document.getElementById('leaveForm').submit();
    }
}

// Başarı alertlerini 4 saniye sonra kaldır
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.tm-alert--success');
    alerts.forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });
});
</script>
</body>
</html>
