# Load test & continuity results

## Infrastructure under test
- Nginx `:8000` with `least_conn`
- Apps: `app1` / `app2` / `app3` (`X-Instance`)
- Email workers: `laravel-queue-emails-1/2/3`
- Mail during load: **Mailpit** (`docker-compose.loadtest.yml`) to avoid Gmail flood

## Distribution sample (after nginx/app3 restart)
90 requests to `/api/v1/products?per_page=1`:

| Instance | Count |
|---|---|
| app1 | 30 |
| app2 | 30 |
| app3 | 30 |

## Scenario S1 — stock enough (500 requests)
Command:
```bash
k6 run -e RUN=s1 -e PRODUCT_ID=1 -e TOTAL=500 -e VUS=50 scaling-tests/04-load-500.js
```

Result:
- `status_201 = 498`
- `status_409 = 0`
- `status_other = 2`
- Mailpit messages ≈ 498
- `email_logs`: all `sent`, **no duplicate order_id**
- Inventory after reset+run: `available=99500`, `reserved=500`

Notes:
- 2 non-201 responses under pressure (document honestly; system mostly healthy).
- Workers remained 3 and processed without duplicate emails.

## Scenario S2 — limited stock (100 units, 500 requests)
```bash
UPDATE stock SET available=100, reserved=0 WHERE product_id=1;
k6 run -e RUN=s2 -e PRODUCT_ID=1 -e TOTAL=500 -e VUS=50 scaling-tests/04-load-500.js
```

Result:
- `status_201 = 100`
- `status_409 = 400`
- stock final: `available=0`, `reserved=100`

This proves the central inventory lock works across the 3 app instances.

## Scenario S3 — continuity (stop instance during load)
```bash
k6 run -e RUN=s3 -e TOTAL=200 -e VUS=40 ...
# while running:
docker stop inventory-app2-1
# later:
docker start inventory-app2-1
```

Result:
- `status_201 = 200`
- `status_other = 0`
- system continued on remaining instances via Nginx `proxy_next_upstream`

## How to reproduce
1. `docker compose -f docker-compose.yml -f docker-compose.loadtest.yml up -d`
2. Reset stock / clear Mailpit
3. Run k6 scenarios above
4. Verify with SQL in `scaling-tests/verify*.sql`
