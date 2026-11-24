import http from 'k6/http';
import { check } from 'k6';
import { API, DEFAULT_HEADERS, standardOptions, recordAppHeaders } from './common.js';

export const options = standardOptions;
const categories = (__ENV.CATEGORIES || 'Electronics,Automotive,Home,Grocery,Beauty,Garden').split(',');

export default function () {
  const category = categories[(__VU + __ITER - 1) % categories.length].trim();
  const perPage = Number(__ENV.PER_PAGE || 10);
  const res = http.get(`${API}/products?category=${encodeURIComponent(category)}&per_page=${perPage}`, {
    headers: DEFAULT_HEADERS,
    tags: { name: 'GET /products?category' },
  });
  recordAppHeaders(res);
  let body = null;
  try { body = res.json(); } catch (_) {}
  check(res, {
    'filtered products returns 200': r => r.status === 200,
    'filtered products has data array': () => !!body && Array.isArray(body.data),
  });
}
