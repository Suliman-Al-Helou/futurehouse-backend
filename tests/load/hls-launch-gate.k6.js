import http from "k6/http";
import { check, sleep } from "k6";

/**
 * Launch Gate HLS Load Test (Ticket 07 / ADR 0024)
 *
 * Usage:
 *   k6 run \
 *     -e API_BASE_URL="https://api.example.com" \
 *     -e LESSON_ID="12" \
 *     -e STUDENT_TOKEN="your_sanctum_bearer_token" \
 *     tests/load/hls-launch-gate.k6.js
 */
export const options = {
  stages: [
    { duration: "15s", target: 20 },
    { duration: "30s", target: 50 },
    { duration: "15s", target: 0 },
  ],
  thresholds: {
    checks: ["rate>0.99"],
    "http_req_duration{endpoint:lesson_api}": ["p(95)<500"],
    "http_req_duration{endpoint:cdn_m3u8}": ["p(95)<300"],
    "http_req_failed{expected_response:true}": ["rate<0.01"],
  },
};

const API_BASE_URL = __ENV.API_BASE_URL || "http://127.0.0.1:8000";
const LESSON_ID = __ENV.LESSON_ID || "1";
const STUDENT_TOKEN = __ENV.STUDENT_TOKEN || "";

export default function () {
  // 1. Authorized lesson playback request (tests DB watchLesson gate + token signing)
  const apiRes = http.get(`${API_BASE_URL}/api/lessons/${LESSON_ID}`, {
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${STUDENT_TOKEN}`,
    },
    tags: { endpoint: "lesson_api" },
  });

  const apiOk = check(apiRes, {
    "lesson API status 200": (r) => r.status === 200,
    "playback provider is hls": (r) => r.status === 200 && r.body !== null && r.json("playback.provider") === "hls",
    "signed url includes token_path": (r) =>
      r.status === 200 && r.body !== null && String(r.json("playback.url") || "").includes("token_path="),
    "no shared cache header": (r) => {
      if (r.status !== 200) return false;
      const cc = String(r.headers["Cache-Control"] || "").toLowerCase();
      return !cc.includes("public");
    },
  });

  if (apiOk) {
    const signedUrl = apiRes.json("playback.url");

    // 2. Fetch signed HLS manifest directly from Bunny CDN Edge
    const cdnRes = http.get(signedUrl, {
      tags: { endpoint: "cdn_m3u8" },
    });
    check(cdnRes, {
      "signed m3u8 returns 200 from CDN": (r) => r.status === 200,
      "m3u8 body starts with #EXTM3U": (r) => String(r.body || "").trim().startsWith("#EXTM3U"),
    });

    // 3. Anti-leech probe: strip query string and confirm CDN returns 403 Forbidden
    const unsignedUrl = String(signedUrl).split("?")[0];
    const unsignedRes = http.get(unsignedUrl, {
      tags: { endpoint: "cdn_unsigned_probe" },
      responseCallback: http.expectedStatuses(403),
    });
    check(unsignedRes, {
      "unsigned m3u8 returns 403 from CDN": (r) => r.status === 403,
    });
  }

  sleep(1);
}
