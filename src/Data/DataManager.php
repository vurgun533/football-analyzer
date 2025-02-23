<?php

class DataManager
{
    private $database;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    public function saveMatch(array $matchData): int
    {
        $sql = "INSERT INTO matches (fixture_id, league_id, team_home, team_away, match_date, stadium) 
                VALUES (:fixture_id, :league_id, :team_home, :team_away, :match_date, :stadium)";
        
        $this->database->execute($sql, [
            'fixture_id' => $matchData['fixture_id'],
            'league_id' => $matchData['league_id'],
            'team_home' => $matchData['team_home'],
            'team_away' => $matchData['team_away'],
            'match_date' => $matchData['match_date'],
            'stadium' => $matchData['stadium'] ?? null
        ]);

        // Son eklenen maçın ID'sini döndür
        return $this->database->lastInsertId();
    }

    public function saveMatchPredictions(int $matchId, array $predictions): void
    {
        $sql = "INSERT INTO match_predictions (match_id, winner_prediction, win_confidence, 
                predicted_score, expected_total_goals, btts_prediction, over_under_2_5) 
                VALUES (:match_id, :winner_prediction, :win_confidence, :predicted_score, 
                :expected_total_goals, :btts_prediction, :over_under_2_5)";
        
        $this->database->execute($sql, [
            'match_id' => $matchId,
            'winner_prediction' => $predictions['winner_prediction'],
            'win_confidence' => $predictions['win_confidence'],
            'predicted_score' => $predictions['predicted_score'],
            'expected_total_goals' => $predictions['expected_total_goals'],
            'btts_prediction' => $predictions['btts_prediction'],
            'over_under_2_5' => $predictions['over_under_2_5']
        ]);
    }

    public function saveMatchStats(int $matchId, array $stats): void
    {
        $sql = "INSERT INTO match_stats (match_id, possession_home, possession_away, 
                shots_home, shots_away, shots_on_target_home, shots_on_target_away, 
                corners_home, corners_away, goals_scored_home, goals_scored_away,
                goals_conceded_home, goals_conceded_away, clean_sheets_home, 
                clean_sheets_away, form_home, form_away) 
                VALUES (:match_id, :possession_home, :possession_away, :shots_home, 
                :shots_away, :shots_on_target_home, :shots_on_target_away, 
                :corners_home, :corners_away, :goals_scored_home, :goals_scored_away,
                :goals_conceded_home, :goals_conceded_away, :clean_sheets_home,
                :clean_sheets_away, :form_home, :form_away)";
        
        $this->database->execute($sql, array_merge(['match_id' => $matchId], $stats));
    }

    public function saveH2HStats(int $matchId, array $h2hStats): void
    {
        $sql = "INSERT INTO h2h_stats (match_id, home_wins, away_wins, draws, 
                avg_goals, btts_ratio, total_goals) 
                VALUES (:match_id, :home_wins, :away_wins, :draws, :avg_goals, 
                :btts_ratio, :total_goals)";
        
        $this->database->execute($sql, array_merge(['match_id' => $matchId], $h2hStats));
    }

    public function saveRecommendedBets(int $matchId, array $bets): void
    {
        $sql = "INSERT INTO recommended_bets (match_id, bet_type, prediction, 
                confidence, description) 
                VALUES (:match_id, :bet_type, :prediction, :confidence, :description)";
        
        foreach ($bets as $bet) {
            $this->database->execute($sql, array_merge(
                ['match_id' => $matchId],
                $bet
            ));
        }
    }

    public function getMatchById(int $matchId): array
    {
        $sql = "SELECT * FROM matches WHERE id = :match_id";
        $result = $this->database->query($sql, ['match_id' => $matchId]);
        return $result[0] ?? [];
    }

    public function getMatchPredictions(int $matchId): array
    {
        $sql = "SELECT * FROM match_predictions WHERE match_id = :match_id";
        return $this->database->query($sql, ['match_id' => $matchId]);
    }

    public function getMatchStats(int $matchId): array
    {
        $sql = "SELECT * FROM match_stats WHERE match_id = :match_id";
        return $this->database->query($sql, ['match_id' => $matchId]);
    }

    public function getH2HStats(int $matchId): array
    {
        $sql = "SELECT * FROM h2h_stats WHERE match_id = :match_id";
        return $this->database->query($sql, ['match_id' => $matchId]);
    }

    public function getRecommendedBets(int $matchId): array
    {
        $sql = "SELECT * FROM recommended_bets WHERE match_id = :match_id";
        return $this->database->query($sql, ['match_id' => $matchId]);
    }
} 