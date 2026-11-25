import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE || 'http://localhost:8000';
const PRODUCT_ID = Number(__ENV.PRODUCT_ID || 1);
const TOTAL = Number(__ENV.TOTAL || 500);
const VUS = Number(__ENV.VUS || 50);
const RUN = __ENV.RUN || 's1';

const byInstance = new Counter('by_instance');
const status201 = new Counter('status_201');
const status409 = new Counter('status_409');
const statusOther = new Counter('status_other');

export const options = {
  scenarios: {
    create_orders: {
      executor: 'shared-iterations',
      vus: VUS,
      iterations: TOTAL,
      maxDuration: '15m',
    },
  },
  thresholds: {
    checks: ['rate>0.90'],
  },
};

export function setup() {
  const password = 'secret12345';
  const tokens = [];
  const count = Math.min(VUS, 50);

  for (let i = 0; i < count; i++) {
    const email = `load.${RUN}.${Date.now()}.${i}@example.test`;
    const reg = http.post(
      `${BASE}/api/v1/auth/register`,
      JSON.stringify({ name: `Load ${i}`, email, password }),
      { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
    );

    let token = '';
    if (reg.status === 201) {
      token = reg.json('access_token') || '';
    } else {
      const login = http.post(
        `${BASE}/api/v1/auth/login`,
        JSON.stringify({ email, password }),
        { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
      );
      token = login.json('access_token') || '';
    }

    if (token) tokens.push(token);
  }

  if (tokens.length === 0) {
    throw new Error('setup failed: no auth tokens');
  }

  console.log(`setup users=${tokens.length}`);
  return { tokens };
}

export default function (data) {
  const token = data.tokens[(__VU - 1) % data.tokens.length];
  const res = http.post(
    `${BASE}/api/v1/orders`,
    JSON.stringify({ items: [{ product_id: PRODUCT_ID, quantity: 1 }] }),
    {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      tags: { name: 'create_order' },
    },
  );

  const inst = res.headers['X-Instance'] || res.headers['x-instance'] || 'unknown';
  byInstance.add(1, { instance: String(inst) });

  if (res.status === 201) status201.add(1);
  else if (res.status === 409) status409.add(1);
  else statusOther.add(1);

  check(res, {
    'not 5xx': (r) => r.status < 500,
  });

  sleep(0.01);
}

export function handleSummary(data) {
  const lines = [];
  lines.push(`RUN=${RUN} TOTAL=${TOTAL} VUS=${VUS} PRODUCT_ID=${PRODUCT_ID}`);
  lines.push(`http_reqs=${data.metrics.http_reqs?.values?.count ?? 'n/a'}`);
  lines.push(`status_201=${data.metrics.status_201?.values?.count ?? 0}`);
  lines.push(`status_409=${data.metrics.status_409?.values?.count ?? 0}`);
  lines.push(`status_other=${data.metrics.status_other?.values?.count ?? 0}`);
  lines.push(`p95=${data.metrics.http_req_duration?.values['p(95)'] ?? 'n/a'}`);

  // Instance distribution from tagged counters
  for (const [name, metric] of Object.entries(data.metrics)) {
    if (name.startsWith('by_instance{') || name === 'by_instance') {
      lines.push(`${name}=${metric.values?.count ?? 0}`);
    }
  }

  return { stdout: lines.join('\n') + '\n' };
}
