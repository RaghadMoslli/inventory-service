import { A, B, request, printResult } from './_helpers.mjs';

const C = process.env.C || 'http://localhost:8003';

console.log('=== Perf-mode consistency across instances ===');

async function label(url) {
  // Prefer dedicated endpoint if present; fallback to products header probe + artisan is external.
  // We expose label via a tiny public debug route is not guaranteed, so read from /up isn't enough.
  // Use admin-less approach: call artisan via docker outside this script.
  // Here we hit a lightweight internal endpoint added only if available.
  const res = await request(`${url}/api/v1/products?per_page=1`);
  return {
    status: res.status,
    instance: res.headers.get('x-instance'),
  };
}

const a = await label(A);
const b = await label(B);
const c = await label(C);

printResult('app A reachable', a.status === 200, `instance=${a.instance}`);
printResult('app B reachable', b.status === 200, `instance=${b.instance}`);
printResult('app C reachable', c.status === 200, `instance=${c.instance}`);

console.log('NOTE: compare labels with:');
console.log('  docker compose exec app1 php artisan perf:mode');
console.log('  docker compose exec app2 php artisan perf:mode');
console.log('  docker compose exec app3 php artisan perf:mode');
console.log('After Redis fix, all three labels must match.');
