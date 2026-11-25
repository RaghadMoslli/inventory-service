// اختبار الحمل: 500 طلب "إنشاء طلب" عبر Nginx (localhost:8000) وتوزيعها على النسخ.
// التشغيل (k6):
//   سيناريو 1 (مخزون كافٍ):   k6 run -e RUN=s1 -e PRODUCT_ID=1 scaling-tests/04-load-500.js
//   سيناريو 2 (مخزون 100):    k6 run -e RUN=s2 -e PRODUCT_ID=1 scaling-tests/04-load-500.js   (بعد ضبط المخزون على 100)
// RUN = اسم الجولة، يدخل بإيميلات المستخدمين حتى نفصل نتائج كل جولة بـ verify.sql
// قبل التشغيل: اضبطي المخزون (انظري الخطوة بالـ md) وبدّلي الإيميل لـ Mailpit (docker-compose.loadtest.yml).
import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const API = `${BASE}/api/v1`;
const PRODUCT_ID = Number(__ENV.PRODUCT_ID || 1);
const USERS = Number(__ENV.USERS || 50);
const RUN = __ENV.RUN || 'run1';
const TOTAL = Number(__ENV.TOTAL || 500);
const VUS = Number(__ENV.VUS || 50);

// عدّاد لكل نسخة + حالات الرد
const inst = { app1: new Counter('reqs_app1'), app2: new Counter('reqs_app2'), app3: new Counter('reqs_app3'), other: new Counter('reqs_other') };
const created = new Counter('orders_created_201');
const stockRejected = new Counter('orders_rejected_409_stock');
const unavailable = new Counter('orders_503_unavailable');
const otherErr = new Counter('orders_other_errors');

export const options = {
  scenarios: {
    burst: { executor: 'shared-iterations', vus: VUS, iterations: TOTAL, maxDuration: '5m' },
  },
  thresholds: { http_req_failed: [{ threshold: 'rate<1', abortOnFail: false }] },
};

// ننشئ مستخدمين مختلفين (register غير محدود بالـ throttle) ونأخذ توكناتهم
export function setup() {
  const tokens = [];
  const stamp = Date.now();
  for (let i = 0; i < USERS; i++) {
    const res = http.post(`${API}/auth/register`,
      JSON.stringify({ name: `Load ${i}`, email: `load-${RUN}-${stamp}-${i}@loadtest.local`, password: 'password123' }),
      { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } });
    if (res.status !== 201) throw new Error(`register failed ${res.status}: ${res.body}`);
    tokens.push(res.json('access_token'));
  }
  return { tokens };
}

export default function (data) {
  const token = data.tokens[(__VU - 1) % data.tokens.length];
  const res = http.post(`${API}/orders`,
    JSON.stringify({ items: [{ product_id: PRODUCT_ID, quantity: 1 }] }),
    { headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` } });

  const who = res.headers['X-Instance'];
  (inst[who] || inst.other).add(1);

  if (res.status === 201) created.add(1);
  else if (res.status === 409) stockRejected.add(1);
  else if (res.status === 503) unavailable.add(1);
  else otherErr.add(1);

  check(res, { 'status is 201 or 409': (r) => r.status === 201 || r.status === 409 });
}

export function handleSummary(data) {
  const v = (n) => (data.metrics[n] && data.metrics[n].values.count) || 0;
  const lines = [
    '',
    '================ نتيجة اختبار الحمل ================',
    `إجمالي الطلبات المرسلة : ${v('http_reqs') - USERS /* طلبات register بـ setup */}`,
    `توزيع النسخ            : app1=${v('reqs_app1')}  app2=${v('reqs_app2')}  app3=${v('reqs_app3')}  other=${v('reqs_other')}`,
    `201 (طلب أُنشئ)        : ${v('orders_created_201')}`,
    `409 (مخزون غير كافٍ)   : ${v('orders_rejected_409_stock')}`,
    `503 (مخزون غير متاح)   : ${v('orders_503_unavailable')}`,
    `أخطاء أخرى             : ${v('orders_other_errors')}`,
    '====================================================',
    `الجولة (RUN) = ${RUN}  ->  استخدميها مع verify.sql وقارني الأرقام (انظري الـ md).`,
    '',
  ].join('\n');
  return { stdout: lines, 'load-result.json': JSON.stringify(data, null, 2) };
}
