<?php
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');

if (aura_mode() === AURA_MODE_ESP && aura_esp_base_url() !== '') {
    $ch=curl_init(aura_esp_base_url().'/motor/status');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5]);
    $resp=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
    if(!$err && $resp!==''){ echo $resp; exit; }
}
try{
    $conn=aura_db();
    $r=$conn->query("SELECT motor_on FROM sensor_data ORDER BY id DESC LIMIT 1");
    $row=$r?$r->fetch_assoc():null;
    aura_json(['motor'=>$row && (int)$row['motor_on']===1 ? 'ON':'OFF','source'=>'database']);
}catch(Throwable $e){aura_json(['error'=>$e->getMessage()],500);}
