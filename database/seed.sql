-- Disable foreign key checks for bulk data insertion
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- 1. GAMES
-- ---------------------------------------------------------
INSERT INTO Game (id, name, genre, max_team_size, api_source_url) VALUES 
(1, 'Counter-Strike 2', 'Tactical Shooter', 5, 'https://api.steampowered.com/cs2'),
(2, 'Valorant', 'Tactical Shooter', 5, 'https://api.riotgames.com/val'),
(3, 'EA Sports FC 24', 'Sports', 11, 'https://api.ea.com/fc24');

-- ---------------------------------------------------------
-- 2. TEAMS (Önce takımlar kaptansız (NULL) olarak oluşturulur)
-- ---------------------------------------------------------
INSERT INTO Team (id, name, rank_point, tag, game, region, description, captain_id) VALUES 
(1, 'Ikarus Esports', 1500.50, 'IKRS', 'Counter-Strike 2', 'TR', 'Official team of Ikarus platform.', NULL),
(2, 'Maanzo Gaming', 1200.00, 'MNZ', 'Counter-Strike 2', 'EU', 'Competitive CS2 roster.', NULL),
(3, 'İlhan Strikers', 1350.25, 'ILHN', 'Counter-Strike 2', 'TR', 'Rising stars of the local scene.', NULL),
(4, 'Enver Knights', 1420.00, 'ENVR', 'Counter-Strike 2', 'EU', 'Veterans of tactical shooters.', NULL);

-- ---------------------------------------------------------
-- 3. PLAYERS (Oyuncular kendi takımlarına atanarak eklenir)
-- ---------------------------------------------------------
INSERT INTO Player (id, username, email, password_hash, birth_date, team_id, user_type, role) VALUES 
(1, 'eneskodal', 'enes@ikarus.gg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2006-01-01', 1, 'organizer', 'IGL'),
(2, 'maanzo', 'maanzo@ikarus.gg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2004-05-12', 2, 'player', 'Entry Fragger'),
(3, 'ilhan', 'ilhan@ikarus.gg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2005-08-20', 3, 'player', 'AWPer'),
(4, 'enver', 'enver@ikarus.gg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2003-11-30', 4, 'player', 'Support'),
(5, 'referee_john', 'ref@ikarus.gg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1995-02-15', NULL, 'referee', 'Admin');

-- ---------------------------------------------------------
-- 3.5 SET CAPTAINS (Dairesel bağımlılık sorunu UPDATE ile çözülür)
-- ---------------------------------------------------------
UPDATE Team SET captain_id = 1 WHERE id = 1;
UPDATE Team SET captain_id = 2 WHERE id = 2;
UPDATE Team SET captain_id = 3 WHERE id = 3;
UPDATE Team SET captain_id = 4 WHERE id = 4;

-- ---------------------------------------------------------
-- 4. TOURNAMENT
-- ---------------------------------------------------------
INSERT INTO Tournament (id, name, description, start_date, end_date, status, prize_pool, prize_1st, prize_2nd, prize_3rd, max_teams, organizer_id, game_id, checkin_minutes, noshow_minutes) VALUES 
(1, 'Ikarus CS2 Spring Cup', 'The most competitive CS2 spring tournament in the region.', '2026-05-10 18:00:00', '2026-05-12 22:00:00', 'ongoing', 10000.00, 6000.00, 3000.00, 1000.00, 4, 1, 1, 15, 10),
(2, 'Valorant Summer Clash', 'Registration is open for the upcoming Valorant series.', '2026-06-01 19:00:00', '2026-06-05 23:00:00', 'open', 5000.00, 3000.00, 1500.00, 500.00, 8, 1, 2, 30, 15);

-- ---------------------------------------------------------
-- 5. TOURNAMENT TEAMS (Registrations)
-- ---------------------------------------------------------
INSERT INTO tournament_teams (team_id, tournament_id) VALUES 
(1, 1), (2, 1), (3, 1), (4, 1);

-- ---------------------------------------------------------
-- 6. MATCHES (The Bracket System)
-- ---------------------------------------------------------
-- Match 3 is the Final. Match 1 and Match 2 are Semi-Finals that feed into Match 3.
-- We are simulating that Semi-Finals are played, and the Final is TBD (To Be Decided)

-- Final Match (ID: 3) - Round 2
INSERT INTO Matches (id, tournament_id, round_number, stage, team1_id, team2_id, score_team1, score_team2, winner_id, next_match_id, date) VALUES 
(3, 1, 2, 'Final', 1, 3, 0, 0, NULL, NULL, '2026-05-12 20:00:00');

-- Semi-Final 1 (ID: 1) - Round 1 (Winner goes to Match 3)
INSERT INTO Matches (id, tournament_id, round_number, stage, team1_id, team2_id, score_team1, score_team2, winner_id, next_match_id, date) VALUES 
(1, 1, 1, 'Semi-Final', 1, 2, 13, 10, 1, 3, '2026-05-10 18:00:00');

-- Semi-Final 2 (ID: 2) - Round 1 (Winner goes to Match 3)
INSERT INTO Matches (id, tournament_id, round_number, stage, team1_id, team2_id, score_team1, score_team2, winner_id, next_match_id, date) VALUES 
(2, 1, 1, 'Semi-Final', 3, 4, 16, 14, 3, 3, '2026-05-10 19:30:00');

-- ---------------------------------------------------------
-- 7. REFEREE
-- ---------------------------------------------------------
INSERT INTO Referee (id, team_id, expertise_game_id) VALUES 
(5, NULL, 1);

-- ---------------------------------------------------------
-- 8. SPONSORS & PRIZES
-- ---------------------------------------------------------
INSERT INTO Sponsor (id, company_name, contribution, website_url) VALUES 
(1, 'HyperX', 5000.00, 'https://hyperx.com'),
(2, 'Logitech G', 5000.00, 'https://logitechg.com');

INSERT INTO tournament_sponsor (tournament_id, sponsor_id) VALUES 
(1, 1), (1, 2);

INSERT INTO Prize (id, amount, type, tournament_id) VALUES 
(1, 6000.00, 'Cash - 1st Place', 1),
(2, 3000.00, 'Cash - 2nd Place', 1),
(3, 1000.00, 'Cash - 3rd Place', 1);

-- ---------------------------------------------------------
-- 9. TOURNAMENT RULES
-- ---------------------------------------------------------
INSERT INTO Tournament_Rule (tournament_id, rule_text, sort_order) VALUES 
(1, 'All players must record POV demos and keep them for 48 hours.', 1),
(1, 'Toxic behavior or exploiting game bugs will result in an immediate ban.', 2),
(1, 'Teams must be present in the lobby 15 minutes before the start time.', 3);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;