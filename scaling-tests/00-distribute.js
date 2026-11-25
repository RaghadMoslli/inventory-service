import http from 'k6/http';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE || 'http://localhost:8000';
const TOTAL = Number(__ENV.TOTAL || 30);

const instApp1 = new Counter('inst_app1');
const instApp2 = new Counter('inst_app2');
const instApp3 = new Counter('inst_app3');
const instUnknown = new Counter('inst_unknown');

export const options = {
  scenarios: {
    distribute: {
      executor: 'shared-iterations',
      vus: 1,
      iterations: TOTAL,
      maxDuration: '2m',
    },
  },
};

export default function () {
  const res = http.get(`${BASE}/api/v1/products?per_page=1`, {
    headers: { Accept: 'application/json' },
  });

  const inst = String(res.headers['X-Instance'] || res.headers['x-instance'] || 'unknown');
  if (inst === 'app1') instApp1.add(1);
  else if (inst === 'app2') instApp2.add(1);
  else if (inst === 'app3') instApp3.add(1);
  else instUnknown.add(1);
}

export function handleSummary(data) {
  const count = (name) => data.metrics[name]?.values?.count ?? 0;
  const lines = [
    `TOTAL=${TOTAL}`,
    '==== X-Instance distribution ====',
    `app1=${count('inst_app1')}`,
    `app2=${count('inst_app2')}`,
    `app3=${count('inst_app3')}`,
    `unknown=${count('inst_unknown')}`,
  ];
  return { stdout: lines.join('\n') + '\n' };
}
