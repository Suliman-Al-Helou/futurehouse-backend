# 📊 Phase 5: Load and Pressure Test Results (k6)

> **Date:** 2026-11-16  
> **Environment:** Isolated PostgreSQL 16 (`127.0.0.1:5433 / courses_testing`) — Cloud Neon Untouched  
> **PHP Runtime:** PHP 8.5.4 (CLI Server with `PHP_CLI_SERVER_WORKERS=16`, cached config & routes)  
> **Load Tool:** k6 v2.3.0  
> **Target Capacity:** 100 concurrent students at peak session start  

---

## 🎯 Summary Dashboard

| Scenario | VUs | Duration | Total Requests | RPS (Throughput) | Error Rate | Checks Rate | p(95) Latency | p(99) Latency | SLA Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Smoke Test** | 3 | 30s | 144 | 4.57 req/s | **0.00%** | **100.00%** | **46.28 ms** | **86.35 ms** | ✅ PASSED |
| **Load Test** | **100** | 3m 43s | **10,844** | **48.62 req/s** | **0.00%** | **100.00%** | **596.77 ms** | **926.07 ms** | ✅ **PASSED (SLA Met)** |
| **Spike Test** | **250** | 1m 50s | **6,954** | **62.87 req/s** | **0.00%** | **100.00%** | 3,390 ms | 3,520 ms | ⚠️ Absorbed (0 Errors) |
| **Stress Test** | **300** | 3m 10s | **11,032** | **57.98 req/s** | **0.00%** | **100.00%** | 4,310 ms | 4,950 ms | 🛡️ Breaking Point Saturation |

---

## 🔬 Detailed Breakdown per Scenario

### 1. Smoke Test (`tests/K6/smoke.js`)
- **Objective:** Verify endpoint functionality and authentication for test accounts.
- **Results:**
  - `http_req_duration`: avg=28.87ms, med=26.27ms, p(95)=46.28ms, p(99)=86.35ms
  - `http_req_failed`: 0.00% (0 / 144)
  - `checks`: 100.00% (216 / 216)
  - Status: All endpoints responded correctly without regression.

### 2. Peak Load Test — 100 Concurrent Users (`tests/K6/load.js`)
- **Objective:** Simulate 100 students arriving simultaneously at the start of a live lecture.
- **Profile:**
  - Stage 1: Ramp-up to 50 VUs (30s)
  - Stage 2: Ramp-up to 100 VUs peak (30s)
  - Stage 3: Hold 100 VUs steady-state for 2 minutes
  - Stage 4: Ramp-down to 0 (20s)
- **Results:**
  - **Total Requests:** 10,844
  - **Sustained Throughput:** 48.62 requests/sec
  - **Error Rate:** `0.00%` (0 failed requests out of 10,844)
  - **SLA Threshold `http_req_duration p(95) < 800ms`:** **596.77 ms** (Well under threshold)
  - **SLA Threshold `http_req_duration p(99) < 2000ms`:** **926.07 ms** (Well under threshold)
  - **SLA Threshold `checks > 99%`:** **100.00%** (16,266 / 16,266 passed)

### 3. Spike Test — 250 Concurrent Users (`tests/K6/spike.js`)
- **Objective:** Simulate a sudden 5× traffic surge when attendance code is broadcast.
- **Profile:** Jump from 50 to 250 VUs in 10s, maintain for 45s, then recover.
- **Results:**
  - Total requests: 6,954
  - Error rate: **0.00%** (Zero 500/502/504 errors, zero dropped connections)
  - Checks: 100.00% (13,908 checks)
  - Socket queue latency: p(95) increased to 3.39s due to worker process concurrency saturation, but recovered immediately when traffic normalized.

### 4. Stress Test — Step-Ladder to Breaking Point (`tests/K6/stress.js`)
- **Objective:** Step up load (50 → 100 → 150 → 200 → 250 → 300 VUs) to discover saturation limits.
- **Results:**
  - Total requests: 11,032
  - Maximum throughput reached: ~63 requests/sec
  - Failure rate: **0.00%** across all 11,032 requests
  - **Saturation Point:** Latency begins exceeding the 800ms SLA at **~120-130 concurrent VUs** on the local single-node test environment.

---

## 🏆 Official Capacity Certification
- **Certified Concurrent User Capacity:** **100 Concurrent Users** (100% Green on all SLAs: `p(95)=596ms < 800ms`, `p(99)=926ms < 2s`, `errors=0%`).
- **Maximum Safe Throughput:** **50 requests/second** sustained.
