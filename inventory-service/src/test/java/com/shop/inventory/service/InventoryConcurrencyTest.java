package com.shop.inventory.service;

import com.shop.inventory.entity.Stock;
import com.shop.inventory.repository.StockRepository;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.DynamicPropertyRegistry;
import org.springframework.test.context.DynamicPropertySource;
import org.testcontainers.containers.PostgreSQLContainer;
import org.testcontainers.junit.jupiter.Container;
import org.testcontainers.junit.jupiter.Testcontainers;

import java.util.List;
import java.util.UUID;
import java.util.concurrent.Callable;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

@SpringBootTest
@Testcontainers
class InventoryConcurrencyTest {

    @Container
    static PostgreSQLContainer<?> postgres = new PostgreSQLContainer<>("postgres:15-alpine")
            .withDatabaseName("inventory_db")
            .withUsername("postgres")
            .withPassword("password");

    @DynamicPropertySource
    static void datasourceProps(DynamicPropertyRegistry registry) {
        registry.add("spring.datasource.url", postgres::getJdbcUrl);
        registry.add("spring.datasource.username", postgres::getUsername);
        registry.add("spring.datasource.password", postgres::getPassword);
        registry.add("grpc.server.port", () -> 0);
    }

    @Autowired
    private InventoryService inventoryService;

    @Autowired
    private StockRepository stockRepository;

    @BeforeEach
    void seedLastUnit() {
        stockRepository.save(Stock.builder()
                .productId(100L)
                .available(1)
                .reserved(0)
                .build());
    }

    @Test
    void onlyOneConcurrentReserveSucceedsForLastUnit() throws Exception {
        ExecutorService pool = Executors.newFixedThreadPool(2);
        try {
            Callable<InventoryService.ReservationResult> orderA = () -> inventoryService.reserveStock(
                    "race-a-" + UUID.randomUUID(),
                    List.of(new InventoryService.ItemRequest(100L, 1))
            );
            Callable<InventoryService.ReservationResult> orderB = () -> inventoryService.reserveStock(
                    "race-b-" + UUID.randomUUID(),
                    List.of(new InventoryService.ItemRequest(100L, 1))
            );

            Future<InventoryService.ReservationResult> futureA = pool.submit(orderA);
            Future<InventoryService.ReservationResult> futureB = pool.submit(orderB);

            InventoryService.ReservationResult resultA = futureA.get(15, TimeUnit.SECONDS);
            InventoryService.ReservationResult resultB = futureB.get(15, TimeUnit.SECONDS);

            long successCount = (resultA.success() ? 1 : 0) + (resultB.success() ? 1 : 0);
            assertEquals(1, successCount, "exactly one order should get the last unit");

            Stock stock = stockRepository.findById(100L).orElseThrow();
            assertEquals(0, stock.getAvailable());
            assertEquals(1, stock.getReserved());
            assertTrue(resultA.success() || resultB.success());
        } finally {
            pool.shutdownNow();
        }
    }

    @Test
    void releaseRestoresStockAfterFailedOrderSaveScenario() {
        InventoryService.ReservationResult reserved = inventoryService.reserveStock(
                "order-save-fail-" + UUID.randomUUID(),
                List.of(new InventoryService.ItemRequest(100L, 1))
        );
        assertTrue(reserved.success());

        boolean released = inventoryService.releaseStock(reserved.reservationId());
        assertTrue(released);

        Stock stock = stockRepository.findById(100L).orElseThrow();
        assertEquals(1, stock.getAvailable());
        assertEquals(0, stock.getReserved());
    }
}
