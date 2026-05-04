<?php
require_once '../includes/session.php';
include '../includes/db.php'; 

// Fetch tournaments from database with registered team count
try {
    $stmt = $pdo->query("
        SELECT t.*, g.name as game_name,
               (SELECT COUNT(*) FROM tournament_teams tt WHERE tt.tournament_id = t.id) as registered_count
        FROM Tournament t 
        LEFT JOIN Game g ON t.game_id = g.id 
        ORDER BY t.start_date DESC
    ");
    $tournaments = $stmt->fetchAll();
} catch (Exception $e) {
    $tournaments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournaments - Ikarus</title>

    <!-- Main Styles -->
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/utils.css">
    
    <!-- Tournament Styles -->
    <link rel="stylesheet" href="../assets/css/tournaments.css">
</head>

<body>
    <?php require_once '../includes/header.php' ?>
    
    <main class="tournaments-container">
        <div class="page-header animate-in" style="--delay: 100ms;">
            <h1 class="page-title">Tournaments</h1>
            <p class="page-sub">Discover and join active tournaments.</p>
        </div>

        <!-- Filtre veya arama çubuğu -->
        <div class="filter-bar animate-in" style="--delay: 200ms;">
          <?php if (isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'organizer'): ?>
            <a href="../organizer/tournament-create.php" class="join-btn" style="text-decoration: none;">Create Tournament</a>
          <?php endif; ?>
        </div>

        <!-- Turnuva Kartlarının listelendiği Grid -->
        <div class="tournament-grid">
            <?php if (!empty($tournaments)): ?>
                <?php foreach($tournaments as $idx => $row): 
                    // Determine game icon and color
                    $game_lower = strtolower($row['game_name'] ?? '');
                    $icon_class = 'icon-all';
                    $game_short = 'T';
                    
                    if (strpos($game_lower, 'counter') !== false || strpos($game_lower, 'cs') !== false) { $icon_class = 'icon-cs'; $game_short = 'CS'; }
                    elseif (strpos($game_lower, 'val') !== false) { $icon_class = 'icon-val'; $game_short = 'V'; }
                    elseif (strpos($game_lower, 'fc') !== false) { $icon_class = 'icon-fc'; $game_short = 'FC'; }
                    
                    // Slot hesaplamaları
                    $registered = (int)$row['registered_count'];
                    $max = (int)$row['max_teams'];
                    $percent = $max > 0 ? min(100, round(($registered / $max) * 100)) : 0;
                ?>
                  <a href="tournaments-details.php?id=<?php echo $row['id']; ?>" class="t-card animate-in" data-game="<?php echo strtolower($game_short); ?>" style="--delay: <?= 250 + ($idx * 50) ?>ms;">
                    <div class="t-game-badge <?php echo $icon_class; ?>"><?php echo $game_short; ?></div>
                    <div class="t-main">
                      <div class="t-top">
                        <span class="t-name"><?php echo htmlspecialchars($row['name']); ?></span>
                        <span class="status-badge s-open"><?php echo strtoupper($row['status']); ?></span>
                      </div>
                      <div class="t-meta-row">
                        <div class="t-meta-item">Format <span>Single Elimination</span></div>
                        <div class="t-meta-item">Teams <span><?php echo $max; ?></span></div>
                        <div class="t-meta-item">Start Date <span><?php echo date('d M Y', strtotime($row['start_date'])); ?></span></div>
                      </div>
                    </div>
                    <div class="t-slots">
                      <div class="slots-label">Slots</div>
                      <div class="slots-bar"><div class="slots-fill" style="width:<?php echo $percent; ?>%"></div></div>
                      <div class="slots-text"><?php echo $registered; ?> / <?php echo $max; ?></div>
                    </div>
                    <div class="t-prize">
                      <div class="prize-label">Prize</div>
                      <div class="prize-val">₺<?php echo number_format($row['prize_pool'], 0, ',', '.'); ?></div>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="empty-state">No tournaments found in the database yet.</div>
              <?php endif; ?>
        </div>
    </main>

    <?php require_once '../includes/footer.php' ?>

    <script>
    function filterGame(game, el) {
      document.querySelectorAll('.game-btn').forEach(b => b.classList.remove('active'));
      el.classList.add('active');
      
      const cards = document.querySelectorAll('.t-card');
      cards.forEach(c => {
        if (game === 'all' || c.dataset.game === game) {
          c.style.display = ''; 
        } else {
          c.style.display = 'none'; 
        }
      });
    }
    </script>
</body>
</html>
