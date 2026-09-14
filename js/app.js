/* ==========================================================
   🌾 AURA Smart Agriculture — Safe Unified App Controller
   Compatible with all dashboards (index, field_crop, warehouse, market)
   ========================================================== */

document.addEventListener("DOMContentLoaded", () => {
  const API_ENDPOINT = "get_latest.php";

  // Safe helper: update text only if element exists
  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function setWidth(id, valuePercent) {
    const el = document.getElementById(id);
    if (el && el.style) el.style.width = (Number(valuePercent) || 0) + "%";
  }

  // Chart
  let chartEl = document.getElementById("chartMain");
  let chart = null;
  function initChart() {
    if (!chartEl) return;
    chart = new Chart(chartEl, {
      type: "line",
      data: {
        labels: [],
        datasets: [
          { label: "Temperature (°C)", borderColor: "#e74c3c", backgroundColor: "rgba(231,76,60,0.08)", data: [], tension: 0.3 },
          { label: "Humidity (%)", borderColor: "#3498db", backgroundColor: "rgba(52,152,219,0.08)", data: [], tension: 0.3 },
          { label: "Soil Moisture (%)", borderColor: "#2ecc71", backgroundColor: "rgba(46,204,113,0.08)", data: [], tension: 0.3 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true },
          x: { }
        }
      }
    });
  }

  function updateChart(data) {
    if (!chart || !data) return;
    const now = new Date().toLocaleTimeString();
    chart.data.labels.push(now);
    chart.data.datasets[0].data.push(Number(data.temperature) || 0);
    chart.data.datasets[1].data.push(Number(data.humidity) || 0);
    chart.data.datasets[2].data.push(Number(data.moisture_pct ?? data.moisture ?? 0));
    if (chart.data.labels.length > 30) {
      chart.data.labels.shift();
      chart.data.datasets.forEach(ds => ds.data.shift());
    }
    chart.update();
  }

  // fetch with timeout helper
  async function fetchWithTimeout(url, opts = {}, timeout = 5000) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeout);
    try {
      const resp = await fetch(url, { signal: controller.signal, ...opts, cache: "no-store" });
      clearTimeout(id);
      return resp;
    } finally {
      clearTimeout(id);
    }
  }

  async function fetchAllData() {
    try {
      const res = await fetchWithTimeout(API_ENDPOINT + "?_ts=" + Date.now(), {}, 4000);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const json = await res.json();

      // consider both shapes
      const esp = json.esp ? json.esp : json;

      // mark connected
      setText("connText", "Connected ✅");

      // WEATHER (if provided by your server)
      if (json.weather) {
        setText("weatherLocation", json.weather.location || "—");
        setText("weatherTemp", `${json.weather.temperature ?? "--"} °C`);
        setText("weatherHum", `${json.weather.humidity ?? "--"} %`);
        setText("weatherCond", json.weather.condition ?? "--");
        setText("weatherWind", `${json.weather.wind_kph ?? "--"} km/h`);
      }

      // ESP SENSOR DATA
      if (esp && !esp.error) {
        setText("moistureMain", (esp.moisture_pct ?? esp.moisture ?? "--") + (typeof esp.moisture_pct === "number" ? "%" : ""));
        setWidth("moistFill", (esp.moisture_pct ?? esp.moisture ?? 0));
        if (typeof esp.temperature === "number") setText("tempMain", `${esp.temperature.toFixed(1)} °C`); else setText("tempMain", `${esp.temp ?? "--"} °C`);
        setText("humMain", (esp.humidity ?? esp.hum ?? "--") + (typeof esp.humidity === "number" ? " %" : ""));
        setText("motorMain", (typeof esp.motor_on === "boolean") ? (esp.motor_on ? "ON" : "OFF") : (esp.motor ?? "--"));
        setText("capacityMain", (esp.capacity_pct ?? esp.storage_capacity ?? "--") + (typeof esp.capacity_pct === "number" ? "%" : ""));
        setWidth("fillMain", (esp.capacity_pct ?? esp.storage_capacity ?? 0));
        setText("fireMain", (esp.fire_detected || esp.fire) ? "🔥 Detected" : "Safe");

        updateChart(esp);
      } else {
        // offline-ish state
        setText("moistureMain", "--%");
        setText("tempMain", "-- °C");
        setText("humMain", "-- %");
        setText("motorMain", "--");
        setText("capacityMain", "--%");
        setText("fireMain", "Offline");
      }

      // REVENUE - if server provides
      if (json.revenue) {
        setText("revenueMain", "₹ " + Math.round(json.revenue.estimated_revenue).toLocaleString());
      }

    } catch (err) {
      console.error("Fetch failed:", err);
      setText("connText", "Offline ❌");
      setText("weatherLocation", "No data");
      setText("moistureMain", "--%");
      setText("tempMain", "-- °C");
      setText("humMain", "-- %");
      setText("motorMain", "--");
      setText("capacityMain", "--%");
      setText("fireMain", "Offline");
      setText("revenueMain", "-- ₹");
    }
  }

  // INIT
  initChart();
  fetchAllData();
  // refresh every 10s
  setInterval(fetchAllData, 10000);
});

