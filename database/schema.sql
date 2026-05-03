-- ---------------------------------------------------------
-- TABLES
-- ---------------------------------------------------------

CREATE TABLE Game (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    genre VARCHAR(50),
    max_team_size BIGINT NOT NULL,
    api_source_url VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Team (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    tag VARCHAR(4) NOT NULL,
    game VARCHAR(50) NOT NULL,
    region VARCHAR(50),               
    description TEXT,                 
    rank_point DECIMAL(18,6) DEFAULT 0 NOT NULL,
    logo_url VARCHAR(255),
    invitation_code CHAR(8) UNIQUE,
    captain_id BIGINT,
    tag VARCHAR(4),
    game VARCHAR(50),
    region VARCHAR(50),
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP(6),
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Player (
    id BIGINT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    birth_date DATETIME,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP(6),
    team_id BIGINT,
    role VARCHAR(50),  
    user_type VARCHAR(20) NOT NULL,
    role VARCHAR(50),
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Tournament (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    prize_pool DECIMAL(18,6),
    prize_1st DECIMAL(10,2),
    prize_2nd DECIMAL(10,2),
    prize_3rd DECIMAL(10,2),
    max_teams BIGINT,
    organizer_id BIGINT NOT NULL,
    game_id BIGINT NOT NULL,
    checkin_minutes INT DEFAULT 15,
    noshow_minutes INT DEFAULT 10,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Matches (
    id BIGINT NOT NULL AUTO_INCREMENT,
    tournament_id BIGINT NOT NULL,
    round_number INT NOT NULL DEFAULT 1,
    stage VARCHAR(30),
    team1_id BIGINT NULL,
    team2_id BIGINT NULL,
    score_team1 BIGINT DEFAULT 0,
    score_team2 BIGINT DEFAULT 0,
    winner_id BIGINT NULL,
    next_match_id BIGINT NULL,
    date DATETIME NULL,
    referee_id BIGINT,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Prize (
    id BIGINT NOT NULL AUTO_INCREMENT,
    amount DECIMAL(18,6) NOT NULL,
    type VARCHAR(50) NOT NULL,
    awarded_date DATETIME,
    tournament_id BIGINT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Tournament_Rule (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tournament_id BIGINT NOT NULL,
    rule_text TEXT NOT NULL,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE Referee (
    id BIGINT NOT NULL,
    team_id BIGINT,
    expertise_game_id BIGINT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Sponsor (
    id BIGINT NOT NULL AUTO_INCREMENT,
    company_name VARCHAR(100) NOT NULL,
    contribution DECIMAL(18,6),
    contract_date DATETIME,
    logo_url VARCHAR(255),
    website_url VARCHAR(255),
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE tournament_sponsor (
    tournament_id BIGINT NOT NULL,
    sponsor_id BIGINT NOT NULL,
    PRIMARY KEY (tournament_id, sponsor_id)
) ENGINE=InnoDB;

CREATE TABLE tournament_teams (
    team_id BIGINT NOT NULL,
    tournament_id BIGINT NOT NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, tournament_id)
) ENGINE=InnoDB;

CREATE TABLE Invitations (
    id BIGINT NOT NULL AUTO_INCREMENT,
    team_id BIGINT NOT NULL,
    sender_id BIGINT NOT NULL,
    receiver_id BIGINT NOT NULL,
    status ENUM('pending' , 'accepted' , 'declined') NOT NULL DEFAULT 'pending',
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Remember_Tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash)
) ENGINE=InnoDB;

CREATE TABLE Password_Reset_Tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reset_token (token_hash)
) ENGINE=InnoDB;


-- ---------------------------------------------------------
-- CONSTRAINTS & FOREIGN KEYS
-- ---------------------------------------------------------

ALTER TABLE Player 
    ADD CONSTRAINT player_email_un UNIQUE (email),
    ADD CONSTRAINT player_username_un UNIQUE (username),
    ADD CONSTRAINT Player_Team_FK FOREIGN KEY (team_id) REFERENCES Team (id) ON DELETE SET NULL;

ALTER TABLE Team
    ADD CONSTRAINT Team_Captain_FK FOREIGN KEY (captain_id) REFERENCES Player (id) ON DELETE SET NULL;

ALTER TABLE Tournament 
    ADD CONSTRAINT tournament_dates_ck CHECK (end_date >= start_date),
    ADD CONSTRAINT tournament_max_teams_ck CHECK (max_teams > 1),
    ADD CONSTRAINT Tournament_Game_FK FOREIGN KEY (game_id) REFERENCES Game (id),
    ADD CONSTRAINT Tournament_Player_FK FOREIGN KEY (organizer_id) REFERENCES Player (id);

ALTER TABLE Matches 
    ADD CONSTRAINT match_scores_ck CHECK (score_team1 >= 0 AND score_team2 >= 0),
    ADD CONSTRAINT Matches_Referee_FK FOREIGN KEY (referee_id) REFERENCES Referee(id) ON DELETE SET NULL,
    ADD CONSTRAINT Matches_Team1_FK FOREIGN KEY (team1_id) REFERENCES Team (id) ON DELETE SET NULL,
    ADD CONSTRAINT Matches_Team2_FK FOREIGN KEY (team2_id) REFERENCES Team (id) ON DELETE SET NULL,
    ADD CONSTRAINT Matches_Winner_FK FOREIGN KEY (winner_id) REFERENCES Team (id) ON DELETE SET NULL,
    ADD CONSTRAINT Matches_Tournament_FK FOREIGN KEY (tournament_id) REFERENCES Tournament (id) ON DELETE CASCADE,
    ADD CONSTRAINT Matches_NextMatch_FK FOREIGN KEY (next_match_id) REFERENCES Matches (id) ON DELETE SET NULL;

ALTER TABLE Prize 
    ADD CONSTRAINT prize_amount_ck CHECK (amount > 0),
    ADD CONSTRAINT Prize_Tournament_FK FOREIGN KEY (tournament_id) REFERENCES Tournament (id);

ALTER TABLE Tournament_Rule
    ADD CONSTRAINT Tour_Rule_Tournament_FK FOREIGN KEY (tournament_id) REFERENCES Tournament(id) ON DELETE CASCADE;

ALTER TABLE Referee 
    ADD CONSTRAINT Referee_Player_FK FOREIGN KEY (id) REFERENCES Player (id),
    ADD CONSTRAINT Referee_Game_FK FOREIGN KEY (expertise_game_id) REFERENCES Game (id);

ALTER TABLE tournament_sponsor 
    ADD CONSTRAINT Tour_Spon_Sponsor_FK FOREIGN KEY (sponsor_id) REFERENCES Sponsor (id),
    ADD CONSTRAINT Tour_Spon_Tournament_FK FOREIGN KEY (tournament_id) REFERENCES Tournament (id);

ALTER TABLE tournament_teams
    ADD CONSTRAINT TournTeam_Team_FK FOREIGN KEY (team_id) REFERENCES Team (id) ON DELETE CASCADE,
    ADD CONSTRAINT TournTeam_Tournament_FK FOREIGN KEY (tournament_id) REFERENCES Tournament (id) ON DELETE CASCADE;

ALTER TABLE Invitations
    ADD CONSTRAINT Inv_Unique_Pending UNIQUE (team_id, receiver_id, status),
    ADD CONSTRAINT Invitations_Team_FK FOREIGN KEY (team_id) REFERENCES Team (id) ON DELETE CASCADE,
    ADD CONSTRAINT Invitations_Sender_FK FOREIGN KEY (sender_id) REFERENCES Player (id) ON DELETE CASCADE,
    ADD CONSTRAINT Invitations_Receiver_FK FOREIGN KEY (receiver_id) REFERENCES Player (id) ON DELETE CASCADE;

ALTER TABLE Remember_Tokens
    ADD CONSTRAINT Remember_Tokens_Player_FK FOREIGN KEY (player_id) REFERENCES Player(id) ON DELETE CASCADE;

ALTER TABLE Password_Reset_Tokens
    ADD CONSTRAINT Pwd_Reset_Player_FK FOREIGN KEY (player_id) REFERENCES Player(id) ON DELETE CASCADE;
