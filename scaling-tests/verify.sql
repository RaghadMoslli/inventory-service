-- ===== يُشغَّل بعد اختبار الحمل للتحقق من سلامة البيانات =====
-- قاعدة Laravel الرئيسية (استبدلي s1 باسم الجولة RUN، و1 برقم المنتج):
--   docker compose exec -T db psql -U postgres -d ecommerce_platform -v run=s1 -v pid=1 -f - < scaling-tests/verify.sql

\echo '--- 1) عدد الطلبات المُنشأة لهذه الجولة (يجب = عدد ردود 201 من k6) ---'
SELECT COUNT(DISTINCT o.id) AS orders_count
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
JOIN users u ON u.id = o.user_id
WHERE oi.product_id = :pid AND u.email LIKE 'load-' || :'run' || '-%';

\echo '--- 2) مجموع الكميات المباعة (يجب = orders_count لأن كل طلب quantity=1) ---'
SELECT COALESCE(SUM(oi.quantity),0) AS units_sold
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
JOIN users u ON u.id = o.user_id
WHERE oi.product_id = :pid AND u.email LIKE 'load-' || :'run' || '-%';

\echo '--- 3) حالات سجلات الإيميل (يجب أن تصير كلها sent بعد انتهاء العمال) ---'
SELECT el.status, COUNT(*) AS n
FROM email_logs el
JOIN orders o ON o.id = el.order_id
JOIN users u ON u.id = o.user_id
WHERE u.email LIKE 'load-' || :'run' || '-%'
GROUP BY el.status ORDER BY el.status;

\echo '--- 4) طلبات لها أكثر من سجل إيميل (يجب أن يرجع صفر صفوف) ---'
SELECT el.order_id, COUNT(*) AS logs_per_order
FROM email_logs el
JOIN orders o ON o.id = el.order_id
JOIN users u ON u.id = o.user_id
WHERE u.email LIKE 'load-' || :'run' || '-%'
GROUP BY el.order_id HAVING COUNT(*) > 1;
