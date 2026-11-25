// أدوات مشتركة لسكربتات الاختبار (Node 18+ فيه fetch جاهز، بدون تثبيت أي مكتبة)
export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export async function call(method, url, { token, body, headers = {} } = {}) {
  const res = await fetch(url, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  let json = null;
  try { json = await res.json(); } catch (_) {}
  return { status: res.status, instance: res.headers.get('x-instance'), perfMode: res.headers.get('x-perf-mode'), json };
}

export async function registerUser(base, tag = 'u') {
  const email = `${tag}-${Date.now()}-${Math.floor(Math.random() * 1e6)}@loadtest.local`;
  const r = await call('POST', `${base}/api/v1/auth/register`, {
    body: { name: `Test ${tag}`, email, password: 'password123' },
  });
  if (r.status !== 201) throw new Error(`register failed: ${r.status} ${JSON.stringify(r.json)}`);
  return { email, token: r.json.access_token };
}
