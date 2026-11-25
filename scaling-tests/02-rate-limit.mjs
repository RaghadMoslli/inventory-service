import { BASE, request, uniqEmail, printResult } from './_helpers.mjs';

console.log('=== Rate limit behind Nginx (6 different client IPs) ===');
console.log(`BASE=${BASE}`);

const password = 'secret12345';
const statuses = [];

for (let i = 1; i <= 6; i++) {
  const email = uniqEmail(`rl${i}`);
  const clientIp = `203.0.113.${i}`; // simulate distinct clients behind Nginx

  const reg = await request(`${BASE}/api/v1/auth/register`, {
    method: 'POST',
    body: { name: `RL ${i}`, email, password },
    headers: { 'X-Forwarded-For': clientIp },
  });
  if (reg.status !== 201) {
    printResult(`register user ${i}`, false, `status=${reg.status}`);
    process.exit(1);
  }

  const login = await request(`${BASE}/api/v1/auth/login`, {
    method: 'POST',
    body: { email, password },
    headers: { 'X-Forwarded-For': clientIp },
  });
  statuses.push(login.status);
  printResult(`login user ${i} ip=${clientIp}`, true, `status=${login.status}`);
}

const all200 = statuses.every((s) => s === 200);
const sixth429 = statuses[5] === 429 && statuses.slice(0, 5).every((s) => s === 200);

if (all200) {
  console.log('RESULT: fixed — distinct forwarded IPs are rate-limited separately (trustProxies).');
  process.exit(0);
}

if (sixth429) {
  console.log('RESULT: bug present — Laravel still keys the limiter on the proxy IP.');
  process.exit(2);
}

console.log(`RESULT: unexpected statuses=${JSON.stringify(statuses)}`);
process.exit(1);
