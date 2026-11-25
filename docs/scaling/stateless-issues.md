# Stateless issues — قبل / إصلاح / بعد

| المشكلة | قبل | الإصلاح | بعد | إثبات |
|---|---|---|---|---|
| JWT blacklist محلي | logout على app1 ثم `/me` على app2 → **200** (ثغرة) | `CACHE_STORE=redis` لكل النسخ | `/me` على app2 → **401** | `node scaling-tests/01-jwt-logout.mjs` → `RESULT: fixed` |
| Rate limit خلف Nginx | 6 مستخدمين بـ IP مختلفة، السادس **429** | `trustProxies(at: '*')` + تمرير `X-Forwarded-For` من Nginx | الستة **200** | `node scaling-tests/02-rate-limit.mjs` → `RESULT: fixed` |
| perf-mode بملف محلي | تغيير الوضع على نسخة لا يظهر على الباقي | `PerfMode` يخزّن في Redis (`perf:mode:flags`) | الثلاث نسخ نفس الـ label | `php artisan perf:mode baseline` ثم label على app1/2/3 = `baseline` |
| صور المنتجات على قرص النسخة | ملف على app1 قد لا يظهر على app2 في تصميم منفصل | Volume مشترك `product_uploads` | `probe.txt` يظهر على app1/2/3 | `docker compose exec appN ls storage/app/public/products` |
| Sessions | `SESSION_DRIVER=database` | `SESSION_DRIVER=redis` | كل النسخ تقرأ نفس الـ session store | `printenv SESSION_DRIVER` → `redis` |

## ملاحظات المناقشة
- التوكن نفسه Stateless (تحقق بالتوقيع + `JWT_SECRET` مشترك).
- الحالة الوحيدة مع JWT هي الـ blacklist، وصارت في Redis.
- `FakeStockProvider` غير مستخدم في النسخ (`INVENTORY_DRIVER=grpc`).
