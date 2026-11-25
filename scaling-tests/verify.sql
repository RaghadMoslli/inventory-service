-- Usage:
-- Get-Content scaling-tests/verify.sql | docker compose exec -T db psql -U postgres -d ecommerce_platform -f -

SELECT count(*) AS orders_count FROM orders;
SELECT coalesce(sum(quantity),0) AS units_sold FROM order_items WHERE product_id = :pid;
SELECT status, count(*) FROM email_logs GROUP BY status ORDER BY status;
SELECT order_id, count(*) AS c
FROM email_logs
GROUP BY order_id
HAVING count(*) > 1;
