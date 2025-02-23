<?php

use FootballAnalyzer\Config\DatabaseConfig;
use FootballAnalyzer\Database\MySQLDatabase;
use FootballAnalyzer\Data\DataManager;

class FootballAnalyzer {
    private $apiKey = 'aac2d042fcmsh1178189679a821cp1ec080jsn14b2c2789ba3';
    private $apiHost = 'api-football-v1.p.rapidapi.com';
    private DataManager $dataManager;

    public function __construct()
    {
        $config = new DatabaseConfig();
        $database = new MySQLDatabase($config);
        $database->connect();
        $this->dataManager = new DataManager($database);
    }

    // API isteği yapmak için yardımcı metod
    private function makeApiRequest($url) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://" . $this->apiHost . "/v3/" . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "X-RapidAPI-Host: " . $this->apiHost,
                "X-RapidAPI-Key: " . $this->apiKey
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);

        if ($err) {
            return ['errors' => ['curl' => $err]];
        }

        if ($httpcode != 200) {
            return ['errors' => ['http' => 'HTTP ' . $httpcode . ': ' . $response]];
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['errors' => ['json' => json_last_error_msg()]];
        }

        return $data;
    }

    // Takım istatistiklerini analiz eden metod
    private function analyzeTeamStats($stats) {
        if (!$stats) {
            return [
                'goals_per_match' => 0,
                'clean_sheets' => 0,
                'failed_to_score' => 0,
                'avg_goals_scored' => 0,
                'avg_goals_conceded' => 0,
                'form_points' => 0
            ];
        }

        return [
            'goals_per_match' => $stats['goals']['for']['average']['total'] ?? 0,
            'clean_sheets' => $stats['clean_sheet']['total'] ?? 0,
            'failed_to_score' => $stats['failed_to_score']['total'] ?? 0,
            'avg_goals_scored' => $stats['goals']['for']['average']['total'] ?? 0,
            'avg_goals_conceded' => $stats['goals']['against']['average']['total'] ?? 0,
            'form_points' => $this->calculateFormPoints($stats['form'] ?? '')
        ];
    }

    // Takımın puan durumunu bulan metod
    private function findTeamStanding($standings, $teamId) {
        if (!is_array($standings)) {
            return [
                'rank' => 0,
                'points' => 0,
                'goalsDiff' => 0,
                'form' => ''
            ];
        }

        foreach ($standings as $standing) {
            if (isset($standing['team']['id']) && $standing['team']['id'] == $teamId) {
                return [
                    'rank' => $standing['rank'] ?? 0,
                    'points' => $standing['points'] ?? 0,
                    'goalsDiff' => $standing['goalsDiff'] ?? 0,
                    'form' => $standing['form'] ?? ''
                ];
            }
        }

        return [
            'rank' => 0,
            'points' => 0,
            'goalsDiff' => 0,
            'form' => ''
        ];
    }

    // H2H analizi yapan metod
    private function analyzeH2H($matches) {
        if (!$matches) {
            return [
                'total_matches' => 0,
                'home_wins' => 0,
                'away_wins' => 0,
                'draws' => 0,
                'avg_goals' => 0
            ];
        }

        $stats = [
            'total_matches' => count($matches),
            'home_wins' => 0,
            'away_wins' => 0,
            'draws' => 0,
            'total_goals' => 0
        ];

        foreach ($matches as $match) {
            if ($match['teams']['home']['winner']) {
                $stats['home_wins']++;
            } elseif ($match['teams']['away']['winner']) {
                $stats['away_wins']++;
            } else {
                $stats['draws']++;
            }
            $stats['total_goals'] += ($match['goals']['home'] + $match['goals']['away']);
        }

        $stats['avg_goals'] = $stats['total_matches'] > 0 ? 
            $stats['total_goals'] / $stats['total_matches'] : 0;

        return $stats;
    }

    // Form analizi yapan metod
    private function analyzeForm($matches) {
        $wins = 0; $draws = 0; $losses = 0;
        $goalsFor = 0; $goalsAgainst = 0;
        
        foreach ($matches as $match) {
            if ($match['teams']['home']['winner']) $wins++;
            elseif ($match['teams']['away']['winner']) $losses++;
            else $draws++;
            
            $goalsFor += $match['goals']['home'];
            $goalsAgainst += $match['goals']['away'];
        }
        
        return [
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'goals_scored_avg' => $goalsFor / 10,
            'goals_conceded_avg' => $goalsAgainst / 10
        ];
    }

    // Form puanlarını hesaplayan yardımcı metod
    private function calculateFormPoints($form) {
        $points = 0;
        $form = str_split($form);
        foreach ($form as $result) {
            switch ($result) {
                case 'W': $points += 3; break;
                case 'D': $points += 1; break;
            }
        }
        return $points;
    }

    // Tahmin üreten metod
    private function generatePrediction($analysis) {
        $homeScore = 0;
        $awayScore = 0;
        
        // Form puanı (son 10 maç)
        $homeScore += ($analysis['home_team']['form']['wins'] * 3) + $analysis['home_team']['form']['draws'];
        $awayScore += ($analysis['away_team']['form']['wins'] * 3) + $analysis['away_team']['form']['draws'];
        
        // Lig pozisyonu etkisi
        $homeScore += (20 - $analysis['home_team']['standing']['rank']) * 2;
        $awayScore += (20 - $analysis['away_team']['standing']['rank']) * 2;
        
        // Gol averajı etkisi
        $homeScore += $analysis['home_team']['form']['goals_scored_avg'] * 2;
        $homeScore -= $analysis['home_team']['form']['goals_conceded_avg'];
        $awayScore += $analysis['away_team']['form']['goals_scored_avg'] * 2;
        $awayScore -= $analysis['away_team']['form']['goals_conceded_avg'];
        
        // Ev sahibi avantajı
        $homeScore *= 1.2;
        
        return [
            'winner_prediction' => $homeScore > $awayScore ? 'home' : 'away',
            'win_confidence' => abs($homeScore - $awayScore) / ($homeScore + $awayScore) * 100,
            'goals' => $analysis['goals'] ?? [  // Gol analizini ekleyelim
                'expected_total_goals' => 0,
                'goals_predictions' => [
                    '1_5' => 'under',
                    '2_5' => 'under',
                    '3_5' => 'under'
                ],
                'btts_prediction' => 'no',
                'confidence' => [
                    '1_5' => 0,
                    '2_5' => 0,
                    '3_5' => 0,
                    'btts' => 0
                ]
            ]
        ];
    }

    // KG (Karşılıklı Gol) analizi
    private function analyzeBTTS($homeStats, $awayStats, $h2h) {
        // H2H maçlarda KG durumu
        $h2hBttsCount = 0;
        $totalH2HMatches = 0;
        if (isset($h2h) && is_array($h2h)) {
            $lastH2HMatches = array_slice($h2h, 0, 5); // Son 5 karşılaşma
            foreach ($lastH2HMatches as $match) {
                if (isset($match['goals'])) {
                    $totalH2HMatches++;
                    if ($match['goals']['home'] > 0 && $match['goals']['away'] > 0) {
                        $h2hBttsCount++;
                    }
                }
            }
        }

        // Ev sahibi takımın ev sahibi olarak gol atma/yeme istatistikleri
        $homeTeamHomeMatches = $homeStats['fixtures']['played']['home'] ?? 0;
        $homeTeamHomeGoalsScored = $homeStats['goals']['for']['total']['home'] ?? 0;
        $homeTeamHomeGoalsConceded = $homeStats['goals']['against']['total']['home'] ?? 0;

        // Deplasman takımının deplasman gol atma/yeme istatistikleri
        $awayTeamAwayMatches = $awayStats['fixtures']['played']['away'] ?? 0;
        $awayTeamAwayGoalsScored = $awayStats['goals']['for']['total']['away'] ?? 0;
        $awayTeamAwayGoalsConceded = $awayStats['goals']['against']['total']['away'] ?? 0;

        // Ortalama hesaplamalar
        $homeTeamScoringRate = $homeTeamHomeMatches > 0 ? $homeTeamHomeGoalsScored / $homeTeamHomeMatches : 0;
        $homeTeamConcedingRate = $homeTeamHomeMatches > 0 ? $homeTeamHomeGoalsConceded / $homeTeamHomeMatches : 0;
        $awayTeamScoringRate = $awayTeamAwayMatches > 0 ? $awayTeamAwayGoalsScored / $awayTeamAwayMatches : 0;
        $awayTeamConcedingRate = $awayTeamAwayMatches > 0 ? $awayTeamAwayGoalsConceded / $awayTeamAwayMatches : 0;

        // KG olasılığı hesaplama
        $bttsChance = false;
        $bttsConfidence = 0;

        // KG için kriterler
        $criteria = [
            // Her iki takım da maç başına ortalama 1 golün üzerinde atıyor
            'scoring_power' => ($homeTeamScoringRate > 1.0 && $awayTeamScoringRate > 1.0),
            // H2H maçlarda KG oranı %60'ın üzerinde
            'h2h_history' => ($totalH2HMatches > 0 && ($h2hBttsCount / $totalH2HMatches) > 0.6),
            // Her iki takım da gol yiyor
            'defensive_weakness' => ($homeTeamConcedingRate > 0.5 && $awayTeamConcedingRate > 0.5)
        ];

        // KG kararı
        if ($criteria['scoring_power'] && $criteria['defensive_weakness']) {
            if ($criteria['h2h_history'] || ($homeTeamScoringRate > 1.5 && $awayTeamScoringRate > 1.5)) {
                $bttsChance = true;
                // Güven skoru hesaplama
                $bttsConfidence = min(
                    ($homeTeamScoringRate * 20) +
                    ($awayTeamScoringRate * 20) +
                    ($h2hBttsCount * 10) +
                    (($homeTeamConcedingRate + $awayTeamConcedingRate) * 10),
                    90
                );
            }
        }

        return [
            'prediction' => $bttsChance ? 'yes' : 'no',
            'confidence' => $bttsConfidence,
            'stats' => [
                'home_scoring_rate' => $homeTeamScoringRate,
                'away_scoring_rate' => $awayTeamScoringRate,
                'h2h_btts_ratio' => $totalH2HMatches > 0 ? ($h2hBttsCount / $totalH2HMatches) : 0,
                'criteria_met' => $criteria
            ]
        ];
    }

    // Popüler bahisleri analiz eden yeni metod
    private function analyzePopularBets($homeStats, $awayStats, $h2h, $expectedTotalGoals) {
        $popularBets = [];
        
        // İlk Yarı/Maç Sonucu
        $homeHalfTimeWins = $homeStats['fixtures']['wins']['home_half'] ?? 0;
        $homeTotalMatches = $homeStats['fixtures']['played']['home'] ?? 1;
        $homeHalfTimeWinRate = $homeHalfTimeWins / $homeTotalMatches;
        
        if ($homeHalfTimeWinRate > 0.5) {
            $popularBets[] = [
                'type' => 'ht_ft',
                'prediction' => '1/1',
                'description' => 'İY/MS: 1/1',
                'confidence' => round($homeHalfTimeWinRate * 100),
                'priority' => 'medium'
            ];
        }

        // Tek/Çift
        $evenGoalsCount = 0;
        $totalMatches = 0;
        if (isset($h2h) && is_array($h2h)) {
            foreach ($h2h as $match) {
                if (isset($match['goals'])) {
                    $totalGoals = $match['goals']['home'] + $match['goals']['away'];
                    if ($totalGoals % 2 == 0) $evenGoalsCount++;
                    $totalMatches++;
                }
            }
            
            if ($totalMatches > 0) {
                $evenRate = $evenGoalsCount / $totalMatches;
                if ($evenRate > 0.65) {
                    $popularBets[] = [
                        'type' => 'odd_even',
                        'prediction' => 'even',
                        'description' => 'Toplam Gol: Çift',
                        'confidence' => round($evenRate * 100),
                        'priority' => 'medium'
                    ];
                }
            }
        }

        // Handikap
        $homeGoalDiff = $homeStats['goals']['for']['total']['home'] - $homeStats['goals']['against']['total']['home'];
        if ($homeGoalDiff > 10) {
            $popularBets[] = [
                'type' => 'handicap',
                'prediction' => 'home_-1',
                'description' => 'Handikap: Ev -1',
                'confidence' => 75,
                'priority' => 'high'
            ];
        }

        // Her İki Devrede Gol
        $bothHalvesGoals = 0;
        if (isset($h2h) && is_array($h2h)) {
            foreach ($h2h as $match) {
                if (isset($match['score'])) {
                    if ($match['score']['halftime']['home'] > 0 && 
                        $match['score']['halftime']['away'] > 0 &&
                        $match['score']['fulltime']['home'] > $match['score']['halftime']['home'] &&
                        $match['score']['fulltime']['away'] > $match['score']['halftime']['away']) {
                        $bothHalvesGoals++;
                    }
                }
            }
            
            if ($totalMatches > 0 && ($bothHalvesGoals / $totalMatches) > 0.5) {
                $popularBets[] = [
                    'type' => 'both_halves_goals',
                    'prediction' => 'yes',
                    'description' => 'Her İki Devrede Gol',
                    'confidence' => round(($bothHalvesGoals / $totalMatches) * 100),
                    'priority' => 'medium'
                ];
            }
        }

        // En yüksek güven oranına sahip bahisi seç
        usort($popularBets, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return $popularBets;
    }

    // Gol analizini geliştiren yeni metod
    private function analyzeGoals($homeStats, $awayStats, $h2h) {
        // Ev sahibi takımın ev sahibi olarak performansı
        $homeTeamHomeMatches = $homeStats['fixtures']['played']['home'] ?? 0;
        $homeTeamHomeGoalsScored = $homeStats['goals']['for']['total']['home'] ?? 0;
        $homeTeamHomeGoalsConceded = $homeStats['goals']['against']['total']['home'] ?? 0;

        // Deplasman takımının deplasman performansı
        $awayTeamAwayMatches = $awayStats['fixtures']['played']['away'] ?? 0;
        $awayTeamAwayGoalsScored = $awayStats['goals']['for']['total']['away'] ?? 0;
        $awayTeamAwayGoalsConceded = $awayStats['goals']['against']['total']['away'] ?? 0;

        // Ortalama gol hesaplamaları
        $homeTeamScoringRate = $homeTeamHomeMatches > 0 ? $homeTeamHomeGoalsScored / $homeTeamHomeMatches : 0;
        $homeTeamConcedingRate = $homeTeamHomeMatches > 0 ? $homeTeamHomeGoalsConceded / $homeTeamHomeMatches : 0;
        $awayTeamScoringRate = $awayTeamAwayMatches > 0 ? $awayTeamAwayGoalsScored / $awayTeamAwayMatches : 0;
        $awayTeamConcedingRate = $awayTeamAwayMatches > 0 ? $awayTeamAwayGoalsConceded / $awayTeamAwayMatches : 0;

        // H2H maçlardaki gol ortalamaları
        $h2hHomeGoals = 0;
        $h2hAwayGoals = 0;
        $h2hMatches = 0;
        if (isset($h2h) && is_array($h2h)) {
            $lastH2HMatches = array_slice($h2h, 0, 5);
            foreach ($lastH2HMatches as $match) {
                if (isset($match['goals'])) {
                    $h2hHomeGoals += $match['goals']['home'];
                    $h2hAwayGoals += $match['goals']['away'];
                    $h2hMatches++;
                }
            }
        }

        // Beklenen gol hesaplaması
        $expectedHomeGoals = (
            ($homeTeamScoringRate * 2) +  // Ev sahibi gol atma oranı
            ($awayTeamConcedingRate * 2) + // Deplasman gol yeme oranı
            ($h2hMatches > 0 ? ($h2hHomeGoals / $h2hMatches) : 0) // H2H ev sahibi gol ortalaması
        ) / 4;

        $expectedAwayGoals = (
            ($awayTeamScoringRate * 2) +   // Deplasman gol atma oranı
            ($homeTeamConcedingRate * 2) +  // Ev sahibi gol yeme oranı
            ($h2hMatches > 0 ? ($h2hAwayGoals / $h2hMatches) : 0) // H2H deplasman gol ortalaması
        ) / 4;

        // Ev sahibi avantajı
        $expectedHomeGoals *= 1.1;

        // Form düzeltmesi
        if (isset($homeStats['form']) && $homeStats['form'] == 'WWWW') $expectedHomeGoals *= 1.1;
        if (isset($awayStats['form']) && $awayStats['form'] == 'WWWW') $expectedAwayGoals *= 1.1;

        // Toplam beklenen gol
        $expectedTotalGoals = $expectedHomeGoals + $expectedAwayGoals;

        // Skor tahmini
        $predictedHomeGoals = round($expectedHomeGoals);
        $predictedAwayGoals = round($expectedAwayGoals);

        // Skor açıklaması
        $scoreDescription = '';
        if ($predictedHomeGoals > $predictedAwayGoals) {
            $scoreDescription = 'Ev sahibi üstünlüğünde';
        } elseif ($predictedHomeGoals < $predictedAwayGoals) {
            $scoreDescription = 'Deplasman üstünlüğünde';
        } else {
            $scoreDescription = 'Dengeli bir maç';
        }

        if ($expectedTotalGoals >= 3.5) {
            $scoreDescription .= ', yüksek skorlu';
        } elseif ($expectedTotalGoals <= 1.5) {
            $scoreDescription .= ', düşük skorlu';
        } else {
            $scoreDescription .= ', normal skorlu';
        }

        $predictions = [
            'expected_total_goals' => $expectedTotalGoals,
            'predicted_score' => $predictedHomeGoals . '-' . $predictedAwayGoals,
            'score_description' => $scoreDescription,
            'scoring_stats' => [
                'home' => [
                    'scoring_rate' => round($homeTeamScoringRate, 2),
                    'conceding_rate' => round($homeTeamConcedingRate, 2),
                    'h2h_avg_goals' => $h2hMatches > 0 ? round($h2hHomeGoals / $h2hMatches, 2) : 0
                ],
                'away' => [
                    'scoring_rate' => round($awayTeamScoringRate, 2),
                    'conceding_rate' => round($awayTeamConcedingRate, 2),
                    'h2h_avg_goals' => $h2hMatches > 0 ? round($h2hAwayGoals / $h2hMatches, 2) : 0
                ]
            ]
        ];

        // Clean sheet (gol yememe) istatistikleri
        $homeCleanSheets = $homeStats['clean_sheet']['total'] ?? 0;
        $homeMatchesPlayed = $homeStats['fixtures']['played']['total'] ?? 1;
        $homeCleanSheetRatio = $homeCleanSheets / $homeMatchesPlayed;

        $awayCleanSheets = $awayStats['clean_sheet']['total'] ?? 0;
        $awayMatchesPlayed = $awayStats['fixtures']['played']['total'] ?? 1;
        $awayCleanSheetRatio = $awayCleanSheets / $awayMatchesPlayed;

        // Gol atamama istatistikleri
        $homeFailedToScore = $homeStats['failed_to_score']['total'] ?? 0;
        $homeFailToScoreRatio = $homeFailedToScore / $homeMatchesPlayed;

        $awayFailedToScore = $awayStats['failed_to_score']['total'] ?? 0;
        $awayFailToScoreRatio = $awayFailedToScore / $awayMatchesPlayed;

        // Son maçlardaki gol istatistiklerini hesapla
        $recentGoalsScoredHome = 0;
        $recentGoalsConcededHome = 0;
        $recentGoalsScoredAway = 0;
        $recentGoalsConcededAway = 0;

        // Son 5 maçtaki gol istatistiklerini hesapla
        if (isset($homeStats['fixtures']['played']['home'])) {
            $homeLastFive = array_slice($homeStats['goals']['for']['minute'] ?? [], 0, 5);
            foreach ($homeLastFive as $goals) {
                $recentGoalsScoredHome += $goals['total'] ?? 0;
            }
            
            $homeLastFiveAgainst = array_slice($homeStats['goals']['against']['minute'] ?? [], 0, 5);
            foreach ($homeLastFiveAgainst as $goals) {
                $recentGoalsConcededHome += $goals['total'] ?? 0;
            }
        }

        if (isset($awayStats['fixtures']['played']['away'])) {
            $awayLastFive = array_slice($awayStats['goals']['for']['minute'] ?? [], 0, 5);
            foreach ($awayLastFive as $goals) {
                $recentGoalsScoredAway += $goals['total'] ?? 0;
            }
            
            $awayLastFiveAgainst = array_slice($awayStats['goals']['against']['minute'] ?? [], 0, 5);
            foreach ($awayLastFiveAgainst as $goals) {
                $recentGoalsConcededAway += $goals['total'] ?? 0;
            }
        }

        // KG analizi
        $bttsAnalysis = $this->analyzeBTTS($homeStats, $awayStats, $h2h);
        $predictions['btts_prediction'] = $bttsAnalysis['prediction'];
        $predictions['confidence']['btts'] = $bttsAnalysis['confidence'];
        $predictions['btts_stats'] = $bttsAnalysis['stats'];

        // Önerilen bahisler güncelleme
        $predictions['recommended_bets'] = [];

        // Skor bazlı öneriler
        if ($predictedHomeGoals >= 2 && $predictedAwayGoals == 0 && $homeCleanSheetRatio > 0.3) {
            $predictions['recommended_bets'][] = ['type' => '1_5', 'bet' => 'over', 'priority' => 'high'];
        }
        if ($predictedHomeGoals + $predictedAwayGoals >= 3) {
            $predictions['recommended_bets'][] = ['type' => '2_5', 'bet' => 'over', 'priority' => 'high'];
        }
        // KG önerisi sadece çok güvenli durumlarda
        if ($bttsAnalysis['prediction'] == 'yes' && $bttsAnalysis['confidence'] > 75 && 
            $bttsAnalysis['stats']['criteria_met']['scoring_power'] && 
            $bttsAnalysis['stats']['criteria_met']['h2h_history']) {
            $predictions['recommended_bets'][] = ['type' => 'btts', 'bet' => 'yes', 'priority' => 'high'];
        }

        // Ev sahibi takımın son 5 maç performansı
        $homeLast5Points = 0;
        $homeLast5GoalsScored = 0;
        $homeLast5GoalsConceded = 0;
        if (isset($homeStats['form'])) {
            $last5Form = str_split(substr($homeStats['form'], 0, 5));
            foreach ($last5Form as $result) {
                switch ($result) {
                    case 'W': $homeLast5Points += 3; break;
                    case 'D': $homeLast5Points += 1; break;
                }
            }
        }

        // Ev sahibi iç saha performansı
        $homeWins = $homeStats['fixtures']['wins']['home'] ?? 0;
        $homeTotalMatches = $homeStats['fixtures']['played']['home'] ?? 1;
        $homeWinRate = round(($homeWins / $homeTotalMatches) * 100);

        // Performans özeti
        $teamStats = [
            'home' => [
                'last_5_points' => $homeLast5Points,
                'win_rate' => $homeWinRate,
                'avg_goals_scored' => round($homeTeamScoringRate, 1),
                'avg_goals_conceded' => round($homeTeamConcedingRate, 1),
                'clean_sheets' => $homeStats['clean_sheet']['total'] ?? 0,
                'clean_sheet_rate' => round($homeCleanSheetRatio * 100)
            ]
        ];

        $predictions['team_stats'] = $teamStats;

        // En iyi bahis seçeneğini belirle
        $bestBet = null;
        $highestConfidence = 0;

        // Beklenen gol sayısına göre en uygun bahis seçeneğini belirle
        if ($expectedTotalGoals >= 3.5 && $homeTeamScoringRate > 2.0 && $awayTeamScoringRate > 1.5) {
            // Yüksek skorlu maç beklentisi
            $confidence = min(($expectedTotalGoals - 3.2) * 20 + 75, 90);
            $bestBet = ['type' => '3_5', 'prediction' => 'over', 'confidence' => $confidence];
            $highestConfidence = $confidence;
        } 
        else if ($expectedTotalGoals >= 2.8) {
            // Orta-yüksek skorlu maç beklentisi
            $confidence = min(($expectedTotalGoals - 2.3) * 25 + 70, 90);
            $bestBet = ['type' => '2_5', 'prediction' => 'over', 'confidence' => $confidence];
            $highestConfidence = $confidence;
        }
        else if ($expectedTotalGoals <= 2.0 && $homeCleanSheetRatio > 0.4) {
            // Düşük skorlu maç beklentisi
            $confidence = min((2.5 - $expectedTotalGoals) * 25 + 70, 90);
            $bestBet = ['type' => '2_5', 'prediction' => 'under', 'confidence' => $confidence];
            $highestConfidence = $confidence;
        }
        else if ($expectedTotalGoals > 1.8) {
            // Normal skor beklentisi
            $confidence = min(($expectedTotalGoals - 1.3) * 30 + 65, 90);
            $bestBet = ['type' => '1_5', 'prediction' => 'over', 'confidence' => $confidence];
            $highestConfidence = $confidence;
        }

        // H2H maçlarda KG durumu analizi
        $h2hBttsCount = 0;
        $totalH2HMatches = 0;
        if (isset($h2h) && is_array($h2h)) {
            $lastH2HMatches = array_slice($h2h, 0, 5); // Son 5 karşılaşma
            foreach ($lastH2HMatches as $match) {
                if (isset($match['goals'])) {
                    $totalH2HMatches++;
                    if ($match['goals']['home'] > 0 && $match['goals']['away'] > 0) {
                        $h2hBttsCount++;
                    }
                }
            }
        }

        // KG Var kontrolü - sadece belirli koşullarda
        if ($homeTeamScoringRate > 1.2 && $awayTeamScoringRate > 1.0 && 
            $homeCleanSheetRatio < 0.4 && $awayCleanSheetRatio < 0.4 &&
            $h2hBttsCount >= 3 && $totalH2HMatches > 0) {  // H2H maç kontrolü eklendi
            
            $bttsConfidence = min(
                ($homeTeamScoringRate * 15) +
                ($awayTeamScoringRate * 15) +
                ((1 - $homeCleanSheetRatio) * 20) +
                ((1 - $awayCleanSheetRatio) * 20) +
                ($h2hBttsCount * 5),
                90
            );

            if ($bttsConfidence > $highestConfidence) {
                $bestBet = ['type' => 'btts', 'prediction' => 'yes', 'confidence' => $bttsConfidence];
                $highestConfidence = $bttsConfidence;
            }
        }

        // Seçilen bahsin güven oranı yeterince yüksek değilse bahis önerme
        if ($highestConfidence < 65) {
            $bestBet = null;
        }

        // En iyi bahis seçeneğini kaydet
        if ($bestBet) {
            $predictions['best_bet'] = $bestBet;
        }

        // Popüler bahisleri analiz et
        $predictions['popular_bets'] = $this->analyzePopularBets($homeStats, $awayStats, $h2h, $expectedTotalGoals);

        return $predictions;
    }

    // Public metodlar
    public function getDetailedAnalysis($homeTeamId, $awayTeamId, $leagueId, $season) {
        $homeLastMatches = $this->makeApiRequest("fixtures?team=" . $homeTeamId . "&last=10");
        $awayLastMatches = $this->makeApiRequest("fixtures?team=" . $awayTeamId . "&last=10");
        $standings = $this->makeApiRequest("standings?league=" . $leagueId . "&season=" . $season);
        $h2h = $this->makeApiRequest("fixtures/headtohead?h2h=" . $homeTeamId . "-" . $awayTeamId);
        $homeStats = $this->makeApiRequest("teams/statistics?league=" . $leagueId . "&season=" . $season . "&team=" . $homeTeamId);
        $awayStats = $this->makeApiRequest("teams/statistics?league=" . $leagueId . "&season=" . $season . "&team=" . $awayTeamId);

        // Hata kontrolü
        if (!isset($standings['response'][0]['league']['standings']) || 
            !isset($homeStats['response']) || 
            !isset($awayStats['response']) || 
            !isset($h2h['response'])) {
            return ['error' => 'Veri alınamadı'];
        }

        // Standings verisi için güvenlik kontrolü
        $standingsData = $standings['response'][0]['league']['standings'];
        $standingsArray = is_array($standingsData) ? $standingsData[0] : $standingsData;

        $analysis = [
            'home_team' => [
                'form' => $this->analyzeForm($homeLastMatches['response'] ?? []),
                'stats' => $this->analyzeTeamStats($homeStats['response']),
                'standing' => $this->findTeamStanding($standingsArray, $homeTeamId)
            ],
            'away_team' => [
                'form' => $this->analyzeForm($awayLastMatches['response'] ?? []),
                'stats' => $this->analyzeTeamStats($awayStats['response']),
                'standing' => $this->findTeamStanding($standingsArray, $awayTeamId)
            ],
            'h2h_analysis' => $this->analyzeH2H($h2h['response'] ?? [])
        ];

        // Gol analizi için güvenlik kontrolü
        if (isset($homeStats['response']) && isset($awayStats['response']) && isset($h2h['response'])) {
            $goalsAnalysis = $this->analyzeGoals($homeStats['response'], $awayStats['response'], $h2h['response']);
            $analysis['goals'] = $goalsAnalysis;
        } else {
            $analysis['goals'] = [
                'expected_total_goals' => 0,
                'goals_predictions' => [
                    '1_5' => 'under',
                    '2_5' => 'under',
                    '3_5' => 'under'
                ],
                'btts_prediction' => 'no',
                'confidence' => [
                    '1_5' => 0,
                    '2_5' => 0,
                    '3_5' => 0,
                    'btts' => 0
                ]
            ];
        }

        return $this->generatePrediction($analysis);
    }

    public function getMatchOdds($fixtureId) {
        $odds = $this->makeApiRequest("odds?fixture=" . $fixtureId);
        
        if (!isset($odds['response'][0]['bookmakers'][0]['bets'])) {
            return [
                'match_winner' => ['home' => '-', 'draw' => '-', 'away' => '-'],
                'goals_over_under' => [
                    'over_1_5' => '-', 'under_1_5' => '-',
                    'over_2_5' => '-', 'under_2_5' => '-',
                    'over_3_5' => '-', 'under_3_5' => '-'
                ],
                'both_teams_score' => ['yes' => '-', 'no' => '-']
            ];
        }
        
        $bets = $odds['response'][0]['bookmakers'][0]['bets'];
        
        // Maç Sonucu
        $matchWinner = array_values(array_filter($bets, function($bet) { 
            return $bet['name'] === 'Match Winner'; 
        }))[0]['values'] ?? [];
        
        // Toplam Gol
        $goalsOU = array_values(array_filter($bets, function($bet) { 
            return $bet['name'] === 'Goals Over/Under'; 
        }))[0]['values'] ?? [];
        
        // KG Var/Yok
        $btts = array_values(array_filter($bets, function($bet) { 
            return $bet['name'] === 'Both Teams Score'; 
        }))[0]['values'] ?? [];
        
        // Gol marketlerini düzenle
        $goalsOverUnder = [];
        foreach ($goalsOU as $goal) {
            if (strpos($goal['value'], 'Over') !== false) {
                $limit = str_replace('Over ', '', $goal['value']);
                $goalsOverUnder['over_' . str_replace('.', '_', $limit)] = $goal['odd'];
            } else {
                $limit = str_replace('Under ', '', $goal['value']);
                $goalsOverUnder['under_' . str_replace('.', '_', $limit)] = $goal['odd'];
            }
        }
        
        return [
            'match_winner' => [
                'home' => $matchWinner[0]['odd'] ?? '-',
                'draw' => $matchWinner[1]['odd'] ?? '-',
                'away' => $matchWinner[2]['odd'] ?? '-'
            ],
            'goals_over_under' => array_merge([
                'over_1_5' => $goalsOverUnder['over_1_5'] ?? '-',
                'under_1_5' => $goalsOverUnder['under_1_5'] ?? '-',
                'over_2_5' => $goalsOverUnder['over_2_5'] ?? '-',
                'under_2_5' => $goalsOverUnder['under_2_5'] ?? '-',
                'over_3_5' => $goalsOverUnder['over_3_5'] ?? '-',
                'under_3_5' => $goalsOverUnder['under_3_5'] ?? '-'
            ], $goalsOverUnder),
            'both_teams_score' => [
                'yes' => array_values(array_filter($btts, function($v) { 
                    return $v['value'] === 'Yes'; 
                }))[0]['odd'] ?? '-',
                'no' => array_values(array_filter($btts, function($v) { 
                    return $v['value'] === 'No'; 
                }))[0]['odd'] ?? '-'
            ]
        ];
    }

    // Yaklaşan maçları getir
    public function getUpcomingMatches($leagueId) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api-football-v1.p.rapidapi.com/v3/fixtures?league=" . $leagueId . "&season=2024&next=5",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "X-RapidAPI-Host: api-football-v1.p.rapidapi.com",
                "X-RapidAPI-Key: aac2d042fcmsh1178189679a821cp1ec080jsn14b2c2789ba3"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);

        if ($err) {
            return ['error' => 'cURL Hatası: ' . $err];
        }

        if ($httpcode != 200) {
            return ['error' => 'API Yanıt Hatası: ' . $response];
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON Çözümleme Hatası: ' . json_last_error_msg()];
        }

        if (!isset($data['response'])) {
            return ['error' => 'Geçersiz API yanıtı'];
        }

        return ['data' => $data['response']];
    }

    // Analiz sonuçlarını görüntüle
    public function displayAnalysis($analysis, $fixtureId) {
        $odds = $this->getMatchOdds($fixtureId);
        
        $html = '<div class="analysis-container">';
        
        // Maç sonucu tahmini
        $html .= '<div class="prediction-section mb-3">';
        $html .= '<strong>Maç Sonucu Tahmini:</strong><br>';
        $html .= $analysis['winner_prediction'] == 'home' ? 'Ev Sahibi Galibiyeti' : 'Deplasman Galibiyeti';
        $html .= ' (Güven: %' . number_format($analysis['win_confidence'], 1) . ')<br>';
        $winnerOdd = $analysis['winner_prediction'] == 'home' ? $odds['match_winner']['home'] : $odds['match_winner']['away'];
        $html .= 'Önerilen Bahis: ' . ($analysis['winner_prediction'] == 'home' ? '1' : '2') . ' @ ' . $winnerOdd;
        $html .= '</div>';
        
        // Gol tahminleri
        if (isset($analysis['goals'])) {
            $html .= '<div class="prediction-section mb-3" style="background: #f8f9fa; padding: 10px; border-radius: 5px;">';
            $html .= '<strong style="color: #495057;">Gol Analizi:</strong><br>';
            
            // Takım performans özeti
            if (isset($analysis['goals']['team_stats']['home'])) {
                $stats = $analysis['goals']['team_stats']['home'];
                $html .= '<div style="background: #fff; padding: 8px; margin: 8px 0; border-radius: 4px; border-left: 3px solid #198754;">';
                $html .= '<strong>Ev Sahibi Avantajları:</strong><br>';
                $html .= '<ul style="list-style: none; padding-left: 0; margin: 5px 0;">';
                $html .= '<li>• Son 5 maçta ' . $stats['last_5_points'] . ' puan</li>';
                $html .= '<li>• İç saha kazanma oranı: %' . $stats['win_rate'] . '</li>';
                $html .= '<li>• Maç başı ' . $stats['avg_goals_scored'] . ' gol ortalaması</li>';
                if ($stats['avg_goals_conceded'] < 1.2) {
                    $html .= '<li>• Güçlü savunma (maç başı ' . $stats['avg_goals_conceded'] . ' gol yiyor)</li>';
                }
                if ($stats['clean_sheet_rate'] > 30) {
                    $html .= '<li>• Gol yememe oranı: %' . $stats['clean_sheet_rate'] . '</li>';
                }
                $html .= '</ul>';
                $html .= '</div>';
            }

            // Skor ve gol tahminleri
            $html .= '<div style="margin: 5px 0;">';
            $html .= 'Tahmini Skor: <strong>' . $analysis['goals']['predicted_score'] . '</strong><br>';
            $html .= '<small class="text-muted">' . $analysis['goals']['score_description'] . '</small><br>';
            $html .= 'Beklenen Toplam Gol: <strong>' . number_format($analysis['goals']['expected_total_goals'], 1) . '</strong>';
            $html .= '</div>';

            // En iyi bahis seçeneğini göster
            if (isset($analysis['goals']['best_bet'])) {
                $bestBet = $analysis['goals']['best_bet'];
                $html .= '<div style="background: #e3f2fd; padding: 8px; margin: 10px 0; border-radius: 4px;">';
                $html .= '<strong>Önerilen Gol Bahisi:</strong><br>';
                $html .= '<span class="badge bg-success me-2">';

                // Bahis türüne göre açıklama
                switch ($bestBet['type']) {
                    case '1_5':
                        $odd = $bestBet['prediction'] == 'over' ? 
                            $odds['goals_over_under']['over_1_5'] : 
                            $odds['goals_over_under']['under_1_5'];
                        $html .= '1.5 ' . ($bestBet['prediction'] == 'over' ? 'Üst' : 'Alt');
                        break;
                    case '2_5':
                        $odd = $bestBet['prediction'] == 'over' ? 
                            $odds['goals_over_under']['over_2_5'] : 
                            $odds['goals_over_under']['under_2_5'];
                        $html .= '2.5 ' . ($bestBet['prediction'] == 'over' ? 'Üst' : 'Alt');
                        break;
                    case '3_5':
                        $odd = $bestBet['prediction'] == 'over' ? 
                            $odds['goals_over_under']['over_3_5'] : 
                            $odds['goals_over_under']['under_3_5'];
                        $html .= '3.5 ' . ($bestBet['prediction'] == 'over' ? 'Üst' : 'Alt');
                        break;
                    case 'btts':
                        $odd = $odds['both_teams_score']['yes'];
                        $html .= 'KG Var';
                        break;
                }

                $html .= ' @ ' . $odd;
                $html .= ' (Güven: %' . number_format($bestBet['confidence'], 1) . ')';
                $html .= '</span>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }
        
        // Popüler bahisler bölümü
        if (isset($analysis['goals']['popular_bets']) && !empty($analysis['goals']['popular_bets'])) {
            $html .= '<div style="background: #fff3cd; padding: 8px; margin: 10px 0; border-radius: 4px; border-left: 3px solid #ffc107;">';
            $html .= '<strong>Popüler Bahis Önerisi:</strong><br>';
            
            // En yüksek güven oranlı popüler bahisi göster
            $bestPopularBet = $analysis['goals']['popular_bets'][0];
            $html .= '<span class="badge bg-warning text-dark me-2">';
            $html .= $bestPopularBet['description'];
            $html .= ' (Güven: %' . $bestPopularBet['confidence'] . ')';
            $html .= '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    // En iyi gol bahsini bulan yeni metod
    private function findBestGoalBet($goals, $odds) {
        $allBets = [];
        
        // Tüm gol marketlerini kontrol et
        foreach (['1_5', '2_5', '3_5', '4_5'] as $goal) {
            if (isset($goals['confidence'][$goal]) && $goals['confidence'][$goal] > 65) {
                $prediction = $goals['goals_predictions'][$goal];
                $formattedGoal = str_replace('_', '.', $goal);
                
                if ($prediction == 'over') {
                    $odd = $odds['goals_over_under']['over_' . $goal] ?? '-';
                    $description = $formattedGoal . ' Üst';
                } else {
                    $odd = $odds['goals_over_under']['under_' . $goal] ?? '-';
                    $description = $formattedGoal . ' Alt';
                }
                
                if ($odd != '-') {
                    $allBets[] = [
                        'description' => $description,
                        'odd' => $odd,
                        'confidence' => $goals['confidence'][$goal],
                        'type' => 'goals'
                    ];
                }
            }
        }
        
        // KG Var/Yok kontrolü
        if (isset($goals['btts_prediction']) && isset($goals['confidence']['btts']) && 
            $goals['confidence']['btts'] > 65) {
            $prediction = $goals['btts_prediction'];
            if ($prediction == 'yes') {
                $odd = $odds['both_teams_score']['yes'] ?? '-';
                if ($odd != '-') {
                    $allBets[] = [
                        'description' => 'KG Var',
                        'odd' => $odd,
                        'confidence' => $goals['confidence']['btts'],
                        'type' => 'btts'
                    ];
                }
            }
        }
        
        // En yüksek güven oranına sahip bahisi seç
        if (!empty($allBets)) {
            usort($allBets, function($a, $b) {
                return $b['confidence'] <=> $a['confidence'];
            });
            return $allBets[0];
        }
        
        return null;
    }

    // Değerli bahisleri bulan yeni metod
    private function findValueBets($analysis, $odds) {
        $valueBets = [];
        
        // Maç sonucu için değer kontrolü
        if ($analysis['win_confidence'] > 65) {
            $predictedWinner = $analysis['winner_prediction'];
            $winnerOdd = $odds['match_winner'][$predictedWinner];
            if ($winnerOdd != '-') {
                $impliedProbability = (1 / floatval($winnerOdd)) * 100;
                
                if ($analysis['win_confidence'] > $impliedProbability + 10) {
                    $valueBets[] = [
                        'description' => $predictedWinner == 'home' ? 'Ev Sahibi Kazanır' : 'Deplasman Kazanır',
                        'odd' => $winnerOdd
                    ];
                }
            }
        }
        
        // Gol bahisleri için değer kontrolü
        if (isset($analysis['goals']) && is_array($analysis['goals'])) {
            foreach (['1_5', '2_5', '3_5'] as $goal) {
                if (isset($analysis['goals']['confidence'][$goal]) && $analysis['goals']['confidence'][$goal] > 65) {
                    $prediction = $analysis['goals']['goals_predictions'][$goal];
                    $odd = $odds['goals_over_under']['over_' . str_replace('_', '.', $goal)] ?? '-';
                    
                    if ($odd != '-') {
                        $impliedProbability = (1 / floatval($odd)) * 100;
                        
                        if ($analysis['goals']['confidence'][$goal] > $impliedProbability + 10) {
                            $valueBets[] = [
                                'description' => str_replace('_', '.', $goal) . ' ' . ($prediction == 'over' ? 'Üst' : 'Alt'),
                                'odd' => $odd
                            ];
                        }
                    }
                }
            }
        }
        
        return $valueBets;
    }

    public function analyze($data)
    {
        // ... existing code ...
        
        // Maç verilerini veritabanına kaydet
        $this->dataManager->saveMatchData([
            'team_home' => $homeTeam,
            'team_away' => $awayTeam,
            'score_home' => $homeScore,
            'score_away' => $awayScore,
            'date' => date('Y-m-d H:i:s')
        ]);
        
        // ... existing code ...
    }
}
?> 