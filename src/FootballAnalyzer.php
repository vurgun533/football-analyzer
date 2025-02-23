<?php

class FootballAnalyzer {
    private $apiKey = 'aac2d042fcmsh1178189679a821cp1ec080jsn14b2c2789ba3';
    private $apiHost = 'api-football-v1.p.rapidapi.com';
    private $dataManager;

    public function __construct() {
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
            return ['error' => 'cURL Hatası: ' . $err];
        }

        if ($httpcode != 200) {
            return ['error' => 'API Yanıt Hatası: ' . $response];
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON Çözümleme Hatası: ' . json_last_error_msg()];
        }

        return $data;
    }

    // Yaklaşan maçları getir
    public function getUpcomingMatches($leagueId) {
        $response = $this->makeApiRequest("fixtures?league=" . $leagueId . "&season=2024&next=5");
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }

        if (!isset($response['response'])) {
            return ['error' => 'Geçersiz API yanıtı'];
        }

        return ['data' => $response['response']];
    }

    // Detaylı analiz yap
    public function getDetailedAnalysis($homeTeamId, $awayTeamId, $leagueId, $season) {
        $homeStats = $this->makeApiRequest("teams/statistics?league=" . $leagueId . "&season=" . $season . "&team=" . $homeTeamId);
        $awayStats = $this->makeApiRequest("teams/statistics?league=" . $leagueId . "&season=" . $season . "&team=" . $awayTeamId);
        $h2h = $this->makeApiRequest("fixtures/headtohead?h2h=" . $homeTeamId . "-" . $awayTeamId);
        $standings = $this->makeApiRequest("standings?league=" . $leagueId . "&season=" . $season);

        if (!isset($homeStats['response']) || !isset($awayStats['response'])) {
            return ['error' => 'Takım istatistikleri alınamadı'];
        }

        // Ev sahibi takımın istatistikleri
        $homeTeamStats = $homeStats['response'];
        $homeGoalsScored = $homeTeamStats['goals']['for']['total']['home'] ?? 0;
        $homeGoalsConceded = $homeTeamStats['goals']['against']['total']['home'] ?? 0;
        $homeForm = $homeTeamStats['form'] ?? '';
        $homeCleanSheets = $homeTeamStats['clean_sheet']['total'] ?? 0;

        // Deplasman takımının istatistikleri
        $awayTeamStats = $awayStats['response'];
        $awayGoalsScored = $awayTeamStats['goals']['for']['total']['away'] ?? 0;
        $awayGoalsConceded = $awayTeamStats['goals']['against']['total']['away'] ?? 0;
        $awayForm = $awayTeamStats['form'] ?? '';
        $awayCleanSheets = $awayTeamStats['clean_sheet']['total'] ?? 0;

        // H2H analizi
        $h2hMatches = $h2h['response'] ?? [];
        $h2hHomeWins = 0;
        $h2hAwayWins = 0;
        $h2hDraws = 0;
        $h2hTotalGoals = 0;
        $h2hBothTeamsScored = 0;

        foreach (array_slice($h2hMatches, 0, 5) as $match) {
            if ($match['teams']['home']['winner']) $h2hHomeWins++;
            elseif ($match['teams']['away']['winner']) $h2hAwayWins++;
            else $h2hDraws++;

            $matchGoals = $match['goals']['home'] + $match['goals']['away'];
            $h2hTotalGoals += $matchGoals;

            if ($match['goals']['home'] > 0 && $match['goals']['away'] > 0) {
                $h2hBothTeamsScored++;
            }
        }

        // Gol analizi ve tahminler
        $expectedHomeGoals = ($homeGoalsScored / 10) * 1.1; // Ev sahibi avantajı
        $expectedAwayGoals = $awayGoalsScored / 10;
        $expectedTotalGoals = $expectedHomeGoals + $expectedAwayGoals;

        // Kazanan tahmini
        $winnerPrediction = 'home';
        $winConfidence = 65;

        if ($homeForm == 'WWWW' || $homeGoalsScored > $awayGoalsScored * 1.5) {
            $winConfidence += 15;
        }
        if ($awayForm == 'WWWW' || $awayGoalsScored > $homeGoalsScored * 1.5) {
            $winConfidence -= 15;
            $winnerPrediction = 'away';
        }

        // Popüler bahisler
        $popularBets = [];

        // KG Var/Yok analizi
        if ($h2hBothTeamsScored >= 3) {
            $popularBets[] = [
                'type' => 'btts',
                'prediction' => 'yes',
                'confidence' => 75,
                'description' => 'Karşılıklı Gol Var'
            ];
        }

        // Toplam gol analizi
        if ($expectedTotalGoals > 2.5) {
            $popularBets[] = [
                'type' => 'over_2_5',
                'prediction' => 'over',
                'confidence' => 70,
                'description' => '2.5 Üst'
            ];
        }

        // İlk yarı/maç sonucu
        if ($winConfidence > 75) {
            $popularBets[] = [
                'type' => 'ht_ft',
                'prediction' => $winnerPrediction == 'home' ? '1/1' : '2/2',
                'confidence' => 65,
                'description' => 'İY/MS: ' . ($winnerPrediction == 'home' ? '1/1' : '2/2')
            ];
        }

        // Handikap
        if ($winConfidence > 80) {
            $popularBets[] = [
                'type' => 'handicap',
                'prediction' => $winnerPrediction == 'home' ? 'home_-1' : 'away_-1',
                'confidence' => 60,
                'description' => ($winnerPrediction == 'home' ? 'Ev -1' : 'Deplasman -1') . ' Handikap'
            ];
        }

        // Veritabanına kaydetmek için veriyi hazırla
        $matchData = [
            'fixture_id' => time(), // Geçici ID
            'league_id' => $leagueId,
            'home_team' => $homeTeamId,
            'away_team' => $awayTeamId,
            'match_date' => date('Y-m-d H:i:s'),
            'stats' => [
                'possession' => ['home' => 0, 'away' => 0],
                'shots' => ['home' => $homeStats['response']['shots']['total']['home'] ?? 0, 'away' => $awayStats['response']['shots']['total']['away'] ?? 0],
                'shots_on_target' => ['home' => $homeStats['response']['shots']['on']['home'] ?? 0, 'away' => $awayStats['response']['shots']['on']['away'] ?? 0],
                'corners' => ['home' => 0, 'away' => 0],
                'goals_scored' => ['home' => $homeGoalsScored, 'away' => $awayGoalsScored],
                'goals_conceded' => ['home' => $homeGoalsConceded, 'away' => $awayGoalsConceded],
                'clean_sheets' => ['home' => $homeCleanSheets, 'away' => $awayCleanSheets],
                'form' => ['home' => $homeForm, 'away' => $awayForm]
            ],
            'predictions' => [
                'winner' => $winnerPrediction,
                'win_confidence' => $winConfidence,
                'score' => round($expectedHomeGoals) . '-' . round($expectedAwayGoals),
                'total_goals' => $expectedTotalGoals,
                'btts' => $h2hBothTeamsScored >= 3 ? 'yes' : 'no',
                'over_under_2_5' => $expectedTotalGoals > 2.5 ? 'over' : 'under'
            ],
            'h2h' => [
                'home_wins' => $h2hHomeWins,
                'away_wins' => $h2hAwayWins,
                'draws' => $h2hDraws,
                'avg_goals' => count($h2hMatches) > 0 ? $h2hTotalGoals / count($h2hMatches) : 0,
                'btts_ratio' => count($h2hMatches) > 0 ? ($h2hBothTeamsScored / count($h2hMatches)) * 100 : 0,
                'total_goals' => $h2hTotalGoals
            ],
            'popular_bets' => $popularBets
        ];

        // Verileri kaydet
        try {
            $this->saveMatchToDatabase($matchData);
        } catch (Exception $e) {
            error_log("Veri kaydedilirken hata: " . $e->getMessage());
        }

        return [
            'winner_prediction' => $winnerPrediction,
            'win_confidence' => $winConfidence,
            'goals' => [
                'expected_total_goals' => round($expectedTotalGoals, 1),
                'predicted_score' => round($expectedHomeGoals) . '-' . round($expectedAwayGoals),
                'score_description' => $this->getScoreDescription($expectedHomeGoals, $expectedAwayGoals),
                'btts_prediction' => $h2hBothTeamsScored >= 3 ? 'yes' : 'no',
                'over_under_2_5' => $expectedTotalGoals > 2.5 ? 'over' : 'under'
            ],
            'stats' => [
                'home' => [
                    'goals_scored' => $homeGoalsScored,
                    'goals_conceded' => $homeGoalsConceded,
                    'clean_sheets' => $homeCleanSheets,
                    'form' => $homeForm
                ],
                'away' => [
                    'goals_scored' => $awayGoalsScored,
                    'goals_conceded' => $awayGoalsConceded,
                    'clean_sheets' => $awayCleanSheets,
                    'form' => $awayForm
                ],
                'h2h' => [
                    'home_wins' => $h2hHomeWins,
                    'away_wins' => $h2hAwayWins,
                    'draws' => $h2hDraws,
                    'avg_goals' => count($h2hMatches) > 0 ? round($h2hTotalGoals / count($h2hMatches), 1) : 0,
                    'btts_ratio' => count($h2hMatches) > 0 ? round(($h2hBothTeamsScored / count($h2hMatches)) * 100) : 0
                ]
            ],
            'popular_bets' => $popularBets
        ];
    }

    private function getScoreDescription($homeGoals, $awayGoals) {
        $totalGoals = $homeGoals + $awayGoals;
        $scoreDiff = abs($homeGoals - $awayGoals);
        
        $description = '';
        
        if ($homeGoals > $awayGoals) {
            $description = 'Ev sahibi üstünlüğünde';
        } elseif ($awayGoals > $homeGoals) {
            $description = 'Deplasman üstünlüğünde';
        } else {
            $description = 'Dengeli';
        }
        
        if ($scoreDiff >= 2) {
            $description .= ', net skorlu';
        }
        
        if ($totalGoals >= 3.5) {
            $description .= ', yüksek skorlu';
        } elseif ($totalGoals <= 1.5) {
            $description .= ', düşük skorlu';
        } else {
            $description .= ', normal skorlu';
        }
        
        return $description . ' bir maç';
    }

    // Analiz sonuçlarını görüntüle
    public function displayAnalysis($analysis, $fixtureId) {
        $html = '<div class="analysis-container">';
        
        // Maç sonucu tahmini
        $html .= '<div class="prediction-section mb-3">';
        $html .= '<strong>Maç Sonucu Tahmini:</strong><br>';
        $html .= $analysis['winner_prediction'] == 'home' ? 'Ev Sahibi Galibiyeti' : 'Deplasman Galibiyeti';
        $html .= ' (Güven: %' . number_format($analysis['win_confidence'], 1) . ')<br>';
        
        // Gol tahminleri
        if (isset($analysis['goals'])) {
            $html .= '<div style="margin: 10px 0; padding: 8px; background: #f8f9fa; border-radius: 4px;">';
            $html .= '<strong>Skor Tahmini:</strong> ' . $analysis['goals']['predicted_score'] . '<br>';
            $html .= '<small class="text-muted">' . $analysis['goals']['score_description'] . '</small><br>';
            $html .= 'Beklenen Toplam Gol: ' . $analysis['goals']['expected_total_goals'] . '<br>';
            $html .= 'KG Var/Yok: ' . ($analysis['goals']['btts_prediction'] == 'yes' ? 'Var' : 'Yok') . '<br>';
            $html .= '2.5 Gol: ' . ($analysis['goals']['over_under_2_5'] == 'over' ? 'Üst' : 'Alt');
            $html .= '</div>';
        }
        
        // İstatistikler
        if (isset($analysis['stats'])) {
            $html .= '<div style="margin: 10px 0;">';
            $html .= '<strong>H2H İstatistikleri:</strong><br>';
            $html .= 'Ev Sahibi Galibiyetleri: ' . $analysis['stats']['h2h']['home_wins'] . '<br>';
            $html .= 'Deplasman Galibiyetleri: ' . $analysis['stats']['h2h']['away_wins'] . '<br>';
            $html .= 'Beraberlikler: ' . $analysis['stats']['h2h']['draws'] . '<br>';
            $html .= 'Maç Başı Ortalama Gol: ' . $analysis['stats']['h2h']['avg_goals'] . '<br>';
            $html .= 'KG Var Oranı: %' . $analysis['stats']['h2h']['btts_ratio'];
            $html .= '</div>';
        }
        
        // Popüler bahisler
        if (isset($analysis['popular_bets']) && !empty($analysis['popular_bets'])) {
            $html .= '<div style="margin: 10px 0; padding: 8px; background: #fff3cd; border-radius: 4px;">';
            $html .= '<strong>Önerilen Bahisler:</strong><br>';
            foreach ($analysis['popular_bets'] as $bet) {
                $html .= '<span class="badge bg-warning text-dark me-2">';
                $html .= $bet['description'] . ' (Güven: %' . $bet['confidence'] . ')';
                $html .= '</span><br>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    public function analyze($matchData)
    {
        // Gelen veriyi kontrol et
        if (!isset($matchData['home_team']) || !isset($matchData['away_team']) || 
            !isset($matchData['home_score']) || !isset($matchData['away_score'])) {
            throw new InvalidArgumentException("Eksik maç verisi");
        }

        // Maç verilerini veritabanına kaydet
        $this->saveMatchToDatabase($matchData);

        // Analiz işlemlerini gerçekleştir
        $analysis = [
            'winner_prediction' => 'home',
            'win_confidence' => 75,
            // ... diğer analiz sonuçları
        ];

        return $analysis;
    }

    // Maç verilerini veritabanına kaydetmek için yeni metod
    private function saveMatchToDatabase($matchData)
    {
        try {
            // Temel maç verilerini kaydet
            $matchId = $this->dataManager->saveMatch([
                'fixture_id' => $matchData['fixture_id'] ?? time(),
                'league_id' => $matchData['league_id'] ?? 0,
                'team_home' => $matchData['home_team'],
                'team_away' => $matchData['away_team'],
                'match_date' => $matchData['match_date'] ?? date('Y-m-d H:i:s'),
                'stadium' => $matchData['stadium'] ?? null
            ]);

            // Detaylı istatistikleri kaydet
            if (isset($matchData['stats'])) {
                $this->dataManager->saveMatchStats($matchId, [
                    'possession_home' => $matchData['stats']['possession']['home'] ?? 0,
                    'possession_away' => $matchData['stats']['possession']['away'] ?? 0,
                    'shots_home' => $matchData['stats']['shots']['home'] ?? 0,
                    'shots_away' => $matchData['stats']['shots']['away'] ?? 0,
                    'shots_on_target_home' => $matchData['stats']['shots_on_target']['home'] ?? 0,
                    'shots_on_target_away' => $matchData['stats']['shots_on_target']['away'] ?? 0,
                    'corners_home' => $matchData['stats']['corners']['home'] ?? 0,
                    'corners_away' => $matchData['stats']['corners']['away'] ?? 0,
                    'goals_scored_home' => $matchData['stats']['goals_scored']['home'] ?? 0,
                    'goals_scored_away' => $matchData['stats']['goals_scored']['away'] ?? 0,
                    'goals_conceded_home' => $matchData['stats']['goals_conceded']['home'] ?? 0,
                    'goals_conceded_away' => $matchData['stats']['goals_conceded']['away'] ?? 0,
                    'clean_sheets_home' => $matchData['stats']['clean_sheets']['home'] ?? 0,
                    'clean_sheets_away' => $matchData['stats']['clean_sheets']['away'] ?? 0,
                    'form_home' => $matchData['stats']['form']['home'] ?? '',
                    'form_away' => $matchData['stats']['form']['away'] ?? ''
                ]);
            }

            // Tahmin sonuçlarını kaydet
            if (isset($matchData['predictions'])) {
                $this->dataManager->saveMatchPredictions($matchId, [
                    'winner_prediction' => $matchData['predictions']['winner'] ?? null,
                    'win_confidence' => $matchData['predictions']['win_confidence'] ?? 0,
                    'predicted_score' => $matchData['predictions']['score'] ?? null,
                    'expected_total_goals' => $matchData['predictions']['total_goals'] ?? 0,
                    'btts_prediction' => $matchData['predictions']['btts'] ?? null,
                    'over_under_2_5' => $matchData['predictions']['over_under_2_5'] ?? null
                ]);
            }

            // H2H istatistiklerini kaydet
            if (isset($matchData['h2h'])) {
                $this->dataManager->saveH2HStats($matchId, [
                    'home_wins' => $matchData['h2h']['home_wins'] ?? 0,
                    'away_wins' => $matchData['h2h']['away_wins'] ?? 0,
                    'draws' => $matchData['h2h']['draws'] ?? 0,
                    'avg_goals' => $matchData['h2h']['avg_goals'] ?? 0,
                    'btts_ratio' => $matchData['h2h']['btts_ratio'] ?? 0,
                    'total_goals' => $matchData['h2h']['total_goals'] ?? 0
                ]);
            }

            // Önerilen bahisleri kaydet
            if (isset($matchData['popular_bets'])) {
                $this->dataManager->saveRecommendedBets($matchId, array_map(function($bet) {
                    return [
                        'bet_type' => $bet['type'] ?? '',
                        'prediction' => $bet['prediction'] ?? '',
                        'confidence' => $bet['confidence'] ?? 0,
                        'description' => $bet['description'] ?? ''
                    ];
                }, $matchData['popular_bets']));
            }

        } catch (Exception $e) {
            error_log("Maç verisi kaydedilirken hata: " . $e->getMessage());
            throw $e;
        }
    }
} 