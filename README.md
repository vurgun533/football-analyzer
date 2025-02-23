# YAPAY ZEKA - Futbol Maç Analiz ve Tahmin Sistemi

Bu proje, futbol maçları için kapsamlı analiz ve tahmin yapan bir PHP uygulamasıdır. API-Football verilerini kullanarak maç istatistiklerini analiz eder, tahminler üretir ve sonuçları veritabanında saklar.

KURULUM:
schema.sql içerisindeki tablo yapısnı MySql de oluşturun.
DatabaseConfig.php içerisinde veri tabanını ayarlayın.
Projeyi başlatın Hepsi bu kadar :)

## Özellikler

### 1. Maç Analizleri
- Yaklaşan maçların listesi ve detayları
- Detaylı maç istatistikleri ve analizleri
- Head-to-head (H2H) karşılaştırmalar
- Form ve performans analizleri
- Gol, şut ve korner istatistikleri
- Takım performans değerlendirmeleri

### 2. Tahmin Sistemi
- Maç sonucu tahminleri (1/0/2)
- Kesin skor tahminleri
- Toplam gol tahminleri (Alt/Üst 2.5)
- Karşılıklı gol olasılığı (Var/Yok)
- İlk yarı/maç sonucu kombinasyonları
- Handikaplı maç sonucu tahminleri
- Güven oranları ile desteklenmiş tahminler

### 3. Bahis Önerileri
- Maça özel bahis tavsiyeleri
- Güven oranları ile risk değerlendirmesi
- Popüler bahis seçenekleri analizi
- İstatistiksel verilerle desteklenmiş öneriler
- Oran/Risk analizi

### 4. Detaylı İstatistikler
- Ev sahibi/deplasman performans analizi
- Sezonluk ve maç başı gol ortalamaları
- Temiz sheet (gol yememe) istatistikleri
- Korner ve şut analizleri
- Top possession ve oyun kontrolü analizi
- Form grafiği ve trend analizi

## Teknik Altyapı

### Veritabanı Tabloları
```sql
- matches (Temel maç bilgileri)
- match_predictions (Tahmin sonuçları)
- match_stats (Maç istatistikleri)
- h2h_stats (Karşılıklı maç istatistikleri)
- recommended_bets (Bahis önerileri)
```

### API Entegrasyonu
- API-Football servisi entegrasyonu
- Gerçek zamanlı veri akışı
- Otomatik veri güncelleme
- Hata kontrolü ve loglama
- İstek limiti yönetimi

## Kurulum

1. Veritabanını oluşturun:
```sql
CREATE DATABASE football_analyzer;
mysql -u kullanıcı_adı -p football_analyzer < database/schema.sql
```

2. Veritabanı bağlantı ayarlarını yapın:
```php:README.md
// src/Config/DatabaseConfig.php
class DatabaseConfig {
    private $host = 'localhost';
    private $dbname = 'football_analyzer';
    private $username = 'root';
    private $password = '';
}
```

3. API anahtarınızı ayarlayın:
```php:README.md
// src/FootballAnalyzer.php
private $apiKey = 'API_ANAHTARINIZ';
private $apiHost = 'api-football-v1.p.rapidapi.com';
```

## Kullanım Örnekleri

### 1. Yaklaşan Maçları Listeleme
```php
$analyzer = new FootballAnalyzer();
$matches = $analyzer->getUpcomingMatches(140); // Süper Lig için
```

### 2. Maç Analizi
```php
$analysis = $analyzer->getDetailedAnalysis(
    $homeTeamId,    // Ev sahibi takım ID
    $awayTeamId,    // Deplasman takımı ID
    $leagueId,      // Lig ID
    $season         // Sezon (örn: 2024)
);
```

### 3. Tahminleri Görüntüleme
```php
$html = $analyzer->displayAnalysis($analysis, $fixtureId);
echo $html; // Bootstrap ile şekillendirilmiş HTML çıktısı
```

## Sistem Gereksinimleri

- PHP 7.4 veya üzeri
- MySQL 5.7 veya üzeri
- PHP Extensions:
  - cURL
  - PDO MySQL
  - JSON
  - mbstring

## Güvenlik Önlemleri

- PDO prepared statements ile SQL injection koruması
- API istek limiti kontrolü
- Kapsamlı hata loglama sistemi
- Girdi doğrulama ve sanitizasyon
- Güvenli veritabanı bağlantısı

## Hata Kodları

- 1001: API bağlantı hatası
- 1002: Veritabanı bağlantı hatası
- 1003: Veri formatlama hatası
- 1004: API istek limiti aşıldı
- 1005: Eksik veya hatalı parametre

## Lisans

Bu proje MIT lisansı altında lisanslanmıştır.
