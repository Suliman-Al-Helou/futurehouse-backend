/**
 * spike.js — Spike Test (Phase 5)
 *
 * Simulates the "attendance code announced" moment:
 * Normal load → sudden 5x jump → sustain briefly → drop back.
 *
 * Run: /snap/bin/k6 run tests/K6/spike.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, authHeaders, LESSON_IDS, MEETING_IDS } from './helpers.js';

export const options = {
  stages: [
    { duration: '20s', target: 50 },   // baseline normal load
    { duration: '10s', target: 250 },  // SPIKE! (5× surge when attendance announced)
    { duration: '45s', target: 250 },  // hold the spike
    { duration: '20s', target: 50 },   // recover back to normal
    { duration: '15s', target: 0 },    // cool down
  ],
  thresholds: {
    http_req_failed: ['rate<0.05'],            // allow up to 5% during spike
    http_req_duration: ['p(95)<2500', 'p(99)<5000'], // relaxed during surge
    checks: ['rate>0.95'],
  },
};

export default function () {
  const params = authHeaders(__VU - 1);
  const tolerant = { ...params, responseCallback: http.expectedStatuses(200, 403) };

  // During a spike, every student rushes to register attendance
  const meetingId = MEETING_IDS[Math.floor(Math.random() * MEETING_IDS.length)];
  const attendRes = http.post(`${BASE_URL}/zoom-meetings/${meetingId}/attend`, null, tolerant);
  check(attendRes, {
    'attend: 200 or 403': (r) => r.status === 200 || r.status === 403,
    'attend: not 500': (r) => r.status !== 500,
  });

  sleep(0.3);

  // Some also check dashboard
  const dashRes = http.get(`${BASE_URL}/dashboard/stats`, params);
  check(dashRes, {
    'dashboard: 200': (r) => r.status === 200,
    'dashboard: not 500': (r) => r.status !== 500,
  });

  sleep(0.3);

  // And browse courses
  const coursesRes = http.get(`${BASE_URL}/courses`, params);
  check(coursesRes, {
    'courses: 200': (r) => r.status === 200,
    'courses: not 500': (r) => r.status !== 500,
  });

  sleep(0.5);
}
