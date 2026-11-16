/**
 * smoke.js — Smoke Test (Phase 5)
 *
 * Quick sanity check: 1-3 VUs for 30 seconds.
 * Verifies all endpoints respond correctly before heavier tests.
 *
 * Run: /snap/bin/k6 run tests/K6/smoke.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, authHeaders, LESSON_IDS, MEETING_IDS } from './helpers.js';

export const options = {
  vus: 3,
  duration: '30s',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<800', 'p(99)<2000'],
    checks: ['rate>0.99'],
  },
};

export default function () {
  const params = authHeaders(__VU - 1);

  // 1. GET /api/courses (public catalog)
  const coursesRes = http.get(`${BASE_URL}/courses`, params);
  check(coursesRes, {
    'courses: status 200': (r) => r.status === 200,
    'courses: has data array': (r) => JSON.parse(r.body).data !== undefined,
  });

  sleep(0.5);

  // 2. GET /api/dashboard/stats (authenticated, returns {stats, exam_results, pass_rate})
  const dashRes = http.get(`${BASE_URL}/dashboard/stats`, params);
  check(dashRes, {
    'dashboard: status 200': (r) => r.status === 200,
    'dashboard: has stats': (r) => JSON.parse(r.body).stats !== undefined,
  });

  sleep(0.5);

  // 3. GET /api/lessons/{lesson} (authenticated + policy)
  //    403 is valid (student may not be enrolled) — don't count as failure
  const lessonId = LESSON_IDS[Math.floor(Math.random() * LESSON_IDS.length)];
  const lessonRes = http.get(`${BASE_URL}/lessons/${lessonId}`, {
    ...params,
    responseCallback: http.expectedStatuses(200, 403),
  });
  check(lessonRes, {
    'lesson: status 200 or 403': (r) => r.status === 200 || r.status === 403,
  });

  sleep(0.5);

  // 4. POST /api/zoom-meetings/{id}/attend (concurrent atomic writes)
  //    403 is valid (meeting not joinable, or not enrolled)
  const meetingId = MEETING_IDS[Math.floor(Math.random() * MEETING_IDS.length)];
  const attendRes = http.post(`${BASE_URL}/zoom-meetings/${meetingId}/attend`, null, {
    ...params,
    responseCallback: http.expectedStatuses(200, 403),
  });
  check(attendRes, {
    'attend: status 200 or 403': (r) => r.status === 200 || r.status === 403,
  });

  sleep(1);
}
