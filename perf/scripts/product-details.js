import http from 'k6/http';
import { check } from 'k6';
import { API, DEFAULT_HEADERS, standardOptions, recordAppHeaders } from './common.js';

export const options = standardOptions;

export default function () {
  const ids = (__ENV.PRODUCT_IDS || '1,2,3,4,5,6,7,8,9,10').split(',');
  const id = ids[(__VU + __ITER - 1) % ids.length].trim();
  const res = http.get(`${API}/products/${encodeURIComponent(id)}`, {
    headers: DEFAULT_HEADERS,
    tags: { name: 'GET /products/{id}' },
  });
  recordAppHeaders(res);
  let body = null;
  try { body = res.json(); } catch (_) {}
  check(res, {
    'product details returns 200': r => r.status === 200,
    'product details has product payload': () => !!body && (body.id !== undefined || body.data?.id !== undefined),
  });
}
