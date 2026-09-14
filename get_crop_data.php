<?php
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');
try{$conn=aura_db();$conn->query("CREATE TABLE IF NOT EXISTS crop_data (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,crop VARCHAR(50) NOT NULL,sowing_date DATE NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");$r=$conn->query('SELECT * FROM crop_data ORDER BY id DESC LIMIT 1');aura_json($r?$r->fetch_assoc():[]);}catch(Throwable $e){aura_json(['error'=>$e->getMessage()],500);}
