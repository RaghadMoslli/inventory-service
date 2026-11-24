import http from 'k6/http';
import { check } from 'k6';
import { API, authHeaders, login, requireEnv } from './common.js';

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
  let beforeBody = null;
  try { beforeBody = before.json(); } catch (_) {}
  const original = beforeBody?.name ?? beforeBody?.data?.name;
  if (before.status !== 200 || !original) throw new Error('Could not read the selected product before mutation.');

  const temporaryName = `K6-CACHE-TEST-${Date.now()}`;
  const update = http.patch(`${API}/products/${id}`, JSON.stringify({ name: temporaryName }), { headers });
  check(update, { 'PATCH product succeeds': r => r.status >= 200 && r.status < 300 });
  if (update.status < 200 || update.status >= 300) throw new Error(`PATCH failed with HTTP ${update.status}`);

  const after = http.get(`${API}/products?per_page=100`);
  let afterBody = null;
  try { afterBody = after.json(); } catch (_) {}
  const rows = Array.isArray(afterBody?.data) ? afterBody.data : [];
  check(after, {
    'updated product appears in products list': r => r.status === 200 && rows.some(p => String(p.id) === String(id) && p.name === temporaryName),
  });

  const restore = http.patch(`${API}/products/${id}`, JSON.stringify({ name: original }), { headers });
  check(restore, { 'original product name restored': r => r.status >= 200 && r.status < 300 });
}
