<?php
require_once '../includes/session.php';
// Veritabanı bağlantısı muhtemelen session.php içinde veya ayriyeten dahil ediliyor

// Get the ID from the URL securely
$t_id = $_GET['id'] ?? 0;

// Fetch the specific tournament using PDO to prevent errors and injection
try {
    $stmt = $pdo->prepare("SELECT * FROM Tournament WHERE id = ?");
    $stmt->execute([$t_id]);
    $tournament = $stmt->fetch();
} catch (Exception $e) {
    $tournament = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tournament ? htmlspecialchars($tournament['name']) : 'Tournament Not Found' ?> - Ikarus</title>

    <!-- Main CSS files -->
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/utils.css">
    
    <!-- Tournament specific CSS -->
    <link rel="stylesheet" href="../assets/css/tournaments.css">
</head>

<body>
    <?php require_once '../includes/header.php' ?>
    
    <main>
        <div class="page">
          <div class="content">
            <?php if ($tournament): ?>
                <div class="container">
                    <div class="page-header">
                        <div>
                            <h1 class="page-title"><?php echo htmlspecialchars($tournament['name']); ?> Details</h1>
                        </div>
                    </div>

                    <!-- Tournament Details Box -->
                    <div class="details" style="background: rgba(255, 255, 255, 0.02); padding: 20px; border-radius: 12px; border: 0.5px solid rgba(255, 255, 255, 0.08); margin-bottom: 20px;">
                        <p style="margin-bottom: 10px; color: rgba(255, 255, 255, 0.7);">
                            <strong style="color: #fff;">Start Date:</strong> <?php echo date('d M Y', strtotime($tournament['start_date'])); ?>
                        </p>
                        <p style="margin-bottom: 10px; color: rgba(255, 255, 255, 0.7);">
                            <strong style="color: #fff;">End Date:</strong> <?php echo date('d M Y', strtotime($tournament['end_date'])); ?>
                        </p>
                        <p style="margin-bottom: 10px; color: rgba(255, 255, 255, 0.7);">
                            <strong style="color: #fff;">Status:</strong> <span class="status-badge s-open"><?php echo strtoupper($tournament['status']); ?></span>
                        </p>
                    </div>
                    
                    <a href="tournaments.php" class="btn-ghost" style="text-decoration: none; display: inline-block;">&larr; Go Back</a>
                </div>
            <?php else: ?>
                <!-- Eğer ID yanlışsa veya turnuva yoksa çıkacak ekran -->
                <div class="empty-state">Tournament not found!</div>
                <div style="text-align: center;">
                    <a href="tournaments.php" class="btn-ghost" style="text-decoration: none; display: inline-block; margin-top: 10px;">&larr; Go Back</a>
                </div>
            <?php endif; ?>
          </div>
        </div>
    </main>

    <?php require_once '../includes/footer.php' ?>
</body>
</html>