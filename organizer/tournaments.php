<?php
/**
 * TOURNAMENT MANAGEMENT PAGE
 * Project: Ikarus Tournament Platform
 */

// 1. Auth & Database
require_once 'guard.php'; 
require_once '../includes/db.php'; 

// ─── SİLME İŞLEMİ (POST - SOFT DELETE) ───────────────────────────────
$deleteError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = filter_input(INPUT_POST, 'tournament_id', FILTER_VALIDATE_INT);
    
    if ($deleteId) {
        try {
            // Transaction ile güvenli silme
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE Tournament SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute(['id' => $deleteId]);
            $pdo->commit();
            
            // Başarılıysa sayfayı parametre ile yenile
            header("Location: tournaments.php?msg=deleted");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $deleteError = "Delete Error: " . $e->getMessage();
        }
    }
}

// 2. Page Meta Data[cite: 6]
$pageTitle = "Tournament Management";
$pageSubtitle = "Create, monitor and manage your esports events.";
$pageAction = [
    'href' => 'tournament-create.php',
    'label' => '+ Create New Tournament'
];

// ─── VERİTABANINDAN TURNUVALARI ÇEKME (GET) ─────────────────────────
$tournaments = [];
$queryError = null;
try {
    // Aktif (Silinmemiş) turnuvaları ve ilişkili oyun/takım sayılarını çekiyoruz
    $query = "
        SELECT t.*, g.name AS game_name,
               (SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = t.id) AS registered_teams
        FROM Tournament t
        LEFT JOIN Game g ON t.game_id = g.id AND g.deleted_at IS NULL
        WHERE t.deleted_at IS NULL 
          AND t.organizer_id = ?
        ORDER BY t.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Hatayı yakala ve ekrana basmak üzere değişkene at
    $queryError = "Query Error: " . $e->getMessage();
}

// 3. Layout Top (Header & Sidebar)[cite: 6]
require_once 'layout-top.php'; 
?>

<div class="organizer-body">
    
    <!-- Hata Mesajları -->
    <?php if (!empty($deleteError)): ?>
        <div class="op-alert op-alert--error animate-in" style="font-family:monospace; margin-bottom: 20px; --delay: 100ms;"><?= htmlspecialchars($deleteError) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($queryError)): ?>
        <div class="op-alert op-alert--error animate-in" style="font-family:monospace; margin-bottom: 20px; --delay: 150ms;">
            <strong>Database Error:</strong><br><?= htmlspecialchars($queryError) ?>
        </div>
    <?php endif; ?>

    <div class="trn-card animate-in" style="--delay: 200ms;">
        <div class="trn-card-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 600; font-size: 14px; color: var(--text-muted);">Active Tournaments</span>
            <div class="trn-table-search">
                <input type="text" placeholder="Search tournaments..." id="trnSearch" style="background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 6px 12px; border-radius: 4px; font-size: 13px;">
            </div>
        </div>
        
        <table class="trn-table" id="tournamentsTable">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Tournament Name</th>
                    <th>Game</th>
                    <th>Start Date</th>
                    <th>Participants</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="tournamentData">
                
                <?php if (empty($tournaments) && empty($queryError)): ?>
                    <!-- Turnuva Yoksa Gösterilecek Boş Durum (Empty State)[cite: 6] -->
                    <tr>
                        <td colspan="7">
                            <div id="trn-empty-state" style="text-align: center; padding: 60px 20px;">
                                 <div style="font-size: 40px; margin-bottom: 10px; opacity: 0.3;">🏆</div>
                                 <p style="color: var(--text-muted);">No tournaments found. Start by creating your first event!</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <!-- Dinamik Turnuva Döngüsü -->
                    <?php foreach ($tournaments as $t): ?>
                        <tr class="trn-row">
                            <td>#<?= htmlspecialchars($t['id']) ?></td>
                            <td>
                                <div style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($t['name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">
                                    <?= htmlspecialchars($t['description'] ?? 'No Description') ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($t['game_name'] ?? 'Unknown') ?></td>
                            <td><?= date('M j, Y', strtotime($t['start_date'])) ?></td>
                            <td><?= $t['registered_teams'] ?> / <?= $t['max_teams'] ?? '∞' ?></td>
                            <td>
                                <?php 
                                    // Status durumuna göre renk/class belirleme
                                    $statusClass = (strtolower($t['status']) === 'active') ? 'active' : 'completed';
                                ?>
                                <span class="trn-badge <?= $statusClass ?>"><?= htmlspecialchars($t['status']) ?></span>
                            </td>
                            <td class="trn-actions" style="justify-content: flex-end;">
                                <a href="tournament-manage.php?id=<?= $t['id'] ?>" class="trn-btn trn-btn-info" title="Manage">Manage</a>
                                <a href="match-results.php?id=<?= $t['id'] ?>" class="trn-btn trn-btn-warning" title="Scores">Results</a>
                                
                                <!-- Forma Bağlı Silme Butonu -->
                                <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete tournament #<?= $t['id'] ?>? This will move it to trash.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="trn-btn trn-btn-danger" title="Delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // İstemci Taraflı Arama İşlevi (Client-Side Search)[cite: 6]
    const searchInput = document.getElementById('trnSearch');
    
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.trn-row');
            
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    }
});
</script>

<?php 
// 4. Layout Bottom (Footer & Global Scripts)[cite: 6]
require_once 'layout-bottom.php'; 
?>