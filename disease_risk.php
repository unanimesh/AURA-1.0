<?php
// disease_risk.php (Groq API version)
// INPUT: POST sensors + crop
// OUTPUT: JSON (fungal, bacterial, viral, overall, reasons[])

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true) ?: [];

$s = $input["sensors"] ?? [];
$crop = strtolower($input["crop"] ?? "wheat");

// Extract sensor values safely
$t = floatval($s["temperature"] ?? 0);
$h = floatval($s["humidity"] ?? 0);
$m = floatval($s["moisture_pct"] ?? 0);

$GROQ_KEY = getenv("") ?: "";   // 🔥 IMPORTANT

// ---------------------------------------
// FALLBACK MODEL (when no Groq key)
// ---------------------------------------
function fallback_model($t,$h,$m,$crop){
    $reasons = [];
    $fungal = $bacter = $viral = 20;

    if ($h > 80) { $fungal += 30; $reasons[] = "High humidity → fungal growth."; }
    if ($h > 60 && $m > 70) { $fungal += 20; }
    if ($t > 32) { $viral += 20; $reasons[] = "High temperature increases viral risk."; }
    if ($m > 80) { $fungal += 20; $reasons[] = "Wet soil favours fungal pathogens."; }
    if ($m < 25) { $bacter += 10; }

    if ($crop === "tomato") { $viral += 15; $fungal += 10; }
    if ($crop === "rice") { $fungal += 20; }

    $fungal = min(100, max(0, $fungal));
    $bacter = min(100, max(0, $bacter));
    $viral  = min(100, max(0, $viral));

    $overall = round(($fungal + $bacter + $viral) / 3);

    return [
        "source" => "fallback",
        "fungal" => $fungal,
        "bacterial" => $bacter,
        "viral" => $viral,
        "overall" => $overall,
        "reasons" => $reasons
    ];
}

// No key → fallback
if (empty($GROQ_KEY)) {
    echo json_encode(fallback_model($t,$h,$m,$crop));
    exit;
}

// ---------------------------------------
// GROQ API CALL
// ---------------------------------------
$prompt = "You are an agricultural disease prediction AI.
Using the following real-time field data:

Temperature: $t °C  
Humidity: $h %  
Soil Moisture: $m %  
Crop: $crop  

Predict disease risks (0–100).  
Return STRICT JSON:

{
  \"fungal\": number,
  \"bacterial\": number,
  \"viral\": number,
  \"overall\": number,
  \"reasons\": [\"...\"] 
}

DO NOT include explanation. JSON ONLY.";

$payload = [
    "model" => "llama3-8b-8192",
    "messages" => [
        ["role" => "system", "content" => "You are a precise agricultural plant disease model."],
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.2,
    "max_tokens" => 300
];

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $GROQ_KEY"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$error = curl_error($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// API failure → fallback
if ($error || $http >= 400) {
    echo json_encode(fallback_model($t,$h,$m,$crop));
    exit;
}

$j = json_decode($response, true);
$content = $j["choices"][0]["message"]["content"] ?? "";

// Try parse JSON
$out = json_decode($content, true);

// If Groq wrapped JSON in text → extract { ... }
if (!$out && preg_match('/\{.*\}/s', $content, $m2))
    $out = json_decode($m2[0], true);

// Still invalid? fallback
if (!$out) {
    echo json_encode(fallback_model($t,$h,$m,$crop));
    exit;
}

$out["source"] = "groq";
echo json_encode($out);
exit;
?>
