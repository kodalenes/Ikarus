<?php
require_once '../includes/session.php';

// Get the ID from the URL securely
$t_id = $_GET['id'] ?? 0;

// Fetch the specific tournament
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
        <!-- Added tournaments-wrapper -->
        <div class="page tournaments-wrapper">
          <div class="content">
            <?php if ($tournament): ?>
                <div class="container">
                    <div class="page-header">
                        <div>
                            <h1 class="page-title"><?php echo htmlspecialchars($tournament['name']); ?> Details</h1>
                        </div>
                    </div>

                    <!-- Tournament Details Box using CSS variables -->
                    <div class="details" style="background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 20px;">
                        <p style="margin-bottom: 10px; color: var(--text-muted);">
                            <strong style="color: var(--text);">Start Date:</strong> <?php echo date('d M Y', strtotime($tournament['start_date'])); ?>
                        </p>
                        <p style="margin-bottom: 10px; color: var(--text-muted);">
                            <strong style="color: var(--text);">End Date:</strong> <?php echo date('d M Y', strtotime($tournament['end_date'])); ?>
                        </p>
                        <p style="margin-bottom: 10px; color: var(--text-muted);">
                            <strong style="color: var(--text);">Status:</strong> <span class="status-badge s-open"><?php echo strtoupper($tournament['status']); ?></span>
                        </p>
                    </div>
                    
                    <a href="tournaments.php" class="btn-ghost" style="text-decoration: none; display: inline-block;">&larr; Go Back</a>
                </div>
            <?php else: ?>
                <!-- Not found state -->
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