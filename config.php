<?php
// AURA central configuration. Prefer environment variables for secrets.
// For Apache, set these in the server environment or replace the empty values locally.

const AURA_DB_HOST = 'localhost';
const AURA_DB_NAME = 'smart_agri';
const AURA_DB_USER = 'iotuser';
const AURA_DB_PASS = 'iotpass';

const AURA_MODE_DB = 'db';
const AURA_MODE_ESP = 'esp';

// Simple local configuration for demos. Prefer environment variables in production.
const AURA_LOCAL_MODE = 'db';
const AURA_LOCAL_ESP_URL = '';
const AURA_LOCAL_API_TOKEN = '';
const AURA_LOCAL_WEATHER_KEY = '1009a9b3a995454c80073905250911';
const AURA_LOCAL_WEATHER_LOCATION = 'Ranchi';

// Default mode is DB-only: dashboard works without ESP.
// Set AURA_MODE=esp in the web-server environment for ESP motor control.
function aura_mode(): string {
    $mode = strtolower(trim(getenv('AURA_MODE') ?: AURA_LOCAL_MODE));
    return $mode === AURA_MODE_ESP ? AURA_MODE_ESP : AURA_MODE_DB;
}

function aura_esp_base_url(): string {
    $url = trim(getenv('AURA_ESP_URL') ?: AURA_LOCAL_ESP_URL);
    return rtrim($url, '/');
}

function aura_api_token(): string {
    return trim(getenv('AURA_API_TOKEN') ?: AURA_LOCAL_API_TOKEN);
}

function aura_weather_key(): string {
    return trim(getenv('WEATHER_API_KEY') ?: AURA_LOCAL_WEATHER_KEY);
}

function aura_weather_location(): string {
    return trim(getenv('WEATHER_LOCATION') ?: AURA_LOCAL_WEATHER_LOCATION);
}

function aura_openai_key(): string {
    return trim(getenv('OPENAI_API_KEY') ?: '');
}

function aura_groq_key(): string {
    return trim(getenv('GROQ_API_KEY') ?: '');
}

function aura_db(): mysqli {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = new mysqli(AURA_DB_HOST, AURA_DB_USER, AURA_DB_PASS, AURA_DB_NAME);
    if ($conn->connect_error) {
        throw new RuntimeException('Database connection failed');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function aura_json($payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function aura_read_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : [];
}

function aura_number($value, float $min, float $max): ?float {
    if ($value === null || $value === '') return null;
    if (!is_numeric($value)) return null;
    $n = (float)$value;
    return ($n >= $min && $n <= $max) ? $n : null;
}

function aura_bool($value): int {
    if (is_bool($value)) return $value ? 1 : 0;
    return in_array(strtolower((string)$value), ['1','true','yes','on'], true) ? 1 : 0;
}
