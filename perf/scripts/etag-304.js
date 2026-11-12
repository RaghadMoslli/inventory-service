import http from 'k6/http';
import { check } from 'k6';
import { API, DEFAULT_HEADERS } from './common.js';

export const options = {
  vus: 1,
  iterations: Number(__ENV.ETAG_ITERATIONS || 10),
  summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
  thresholds: { checks: ['rate>0.99'], http_req_failed: ['rate<0.01'] },
};

export default function () {
  const url = `${API}/products?per_page=5`;
  const first = http.get(url, { headers: DEFAULT_HEADERS, tags: { name: 'GET /products ETag initial' } });
  const etag = first.headers.ETag || first.headers.etag;

  check(first, {
    'initial ETag request returns 200': r => r.status === 200,
    'initial response contains ETag': () => !!etag,
  });

  if (!etag) return;

  const second = http.get(url, {
    headers: { ...DEFAULT_HEADERS, 'If-None-Match': etag },
    tags: { name: 'GET /products If-None-Match' },
  });

  check(second, {
    'conditional request returns 304': r => r.status === 304,
  });
}
