import http from 'k6/http';
import { check } from 'k6';
import { API, DEFAULT_HEADERS, recordAppHeaders } from './common.js';

export const options = {
  vus: Number(__ENV.CACHE_VUS || 1),
  iterations: Number(__ENV.CACHE_ITERATIONS || 10),
  summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
  thresholds: { checks: ['rate>0.99'], http_req_failed: ['rate<0.01'] },
};

export default function () {
  const url = `${API}/products?per_page=${Number(__ENV.PER_PAGE || 5)}`;
  const first = http.get(url, { headers: DEFAULT_HEADERS, tags: { name: 'GET /products cache first' } });
  recordAppHeaders(first);
  check(first, { 'first products request returns 200': r => r.status === 200 });

  const second = http.get(url, { headers: DEFAULT_HEADERS, tags: { name: 'GET /products cache repeat' } });
  recordAppHeaders(second);
  check(second, { 'repeat products request returns 200': r => r.status === 200 });
}
