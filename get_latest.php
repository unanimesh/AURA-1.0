<?php
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');

$data = ['esp'=>[],'weather'=>[],'motor'=>[],'revenue'=>[]];

try {
    $conn = aura_db();
    $result = $conn->query('SELECT * FROM sensor_data ORDER BY id DESC LIMIT 1');
    if ($result && ($row = $result->fetch_assoc())) {
        $data['esp'] = [
            'temperature' => $row['temperature'] !== null ? (float)$row['temperature'] : null,
            'humidity' => $row['humidity'] !== null ? (float)$row['humidity'] : null,
            'moisture_pct' => $row['moisture_pct'] !== null ? (float)$row['moisture_pct'] : null,
            'fire_detected' => (bool)$row['fire_detected'],
            'ultrasonic1_cm' => $row['ultrasonic1_cm'] !== null ? (float)$row['ultrasonic1_cm'] : null,
            'ultrasonic2_cm' => $row['ultrasonic2_cm'] !== null ? (float)$row['ultrasonic2_cm'] : null,
            'capacity_pct' => $row['capacity_pct'] !== null ? (float)$row['capacity_pct'] : null,
            'motor_on' => (bool)$row['motor_on'],
            'created_at' => $row['created_at']
        ];
        $data['latest_db'] = $row;
        $cap = (float)($row['capacity_pct'] ?? 0);
    } else {
        $data['esp']['error'] = 'No sensor data available';
        $cap = 0;
    }

    $r2 = $conn->query("SELECT action, created_at FROM motor_actions ORDER BY id DESC LIMIT 1");
    if ($r2 && ($m = $r2->fetch_assoc())) $data['motor'] = ['last_action'=>$m['action'],'created_at'=>$m['created_at']];
    $data['revenue'] = [
        'capacity_pct'=>$cap,
        'est_weight_kg'=>round(($cap/100)*1000,2),
        'price_per_kg'=>28,
        'estimated_revenue'=>round(($cap/100)*1000*28,2)
    ];
} catch (Throwable $e) {
    $data['db_error'] = $e->getMessage();
}

$key = aura_weather_key();
if ($key !== '') {
    $url = 'https://api.weatherapi.com/v1/current.json?key='.rawurlencode($key).'&q='.rawurlencode(aura_weather_location()).'&aqi=no';
    $json = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $json = curl_exec($ch);
        curl_close($ch);
    }
    if ($json === false || $json === '') {
        $ctx = stream_context_create(['http'=>['timeout'=>8], 'ssl'=>['verify_peer'=>true, 'verify_peer_name'=>true]]);
        $json = @file_get_contents($url, false, $ctx);
    }
    $weather = $json ? json_decode($json,true) : null;
    if (is_array($weather) && isset($weather['current'])) {
        $data['weather'] = [
            'location'=>($weather['location']['name'] ?? '').', '.($weather['location']['region'] ?? ''),
            'temperature'=>(float)($weather['current']['temp_c'] ?? 0),
            'humidity'=>(float)($weather['current']['humidity'] ?? 0),
            'condition'=>$weather['current']['condition']['text'] ?? '--',
            'wind_kph'=>(float)($weather['current']['wind_kph'] ?? 0),
            'pressure_mb'=>(float)($weather['current']['pressure_mb'] ?? 0),
            'icon'=>isset($weather['current']['condition']['icon']) ? 'https:'.$weather['current']['condition']['icon'] : ''
        ];
    } else $data['weather']['error']='Weather unavailable';
} else {
    $data['weather']=['error'=>'Weather API key not configured'];
}

aura_json($data);
