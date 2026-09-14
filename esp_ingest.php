<?php
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$expected = aura_api_token();
$provided = trim($_SERVER['HTTP_X_AURA_TOKEN'] ?? ($_POST['token'] ?? ''));
if ($expected !== '' && !hash_equals($expected, $provided)) {
    aura_json(['success'=>false,'error'=>'Unauthorized'], 401);
}

$input = aura_read_json();
if (!$input) $input = $_POST;

$required = ['temperature','humidity','moisture_pct','fire_detected','ultrasonic1_cm','ultrasonic2_cm','capacity_pct','motor_on'];
foreach ($required as $key) {
    if (!array_key_exists($key, $input)) aura_json(['success'=>false,'error'=>"Missing field: $key"], 400);
}

// Reuse the same validation/insert logic by inserting directly here.
$temperature = aura_number($input['temperature'], -50, 100);
$humidity = aura_number($input['humidity'], 0, 100);
$moisture = aura_number($input['moisture_pct'], 0, 100);
$ultra1 = aura_number($input['ultrasonic1_cm'], 0, 100000);
$ultra2 = aura_number($input['ultrasonic2_cm'], 0, 100000);
$capacity = aura_number($input['capacity_pct'], 0, 100);
$fire = aura_bool($input['fire_detected']);
$motor = aura_bool($input['motor_on']);

try {
    $conn = aura_db();
    $stmt = $conn->prepare('INSERT INTO sensor_data (temperature,humidity,moisture_pct,fire_detected,ultrasonic1_cm,ultrasonic2_cm,capacity_pct,motor_on) VALUES (?,?,?,?,?,?,?,?)');
    if (!$stmt) throw new RuntimeException('Database prepare failed');
    $stmt->bind_param('dddidddi', $temperature, $humidity, $moisture, $fire, $ultra1, $ultra2, $capacity, $motor);
    if (!$stmt->execute()) throw new RuntimeException('Database insert failed');
    aura_json(['success'=>true,'id'=>$stmt->insert_id]);
} catch (Throwable $e) {
    aura_json(['success'=>false,'error'=>$e->getMessage()], 500);
}
