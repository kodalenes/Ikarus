-- ============================================================
--  IKARUS — TEST VERİSİ
--  Tüm şifreler: Test1234
--  Bu dosyayı doğrudan MySQL / phpMyAdmin'e çalıştırabilirsin.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─── 1. GAME ────────────────────────────────────────────────
INSERT INTO Game (id, name, genre, max_team_size, api_source_url) VALUES
(1, 'Valorant',          'FPS',    5, 'https://api.henrikdev.xyz/valorant'),
(2, 'League of Legends', 'MOBA',   5, 'https://ddragon.leagueoflegends.com'),
(3, 'CS2',               'FPS',    5, NULL),
(4, 'Rocket League',     'Sports', 3, NULL),
(5, 'FIFA 25',           'Sports', 1, NULL);

-- ─── 2. TEAM (captain_id sonra UPDATE ile set edilir) ────────
INSERT INTO Team (id, name, rank_point, invitation_code, captain_id) VALUES
(1, 'NightFall',    1200.00, 'NF001AAA', NULL),
(2, 'StormRisers',   980.00, 'SR002BBB', NULL),
(3, 'IronClad',      850.00, 'IC003CCC', NULL),
(4, 'Phantom Squad', 760.00, 'PS004DDD', NULL),
(5, 'Nova Esports',  640.00, 'NV005EEE', NULL);

-- ─── 3. PLAYER ──────────────────────────────────────────────
-- Sifre: Test1234  bcrypt $2y$10$
SET @h = '$2y$10$.st2tyyN60qoOgd7/thw4O8w657l2Y8luDHqOZQ7JUw60YQ..VqTK';

INSERT INTO Player (id, username, email, password_hash, birth_date, team_id, user_type) VALUES
(1,  'admin_ikarus',  'admin@ikarus.gg',       @h, '1990-01-15', NULL, 'admin'),
(2,  'org_mehmet',    'mehmet@ikarus.gg',       @h, '1992-03-22', NULL, 'organizer'),
(3,  'org_elif',      'elif@ikarus.gg',         @h, '1994-07-10', NULL, 'organizer'),
(4,  'shadow_x',      'shadowx@mail.com',       @h, '1999-05-18', 1,    'player'),
(5,  'lunar_k',       'lunark@mail.com',         @h, '2000-11-02', 1,    'player'),
(6,  'frost_aim',     'frostaim@mail.com',       @h, '2001-02-28', 1,    'player'),
(7,  'viper_nf',      'viper_nf@mail.com',       @h, '1998-09-14', 1,    'player'),
(8,  'blaze_nf',      'blaze_nf@mail.com',       @h, '2002-04-07', 1,    'player'),
(9,  'storm_king',    'stormking@mail.com',      @h, '1997-12-01', 2,    'player'),
(10, 'thunder_r',     'thunderr@mail.com',        @h, '2000-06-30', 2,    'player'),
(11, 'volt_sr',       'voltsr@mail.com',          @h, '2001-08-17', 2,    'player'),
(12, 'gale_sr',       'galesr@mail.com',          @h, '1999-03-25', 2,    'player'),
(13, 'cyclone_sr',    'cyclonesr@mail.com',        @h, '2002-01-11', 2,    'player'),
(14, 'iron_wall',     'ironwall@mail.com',        @h, '1998-07-22', 3,    'player'),
(15, 'steel_ic',      'steelic@mail.com',          @h, '2000-10-05', 3,    'player'),
(16, 'forge_ic',      'forgeic@mail.com',          @h, '2001-12-19', 3,    'player'),
(17, 'anvil_ic',      'anvil_ic@mail.com',         @h, '1999-02-14', 3,    'player'),
(18, 'titan_ic',      'titan_ic@mail.com',         @h, '2003-06-08', 3,    'player'),
(19, 'phantom_one',   'phantomone@mail.com',      @h, '1997-11-30', 4,    'player'),
(20, 'specter_ps',    'specterps@mail.com',        @h, '2001-04-21', 4,    'player'),
(21, 'wraith_ps',     'wraithps@mail.com',         @h, '2000-08-13', 4,    'player'),
(22, 'reaper_ps',     'reaperps@mail.com',         @h, '1999-01-06', 4,    'player'),
(23, 'banshee_ps',    'bansheeps@mail.com',        @h, '2002-09-27', 4,    'player'),
(24, 'nova_ace',      'novaace@mail.com',          @h, '1998-03-16', 5,    'player'),
(25, 'comet_nv',      'cometnv@mail.com',          @h, '2001-07-04', 5,    'player'),
(26, 'pulsar_nv',     'pulsarnv@mail.com',         @h, '2000-12-22', 5,    'player'),
(27, 'nebula_nv',     'nebulanv@mail.com',         @h, '1999-05-09', 5,    'player'),
(28, 'quasar_nv',     'quasarnv@mail.com',         @h, '2003-02-03', 5,    'player'),
(29, 'free_agent1',   'free1@mail.com',            @h, '2000-01-01', NULL, 'player'),
(30, 'free_agent2',   'free2@mail.com',            @h, '2001-06-15', NULL, 'player');