/* ==========================================================
   🌱 FIELD & CROP CONTROL — DYNAMIC SECTION (keeps existing simulation)
   ========================================================== */
if (document.getElementById("fieldMoist")) {
  const moist = document.getElementById("fieldMoist");
  const moistAnim = document.getElementById("moistAnim");
  const temp = document.getElementById("fieldTemp");
  const hum = document.getElementById("fieldHum");
  const cropRec = document.getElementById("cropRec");
  const flowRateEl = document.getElementById("flowRate");
  const waterTimeEl = document.getElementById("wateringTime");
  const growthBar = document.getElementById("growthBar");
  const growthAnim = document.getElementById("growthAnim");
  const harvestDays = document.getElementById("harvestDays");
  const yieldEl = document.getElementById("expectedYield");

  const startBtn = document.getElementById("startMotor");
  const stopBtn = document.getElementById("stopMotor");
  const calcBtn = document.getElementById("calcBtn");

  // ---------- Dummy Sensor Data (simulate real-time) ----------
  let soilMoisture = 55;
  let growthPercent = 40;

  // ---------- Crop Recommendation ----------
  function recommendCrop(tempC, humPct) {
    if (tempC < 20) return "🌾 Wheat / Mustard";
    if (tempC < 28 && humPct > 50) return "🌾 Rice / Maize";
    if (tempC > 30) return "🌿 Millet / Pulses";
    return "🌱 Vegetables / Mixed Crops";
  }

  // ---------- Motor Control ----------
  if (startBtn) startBtn.addEventListener("click", async () => {
    alert("🚿 Irrigation Started");
    fetch("motor_on.php", { method: "POST" }).catch(() => {});
  });

  if (stopBtn) stopBtn.addEventListener("click", async () => {
    alert("⛔ Irrigation Stopped");
    fetch("motor_off.php", { method: "POST" }).catch(() => {});
  });

  if (calcBtn) calcBtn.addEventListener("click", () => {
    const hp = parseFloat(document.getElementById("motorHP").value || 0);
    const area = parseFloat(document.getElementById("fieldArea").value || 0);
    const flowRate = hp * 15; // litres per minute (rough)
    const requiredLitres = area * 0.04; // simple water factor
    const minutes = flowRate > 0 ? (requiredLitres / flowRate).toFixed(1) : "0";
    flowRateEl.textContent = `${flowRate.toFixed(1)} L/min`;
    waterTimeEl.textContent = `${minutes} mins`;
  });

  // ---------- Simulate Growth ----------
  setInterval(() => {
    soilMoisture = Math.min(100, soilMoisture + (Math.random() * 2 - 1));
    if (moist) moist.textContent = `${soilMoisture.toFixed(1)}%`;
    if (moistAnim) moistAnim.style.width = `${soilMoisture}%`;

    const randomTemp = 22 + Math.random() * 10;
    const randomHum = 50 + Math.random() * 30;
    if (temp) temp.textContent = `${randomTemp.toFixed(1)} °C`;
    if (hum) hum.textContent = `${randomHum.toFixed(0)} %`;
    if (cropRec) cropRec.textContent = recommendCrop(randomTemp, randomHum);

    // Growth Simulation
    growthPercent = Math.min(100, growthPercent + 0.5);
    if (growthBar) growthBar.style.width = `${growthPercent}%`;
    if (growthAnim) growthAnim.style.width = `${growthPercent}%`;

    const daysRemaining = Math.max(0, Math.round((100 - growthPercent) / 2));
    if (harvestDays) harvestDays.textContent = `${daysRemaining} days remaining`;

    const area = parseFloat(document.getElementById("fieldArea").value || 0);
    const yieldKg = (area / 1000) * (growthPercent / 100) * 60;
    if (yieldEl) yieldEl.textContent = `${yieldKg.toFixed(1)} kg`;
  }, 4000);
}