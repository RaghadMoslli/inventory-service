import { A, B, request, uniqEmail, printResult } from './_helpers.mjs';

const password = 'secret12345';
const email = uniqEmail('jwt');

console.log('=== JWT logout across instances ===');
console.log(`register on ${A}`);

const reg = await request(`${A}/api/v1/auth/register`, {
  method: 'POST',
  body: { name: 'JWT Test', email, password },
});

if (reg.status !== 201 || !reg.data?.access_token) {
  printResult('register', false, `status=${reg.status}`);
  process.exit(1);
}

const token = reg.data.access_token;
printResult('register', true, `email=${email}`);

const meA1 = await request(`${A}/api/v1/me`, { token });
const meB1 = await request(`${B}/api/v1/me`, { token });
printResult('me before logout on A', meA1.status === 200, `status=${meA1.status}`);
printResult('me before logout on B', meB1.status === 200, `status=${meB1.status}`);

const logout = await request(`${A}/api/v1/auth/logout`, { method: 'POST', token });
printResult('logout on A', logout.status === 200, `status=${logout.status}`);

const meA2 = await request(`${A}/api/v1/me`, { token });
const meB2 = await request(`${B}/api/v1/me`, { token });

printResult('me after logout on A (expect 401)', meA2.status === 401, `status=${meA2.status}`);
printResult(
  'me after logout on B (expect 401 after fix / 200 before fix)',
  true,
  `status=${meB2.status}`,
);

if (meB2.status === 401) {
  console.log('RESULT: fixed — blacklist shared (Redis). Token rejected on both instances.');
  process.exit(0);
}

if (meB2.status === 200) {
  console.log('RESULT: vulnerability — token still accepted on B after logout on A.');
  process.exit(2);
}

console.log(`RESULT: unexpected B status=${meB2.status}`);
process.exit(1);