-- ─── 4. captain_id guncelle ──────────────────────────────────
UPDATE Team SET captain_id = 4  WHERE id = 1;
UPDATE Team SET captain_id = 9  WHERE id = 2;
UPDATE Team SET captain_id = 14 WHERE id = 3;
UPDATE Team SET captain_id = 19 WHERE id = 4;
UPDATE Team SET captain_id = 24 WHERE id = 5;

-- ─── 5. SPONSOR ─────────────────────────────────────────────
INSERT INTO Sponsor (id, company_name, contribution, contract_date, website_url) VALUES
(1, 'TechGear Pro',  15000.00, '2025-01-10', 'https://techgear.example.com'),
(2, 'EnergyUp',       8000.00, '2025-02-01', 'https://energyup.example.com'),
(3, 'GameFuel TR',   12000.00, '2025-03-15', 'https://gamefuel.example.com');

-- ─── 6. TOURNAMENT ──────────────────────────────────────────
INSERT INTO Tournament
    (id, name, start_date, end_date, status, prize_pool, max_teams,
     organizer_id, game_id, prize_1st, prize_2nd, prize_3rd, created_at)
VALUES
(1, 'Ikarus Valorant Spring Cup',
    '2025-03-01 10:00:00', '2025-03-15 22:00:00',
    'finished', 10000.00, 8, 2, 1, 6000.00, 2500.00, 1500.00, '2025-02-01 00:00:00'),
(2, 'CS2 Summer Showdown',
    '2026-06-01 12:00:00', '2026-06-20 22:00:00',
    'registration', 5000.00, 8, 2, 3, 3000.00, 1200.00, 800.00, '2026-04-01 00:00:00'),
(3, 'LoL Legends Battle',
    '2026-04-20 14:00:00', '2026-05-10 22:00:00',
    'live', 8000.00, 8, 3, 2, 5000.00, 2000.00, 1000.00, '2026-03-10 00:00:00'),
(4, 'Rocket League Turbo Cup',
    '2026-07-15 16:00:00', '2026-07-25 22:00:00',
    'upcoming', 3000.00, 8, 3, 4, 1800.00, 800.00, 400.00, '2026-04-15 00:00:00'),
(5, 'FIFA 25 Championship',
    '2026-09-01 10:00:00', '2026-09-30 22:00:00',
    'draft', 2000.00, 16, 2, 5, 1200.00, 500.00, 300.00, '2026-04-20 00:00:00');

-- ─── 7. TOURNAMENT RULES ────────────────────────────────────
INSERT INTO Tournament_Rule (tournament_id, rule_text, sort_order) VALUES
(1, 'Format Bo3, Final Bo5', 1),
(1, 'Her takimda 5 aktif oyuncu zorunludur', 2),
(1, 'Check-in: mactan 15 dakika once zorunlu', 3),
(2, 'Format Bo3', 1),
(2, 'Her takimda 5 aktif oyuncu zorunludur', 2),
(2, 'Skor itirazlari 10 dakika icinde yapilmalidir', 3),
(2, 'Hack/cheat tespitinde direkt diskalifiye', 4),
(3, 'Format Bo3, Final Bo5', 1),
(3, 'Her takimda 5 aktif oyuncu zorunludur', 2),
(3, 'Turnuva suresince takas yasaktir', 3),
(4, 'Format Bo5 (Rocket League)', 1),
(4, 'Her takimda 3 aktif oyuncu zorunludur', 2),
(4, 'Baglanti sorunu ekibin sorumlulugundadir', 3);

