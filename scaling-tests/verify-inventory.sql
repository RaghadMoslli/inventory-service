-- Usage:
-- Get-Content scaling-tests/verify-inventory.sql | docker compose exec -T inventory-db psql -U postgres -d inventory_db -f -

SELECT product_id, available, reserved
FROM stock
WHERE product_id = :pid;
