# Infrastructure (Requirement 5 — Part 1)

## Topology
```
Client → Nginx:8000 (least_conn)
           ├─ app1:8001
           ├─ app2:8002
           └─ app3:8003
Shared: Postgres / Redis / RabbitMQ / Spring Inventory
Queue emails ← worker1, worker2, worker3
```

## Why `least_conn`?
- Create-order requests are heavier/variable than product reads.
- Least connections sends new work to the least-busy instance.
- Not `ip_hash`: we want Stateless apps; any instance can serve any user.
- Not Geo: all instances are local/same region for this project.

## Continuity knobs
- `max_fails=2 fail_timeout=10s`
- `proxy_next_upstream error timeout http_502 http_503 http_504`

## Workers without duplicate mail
- 3 explicit services: `laravel-queue-emails-1/2/3`
- `SendOrderEmailJob` uses `lockForUpdate()` + status re-check after lock

## Required local secrets (root `.env`)
```
JWT_SECRET=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
```
