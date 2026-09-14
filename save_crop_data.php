<?php
require_once __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: *');
$input=$_SERVER['REQUEST_METHOD']==='POST'?aura_read_json():$_GET;
$crop=trim($input['crop']??''); $date=trim($input['sowing_date']??'');
if($crop===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) aura_json(['status'=>'error','error'=>'Valid crop and sowing_date are required'],400);
try{$conn=aura_db();$stmt=$conn->prepare('INSERT INTO crop_data (crop,sowing_date) VALUES (?,?)');$stmt->bind_param('ss',$crop,$date);$stmt->execute();aura_json(['status'=>'ok','id'=>$stmt->insert_id]);}catch(Throwable $e){aura_json(['status'=>'error','error'=>$e->getMessage()],500);}
