import http from 'k6/http';
import { check } from 'k6';
import { API, authHeaders, login, requireEnv } from './common.js';

// Functional test for read-your-writes. Run with optimized mode and an admin account.
export const options = {
  vus: 1,
  iterations: 1,
  summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
};

export function setup() {
  return { token: login(requireEnv('ADMIN_EMAIL'), requireEnv('ADMIN_PASSWORD')) };
}

export default function (data) {
  const id = requireEnv('MUTATION_PRODUCT_ID');
  const headers = { ...authHeaders(data.token), 'Content-Type': 'application/json' };
  const before = http.get(`${API}/products/${id}`, { headers: authHeaders(data.token) });
  let body = null;
  try { body = before.json(); } catch (_) {}
  const original = body?.name ?? body?.data?.name;
  if (before.status !== 200 || !original) throw new Error('Could not read product before update.');

  const updatedName = `K6-READ-YOUR-WRITES-${Date.now()}`;
  const patch = http.patch(`${API}/products/${id}`, JSON.stringify({ name: updatedName }), { headers });
  check(patch, { 'product PATCH succeeds': r => r.status >= 200 && r.status < 300 });
  if (patch.status < 200 || patch.status >= 300) throw new Error(`PATCH failed with HTTP ${patch.status}`);

  const read = http.get(`${API}/products/${id}`, { headers: authHeaders(data.token) });
  let readBody = null;
  try { readBody = read.json(); } catch (_) {}
  const actual = readBody?.name ?? readBody?.data?.name;
  check(read, {
    'read after write returns 200': r => r.status === 200,
    'read-your-writes returns updated name immediately': () => actual === updatedName,
  });

  const restore = http.patch(`${API}/products/${id}`, JSON.stringify({ name: original }), { headers });
  check(restore, { 'original product name restored': r => r.status >= 200 && r.status < 300 });
}
