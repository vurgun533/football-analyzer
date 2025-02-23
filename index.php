<?php

require_once 'src/Config/DatabaseConfig.php';
require_once 'src/Database/DatabaseInterface.php';
require_once 'src/Database/MySQLDatabase.php';
require_once 'src/Data/DataManager.php';
require_once 'src/FootballAnalyzer.php';

$analyzer = new FootballAnalyzer();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Futbol Maç Programı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .league-title {
            background-color: #f8f9fa;
            padding: 10px;
            margin-top: 20px;
            border-radius: 5px;
            border-left: 5px solid #0d6efd;
        }
        .prediction-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .progress {
            height: 25px;
            margin-bottom: 15px;
        }
        .progress-bar {
            line-height: 25px;
            font-size: 14px;
        }
        .alert-info {
            background-color: #e3f2fd;
            border-color: #90caf9;
        }
        .analysis-container {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-size: 13px;
            line-height: 1.4;
        }
        .analysis-container strong {
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Yaklaşan Maçlar</h1>
        <?php
        $leagues = [
            
             ['id' => 39, 'name' => 'İNGİLTERE PREMIER LİG'],
            ['id' => 140, 'name' => 'İSPANYA LA LIGA'],
             ['id' => 78, 'name' => 'ALMAN BUNDESLİGA'],
            ['id' => 135, 'name' => 'İTALY SERIE A'],
            ['id' => 61, 'name' => 'FRANSA LIGUE 1'],
            ['id' => 144, 'name' => 'BELÇİKA JUPILER PRO'],
            ['id' => 88, 'name' => 'HOLLANDA EREDIVISIE'],
            ['id' => 94, 'name' => 'PORTEKİZ PRIMEIRA LIGA'],
            ['id' => 207, 'name' => 'TÜRKİYESÜPER LİG'],
            ['id' => 283, 'name' => 'ROMANYA LIGA 1'],
        ];

        foreach ($leagues as $league) {
            echo '<h3 class="league-title">' . $league['name'] . '</h3>';
            
            $matches = $analyzer->getUpcomingMatches($league['id']);
            
            if (isset($matches['error'])) {
                echo '<div class="alert alert-danger">' . $matches['error'] . '</div>';
                continue;
            }
            
            if (empty($matches['data'])) {
                echo '<div class="alert alert-warning">' . $league['name'] . ' için yaklaşan maç bulunamadı.</div>';
                continue;
            }
            
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped table-hover">';
            echo '<thead><tr>';
            echo '<th>Tarih</th>';
            echo '<th>Ev Sahibi</th>';
            echo '<th>Deplasman</th>';
            echo '<th>Stadyum</th>';
            echo '<th>Tahmin Detayları</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($matches['data'] as $match) {
                $date = new DateTime($match['fixture']['date']);
                $date->setTimezone(new DateTimeZone('Europe/Istanbul'));
                
                $analysis = $analyzer->getDetailedAnalysis(
                    $match['teams']['home']['id'],
                    $match['teams']['away']['id'],
                    $league['id'],
                    2024
                );
                
                echo '<tr>';
                echo '<td>' . $date->format('d.m.Y H:i') . '</td>';
                echo '<td>' . $match['teams']['home']['name'] . '</td>';
                echo '<td>' . $match['teams']['away']['name'] . '</td>';
                echo '<td>' . $match['fixture']['venue']['name'] . '</td>';
                echo '<td>';
                
                if (isset($analysis['error'])) {
                    echo '<div class="alert alert-warning">' . $analysis['error'] . '</div>';
                } else {
                    echo $analyzer->displayAnalysis($analysis, $match['fixture']['id']);
                }
                
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
        }
        ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 