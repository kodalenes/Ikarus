<?php
include '../includes/db.php';
include '../includes/header.php';

// URL'den gelen ID'yi alıyoruz
$t_id = $_GET['id'];

// Sadece o ID'ye ait turnuvayı çekiyoruz[cite: 1]
$sql = "SELECT * FROM tournament WHERE id = $t_id";
$result = $conn->query($sql);
$tournament = $result->fetch_assoc();
?>

<div class="container">
    <h1><?php echo $tournament['name']; ?> Detayları</h1>
    <div class="details">
        <p><strong>Başlangıç Tarihi:</strong> <?php echo $tournament['start_date']; ?></p>
        <p><strong>Bitiş Tarihi:</strong> <?php echo $tournament['end_date']; ?></p>
        <p><strong>Durum:</strong> <?php echo $tournament['status']; ?></p>
    </div>
    
    <a href="tournaments.php">Geri Dön</a>
</div>

<?php include '../includes/footer.php'; ?>