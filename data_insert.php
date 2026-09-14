<?php
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? aura_read_json() : $_GET;

$temperature = aura_number($input['temperature'] ?? null, -50, 100);
$humidity = aura_number($input['humidity'] ?? null, 0, 100);
$moisture = aura_number($input['moisture_pct'] ?? null, 0, 100);
$ultra1 = aura_number($input['ultrasonic1_cm'] ?? null, 0, 100000);
$ultra2 = aura_number($input['ultrasonic2_cm'] ?? null, 0, 100000);
$capacity = aura_number($input['capacity_pct'] ?? null, 0, 100);
$fire = aura_bool($input['fire_detected'] ?? 0);
$motor = aura_bool($input['motor_on'] ?? 0);

try {
    $conn = aura_db();
    $stmt = $conn->prepare('INSERT INTO sensor_data (temperature,humidity,moisture_pct,fire_detected,ultrasonic1_cm,ultrasonic2_cm,capacity_pct,motor_on) VALUES (?,?,?,?,?,?,?,?)');
    if (!$stmt) throw new RuntimeException('Database prepare failed');
    $stmt->bind_param('dddidddi', $temperature, $humidity, $moisture, $fire, $ultra1, $ultra2, $capacity, $motor);
    if (!$stmt->execute()) throw new RuntimeException('Database insert failed');
    aura_json(['success'=>true, 'id'=>$stmt->insert_id, 'created_at'=>date('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    aura_json(['success'=>false, 'error'=>$e->getMessage()], 500);
}
