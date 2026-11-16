/**
 * load.js — Load Test (Phase 5)
 *
 * Simulates 100 concurrent students arriving for a live session.
 * Ramp up → hold peak 5 min → ramp down.
 *
 * Run: /snap/bin/k6 run tests/K6/load.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, authHeaders, STANDARD_THRESHOLDS, LESSON_IDS, MEETING_IDS } from './helpers.js';

export const options = {
  stages: [
    { duration: '30s', target: 50 },   // ramp to 50 VUs
    { duration: '30s', target: 100 },  // ramp to 100 VUs peak
    { duration: '2m',  target: 100 },  // hold 100 concurrent users
    { duration: '20s', target: 0 },    // cool down
  ],
  thresholds: STANDARD_THRESHOLDS,
};

export default function () {
  const params = authHeaders(__VU - 1);
  const tolerant = { ...params, responseCallback: http.expectedStatuses(200, 403) };

  // ── Simulates a student entering a live session ──

  // 1. Browse course catalog (cache-hit path)
  const coursesRes = http.get(`${BASE_URL}/courses`, params);
  check(coursesRes, {
    'courses: 200': (r) => r.status === 200,
    'courses: has data': (r) => JSON.parse(r.body).data !== undefined,
  });

  sleep(1);

  // 2. Open student dashboard (aggregation queries)
  const dashRes = http.get(`${BASE_URL}/dashboard/stats`, params);
  check(dashRes, {
    'dashboard: 200': (r) => r.status === 200,
    'dashboard: has stats': (r) => JSON.parse(r.body).stats !== undefined,
  });

  sleep(1);

  // 3. Open a lesson (policy + progress tracking)
  const lessonId = LESSON_IDS[Math.floor(Math.random() * LESSON_IDS.length)];
  const lessonRes = http.get(`${BASE_URL}/lessons/${lessonId}`, tolerant);
  check(lessonRes, {
    'lesson: 200 or 403': (r) => r.status === 200 || r.status === 403,
  });

  sleep(1);

  // 4. Register attendance (concurrent atomic writes)
  const meetingId = MEETING_IDS[Math.floor(Math.random() * MEETING_IDS.length)];
  const attendRes = http.post(`${BASE_URL}/zoom-meetings/${meetingId}/attend`, null, tolerant);
  check(attendRes, {
    'attend: 200 or 403': (r) => r.status === 200 || r.status === 403,
  });

  sleep(2);
}
