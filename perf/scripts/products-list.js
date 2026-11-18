import http from 'k6/http';
import { check } from 'k6';
import { API, DEFAULT_HEADERS, standardOptions, recordAppHeaders } from './common.js';

export const options = standardOptions;

export default function () {
  const page = (__VU + __ITER) % 500 + 1;
  const perPage = Number(__ENV.PER_PAGE || 10);
  const res = http.get(`${API}/products?page=${page}&per_page=${perPage}`, {
    headers: DEFAULT_HEADERS,
    tags: { name: 'GET /products' },
  });
  recordAppHeaders(res);
  let body = null;
  try { body = res.json(); } catch (_) {}
  check(res, {
    'products list returns 200': r => r.status === 200,
    'products list has data array': () => !!body && Array.isArray(body.data),
    'products pagination metadata exists': () => !!body && body.current_page !== undefined && body.total !== undefined,
  });
}
