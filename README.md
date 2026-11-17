# Ecommerce platform (merged)

Monorepo integrating:
- **Requirement 1** → Laravel API (`backend/laravel-app`) on branch `Rama`
- **Requirement 2** → Spring Boot inventory gRPC (`inventory-service`) on branch `raghad-spring`

## Layout

```text
/
├── backend/laravel-app/     # Users, Products, Orders, Notifications (JWT REST)
├── inventory-service/       # Independent inventory microservice (gRPC + private DB)
└── docker-compose.yml       # Unified stack
```

## Run

```bash
docker compose up --build
```

| Service | URL / Port |
|---|---|
| Laravel API | http://localhost:8000 |
| Inventory gRPC | localhost:9090 |
| Inventory health | http://localhost:8080/actuator/health |
| Laravel DB | localhost:5432 |
| Inventory DB | localhost:5433 |

## Integration behavior

1. Product list/details call inventory `GetStock` and return `available_quantity`
2. Creating a product calls `SetStock`
3. Creating an order calls `Reserve` once for all lines
4. If order save fails after reserve → `Release`
5. Cancel / admin cancel → `Release`
6. If inventory is down/timeout → clear `503 INVENTORY_UNAVAILABLE` (no hang)

## Proof demos

- Stop inventory: `docker stop inventory_service` then create order → 503
- Race: last unit reserved concurrently in inventory-service tests
