package com.shop.inventory.service;

import com.shop.inventory.model.Stock;
import com.shop.inventory.repository.StockRepository;
import com.shop.inventory.repository.ReservationRepository;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import java.util.Optional;

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
}