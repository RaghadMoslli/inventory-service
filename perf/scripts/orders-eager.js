import http from 'k6/http';
import { check } from 'k6';
import { API, authHeaders, login, recordAppHeaders, requireEnv } from './common.js';

export const options = {
  vus: Number(__ENV.EAGER_VUS || 1),
  iterations: Number(__ENV.EAGER_ITERATIONS || 20),
  summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
  thresholds: {
    checks: ['rate>0.99'],
    http_req_failed: ['rate<0.01'],
  },
};

export function setup() {
  const email = __ENV.ADMIN_EMAIL || requireEnv('USER_EMAIL');
  const password = __ENV.ADMIN_PASSWORD || requireEnv('USER_PASSWORD');
  return { token: login(email, password) };
}

export default function (data) {
  const perPage = Number(__ENV.PER_PAGE || 10);
  const res = http.get(`${API}/orders?page=1&per_page=${perPage}`, {
    headers: authHeaders(data.token),
    tags: { name: 'GET /orders eager-demo' },
  });
  recordAppHeaders(res);

  let body = null;
  try { body = res.json(); } catch (_) {}
  const rows = body && body.data && Array.isArray(body.data.data) ? body.data.data : [];

  check(res, {
    'orders list returns 200': r => r.status === 200,
    'orders response has rows': () => rows.length > 0,
    'orders rows include items array': () => rows.length > 0 && Array.isArray(rows[0].items),
  });
}
