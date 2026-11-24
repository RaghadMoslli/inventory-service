# حزمة اختبارات الأداء باستخدام k6

ضعي مجلد `perf` الموجود داخل الحزمة مباشرةً في جذر مشروع Laravel، بجانب `docker-compose.yml` أو `compose.yaml`. نفّذي أوامر Docker من جذر المشروع، وأوامر k6 من جذر المشروع أيضاً باستخدام المسارات `perf/...`.

## قبل البدء — مهم جداً
1. شغّلي Docker وتحققي أن الخدمات تعمل: `docker compose ps`.
2. لا تنفذي أمر `perf:seed --fresh` إلا بعد موافقة الفريق والتأكد من أن قاعدة البيانات محلية/مخصصة للاختبار؛ قد يعيد إنشاء البيانات ويحذف بيانات موجودة.
3. لا تشغّلي اختبارات الضغط على بيئة مشتركة دون تنسيق مع الفريق.
4. نفذي Baseline أولاً واحفظي نتائجه، ثم Optimized بنفس البيانات والسكربتات والحمل.
5. السكربتات لا تغيّر وضع السيرفر بنفسها. أوامر `perf:mode` تنفذ يدوياً قبل كل مجموعة اختبارات.

## الملفات
- `products-list.js`: قائمة المنتجات والتنقل بين الصفحات.
- `products-filter.js`: فلترة المنتجات حسب الفئة.
- `product-details.js`: تفاصيل منتج.
- `orders-list.js`: قائمة الطلبات مع تسجيل دخول مرة واحدة في setup.
- `cache-warm.js`: يرسل طلبين متتاليين لنفس قائمة المنتجات لملاحظة MISS ثم HIT عند كون المفتاح غير مخزن مسبقاً.
- `etag-304.js`: يختبر ETag ثم If-None-Match ويتوقع 304.
- `cache-invalidation.js`: اختبار وظيفي يعدل اسم منتج مؤقتاً ويتحقق من تحديث القائمة ثم يعيد الاسم الأصلي.
- `read-your-writes.js`: يعدل منتجاً مؤقتاً ثم يقرأه فوراً ويتحقق من ظهور التعديل، ثم يعيد الاسم الأصلي.

## المتطلبات
- k6 مثبت على Windows.
- Base URL الافتراضي: `http://localhost:8000` (عند تشغيل k6 على جهاز Windows نفسه).
- عند تشغيل k6 داخل حاوية Docker، لا تستخدمي localhost؛ استخدمي اسم الخدمة/العنوان الذي يمكن الوصول إليه من تلك الحاوية.

## متغيرات البيئة في PowerShell
ضعي القيم محلياً في نافذة PowerShell الحالية فقط:

```powershell
$env:BASE_URL="http://localhost:8000"
$env:USER_EMAIL="بريد-حساب-المستخدم"
$env:USER_PASSWORD="كلمة-مرور-حساب-المستخدم"
$env:ADMIN_EMAIL="بريد-حساب-المشرف"
$env:ADMIN_PASSWORD="كلمة-مرور-حساب-المشرف"
$env:MUTATION_PRODUCT_ID="رقم-منتج-مخصص-للاختبار"
```

لا ترسلي كلمات المرور أو Tokens في المحادثة. لا تضعيها داخل ملفات JavaScript.

## تجهيز البيانات
أمر زميلتك المذكور:
```powershell
docker compose exec laravel-app php artisan perf:seed --fresh --force --orders=300000 --products=20000 --users=5000
```
هذا أمر قد يعيد إنشاء البيانات. لا تنفذيه إلا بعد تأكيد الفريق أن قاعدة البيانات مخصصة ويمكن مسح بياناتها. لا تعيدي تشغيله بين Baseline وOptimized.

