<?php
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');
try {
 $conn=aura_db(); $data=[];
 $r=$conn->query("SELECT ROUND(AVG(temperature),2) avg_temp, ROUND(AVG(humidity),2) avg_hum, ROUND(AVG(moisture_pct),2) avg_moisture, MAX(temperature) max_temp, MIN(temperature) min_temp FROM sensor_data WHERE created_at > NOW()-INTERVAL 1 DAY");
 $data['sensors']=$r?$r->fetch_assoc():[];
 $r=$conn->query("SELECT SUM(action='ON') on_count, SUM(action='OFF') off_count FROM motor_actions WHERE created_at > NOW()-INTERVAL 1 DAY");
 $data['motor']=$r?$r->fetch_assoc():[];
 $r=$conn->query("SELECT capacity_pct,(capacity_pct/100)*1000 est_weight_kg FROM sensor_data ORDER BY id DESC LIMIT 1");
 $data['warehouse']=$r?$r->fetch_assoc():[];
 $weight=(float)($data['warehouse']['est_weight_kg']??0); $data['revenue']=['price_per_kg'=>28,'est_revenue'=>round($weight*28,2)];
 aura_json($data);
} catch(Throwable $e){aura_json(['error'=>$e->getMessage()],500);}
