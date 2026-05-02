<?php
include '../includes/db.php'; // Veritabanı bağlantımızı dahil ettik
include '../includes/header.php'; // Sayfa başlığını dahil ettik[cite: 1]

// Veritabanından turnuvaları çekiyoruz[cite: 1]
$sql = "SELECT * FROM tournament"; 
$result = $conn->query($sql);
?>

<div class="container">
    <h2>Aktif Turnuvalar</h2>
    <div class="tournament-list">
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="tournament-card">
                    <h3><?php echo $row['name']; ?></h3>
                    <p>Oyun: <?php echo $row['game_id']; ?></p>
                    <!-- Detay butonuna turnuvanın ID'sini gönderiyoruz -->
                    <a href="tournament-details.php?id=<?php echo $row['id']; ?>" class="btn">Detayları Gör</a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Henüz turnuva bulunmuyor.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>