import http from 'k6/http';
import { check } from 'k6';
import { API, authHeaders, standardOptions, login, recordAppHeaders, requireEnv } from './common.js';

export const options = standardOptions;

export function setup() {
  const token = login(requireEnv('USER_EMAIL'), requireEnv('USER_PASSWORD'));
  return { token };
}

export default function (data) {
  const perPage = Number(__ENV.PER_PAGE || 10);
  const page = (__VU + __ITER) % 100 + 1;
  const res = http.get(`${API}/orders?page=${page}&per_page=${perPage}`, {
    headers: authHeaders(data.token),
    tags: { name: 'GET /orders' },
  });
  recordAppHeaders(res);
  let body = null;
  try { body = res.json(); } catch (_) {}
  check(res, {
    'orders list returns 200': r => r.status === 200,
    'orders response has data object': () => !!body && !!body.data,
    'orders pagination has inner data array': () => !!body && !!body.data && Array.isArray(body.data.data),
  });
}
