<?php
// cron/formats/SingleEliminationFormat.php

class SingleEliminationFormat implements BracketFormatInterface
{
    public function getName(): string
    {
        return 'single_elimination';
    }

    public function generateMatches(array $teams, int $tournamentId): array
    {
        $matches = [];
        $teamCount = count($teams);

        if ($teamCount < 2) {
            return []; // En az 2 takım yoksa bracket oluşmaz
        }

        // 1. ADIM: En yakın 2'nin kuvvetini bul (Örn: 5,6,7 takım varsa tabloyu 8'lik sisteme oturt)
        $powerOfTwo = 1;
        while ($powerOfTwo < $teamCount) {
            $powerOfTwo *= 2;
        }

        // 2. ADIM: Eksik takımları "BYE" (Boş) ile doldur
        $byes = $powerOfTwo - $teamCount;
        for ($i = 0; $i < $byes; $i++) {
            // id'si null olanlar sistemde BYE (bay geçen/rakipsiz) olarak kabul edilecek
            $teams[] = ['id' => null, 'name' => 'BYE', 'rank_point' => 0]; 
        }

        // 3. ADIM: Seeding - Takımları güçlerine göre eşleştir (1 numara vs Son numara)
        $seededTeams = $this->generateSeeding($teams);

        // 4. ADIM: İlk Tur (Round 1) Maçlarını Veritabanı İçin Hazırla
        $roundNumber = 1;
        $totalRounds = (int)log($powerOfTwo, 2);

        for ($i = 0; $i < $powerOfTwo; $i += 2) {
            $t1 = $seededTeams[$i]['id'];
            $t2 = $seededTeams[$i + 1]['id'];

            $matches[] = [
                'round_number' => $roundNumber,
                'stage'        => $this->getStageName($roundNumber, $totalRounds),
                'team1_id'     => $t1,
                'team2_id'     => $t2,
                'date'         => date('Y-m-d H:i:s')
            ];
        }

        // 5. ADIM: İlerleyen Turları (Placeholder olarak) Hazırla
        // jquery-bracket kütüphanesi ilerideki turları görebilmek için DB'de "boş" maç kayıtları bekler.
        for ($r = 2; $r <= $totalRounds; $r++) {
            // Her turda maç sayısı bir öncekinin yarısına düşer
            $matchesInRound = $powerOfTwo / pow(2, $r); 
            
            for ($m = 0; $m < $matchesInRound; $m++) {
                $matches[] = [
                    'round_number' => $r,
                    'stage'        => $this->getStageName($r, $totalRounds),
                    'team1_id'     => null, // Kazananlar belli olmadığı için ID'ler henüz NULL
                    'team2_id'     => null, 
                    'date'         => date('Y-m-d H:i:s', strtotime("+$r days")) // Turlar arasına sembolik 1'er gün ekler
                ];
            }
        }

        return $matches;
    }

    /**
     * Array'i katlama yöntemiyle eşleştirir: 1. Takım ile Sonuncu, 2. ile Sondan 2. vb.
     */
    private function generateSeeding(array $teams): array
    {
        $count = count($teams);
        if ($count <= 2) return $teams;

        $seeded = [];
        $half = $count / 2;
        for ($i = 0; $i < $half; $i++) {
            $seeded[] = $teams[$i];
            $seeded[] = $teams[$count - 1 - $i];
        }
        return $seeded;
    }

    /**
     * Tur sayısına göre o maçın "Stage" (Aşama) ismini dinamik olarak belirler.
     */
    private function getStageName(int $roundNumber, int $totalRounds): string
    {
        $remainingRounds = $totalRounds - $roundNumber;
        
        return match ($remainingRounds) {
            0 => 'Final',
            1 => 'Semi Final',
            2 => 'Quarter Final',
            default => 'Round ' . $roundNumber,
        };
    }
}
?>