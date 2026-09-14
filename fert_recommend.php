<?php
// fert_recommend.php
// AI-based fertilizer recommendation endpoint with fallback rules.
// Accepts POST JSON: { "crop":"wheat", "area":1200, "sensors":{ "temperature":.., "humidity":.., "moisture_pct":.. } }
// Returns JSON with recommendation, schedule and dosages.
//
// IMPORTANT: To enable AI mode, set $OPENAI_KEY below (or put in an env var).
// If not set, the endpoint uses the internal rule-based recommender.

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

// Read input
$raw = file_get_contents("php://input");
$input = json_decode($raw, true) ?: [];

// Basic validation & defaults
$crop = strtolower(trim($input['crop'] ?? 'mixed'));
$area = floatval($input['area'] ?? 1000); // in sq ft
$sensors = $input['sensors'] ?? null;
$temp = isset($sensors['temperature']) ? floatval($sensors['temperature']) : null;
$hum  = isset($sensors['humidity']) ? floatval($sensors['humidity']) : null;
$moist = isset($sensors['moisture_pct']) ? floatval($sensors['moisture_pct']) : null;

// Replace with your OpenAI key to enable AI mode (or set OPENAI_API_KEY env var)
$OPENAI_KEY = getenv('OPENAI_API_KEY') ?: ''; // set in server env is preferred
// Or uncomment to hardcode (not recommended): $OPENAI_KEY = "sk-...";

// Helper: fallback deterministic recommender
function fallback_recommendation($crop, $area, $temp, $hum, $moist) {
    // Base doses per 1000 sq ft (approx, example values)
    $base = [
        'wheat' => ['N' => 0.8, 'P' => 0.5, 'K' => 0.4],   // kg per 1000 sq ft
        'rice'  => ['N' => 1.2, 'P' => 0.6, 'K' => 0.6],
        'maize' => ['N' => 1.0, 'P' => 0.5, 'K' => 0.5],
        'pulses'=> ['N' => 0.4, 'P' => 0.4, 'K' => 0.3],
        'vegetables'=> ['N'=>0.8,'P'=>0.6,'K'=>0.5],
        'mixed' => ['N' => 0.8, 'P' => 0.5, 'K' => 0.4]
    ];
    $c = array_key_exists($crop, $base) ? $crop : 'mixed';
    $factor = max(0.2, $area / 1000.0);
    $doses = array_map(function($v) use ($factor){ return round($v * $factor, 2); }, $base[$c]);

    // Adjust for soil moisture & temp: if moisture very low -> smaller N, if high -> reduce N slightly (less uptake)
    if ($moist !== null) {
        if ($moist < 30) $doses['N'] = round($doses['N'] * 0.9, 2);
        if ($moist > 80) $doses['N'] = round($doses['N'] * 0.85, 2);
    }
    if ($temp !== null && $temp > 35) {
        // heat stress -> avoid heavy N, prefer foliar micro
        $doses['N'] = round($doses['N'] * 0.85, 2);
    }

    // Make simple schedule
    $schedule = [
        ["phase"=>"Baseline (sowing)", "when"=>"Day 0", "notes"=>"Apply basal P and K; small portion of N if recommended."],
        ["phase"=>"Vegetative", "when"=>"Day 30-45", "notes"=>"Top-dress nitrogen based on crop growth."],
        ["phase"=>"Pre-harvest", "when"=>"Day 60-90", "notes"=>"Potassium & micronutrients for yield and filling."]
    ];

    $rec = "Recommended per {$area} sq ft: N {$doses['N']} kg, P {$doses['P']} kg, K {$doses['K']} kg. Follow schedule and adjust depending on crop stage and local soil tests.";

    return [
        'source' => 'fallback',
        'recommendation' => $rec,
        'doses_kg' => $doses,
        'schedule' => $schedule,
        'notes' => 'This is a rule-based suggestion. For a precise plan, provide soil test (N, P, K) or enable AI mode.'
    ];
}

// If OpenAI key present -> call OpenAI for tailored plan
if (!empty($OPENAI_KEY)) {
    // Build a concise prompt
    $prompt = "You are an agronomist. Provide a concise fertilizer plan for the following:\n";
    $prompt .= "Crop: " . ($crop ?: "mixed") . "\n";
    $prompt .= "Area (sq ft): " . ($area ?: 1000) . "\n";
    if ($temp !== null) $prompt .= "Temperature (°C): $temp\n";
    if ($hum !== null)  $prompt .= "Humidity (%): $hum\n";
    if ($moist !== null) $prompt .= "Soil moisture (%): $moist\n";
    $prompt .= "\nReturn JSON only with keys: recommendation (short text), doses_kg (object with N,P,K), schedule (array of {phase,when,notes}), and short_notes.\n";
    $prompt .= "If any value is unknown, make reasonable assumptions and state them in short_notes.";

    // Prepare payload for Chat Completions (v1/chat/completions)
    $payload = [
        "model" => "gpt-4o-mini",     // change if you prefer another model
        "messages" => [
            ["role"=>"system","content"=>"You are a helpful agronomist assistant."],
            ["role"=>"user","content"=>$prompt]
        ],
        "temperature" => 0.2,
        "max_tokens" => 450
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $OPENAI_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        http_response_code(500);
        echo json_encode(['error'=>"OpenAI curl error: $err", 'fallback' => fallback_recommendation($crop,$area,$temp,$hum,$moist)]);
        exit;
    }

    if ($http < 200 || $http >= 300) {
        // Return fallback but include OpenAI response for debugging
        $resp_body = $resp ? json_decode($resp, true) : $resp;
        echo json_encode(['error'=>"OpenAI API HTTP $http", 'openai'=>$resp_body, 'fallback' => fallback_recommendation($crop,$area,$temp,$hum,$moist)]);
        exit;
    }

    $j = json_decode($resp, true);
    // Extract text content
    $content = $j['choices'][0]['message']['content'] ?? ($j['choices'][0]['text'] ?? null);

    // Try to parse JSON returned by model
    $out = null;
    if ($content) {
        $maybe = json_decode($content, true);
        if ($maybe !== null) {
            $out = $maybe;
        } else {
            // try to find JSON block
            if (preg_match('/\{.*\}/s', $content, $m)) {
                $parsed = json_decode($m[0], true);
                if ($parsed !== null) $out = $parsed;
            }
        }
    }

    if ($out !== null) {
        echo json_encode(array_merge(['source'=>'openai'], $out));
        exit;
    }

    // If model didn’t return JSON, provide the raw content plus fallback
    echo json_encode(['source'=>'openai_raw','raw'=>$content, 'fallback'=> fallback_recommendation($crop,$area,$temp,$hum,$moist)]);
    exit;
}

// No OpenAI key -> use fallback
echo json_encode(fallback_recommendation($crop,$area,$temp,$hum,$moist));
exit;
?>
