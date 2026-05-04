<?php
// Sayfa başlığı ve yüklenecek ekstra CSS'ler için varsayılanlar
$pageTitle = isset($customTitle) ? $customTitle . " | Ikarus" : "Ikarus - Tournament Platform";
$extraCss = isset($extraCss) ? $extraCss : [];
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
<meta name="author" content="Ahmet Enes Kodal">

<!-- Favicon - Mutlak yol ile tüm sayfalarda çalışır -->
<link rel="shortcut icon" href="/assets/images/Ikarus_Logo.webp" type="image/x-icon">

<!-- Global CSS Dosyaları (Her sayfada mutlaka olanlar) -->
<link rel="stylesheet" href="/assets/css/global.css"> 
<link rel="stylesheet" href="/assets/css/utils.css">
<link rel="stylesheet" href="/assets/css/modal.css">

<!-- Dinamik CSS Yükleme -->
<?php foreach ($extraCss as $cssFile): ?>
    <link rel="stylesheet" href="/assets/css/<?php echo $cssFile; ?>.css">
<?php endforeach; ?>

<title><?php echo $pageTitle; ?></title>