package com.shop.inventory.service;

import com.shop.inventory.entity.Reservation;
import com.shop.inventory.entity.ReservationItem;
import com.shop.inventory.entity.Stock;
import com.shop.inventory.repository.ReservationItemRepository;
import com.shop.inventory.repository.ReservationRepository;
import com.shop.inventory.repository.StockRepository;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.ArrayList;
import java.util.Comparator;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;
import java.util.stream.Collectors;

@Service
public class InventoryService {

    public static final String STATUS_CONFIRMED = "CONFIRMED";
    public static final String STATUS_RELEASED = "RELEASED";

    private final StockRepository stockRepository;
    private final ReservationRepository reservationRepository;
    private final ReservationItemRepository reservationItemRepository;

    public InventoryService(
            StockRepository stockRepository,
            ReservationRepository reservationRepository,
            ReservationItemRepository reservationItemRepository
    ) {
        this.stockRepository = stockRepository;
        this.reservationRepository = reservationRepository;
        this.reservationItemRepository = reservationItemRepository;
    }

    @Transactional(readOnly = true)
    public List<StockItem> getStock(List<Long> productIds) {
        if (productIds == null || productIds.isEmpty()) {
            return List.of();
        }

        Map<Long, Stock> stocks = stockRepository.findAllById(productIds).stream()
                .collect(Collectors.toMap(Stock::getProductId, s -> s));

        List<StockItem> items = new ArrayList<>();
        for (Long productId : productIds) {
            Stock stock = stocks.get(productId);
            int available = stock != null ? stock.getAvailable() : 0;
            items.add(new StockItem(productId, available));
        }
        return items;
    }

    @Transactional
    public boolean setStock(Long productId, int quantity) {
        if (productId == null || quantity < 0) {
            return false;
        }

        Stock stock = stockRepository.findById(productId).orElseGet(() ->
                Stock.builder()
                        .productId(productId)
                        .available(0)
                        .reserved(0)
                        .build()
        );
        stock.setAvailable(quantity);
        if (stock.getReserved() == null) {
            stock.setReserved(0);
        }
        stockRepository.save(stock);
        return true;
    }

    /**
     * Atomically checks and reserves stock for all items in one transaction.
     * Uses pessimistic locks ordered by productId to prevent race conditions and deadlocks.
     */
    @Transactional
    public ReservationResult reserveStock(String requestId, List<ItemRequest> items) {
        if (requestId == null || requestId.isBlank()) {
            throw new IllegalArgumentException("request_id is required");
        }
        if (items == null || items.isEmpty()) {
            throw new IllegalArgumentException("at least one item is required");
        }

        Optional<Reservation> existing = reservationRepository.findByRequestId(requestId);
        if (existing.isPresent()) {
            return new ReservationResult(true, existing.get().getId(), List.of());
        }

        Map<Long, Integer> requestedByProduct = mergeQuantities(items);
        List<Long> productIds = new ArrayList<>(requestedByProduct.keySet());
        productIds.sort(Comparator.naturalOrder());

        Map<Long, Stock> lockedStocks = stockRepository.findAllByProductIdInForUpdate(productIds).stream()
                .collect(Collectors.toMap(Stock::getProductId, s -> s, (a, b) -> a, LinkedHashMap::new));

        List<ShortageItem> shortages = new ArrayList<>();
        for (Long productId : productIds) {
            int requested = requestedByProduct.get(productId);
            Stock stock = lockedStocks.get(productId);
            int available = stock != null ? stock.getAvailable() : 0;
            if (stock == null || available < requested) {
                shortages.add(new ShortageItem(productId, requested, available));
            }
        }

        if (!shortages.isEmpty()) {
            return new ReservationResult(false, null, shortages);
        }

        String reservationId = UUID.randomUUID().toString();
        Reservation reservation = Reservation.builder()
                .id(reservationId)
                .requestId(requestId)
                .status(STATUS_CONFIRMED)
                .build();
        reservationRepository.save(reservation);

        List<ReservationItem> reservationItems = new ArrayList<>();
        for (Long productId : productIds) {
            int quantity = requestedByProduct.get(productId);
            Stock stock = lockedStocks.get(productId);
            stock.setAvailable(stock.getAvailable() - quantity);
            stock.setReserved(stock.getReserved() + quantity);
            stockRepository.save(stock);

            reservationItems.add(ReservationItem.builder()
                    .reservationId(reservationId)
                    .productId(productId)
                    .quantity(quantity)
                    .build());
        }
        reservationItemRepository.saveAll(reservationItems);

        return new ReservationResult(true, reservationId, List.of());
    }

    /**
     * Releases a previous reservation and restores stock.
     * Safe to call when order persistence fails after a successful reserve.
     */
    @Transactional
    public boolean releaseStock(String reservationId) {
        if (reservationId == null || reservationId.isBlank()) {
            return false;
        }

        Optional<Reservation> reservationOpt = reservationRepository.findById(reservationId);
        if (reservationOpt.isEmpty()) {
            return false;
        }

        Reservation reservation = reservationOpt.get();
        if (STATUS_RELEASED.equals(reservation.getStatus())) {
            return true;
        }

        List<ReservationItem> items = reservationItemRepository.findByReservationId(reservationId);
        List<Long> productIds = items.stream()
                .map(ReservationItem::getProductId)
                .sorted()
                .toList();

        Map<Long, Stock> lockedStocks = stockRepository.findAllByProductIdInForUpdate(productIds).stream()
                .collect(Collectors.toMap(Stock::getProductId, s -> s));

        for (ReservationItem item : items) {
            Stock stock = lockedStocks.get(item.getProductId());
            if (stock == null) {
                stock = Stock.builder()
                        .productId(item.getProductId())
                        .available(0)
                        .reserved(0)
                        .build();
            }
            stock.setAvailable(stock.getAvailable() + item.getQuantity());
            stock.setReserved(Math.max(0, stock.getReserved() - item.getQuantity()));
            stockRepository.save(stock);
        }

        reservation.setStatus(STATUS_RELEASED);
        reservationRepository.save(reservation);
        return true;
    }

    private Map<Long, Integer> mergeQuantities(List<ItemRequest> items) {
        Map<Long, Integer> merged = new LinkedHashMap<>();
        for (ItemRequest item : items) {
            if (item.productId() == null || item.quantity() == null || item.quantity() <= 0) {
                throw new IllegalArgumentException("each item needs product_id and positive quantity");
            }
            merged.merge(item.productId(), item.quantity(), Integer::sum);
        }
        return merged;
    }

    public record StockItem(Long productId, Integer available) {}
    public record ItemRequest(Long productId, Integer quantity) {}
    public record ShortageItem(Long productId, Integer requested, Integer available) {}
    public record ReservationResult(boolean success, String reservationId, List<ShortageItem> shortages) {}
}
