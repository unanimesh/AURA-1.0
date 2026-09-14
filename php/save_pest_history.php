<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') aura_json(['ok'=>false,'error'=>'POST required'],405);

$data = json_decode($_POST['pest'] ?? '{}', true);
if (!is_array($data) || empty($data['name'])) aura_json(['ok'=>false,'error'=>'No pest data'],400);

try {
    $db=aura_db();
    $stmt=$db->prepare('INSERT INTO pest_history (pest_name,confidence,symptoms,organic_treatment,chemical_treatment,prevention,image_path) VALUES (?,?,?,?,?,?,?)');
    $name=(string)$data['name']; $confidence=(float)($data['confidence'] ?? $data['confidence_pct'] ?? 0);
    $symptoms=(string)($data['symptoms']??''); $organic=(string)($data['organic_treatment']??''); $chemical=(string)($data['chemical_treatment']??''); $prevention=(string)($data['prevention']??''); $image=(string)($data['image_path']??'');
    $stmt->bind_param('sdsssss',$name,$confidence,$symptoms,$organic,$chemical,$prevention,$image);
    $stmt->execute();
    aura_json(['ok'=>true,'id'=>$stmt->insert_id]);
} catch(Throwable $e) { aura_json(['ok'=>false,'error'=>$e->getMessage()],500); }
