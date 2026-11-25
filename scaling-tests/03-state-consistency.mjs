// اختبار حالة وضع الأداء (perf-mode) عبر النسخ الثلاث.
// الخطوات:
//   1) شغّلي:  docker compose exec app1 php artisan perf:mode baseline
//   2) ثم:     node 03-state-consistency.mjs
//   المتوقع بعد الإصلاح: الثلاث نسخ تُرجع X-Perf-Mode: baseline
//   قبل الإصلاح (docker-compose.before.yml): app1 => baseline و app2 => optimized (غير متسقة)
// لا تنسي بالنهاية:  docker compose exec app1 php artisan perf:mode optimized
import { call } from './_helpers.mjs';

const PORTS = [8001, 8002, 8003];
const seen = new Set();
for (const p of PORTS) {
  const r = await call('GET', `http://localhost:${p}/api/v1/products?per_page=1`);
  console.log(`port ${p} -> HTTP ${r.status}  instance=${r.instance}  X-Perf-Mode=${r.perfMode}`);
  seen.add(r.perfMode);
}
console.log('\nالنتيجة:', seen.size === 1
  ? `✅ متسقة: كل النسخ بوضع ${[...seen][0]}`
  : `❌ غير متسقة: النسخ بأوضاع مختلفة ${[...seen].join(' / ')}`);
