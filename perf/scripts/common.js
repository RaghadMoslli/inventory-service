import http from 'k6/http';
import { check } from 'k6';
import { Trend, Counter } from 'k6/metrics';

export const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
export const API = `${BASE_URL}/api/v1`;
export const DEFAULT_HEADERS = { Accept: 'application/json' };

export const queryTimeMs = new Trend('app_query_time_ms');
export const queryCount = new Trend('app_query_count');
export const appPerfMode = new Counter('app_perf_mode_header_missing');

export function authHeaders(token) {
  return { ...DEFAULT_HEADERS, Authorization: `Bearer ${token}` };
}

export function recordAppHeaders(res) {
  const qt = res.headers['X-Query-Time-Ms'] || res.headers['x-query-time-ms'];
  const qc = res.headers['X-Query-Count'] || res.headers['x-query-count'];
  if (qt !== undefined) queryTimeMs.add(Number(qt));
  if (qc !== undefined) queryCount.add(Number(qc));
}

export function jsonBody(res) {
  try { return res.json(); } catch (_) { return null; }
}

export function login(email, password) {
  const res = http.post(`${API}/auth/login`,
    JSON.stringify({ email, password }),
    { headers: { ...DEFAULT_HEADERS, 'Content-Type': 'application/json' }, tags: { name: 'POST /auth/login' } }
  );
  const body = jsonBody(res);
  const ok = check(res, {
    'login returns 200': r => r.status === 200,
    'login response contains access_token': () => !!(body && body.access_token),
  });
  if (!ok || !body || !body.access_token) {
    throw new Error(`Login failed (HTTP ${res.status}). Check credentials and API availability.`);
  }
  return body.access_token;
}

export function requireEnv(name) {
  const value = __ENV[name];
  if (!value) throw new Error(`Missing environment variable ${name}. Set it before running k6.`);
  return value;
}

export const standardOptions = {
  stages: [
    { duration: __ENV.RAMP_UP || '30s', target: Number(__ENV.MAX_VUS || 10) },
    { duration: __ENV.STEADY || '1m', target: Number(__ENV.MAX_VUS || 10) },
    { duration: __ENV.RAMP_DOWN || '30s', target: 0 },
  ],
  summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
  thresholds: {
    checks: ['rate>0.99'],
    http_req_failed: ['rate<0.01'],
  },
};

export function reportResponse(res, expectedStatus = 200, label = 'response') {
  recordAppHeaders(res);
  check(res, {
    [`${label}: HTTP ${expectedStatus}`]: r => r.status === expectedStatus,
    [`${label}: response time recorded`]: r => r.timings.duration >= 0,
  });
}
