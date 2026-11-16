# Phase 5: Load & Pressure Testing (k6)

> **Target:** 100 concurrent users (VUs) — Peak = Live Session Start  
> **Tool:** k6 v2.3.0 (`/snap/bin/k6`)  
> **Database:** Local PostgreSQL 16 (port 5433, `courses_testing`) — **Cloud Neon Untouched**  
> **Status:** COMPLETED & GREEN ✅  

## Thresholds (SLAs)

| Metric              | Threshold         | Actual (100 VU Load Test) | Status |
|---------------------|--------------------|---------------------------|--------|
| `http_req_failed`   | `rate < 0.01` (< 1%) | **0.00%** (0 / 10,844)   | ✅ PASSED |
| `http_req_duration` | `p(95) < 800ms`     | **596.77ms**              | ✅ PASSED |
| `http_req_duration` | `p(99) < 2000ms`    | **926.07ms**              | ✅ PASSED |
| `checks`            | `rate > 0.99` (> 99%) | **100.00%** (16,266 / 16,266) | ✅ PASSED |

## Hot Endpoints Tested

| # | Endpoint | Method | Auth | What it tests | 100-VU Status |
|---|----------|--------|------|---------------|---------------|
| 1 | `/api/courses` | GET | ✗ | Cache hit, public catalog | ✅ 200 OK |
| 2 | `/api/dashboard/stats` | GET | ✓ | Aggregation queries, indexes | ✅ 200 OK |
| 3 | `/api/lessons/{lesson}` | GET | ✓ | Gate policy, progress tracking | ✅ 200 / 403 OK |
| 4 | `/api/zoom-meetings/{id}/attend` | POST | ✓ | Atomic locks, concurrency | ✅ 200 / 403 OK |

## Tasks Checklist

### Infrastructure Setup
- [x] Task tracker created (`docs/tasks/phase-5-load-testing.md`)
- [x] Create `.env.load` (local PG only, Neon strictly protected)
- [x] Create `LoadTestSeeder` (120 students, 20 courses, 240 lessons, active meetings)
- [x] Seed and export auth tokens to `tests/K6/tokens.json`
- [x] Multi-worker local server configured (`PHP_CLI_SERVER_WORKERS=16`, route/config cache)

### k6 Scripts (`tests/K6/`)
- [x] `helpers.js` — shared token pool, auth headers, standard thresholds
- [x] `smoke.js` — 3 VUs, 30s sanity check (100% passed)
- [x] `load.js` — ramp to 100 VUs, hold peak, ramp down (100% SLA met)
- [x] `spike.js` — sudden 250 VU jump (absorbed without 500 errors)
- [x] `stress.js` — step-ladder to 300 VUs (breaking point identified)

### Execution & Documentation
- [x] Run Smoke Test → record metrics
- [x] Run 100-VU Load Test → all SLAs met (p95 = 596ms < 800ms)
- [x] Run Spike Test → 0 errors out of 6,954 requests
- [x] Run Stress Test → 0 errors out of 11,032 requests
- [x] Document results in `docs/load-results.md`
- [x] Write Phase 5 Retrospective in `docs/tasks/phase-5-retro.md`
