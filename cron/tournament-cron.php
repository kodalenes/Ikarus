<?php
// cron/tournament_cron.php
// Cron ayarı: * * * * * php /path/to/cron/tournament_cron.php >> /var/log/ikarus_cron.log 2>&1


declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/autoload.php';

$logger = new CronLogger();
$engine = new TournamentEngine($pdo, $logger);

$logger->log("=== Cron started: " . date('Y-m-d H:i:s') . " ===");

// 1. Adım: Status güncellemeleri
$statusUpdater = new TournamentStatusUpdater($pdo, $logger);
$statusUpdater->run();

// 2. Adım: Yeni live olan turnuvalar için bracket oluştur
$bracketGenerator = new BracketGenerator($pdo, $logger, $engine);
$bracketGenerator->run();

$logger->log("=== Cron ended ===\n");

?>