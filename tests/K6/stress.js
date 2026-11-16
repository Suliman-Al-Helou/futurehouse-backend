/**
 * stress.js — Stress Test / Breaking Point (Phase 5)
 *
 * Gradually ramps VUs until the system breaks to find max capacity.
 * Steps: 50 → 100 → 200 → 300 → 400 → 500+ until thresholds fail.
 *
 * Run: /snap/bin/k6 run tests/K6/stress.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, authHeaders, LESSON_IDS, MEETING_IDS } from './helpers.js';

export const options = {
  stages: [
    { duration: '20s', target: 50 },   // baseline
    { duration: '30s', target: 100 },  // target capacity (100 VUs)
    { duration: '30s', target: 150 },  // 1.5× capacity
    { duration: '30s', target: 200 },  // 2× capacity
    { duration: '30s', target: 250 },  // 2.5× capacity
    { duration: '30s', target: 300 },  // 3× capacity
    { duration: '15s', target: 0 },    // cool down
  ],
  thresholds: {
    http_req_duration: ['p(95)<8000'],  // observation only
  },
};

export default function () {
  const params = authHeaders(__VU - 1);
  const tolerant = { ...params, responseCallback: http.expectedStatuses(200, 403) };

  // Full user flow: catalog → dashboard → lesson → attendance
  const coursesRes = http.get(`${BASE_URL}/courses`, params);
  check(coursesRes, {
    'courses: not 500': (r) => r.status !== 500,
  });

  sleep(0.5);

  const dashRes = http.get(`${BASE_URL}/dashboard/stats`, params);
  check(dashRes, {
    'dashboard: not 500': (r) => r.status !== 500,
  });

  sleep(0.5);

  const lessonId = LESSON_IDS[Math.floor(Math.random() * LESSON_IDS.length)];
  const lessonRes = http.get(`${BASE_URL}/lessons/${lessonId}`, tolerant);
  check(lessonRes, {
    'lesson: not 500': (r) => r.status !== 500,
  });

  sleep(0.5);

  const meetingId = MEETING_IDS[Math.floor(Math.random() * MEETING_IDS.length)];
  const attendRes = http.post(`${BASE_URL}/zoom-meetings/${meetingId}/attend`, null, tolerant);
  check(attendRes, {
    'attend: not 500': (r) => r.status !== 500,
  });

  sleep(1);
}
