CREATE TABLE Game (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    genre VARCHAR(50),
    max_team_size BIGINT NOT NULL,
    api_source_url VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP(6) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Team (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    rank_point DECIMAL(18,6) DEFAULT 0 NOT NULL,
    logo_url VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Player (
    id BIGINT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password_hash CHAR(64) NOT NULL,
    birth_date DATETIME,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP(6),
    team_id BIGINT,
    user_type VARCHAR(20) NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT player_email_un UNIQUE (email),
    CONSTRAINT player_username_un UNIQUE (username)
) ENGINE=InnoDB;

CREATE TABLE Tournament (
    id BIGINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    prize_pool DECIMAL(18,6),
    max_teams BIGINT,
    organizer_id BIGINT NOT NULL,
    game_id BIGINT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE Matches (
    id BIGINT NOT NULL AUTO_INCREMENT,
    date DATETIME NOT NULL,
    score_team1 BIGINT,
    score_team2 BIGINT,
    stage VARCHAR(30),
    away_team_id BIGINT NOT NULL,
    home_team_id BIGINT NOT NULL,
    tournament_id BIGINT NOT NULL,
    referee_id BIGINT,
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

ALTER TABLE Matches 
ADD CONSTRAINT match_scores_ck 
CHECK (score_team1 >= 0 AND score_team2 >= 0);

ALTER TABLE Matches 
ADD CONSTRAINT Matches_Player_FK 
FOREIGN KEY (referee_id) REFERENCES Player (id);

ALTER TABLE Matches 
ADD CONSTRAINT Matches_Home_Team_FK 
FOREIGN KEY (home_team_id) REFERENCES Team (id);

ALTER TABLE Matches 
ADD CONSTRAINT Matches_Away_Team_FK 
FOREIGN KEY (away_team_id) REFERENCES Team (id);

ALTER TABLE Matches 
ADD CONSTRAINT Matches_Tournament_FK 
FOREIGN KEY (tournament_id) REFERENCES Tournament (id);

ALTER TABLE Player ADD CONSTRAINT Player_Team_FK 
FOREIGN KEY (team_id) REFERENCES Team (id) ON DELETE SET NULL;

ALTER TABLE Prize 
ADD CONSTRAINT prize_amount_ck CHECK (amount > 0);

ALTER TABLE Prize 
ADD CONSTRAINT Prize_Tournament_FK 
FOREIGN KEY (tournament_id) REFERENCES Tournament (id);

ALTER TABLE Referee 
ADD CONSTRAINT Referee_Player_FK 
FOREIGN KEY (id) REFERENCES Player (id);

ALTER TABLE Tournament 
ADD CONSTRAINT tournament_dates_ck CHECK (end_date >= start_date);

ALTER TABLE Tournament 
ADD CONSTRAINT tournament_max_teams_ck CHECK (max_teams > 1);

ALTER TABLE Tournament 
ADD CONSTRAINT Tournament_Game_FK 
FOREIGN KEY (game_id) REFERENCES Game (id);

ALTER TABLE Tournament 
ADD CONSTRAINT Tournament_Player_FK 
FOREIGN KEY (organizer_id) REFERENCES Player (id);

ALTER TABLE tournament_sponsor 
ADD CONSTRAINT Tour_Spon_Sponsor_FK 
FOREIGN KEY (sponsor_id) REFERENCES Sponsor (id);

ALTER TABLE tournament_sponsor 
ADD CONSTRAINT Tour_Spon_Tournament_FK 
FOREIGN KEY (tournament_id) REFERENCES Tournament (id);