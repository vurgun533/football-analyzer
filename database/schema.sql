CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fixture_id INT NOT NULL,
    league_id INT NOT NULL,
    team_home VARCHAR(100) NOT NULL,
    team_away VARCHAR(100) NOT NULL,
    match_date DATETIME NOT NULL,
    stadium VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS match_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    winner_prediction ENUM('home', 'draw', 'away'),
    win_confidence FLOAT,
    predicted_score VARCHAR(10),
    expected_total_goals FLOAT,
    btts_prediction ENUM('yes', 'no'),
    over_under_2_5 ENUM('over', 'under'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id)
);

CREATE TABLE IF NOT EXISTS match_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    possession_home FLOAT,
    possession_away FLOAT,
    shots_home INT,
    shots_away INT,
    shots_on_target_home INT,
    shots_on_target_away INT,
    corners_home INT,
    corners_away INT,
    goals_scored_home INT,
    goals_scored_away INT,
    goals_conceded_home INT,
    goals_conceded_away INT,
    clean_sheets_home INT,
    clean_sheets_away INT,
    form_home VARCHAR(10),
    form_away VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id)
);

CREATE TABLE IF NOT EXISTS h2h_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    home_wins INT,
    away_wins INT,
    draws INT,
    avg_goals FLOAT,
    btts_ratio FLOAT,
    total_goals INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id)
);

CREATE TABLE IF NOT EXISTS recommended_bets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    bet_type VARCHAR(50),
    prediction VARCHAR(50),
    confidence FLOAT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id)
);

CREATE TABLE IF NOT EXISTS match_odds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    home_win FLOAT,
    draw FLOAT,
    away_win FLOAT,
    over_2_5 FLOAT,
    under_2_5 FLOAT,
    btts_yes FLOAT,
    btts_no FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id)
);

CREATE TABLE IF NOT EXISTS predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    predicted_winner VARCHAR(50),
    win_confidence FLOAT,
    predicted_score VARCHAR(10),
    predicted_total_goals FLOAT,
    btts_prediction VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id)
); 