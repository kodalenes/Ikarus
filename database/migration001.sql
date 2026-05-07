ALTER TABLE Tournament
    ADD COLUMN bracket_generated TINYINT(1) DEFAULT 0 AFTER status,
    ADD COLUMN format VARCHAR(30) DEFAULT 'single_elimination' AFTER bracket_generated,
    ADD COLUMN updated_at DATETIME DEFAULT NULL;

CREATE INDEX idx_tournament_cron
    ON Tournament(status, bracket_generated, deleted_at);