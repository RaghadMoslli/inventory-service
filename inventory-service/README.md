# Inventory Service (Requirement 2)

Independent **Spring Boot + gRPC** inventory microservice with its own PostgreSQL database.

## Contract (`proto/inventory.proto`)

| RPC | Purpose |
|---|---|
| `GetStock` | Return available quantity for one or more products |
| `Reserve` | Atomically check + reserve all order items in one call |
| `Release` | Return reserved stock (rollback when order save fails) |
| `SetStock` | Seed/update stock (helper for demos & admin sync) |

## Run with Docker

```bash
./gradlew bootJar -x test
docker compose up --build
```

- gRPC: `localhost:9090`
- Health: `http://localhost:8080/actuator/health`
- Postgres (host): `localhost:5433` → `inventory_db` / `postgres` / `password`

## Integration with Requirement 1 app

1. Share the same `proto/inventory.proto` with the Orders/Products client.
2. **Product details:** call `GetStock` and show `available` with the product.
3. **Create order:** call `Reserve` once with all order lines.
   - `success=false` → reject order and return shortages to the user.
   - `success=true` → save the order; keep `reservation_id`.
4. **If order save fails after reserve:** immediately call `Release(reservation_id)`.
5. **Timeouts:** set a short gRPC deadline on the client (e.g. 2–3s) so users never hang when inventory is down.

## Proof scenarios

### Race condition
```bash
./gradlew test --tests com.shop.inventory.service.InventoryConcurrencyTest
```
Two concurrent reserves for the last unit → exactly one succeeds.

### Inventory unavailable
1. `docker compose up --build`
2. Stop inventory: `docker stop inventory_service`
3. Create an order from the main app → client should fail fast with a clear timeout/unavailable message (not hang).

## Local development

```bash
docker compose up postgres -d
./gradlew bootRun
```
