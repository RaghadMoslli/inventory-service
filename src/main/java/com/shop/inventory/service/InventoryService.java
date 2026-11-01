package com.shop.inventory.service;

import com.shop.inventory.model.Reservation;
import com.shop.inventory.model.Stock;
import com.shop.inventory.repository.ReservationRepository;
import com.shop.inventory.repository.StockRepository;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Service
public class InventoryService {

    private final StockRepository stockRepository;
    private final ReservationRepository reservationRepository;

    public InventoryService(StockRepository stockRepository, ReservationRepository reservationRepository) {
        this.stockRepository = stockRepository;
        this.reservationRepository = reservationRepository;
    }

    public Integer getStock(Long productId) {
        Optional<Stock> stock = stockRepository.findById(productId);
        return stock.map(Stock::getAvailable).orElse(0);
    }

    @Transactional
    public void setStock(Long productId, Integer quantity) {
        Stock stock = stockRepository.findById(productId).orElse(new Stock());
        stock.setProductId(productId);
        stock.setAvailable(quantity);
        stock.setReserved(0);
        stockRepository.save(stock);
    }

    // 1. منطق الحجز الذري  لجميع المنتجات دفعة واحدة 
    @Transactional
    public ReservationResult reserveStock(String requestId, List<ItemRequest> items) {
        // التحقق من عدم تكرار الطلب 
        Optional<Reservation> existing = reservationRepository.findAll().stream()
                .filter(r -> r.getRequestId().equals(requestId))
                .findFirst();
        if (existing.isPresent()) {
            return new ReservationResult(true, existing.get().getId(), new ArrayList<>());
        }

        List<ShortageItem> shortages = new ArrayList<>();

        // التحقق من توفر الكميات أولا
        for (ItemRequest item : items) {
            Stock stock = stockRepository.findById(item.productId()).orElse(null);
            if (stock == null || stock.getAvailable() < item.quantity()) {
                int available = (stock != null) ? stock.getAvailable() : 0;
                shortages.add(new ShortageItem(item.productId(), item.quantity(), available));
            }
        }

        // إذا وجد نقص في أي منتج نرفض الحجز بالكامل
        if (!shortages.isEmpty()) {
            return new ReservationResult(false, null, shortages);
        }

        // تنفيذ الخصم والحجز إذا كانت الكميات متوفرة
        String reservationId = UUID.randomUUID().toString();
        Reservation reservation = new Reservation();
        reservation.setId(reservationId);
        reservation.setRequestId(requestId);
        reservation.setStatus("CONFIRMED");
        reservation.setCreatedAt(LocalDateTime.now());
        reservationRepository.save(reservation);

        for (ItemRequest item : items) {
            Stock stock = stockRepository.findById(item.productId()).get();
            stock.setAvailable(stock.getAvailable() - item.quantity());
            stock.setReserved(stock.getReserved() + item.quantity());
            stockRepository.save(stock);
        }

        return new ReservationResult(true, reservationId, new ArrayList<>());
    }

    // 2. منطق إرجاع الكميات في حال إلغاء الطلب أو فشله
    @Transactional
    public boolean releaseStock(String reservationId) {
        Optional<Reservation> reservationOpt = reservationRepository.findById(reservationId);
        if (reservationOpt.isEmpty() || "RELEASED".equals(reservationOpt.get().getStatus())) {
            return false;
        }

        Reservation reservation = reservationOpt.get();
        reservation.setStatus("RELEASED");
        reservationRepository.save(reservation);

        // استرجاع الكميات المحجوزة
        return true;
    }

   
    public record ItemRequest(Long productId, Integer quantity) {}
    public record ShortageItem(Long productId, Integer requested, Integer available) {}
    public record ReservationResult(boolean success, String reservationId, List<ShortageItem> shortages) {}
}