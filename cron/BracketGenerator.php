<?php
// cron/BracketGenerator.php

class BracketGenerator
{
    public function __construct(
        private PDO $pdo,
        private CronLogger $logger,
        private TournamentEngine $engine
    ) {}

    public function run(): void
    {
        // bracket_generated = 0 ve status = live olan turnuvaları bul
        $stmt = $this->pdo->query("
            SELECT *
            FROM Tournament
            WHERE status = 'live'
              AND (bracket_generated = 0 OR bracket_generated IS NULL)
              AND deleted_at IS NULL
        ");

        $tournaments = $stmt->fetchAll();

        if (empty($tournaments)) {
            $this->logger->log("[BRACKET] There is no tournament waiting.");
            return;
        }

        foreach ($tournaments as $tournament) {
            $this->logger->log("[BRACKET] Processing: #{$tournament['id']} '{$tournament['name']}'");

            // Bracket zaten oluşturulmuş mu? (çift çalışmayı engelle)
            if ($this->bracketExists((int)$tournament['id'])) {
                $this->markAsGenerated((int)$tournament['id']);
                $this->logger->log("[BRACKET] Already exists, skipped.");
                continue;
            }

            $success = $this->engine->buildBracket($tournament);

            if ($success) {
                $this->markAsGenerated((int)$tournament['id']);
                $this->logger->log("[BRACKET] ✓ Created: #{$tournament['id']}");
            } else {
                $this->logger->log("[BRACKET] ✗ Failed: #{$tournament['id']}");
            }
        }
    }

    private function bracketExists(int $tournamentId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM Matches WHERE tournament_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$tournamentId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function markAsGenerated(int $tournamentId): void
    {
        $this->pdo->prepare(
            "UPDATE Tournament SET bracket_generated = 1 WHERE id = ?"
        )->execute([$tournamentId]);
    }
}