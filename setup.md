# AURA 1.1 — Setup Guide

AURA supports **two sensor-data modes**:

1. **MariaDB-only / Simulator mode** — no ESP8266 required.
2. **ESP8266 mode** — ESP8266 reads sensors and sends JSON to PHP, which stores it in MariaDB.

The dashboard reads sensor data from `get_latest.php`, so the frontend does **not** need to know the ESP8266 IP address.

## 1. Requirements

- Debian/Ubuntu Linux
- Apache2
- PHP 8.x with `mysqli` and `curl`
- MariaDB
- Browser
- Optional: ESP8266 NodeMCU + DHT11 + soil sensor + ultrasonic + fire sensor + relay

Check:

```bash
php -v
php -m | grep -E 'mysqli|curl'
sudo systemctl status apache2
sudo systemctl status mariadb
```

## 2. MariaDB installation

```bash
sudo apt update
sudo apt install apache2 mariadb-server php php-mysql php-curl unzip -y
sudo systemctl enable --now apache2 mariadb
```

## 3. Create AURA database

From the project directory:

```bash
sudo mariadb < schema.sql
```

The included schema creates:

- `smart_agri.sensor_data`
- `smart_agri.motor_actions`
- `smart_agri.crop_data`
- `smart_agri.pest_history`

The application uses:

- DB: `smart_agri`
- User: `iotuser`
- Password: `iotpass`

For production, change these credentials and update `config.php`.

## 4. Install AURA under Apache

If the project is in Downloads:

```bash
sudo cp -r AURA-1.0-main /var/www/html/
sudo chown -R www-data:www-data /var/www/html/AURA-1.0-main
```

Test:

```text
http://localhost/AURA-1.0-main/
```

## 5. Choose operating mode

Open `config.php`. For MariaDB-only testing keep:

```php
const AURA_LOCAL_MODE = 'db';
const AURA_LOCAL_ESP_URL = '';
const AURA_LOCAL_API_TOKEN = '';
```

For ESP mode use:

```php
const AURA_LOCAL_MODE = 'esp';
const AURA_LOCAL_ESP_URL = 'http://ESP8266_IP';
const AURA_LOCAL_API_TOKEN = '';
```

Environment variables with the same names (`AURA_MODE`, `AURA_ESP_URL`, `AURA_API_TOKEN`) override these demo values.

## 5. Test database connection

Create `db_test.php` if required:

```php
<?php
require_once __DIR__ . '/config.php';
try { aura_db(); echo 'AURA MariaDB connection successful!'; }
catch (Throwable $e) { http_response_code(500); echo $e->getMessage(); }
```

Open:

```text
http://localhost/AURA-1.0-main/db_test.php
```

## 7. MariaDB-only mode

Open:

```text
http://localhost/AURA-1.0-main/simulator.php
```

Enter values and click **Inject once**.

You can also start automatic simulation. It inserts a new reading every 5 seconds.

Verify:

```bash
sudo mariadb smart_agri
```

```sql
SELECT * FROM sensor_data ORDER BY id DESC LIMIT 10;
```

Then open the dashboard. It refreshes the database-backed API every 10 seconds.

API test:

```text
http://localhost/AURA-1.0-main/get_latest.php
```

## 8. ESP8266 mode

### Step A — Find the PC/server LAN IP

On the Linux PC:

```bash
hostname -I
```

Example:

```text
192.168.1.10
```

### Step B — Configure ESP code

Open `1Prompt` in Arduino IDE.

Install ESP8266 board support and these libraries:

- ESP8266WiFi
- ESP8266HTTPClient
- ESP8266WebServer
- ArduinoJson
- DHT sensor library

Change:

```cpp
const char* WIFI_SSID = "YOUR_WIFI";
const char* WIFI_PASSWORD = "YOUR_PASS";
const char* API_URL = "http://192.168.1.10/AURA-1.0-main/esp_ingest.php";
```

Replace `192.168.1.10` with your Linux PC/server LAN IP.

Upload to the ESP8266.

### Step C — Make Apache reachable from the ESP

The ESP8266 and PC must be on the same LAN/Wi-Fi network.

Test from another device first:

```text
http://PC_LAN_IP/AURA-1.0-main/get_latest.php
```

If a firewall is enabled, allow TCP port 80.

### Step D — Start ESP ingestion

The ESP sends a reading approximately every 10 seconds to:

```text
POST /AURA-1.0-main/esp_ingest.php
```

The PHP API validates the values and inserts them into MariaDB.

Verify:

```sql
SELECT * FROM sensor_data ORDER BY id DESC LIMIT 5;
```

## 9. Optional API token

For a LAN demo you can leave the token empty.

For a real deployment, configure a long random token on the server as:

```text
AURA_API_TOKEN=your-long-random-token
```

and put the same token in the ESP sketch:

```cpp
const char* API_TOKEN = "your-long-random-token";
```

The ESP sends it as `X-AURA-TOKEN`.

## 10. ESP motor-control mode

Default AURA mode is **DB-only**. This is intentional: the dashboard can be developed and tested without hardware.

To allow dashboard/chatbot motor commands to reach the ESP, configure the web server environment:

```text
AURA_MODE=esp
AURA_ESP_URL=http://192.168.1.20
```

where `192.168.1.20` is the ESP8266 IP.

Then:

```text
Dashboard → motor_on.php → ESP8266 → relay
Dashboard → motor_off.php → ESP8266 → relay
```

Motor actions are also logged in `motor_actions`.

## 11. Important hardware note

Do not connect a water pump directly to an ESP8266 GPIO. Use a correctly rated relay/driver and suitable external power supply. Ensure the relay logic level and pump voltage/current ratings are appropriate.

For ultrasonic sensors, ensure the ESP8266 GPIO never receives a voltage above its safe input level; many HC-SR04 modules output a 5 V echo signal and require level shifting.

## 12. Optional AI configuration

The project has rule-based fallbacks, so the core dashboard works without AI keys.

If desired, configure environment variables:

```text
GROQ_API_KEY=...
OPENAI_API_KEY=...
WEATHER_API_KEY=...
WEATHER_LOCATION=Ranchi
```

Do **not** commit API keys into Git or put them directly in JavaScript/HTML.

## 13. Recommended production architecture

```text
             ┌──────────────────┐
             │ ESP8266 Sensors  │
             └────────┬─────────┘
                      │ HTTP POST
                      ▼
             ┌──────────────────┐
             │   esp_ingest.php │
             └────────┬─────────┘
                      │
                      ▼
             ┌──────────────────┐
             │     MariaDB      │
             │    smart_agri    │
             └────────┬─────────┘
                      │
                      ▼
             ┌──────────────────┐
             │  get_latest.php  │
             └────────┬─────────┘
                      │ JSON
                      ▼
             ┌──────────────────┐
             │   AURA Dashboard │
             └──────────────────┘
```

For Internet deployment, use HTTPS, authentication/API tokens, a firewall, and do not expose MariaDB port 3306 directly to the Internet.