## تشغيل Baseline
أولاً:
```powershell
docker compose exec laravel-app php artisan perf:mode baseline
```
ثم من مجلد الحزمة:
```powershell
k6 run --summary-export perf/results/baseline-products-list.json perf/scripts/products-list.js
k6 run --summary-export perf/results/baseline-products-filter.json perf/scripts/products-filter.js
k6 run --summary-export perf/results/baseline-product-details.json perf/scripts/product-details.js
```
بعد ضبط متغيرات USER_EMAIL وUSER_PASSWORD:
```powershell
k6 run --summary-export perf/results/baseline-orders-list.json perf/scripts/orders-list.js
```

## تشغيل Optimized
بعد حفظ نتائج Baseline، نفذي:
```powershell
docker compose exec laravel-app php artisan perf:mode optimized
```
ثم أعيدي تشغيل نفس أوامر k6، مع تغيير أسماء الملفات إلى `optimized-...json`.

## اختبارات الميزات المنفصلة
نفذي أمر تفعيل الميزة حسب تعليمات الفريق، ثم الاختبار المقابل:
- Eager Loading:
  `docker compose exec laravel-app php artisan perf:mode baseline --enable=eager_loading`
  ثم `orders-list.js`.
- Cache:
  `docker compose exec laravel-app php artisan perf:mode baseline --enable=cache`
  ثم `docker compose exec laravel-app php artisan perf:cache-stats --reset`
  ثم `cache-warm.js`.
  ملاحظة: تصفير العدادات لا يعني بالضرورة مسح قيمة الكاش نفسها. اتبعي طريقة الفريق لتجهيز Cache MISS قبل الاختبار، ولا تفترضي أن الطلب الأول سيكون MISS إذا كان المفتاح مخزناً مسبقاً.
- Cache invalidation:
  `docker compose exec laravel-app php artisan perf:mode baseline --enable=cache`
  ثم `cache-invalidation.js`. يتطلب حساب Admin ومنتجاً مخصصاً للاختبار.
- ETag:
  `docker compose exec laravel-app php artisan perf:mode baseline --enable=etag`
  ثم `etag-304.js`.
- Read Replica:
  `docker compose exec laravel-app php artisan perf:mode baseline --enable=replica`
  اتبعي أوامر SQL اليدوية التي زودتك بها زميلتك حرفياً بعد مراجعتها معها. لا تشغليها من k6 ولا تخمني أوامر SQL الناقصة من الصور.
- Read-Your-Writes:
  فعّلي وضع `optimized` ثم شغلي `read-your-writes.js` بحساب Admin ومنتج مخصص. هذا اختبار وظيفي قصير وليس اختبار ضغط.

## مثال أول اختبار
ابدئي فقط بعد التأكد من جاهزية البيانات:
```powershell
k6 run --summary-export perf/results/baseline-products-list.json perf/scripts/products-list.js
```
لا تشغلي كل الاختبارات دفعة واحدة. أرسلي مخرجات أول اختبار لمراجعتها قبل الانتقال إلى التالي.

## ملاحظات تفسير النتائج
- `http_req_duration` يقيس زمن HTTP من جهة k6.
- `app_query_time_ms` و`app_query_count` يقرآن الترويسات إذا أعادها السيرفر؛ هما مؤشرات تطبيقية مساعدة وليسا بديلاً عن زمن HTTP.
- `p(95)` يعني أن 95% من الطلبات انتهت خلال هذا الزمن أو أقل.
- قارنة Baseline وOptimized فقط إذا تطابقت البيانات، السيناريوهات، عدد VUs، المدد، والجهاز والظروف قدر الإمكان.
- `cache-warm.js` و`etag-304.js` وملفات التعديل اختبارات قصيرة بوضع VU واحد لتجنب إفساد/تشويش الاختبار بسبب تعديلات متزامنة.
- استجابة الطلبات في `/orders` متداخلة حسب العينة التي أرسلتِها: `body.data.data`.
- قد تختلف صلاحيات Admin أو شكل استجابة التفاصيل حسب التطبيق؛ إذا فشل check سنراجع الاستجابة الفعلية ولا نغيّر التوقع بالتخمين.
