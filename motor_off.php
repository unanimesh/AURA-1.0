<?php
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');
$mode=aura_mode();
$espResponse=null;
if($mode===AURA_MODE_ESP){
    $base=aura_esp_base_url();
    if($base==='') aura_json(['success'=>false,'error'=>'ESP mode enabled but AURA_ESP_URL is not configured'],500);
    $ch=curl_init($base.'/motor/off');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5]);
    $espResponse=curl_exec($ch); $err=curl_error($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($err||$http<200||$http>=300) aura_json(['success'=>false,'error'=>'ESP motor OFF failed','details'=>$err?:'HTTP '.$http],502);
}
try{
    $conn=aura_db();
    $conn->query("INSERT INTO motor_actions (action) VALUES ('OFF')");
    $conn->query('UPDATE sensor_data SET motor_on=0 ORDER BY id DESC LIMIT 1');
    aura_json(['success'=>true,'mode'=>$mode,'motor'=>'OFF','esp_response'=>$espResponse]);
}catch(Throwable $e){aura_json(['success'=>false,'error'=>$e->getMessage()],500);}
