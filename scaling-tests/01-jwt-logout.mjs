// اختبار الـ "تركاية": logout على نسخة، ثم استخدام نفس التوكن على نسخة أخرى.
// التشغيل:  node 01-jwt-logout.mjs
// يحتاج المنافذ التجريبية 8001/8002/8003 (موجودة بملف شهد).
//
//   وضع "قبل" (docker-compose.before.yml): المتوقع بعد الـ logout على app2 => 200  (ثغرة)
//   وضع "بعد" (docker-compose.after.yml) : المتوقع => 401 (سليم)
import { call, registerUser } from './_helpers.mjs';

const A = process.env.A || 'http://localhost:8001'; // نسخة الـ logout
const B = process.env.B || 'http://localhost:8002'; // النسخة التي ستستقبل التوكن الملغى (app2 هي المكسورة بوضع قبل)

const { email, token } = await registerUser(A, 'jwt');
console.log(`مستخدم تجريبي: ${email}`);

let r = await call('GET', `${A}/api/v1/me`, { token });
console.log(`1) /me على A قبل الـ logout        -> ${r.status}  (instance=${r.instance})   متوقع 200`);

r = await call('GET', `${B}/api/v1/me`, { token });
console.log(`2) /me على B قبل الـ logout        -> ${r.status}  (instance=${r.instance})   متوقع 200`);

r = await call('POST', `${A}/api/v1/auth/logout`, { token });
console.log(`3) logout على A                    -> ${r.status}  (instance=${r.instance})   متوقع 200`);

r = await call('GET', `${A}/api/v1/me`, { token });
console.log(`4) /me على A بعد الـ logout        -> ${r.status}  (instance=${r.instance})   متوقع 401`);

r = await call('GET', `${B}/api/v1/me`, { token });
console.log(`5) /me على B بعد الـ logout        -> ${r.status}  (instance=${r.instance})   <== الاختبار الحقيقي`);

console.log('\nالنتيجة:', r.status === 401
  ? '✅ سليم: التوكن الملغى مرفوض على كل النسخ (الـ blacklist مشترك بـ Redis)'
  : '❌ ثغرة: التوكن الملغى ما زال مقبولاً على نسخة أخرى (الـ blacklist محلي)');
