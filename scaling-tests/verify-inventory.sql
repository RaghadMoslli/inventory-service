-- قاعدة المخزون (Spring):
--   docker compose exec -T inventory-db psql -U postgres -d inventory_db -v pid=1 -f - < scaling-tests/verify-inventory.sql
-- القاعدة: available_النهائي = available_الأولي - عدد الطلبات الناجحة، ولا يصير سالباً أبداً.
-- و reserved يزيد بنفس العدد.
\echo '--- حالة المخزون للمنتج ---'
SELECT product_id, available, reserved FROM stock WHERE product_id = :pid;
\echo '--- الحجوزات حسب الحالة (CONFIRMED = حجز فعّال، RELEASED = أُرجع) ---'
SELECT status, COUNT(*) FROM reservations GROUP BY status;
