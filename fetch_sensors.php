<?php
// Legacy helper retained for ESP-mode installations. The dashboard does not call this file.
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');
$base=aura_esp_base_url();
if($base==='') aura_json(['error'=>'ESP URL not configured'],503);
$ch=curl_init($base.'/sensors');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5]);
$response=curl_exec($ch); $err=curl_error($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($err||$http<200||$http>=300) aura_json(['error'=>'ESP unreachable','details'=>$err?:'HTTP '.$http],502);
$data=json_decode($response,true);
if(!is_array($data)) aura_json(['error'=>'Invalid JSON from ESP'],502);
// Persist the ESP reading in the same database used by the dashboard.
$ch2=curl_init('http://127.0.0.1'.dirname($_SERVER['SCRIPT_NAME']).'/data_insert.php');
curl_setopt_array($ch2,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($data),CURLOPT_TIMEOUT=>5]);
@curl_exec($ch2); curl_close($ch2);
aura_json($data);
