<?php
date_default_timezone_set('Europe/Istanbul');
// Ana dizindeki cron.php

// 1. Güvenlik Kilidi (Dışarıdan izinsiz tetiklemeyi önler)
$secretKey = '/Enes5384';
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die('Request denied.');
}

// 2. Yeni Gelişmiş Cron Motorunu Çağır
// (Hata mesajlarını ekrana basması için try-catch bloğuna alıyoruz)
try {
    require_once __DIR__ . '/cron/tournament-cron.php';
    echo "Cron engine successfully executed.";
} catch (Throwable $e) {
    echo "System error: " . $e->getMessage();
}