<?php
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$input=aura_read_json();
$userMsg=trim($input['message']??'');

// Motor commands work in both DB-only and ESP modes.
if(preg_match('/\bmotor on\b|\bstart motor\b|\bpump on\b|\birrigation on\b/i',$userMsg)){
    $_GET['hp']=1;
    require __DIR__.'/motor_on.php';
    exit;
}
if(preg_match('/\bmotor off\b|\bstop motor\b|\bpump off\b|\birrigation off\b/i',$userMsg)){
    require __DIR__.'/motor_off.php';
    exit;
}

try{
 $conn=aura_db();
 $r=$conn->query('SELECT temperature,humidity,moisture_pct,fire_detected,capacity_pct,motor_on,created_at FROM sensor_data ORDER BY id DESC LIMIT 1');
 $row=$r?$r->fetch_assoc():null;
}catch(Throwable $e){aura_json(['reply'=>'⚠ Database error: '.$e->getMessage()],500);}

if(!$row){aura_json(['reply'=>'⚠ Abhi koi sensor reading database mein available nahi hai. Pehle data inject/send karo.']);}

$sensorText="Temperature: {$row['temperature']} °C\nHumidity: {$row['humidity']} %\nSoil Moisture: {$row['moisture_pct']} %\nFire: ".((int)$row['fire_detected']?'Detected':'Safe')."\nCapacity: {$row['capacity_pct']} %\nMotor: ".((int)$row['motor_on']?'ON':'OFF')."\nLast reading: {$row['created_at']}";

// If no AI key is configured, provide deterministic local assistance.
$key=aura_groq_key();
if($key===''){
 $reply="🌾 AURA Sensor Summary\n\n$sensorText\n\n";
 if((int)$row['fire_detected']) $reply.='🚨 Fire detected — motor should remain OFF.\n';
 elseif((float)$row['moisture_pct']<30) $reply.='💧 Soil moisture low — irrigation may be needed.\n';
 elseif((float)$row['moisture_pct']>70) $reply.='💧 Soil moisture is high — irrigation can be stopped.\n';
 else $reply.='✅ Soil moisture is in the normal demo range.\n';
 aura_json(['reply'=>$reply]);
}

$payload=['model'=>'llama-3.3-70b-versatile','messages'=>[
 ['role'=>'system','content'=>"AURA Smart Farming AI. Use concise Hinglish and emojis. Current sensor data:\n$sensorText"],
 ['role'=>'user','content'=>$userMsg]
],'temperature'=>0.2,'max_tokens'=>500];
$ch=curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>20]);
$response=curl_exec($ch); $err=curl_error($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($err||$http<200||$http>=300){aura_json(['reply'=>'⚠ AI service unavailable. Local sensor data:\n'.$sensorText]);}
$data=json_decode($response,true); aura_json(['reply'=>$data['choices'][0]['message']['content']??'⚠ AI response unavailable.']);
