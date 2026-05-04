<?php
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/../includes/db.php';

// Güvenli ID alımı
$tId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ─── 1. SİLME İŞLEMİ SORGUSU (POST) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = filter_input(INPUT_POST, 'tournament_id', FILTER_VALIDATE_INT);
    
    if ($deleteId) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE Tournament SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute(['id' => $deleteId]);

            if ($stmt->rowCount() > 0) {
                $pdo->commit();
                header("Location: tournaments.php?msg=deleted");
                exit;
            } else {
                $pdo->rollBack();
                $errorMsg = "Tournament could not be deleted or already deleted.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Hatayı yakala ve değişkene ata
            $errorMsg = "Delete Query Error: " . $e->getMessage();
        }
    }
}

if (!$tId) {
    die("Invalid Tournament ID. Lütfen URL'de ?id=X parametresinin olduğuna emin ol.");
}


// ─── 2. TURNUVA DETAYLARINI ÇEKME SORGUSU ────────────────────────────
$tournament = null;
try {
    $query = "
        SELECT t.*, g.name AS game_name,
               (SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = t.id) AS registered_teams,
               (SELECT COUNT(*) FROM Matches WHERE tournament_id = t.id AND deleted_at IS NULL) AS total_matches,
               (SELECT COUNT(*) FROM Matches WHERE tournament_id = t.id AND winner_id IS NULL AND deleted_at IS NULL) AS pending_matches
        FROM Tournament t
        LEFT JOIN Game g ON t.game_id = g.id
        WHERE t.id = :id AND t.deleted_at IS NULL
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $tId]);
    $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournament) {
        die("Tournament not found or has been deleted. (Aranan ID: {$tId}) <br>Not: Eğer ekranda SQL hatası yoksa, sorgu başarılı çalışıyor ancak veritabanında gerçekten bu ID'ye sahip bir turnuva yok demektir.");
    }

} catch (PDOException $e) {
    // Veritabanı hatasını doğrudan ekrana bas ve çalışmayı durdur
    die("<div style='background:#f8d7da; color:#991b1b; padding:15px; border-radius:5px; margin:20px; font-family:monospace;'>
            <strong>Tournament Query Error:</strong><br>" . $e->getMessage() . "
         </div>");
}


// ─── 3. SON KATILAN TAKIMLARI ÇEKME SORGUSU ──────────────────────────
$recentTeams = [];
$teamErrorMsg = null;
try {
    $teamQuery = "
        SELECT tm.name, tt.registered_at 
        FROM tournament_teams tt
        JOIN Team tm ON tt.team_id = tm.id
        WHERE tt.tournament_id = :id
        ORDER BY tt.registered_at DESC 
        LIMIT 5
    ";
    $teamStmt = $pdo->prepare($teamQuery);
    $teamStmt->execute(['id' => $tId]);
    $recentTeams = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Sayfanın geri kalanının yüklenmesini engellememek için hatayı değişkene alalım
    $teamErrorMsg = "Team Query Error: " . $e->getMessage();
}

$pageTitle = "Tournament Detail";
require_once __DIR__ . '/layout-top.php';
?>

<!-- Modüler CSS ve JS Dahil Edimi -->
<link rel="stylesheet" href="../assets/css/organizer/tournament-manage.css">
<script src="../assets/js/organizer/tournament-manage.js" defer></script>

<!-- Genel Hata Mesajı Alanı -->
<?php if (!empty($errorMsg)): ?>
    <div class="op-alert op-alert--error animate-in" style="font-family:monospace; --delay: 50ms;"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<!-- Üst Bilgi Kartı -->
<div class="td-header-card animate-in" style="--delay: 100ms;">
    <div class="td-header-info">
        <div class="td-title">
            <?= htmlspecialchars($tournament['name'] ?? 'No Name') ?> 
            <span class="td-status-badge"><?= htmlspecialchars($tournament['status'] ?? 'Unknown') ?></span>
        </div>
        <div class="td-meta">
            <span class="td-meta-item">🎮 <?= htmlspecialchars($tournament['game_name'] ?? 'Unknown Game') ?></span>
            <span class="td-meta-item">🗓️ <?= isset($tournament['start_date']) ? date('M j, Y', strtotime($tournament['start_date'])) : 'No Date' ?></span>
            <span class="td-meta-item">👥 <?= $tournament['registered_teams'] ?? 0 ?> / <?= $tournament['max_teams'] ?? '∞' ?> Teams</span>
        </div>
    </div>
    <div class="td-actions">
        <a href="tournament-create.php?id=<?= $tId ?>" class="op-btn op-btn--ghost">Edit Details</a>
        <a href="tournaments.php" class="op-btn op-btn--primary">Back to List</a>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="op-stat-grid" style="margin-bottom: 24px;">
    <div class="op-stat-card animate-in" style="--delay: 150ms;">
        <div class="op-stat-label">Prize Pool</div>
        <div class="op-stat-val" style="color: var(--highlight);">₺<?= number_format($tournament['prize_pool'] ?? 0) ?></div>
    </div>
    <div class="op-stat-card animate-in" style="--delay: 200ms;">
        <div class="op-stat-label">Registered Teams</div>
        <div class="op-stat-val"><?= $tournament['registered_teams'] ?? 0 ?></div>
    </div>
    <div class="op-stat-card animate-in" style="--delay: 250ms;">
        <div class="op-stat-label">Total Matches</div>
        <div class="op-stat-val"><?= $tournament['total_matches'] ?? 0 ?></div>
    </div>
    <div class="op-stat-card animate-in" style="border-color: rgba(239,159,39,0.3); background: rgba(239,159,39,0.04); --delay: 300ms;">
        <div class="op-stat-label">Pending Matches</div>
        <div class="op-stat-val" style="color: #EF9F27;"><?= $tournament['pending_matches'] ?? 0 ?></div>
    </div>
</div>

<!-- Ana İçerik Izgarası -->
<div class="td-grid">
    <!-- Sol Panel: Takımlar -->
    <div class="td-panel animate-in" style="--delay: 350ms;">
        <div class="td-panel-header">
            <span class="td-panel-title">Recently Registered Teams</span>
            <a href="teams.php?tournament_id=<?= $tId ?>" class="op-link">View All</a>
        </div>
        
        <!-- Eğer Takım sorgusunda hata varsa burada bas -->
        <?php if (!empty($teamErrorMsg)): ?>
            <div class="op-alert op-alert--error" style="font-family:monospace; font-size:11px;">
                <?= htmlspecialchars($teamErrorMsg) ?>
            </div>
        <?php endif; ?>

        <div class="td-team-list">
            <?php if (empty($recentTeams) && empty($teamErrorMsg)): ?>
                <p style="color: var(--text-faint); font-size: 13px;">No teams registered yet.</p>
            <?php else: ?>
                <?php foreach($recentTeams as $team): ?>
                    <div class="td-team-item">
                        <div class="td-team-info">
                            <div class="td-team-avatar"><?= substr(htmlspecialchars($team['name']), 0, 2) ?></div>
                            <div>
                                <div class="td-team-name"><?= htmlspecialchars($team['name']) ?></div>
                                <div class="td-team-date"><?= date('Y-m-d H:i', strtotime($team['registered_at'])) ?></div>
                            </div>
                        </div>
                        <button class="op-btn-sm op-btn-sm--ghost">Profile</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sağ Panel: Hızlı Aksiyonlar ve Tehlike Alanı -->
    <div>
        <div class="td-panel animate-in" style="--delay: 400ms;">
            <div class="td-panel-header">
                <span class="td-panel-title">Quick Actions</span>
            </div>
            <div style="display: flex; flex-direction: column;">
                <a href="match-results.php?id=<?= $tId ?>" class="td-setting-item">
                    <span>📝 Enter Match Results</span><span class="td-setting-icon">→</span>
                </a>
                <a href="players.php?tournament_id=<?= $tId ?>" class="td-setting-item">
                    <span>👥 Manage Participants</span><span class="td-setting-icon">→</span>
                </a>
            </div>
        </div>
        
        <div class="td-panel td-panel--danger animate-in" style="--delay: 450ms;">
            <div class="td-panel-header td-panel-header--danger">
                <span class="td-panel-title" style="color: #f87171;">Danger Zone</span>
            </div>
            <p style="font-size: 12px; color: var(--text-faint); margin-bottom: 12px;">This will safely hide the tournament (Soft Delete).</p>
            
            <form id="formDeleteTournament" method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="tournament_id" value="<?= $tId ?>">
                <button type="button" id="btnDeleteTournament" class="op-btn td-btn-danger">
                    Delete Tournament
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout-bottom.php'; ?>