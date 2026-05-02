<?php
require_once '../includes/session.php';
include '../includes/db.php'; 

// PDO kullanarak veritabanından turnuvaları çekiyoruz
try {
    $stmt = $pdo->query("
        SELECT t.*, g.name as game_name 
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

    <!-- Ana Stiller -->
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/utils.css">
    
    <!-- Turnuva Stilleri -->
    <link rel="stylesheet" href="../assets/css/tournaments.css">

    <style>
        /* Tıklamayı engelleyen görünmez bir katman (overlay) varsa diye üstte tutma garantisi */
        main, .game-filter, .t-card { position: relative; z-index: 10; }
        .t-card { text-decoration: none !important; cursor: pointer !important; }
    </style>
</head>

<body>
    <?php require_once '../includes/header.php' ?>
    
    <main>
        <div class="page">
          <div class="content">
            <div class="page-header">
              <div>
                <div class="page-title">Tournaments</div>
                <div class="page-sub">Choose the tournament you want to join and compete with your team.</div>
              </div>
            </div>

            <!-- Game Filters -->
            <div class="game-filter" id="gameFilter">
              <button type="button" class="game-btn all active" onclick="filterGame('all',this)">
                <div class="game-icon icon-all">∞</div>All Games
              </button>
              <button type="button" class="game-btn" onclick="filterGame('cs',this)">
                <div class="game-icon icon-cs">CS</div>CS2
              </button>
              <button type="button" class="game-btn" onclick="filterGame('v',this)">
                <div class="game-icon icon-val">V</div>Valorant
              </button>
            </div>

            <div class="tournaments-list" id="tList">
              <?php if (!empty($tournaments)): ?>
                <?php foreach($tournaments as $row): 
                    // Oyun ismine göre ikon ve renk belirleme
                    $game_lower = strtolower($row['game_name'] ?? '');
                    $icon_class = 'icon-all';
                    $game_short = 'T';
                    
                    if (strpos($game_lower, 'counter') !== false || strpos($game_lower, 'cs') !== false) { $icon_class = 'icon-cs'; $game_short = 'CS'; }
                    elseif (strpos($game_lower, 'val') !== false) { $icon_class = 'icon-val'; $game_short = 'V'; }
                    elseif (strpos($game_lower, 'fc') !== false) { $icon_class = 'icon-fc'; $game_short = 'FC'; }
                ?>
                  <!-- Link Etiketi İle Kart Oluşturma -->
                  <a href="tournaments-details.php?id=<?php echo $row['id']; ?>" class="t-card" data-game="<?php echo strtolower($game_short); ?>">
                    <div class="t-game-badge <?php echo $icon_class; ?>"><?php echo $game_short; ?></div>
                    <div class="t-main">
                      <div class="t-top">
                        <span class="t-name"><?php echo htmlspecialchars($row['name']); ?></span>
                        <span class="status-badge s-open"><?php echo strtoupper($row['status']); ?></span>
                      </div>
                      <div class="t-meta-row">
                        <div class="t-meta-item">Format <span>Single Elimination</span></div>
                        <div class="t-meta-item">Teams <span><?php echo $row['max_teams']; ?></span></div>
                        <div class="t-meta-item">Start Date <span><?php echo date('d M Y', strtotime($row['start_date'])); ?></span></div>
                      </div>
                    </div>
                    <div class="t-slots">
                      <div class="slots-label">Slots</div>
                      <div class="slots-bar"><div class="slots-fill" style="width:50%"></div></div>
                      <div class="slots-text">0 / <?php echo $row['max_teams']; ?></div>
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
          </div>
        </div>
    </main>

    <?php require_once '../includes/footer.php' ?>

    <!-- JavaScript Filtreleme Fonksiyonu -->
    <script>
    function filterGame(game, el) {
      // Aktif buton rengini değiştirme
      document.querySelectorAll('.game-btn').forEach(b => b.classList.remove('active'));
      el.classList.add('active');
      
      // Kartları filtreleme
      const cards = document.querySelectorAll('.t-card');
      cards.forEach(c => {
        if (game === 'all' || c.dataset.game === game) {
          c.style.display = ''; // Görünür yap
        } else {
          c.style.display = 'none'; // Gizle
        }
      });
    }
    </script>
</body>
</html>