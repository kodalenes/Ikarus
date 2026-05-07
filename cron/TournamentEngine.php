<?php
// cron/TournamentEngine.php

/**
 * Her format bu interface'i uygular.
 * İleride RoundRobin, Swiss, DoubleElimination eklemek
 * sadece bu interface'i implement etmek demektir.
 */
interface BracketFormatInterface
{
    /**
     * @param array $teams  Sıralanmış takım listesi
     * @param int   $tournamentId
     * @return array        Oluşturulacak maç listesi
     */
    public function generateMatches(array $teams, int $tournamentId): array;

    /** Formatın adı (DB'ye kayıt için) */
    public function getName(): string;
}

class TournamentEngine
{
    /** @var array<string, BracketFormatInterface> */
    private array $formats = [];

    public function __construct(
        private PDO $pdo,
        private CronLogger $logger
    ) {
        // Format kayıt — yeni format eklemek sadece buraya bir satır eklemek
        $this->registerFormat(new SingleEliminationFormat());
        // $this->registerFormat(new RoundRobinFormat());    ← ileride
        // $this->registerFormat(new DoubleEliminationFormat()); ← ileride
    }

    public function registerFormat(BracketFormatInterface $format): void
    {
        $this->formats[$format->getName()] = $format;
    }

    /**
     * Turnuva için bracket oluşturur.
     * format parametresi DB'deki format alanından gelir.
     */
    public function buildBracket(array $tournament): bool
    {
        $format = $tournament['format'] ?? 'single_elimination';

        if (!isset($this->formats[$format])) {
            $this->logger->log("[ENGINE] Unknown format: {$format}");
            return false;
        }

        // Seeding: Takımları sırala (rank_point'e göre — en güçlü en üstte)
        $teams = $this->getSeededTeams((int)$tournament['id']);

        if (count($teams) < 2) {
            $this->logger->log("[ENGINE] #{$tournament['id']}: Not enough teams (" . count($teams) . ")");
            return false;
        }

        $matches = $this->formats[$format]->generateMatches($teams, (int)$tournament['id']);

        return $this->persistMatches($matches, (int)$tournament['id']);
    }

    /**
     * Seeding (Kura çekimi) mantığı:
     * 1 numara vs son numara, 2 vs sondan 2... şeklinde eşleşir.
     * Bu sayede en güçlüler final öncesi karşılaşmaz.
     */
    private function getSeededTeams(int $tournamentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name, t.tag, t.rank_point
            FROM tournament_teams tt
            JOIN Team t ON t.id = tt.team_id AND t.deleted_at IS NULL
            WHERE tt.tournament_id = ?
            ORDER BY t.rank_point DESC
        ");
        $stmt->execute([$tournamentId]);
        return $stmt->fetchAll();
    }

    private function persistMatches(array $matches, int $tournamentId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO Matches
                    (tournament_id, round_number, stage, team1_id, team2_id, date, deleted_at)
                VALUES
                    (:tournament_id, :round_number, :stage, :team1_id, :team2_id, :date, NULL)
            ");

            foreach ($matches as $match) {
                $stmt->execute([
                    ':tournament_id' => $tournamentId,
                    ':round_number'  => $match['round_number'],
                    ':stage'         => $match['stage'],
                    ':team1_id'      => $match['team1_id'],
                    ':team2_id'      => $match['team2_id'],
                    ':date'          => $match['date'],
                ]);
            }

            $this->pdo->commit();
            return true;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->log("[ENGINE] DB Error: " . $e->getMessage());
            return false;
        }
    }
}

?>