-- ─── 8. TOURNAMENT - SPONSOR ────────────────────────────────
INSERT INTO tournament_sponsor (tournament_id, sponsor_id) VALUES
(1,1),(1,2),(2,2),(2,3),(3,1),(3,3),(4,2);

-- ─── 9. TOURNAMENT - TEAM ───────────────────────────────────
INSERT INTO tournament_teams (team_id, tournament_id, registered_at) VALUES
(1,1,'2025-02-15 10:00:00'),
(2,1,'2025-02-16 11:00:00'),
(3,1,'2025-02-17 09:30:00'),
(4,1,'2025-02-18 14:00:00'),
(5,1,'2025-02-19 16:00:00'),
(1,2,'2026-04-05 10:00:00'),
(3,2,'2026-04-06 13:00:00'),
(5,2,'2026-04-07 15:00:00'),
(1,3,'2026-03-15 10:00:00'),
(2,3,'2026-03-16 11:00:00'),
(3,3,'2026-03-17 09:30:00'),
(4,3,'2026-03-18 14:00:00'),
(2,4,'2026-04-20 10:00:00'),
(4,4,'2026-04-21 12:00:00');

-- ─── 10. MATCHES ────────────────────────────────────────────
INSERT INTO Matches (id, date, score_team1, score_team2, stage, home_team_id, away_team_id, tournament_id) VALUES
(1,  '2025-03-02 14:00:00', 2,    0,    'Quarterfinal', 1, 5, 1),
(2,  '2025-03-02 17:00:00', 2,    1,    'Quarterfinal', 2, 4, 1),
(3,  '2025-03-03 14:00:00', 1,    2,    'Quarterfinal', 3, 1, 1),
(4,  '2025-03-03 17:00:00', 2,    0,    'Quarterfinal', 2, 3, 1),
(5,  '2025-03-08 15:00:00', 2,    1,    'Semifinal',    1, 4, 1),
(6,  '2025-03-08 18:00:00', 1,    2,    'Semifinal',    3, 2, 1),
(7,  '2025-03-15 18:00:00', 3,    1,    'Final',        1, 2, 1),
(8,  '2026-04-21 14:00:00', 2,    0,    'Quarterfinal', 1, 4, 3),
(9,  '2026-04-21 17:00:00', 1,    2,    'Quarterfinal', 2, 3, 3),
(10, '2026-04-25 15:00:00', NULL, NULL, 'Semifinal',    1, 3, 3),
(11, '2026-04-26 18:00:00', NULL, NULL, 'Semifinal',    2, 4, 3);

-- ─── 11. PRIZE ──────────────────────────────────────────────
INSERT INTO Prize (id, amount, type, awarded_date, tournament_id) VALUES
(1, 6000.00, 'cash', '2025-03-16', 1),
(2, 2500.00, 'cash', '2025-03-16', 1),
(3, 1500.00, 'cash', '2025-03-16', 1);

-- ─── 12. INVITATIONS ────────────────────────────────────────
INSERT INTO Invitations (team_id, sender_id, receiver_id, status, sent_at) VALUES
(1,  4, 29, 'pending',  NOW()),
(2,  9, 30, 'declined', NOW()),
(3, 14, 30, 'pending',  NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  TEST GIRISLERI  (sifre: Test1234)
--  admin@ikarus.gg      admin
--  mehmet@ikarus.gg     organizer
--  elif@ikarus.gg       organizer
--  shadowx@mail.com     kaptan - NightFall
--  stormking@mail.com   kaptan - StormRisers
--  free1@mail.com       takimsiz oyuncu
-- ============================================================