/************************************************************
   AURA — ESP8266 Sensor Node
   Sends sensor readings to AURA PHP API over HTTP POST.

   IMPORTANT:
   - Replace WIFI_SSID / WIFI_PASSWORD.
   - Set API_URL to your computer/server LAN IP, e.g.
     http://192.168.1.10/AURA-1.0-main/esp_ingest.php
   - If you configured AURA_API_TOKEN on the server, put the same
     token in API_TOKEN below.
************************************************************/
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ESP8266WebServer.h>
#include <ArduinoJson.h>
#include <DHT.h>

#define DHTTYPE DHT11
#define DHT_FIELD_PIN D4
#define DHT_WARE_PIN D5
#define SOIL_PIN A0
#define FIRE_PIN D6
#define TRIG_PIN D1
#define ECHO_PIN D2
#define MOTOR_PIN D3

const char* WIFI_SSID = "YOUR_WIFI";
const char* WIFI_PASSWORD = "YOUR_PASS";
const char* API_URL = "http://192.168.1.10/AURA-1.0-main/esp_ingest.php";
const char* API_TOKEN = "";

ESP8266WebServer server(80);
DHT dhtField(DHT_FIELD_PIN, DHTTYPE);
DHT dhtWare(DHT_WARE_PIN, DHTTYPE);
bool motorState = false;
unsigned long lastSend = 0;

long readUltrasonic() {
  digitalWrite(TRIG_PIN, LOW); delayMicroseconds(4);
  digitalWrite(TRIG_PIN, HIGH); delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  unsigned long duration = pulseIn(ECHO_PIN, HIGH, 30000UL);
  if (!duration) return -1;
  return (long)(duration * 0.0343f / 2.0f);
}

int capacityFromDistance(long cm) {
  if (cm <= 0) return 0;
  int cap = 100 - (int)((cm / 30.0f) * 100.0f);
  return constrain(cap, 0, 100);
}

void sendReading() {
  if (WiFi.status() != WL_CONNECTED) return;
  float temp = dhtWare.readTemperature();
  float hum = dhtWare.readHumidity();
  if (isnan(temp) || isnan(hum)) return;

  int raw = analogRead(SOIL_PIN);
  int moisture = constrain(map(raw, 0, 1023, 100, 0), 0, 100);
  int fire = digitalRead(FIRE_PIN) == LOW ? 1 : 0;
  long dist = readUltrasonic();
  int cap = capacityFromDistance(dist);

  StaticJsonDocument<512> doc;
  doc["temperature"] = temp;
  doc["humidity"] = hum;
  doc["moisture_pct"] = moisture;
  doc["fire_detected"] = fire;
  doc["ultrasonic1_cm"] = dist > 0 ? dist : 0;
  doc["ultrasonic2_cm"] = dist > 0 ? dist : 0;
  doc["capacity_pct"] = cap;
  doc["motor_on"] = motorState ? 1 : 0;

  String body; serializeJson(doc, body);
  WiFiClient client;
  HTTPClient http;
  if (!http.begin(client, API_URL)) return;
  http.addHeader("Content-Type", "application/json");
  if (strlen(API_TOKEN) > 0) http.addHeader("X-AURA-TOKEN", API_TOKEN);
  http.setTimeout(5000);
  http.POST(body);
  http.end();
}

void handleSensors() {
  StaticJsonDocument<400> doc;
  float t = dhtWare.readTemperature();
  float h = dhtWare.readHumidity();
  long dist = readUltrasonic();
  doc["temperature"] = isnan(t) ? -999 : t;
  doc["humidity"] = isnan(h) ? -999 : h;
  doc["moisture_pct"] = constrain(map(analogRead(SOIL_PIN), 0, 1023, 100, 0), 0, 100);
  doc["fire_detected"] = digitalRead(FIRE_PIN) == LOW ? 1 : 0;
  doc["ultrasonic1_cm"] = dist > 0 ? dist : 0;
  doc["ultrasonic2_cm"] = dist > 0 ? dist : 0;
  doc["capacity_pct"] = capacityFromDistance(dist);
  doc["motor_on"] = motorState ? 1 : 0;
  String out; serializeJson(doc, out);
  server.send(200, "application/json", out);
}

void handleMotorOn() { motorState = true; digitalWrite(MOTOR_PIN, HIGH); server.send(200,"application/json","{\"motor\":\"ON\"}"); }
void handleMotorOff() { motorState = false; digitalWrite(MOTOR_PIN, LOW); server.send(200,"application/json","{\"motor\":\"OFF\"}"); }
void handleMotorStatus() { server.send(200,"application/json", motorState ? "{\"motor\":\"ON\"}" : "{\"motor\":\"OFF\"}"); }

void setup() {
  Serial.begin(115200);
  pinMode(MOTOR_PIN, OUTPUT); digitalWrite(MOTOR_PIN, LOW);
  pinMode(FIRE_PIN, INPUT); pinMode(TRIG_PIN, OUTPUT); pinMode(ECHO_PIN, INPUT);
  dhtField.begin(); dhtWare.begin();
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) delay(300);
  server.on("/sensors", handleSensors);
  server.on("/motor/on", handleMotorOn);
  server.on("/motor/off", handleMotorOff);
  server.on("/motor/status", handleMotorStatus);
  server.begin();
}

void loop() {
  server.handleClient();
  if (millis() - lastSend >= 10000UL) { lastSend = millis(); sendReading(); }
}
