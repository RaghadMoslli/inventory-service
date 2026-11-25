// اختبار الـ Rate Limit على login خلف Nginx.
// الفكرة: 6 مستخدمين مختلفين (6 عناوين IP مختلفة) يسجلون دخول خلال دقيقة، من خلال Nginx (8000).
// الحد الحالي: 5 محاولات/دقيقة لكل IP.
//   قبل trustProxies: كل الطلبات تبدو من IP الـ Nginx => السادس يُرفض 429 (خطأ!)
//   بعد trustProxies: لكل مستخدم IP خاص => كلهم 200
// مهم: شغّليه بالوضع العادي (docker compose up -d) وليس بـ before.yml.
// مهم: انتظري 60 ثانية بين كل تشغيلين (العداد يُصفّر كل دقيقة).
// ملاحظة: نحاكي IPs مختلفة بالترويسة X-Forwarded-For لأنك تجربين من جهاز واحد.
import { call, registerUser } from './_helpers.mjs';

const BASE = process.env.BASE || 'http://localhost:8000';
const N = 6;

const users = [];
for (let i = 0; i < N; i++) users.push(await registerUser(BASE, 'rl'));

const codes = [];
for (let i = 0; i < N; i++) {
  const r = await call('POST', `${BASE}/api/v1/auth/login`, {
    body: { email: users[i].email, password: 'password123' },
    headers: { 'X-Forwarded-For': `203.0.113.${10 + i}` },
  });
  codes.push(r.status);
  console.log(`مستخدم ${i + 1} (IP وهمي 203.0.113.${10 + i}) -> HTTP ${r.status} (instance=${r.instance})`);
}

const blocked = codes.filter((c) => c === 429).length;
console.log('\nالنتيجة:', blocked === 0
  ? '✅ سليم: كل مستخدم له عداد مستقل (الـ IP الحقيقي يصل لـ Laravel)'
  : `❌ مشكلة: ${blocked} مستخدم/مستخدمين رُفضوا بـ 429 رغم أنهم مستخدمون مختلفون (كل الطلبات تظهر من IP الـ Nginx)`);
