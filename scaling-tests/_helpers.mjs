export const BASE = process.env.BASE || 'http://localhost:8000';
export const A = process.env.A || 'http://localhost:8001';
export const B = process.env.B || 'http://localhost:8002';

export async function request(url, { method = 'GET', token, body, headers = {} } = {}) {
  const h = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...headers,
  };
  if (token) h.Authorization = `Bearer ${token}`;

  const res = await fetch(url, {
    method,
    headers: h,
    body: body ? JSON.stringify(body) : undefined,
  });

  let data = null;
  const text = await res.text();
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = text;
  }

  return { status: res.status, data, headers: res.headers };
}

export function uniqEmail(prefix = 'u') {
  return `${prefix}.${Date.now()}.${Math.floor(Math.random() * 1e6)}@example.test`;
}

export function printResult(title, ok, detail = '') {
  const mark = ok ? 'PASS' : 'FAIL';
  console.log(`[${mark}] ${title}${detail ? ' — ' + detail : ''}`);
}
