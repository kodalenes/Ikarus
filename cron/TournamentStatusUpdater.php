<?php
// cron/TournamentStatusUpdater.php

class TournamentStatusUpdater
{
    public function __construct(
        private PDO $pdo,
        private CronLogger $logger
    ) {}

    public function run(): void
    {
        // Kural tablosu — sıralı işlenir
        // [mevcut_status, yeni_status, zaman_koşulu]
        $transitions = [
            // Kayıt süresi geldiyse → Kayıt aç
            [
                'from'      => 'upcoming',
                'to'        => 'registration',
                // Başlangıca 7 gün kala kaydı aç (isteğe göre ayarlanabilir)
                'condition' => "start_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)",
            ],
            // Başlangıç zamanı geldiyse → Live yap
            [
                'from'      => 'registration',
                'to'        => 'live',
                'condition' => "start_date <= NOW()",
            ],
            // Bitiş zamanı geçtiyse → Bitmiş say
            [
                'from'      => 'live',
                'to'        => 'finished',
                'condition' => "end_date < NOW()",
            ],
        ];

        foreach ($transitions as $t) {
            $this->applyTransition($t['from'], $t['to'], $t['condition']);
        }
    }

    private function applyTransition(string $from, string $to, string $condition): void
    {
        // Önce etkilenecek ID'leri al (logging için)
        $stmt = $this->pdo->prepare("
            SELECT id, name FROM Tournament
            WHERE status = ? AND {$condition} AND deleted_at IS NULL
        ");
        $stmt->execute([$from]);
        $affected = $stmt->fetchAll();

        if (empty($affected)) return;

        // Güncelle
        $update = $this->pdo->prepare("
            UPDATE Tournament
            SET status = ?, updated_at = NOW()
            WHERE status = ? AND {$condition} AND deleted_at IS NULL
        ");
        $update->execute([$to, $from]);

        foreach ($affected as $t) {
            $this->logger->log("[STATUS] #{$t['id']} '{$t['name']}': {$from} → {$to}");
        }
    }
}