package com.shop.inventory.repository;

import com.shop.inventory.entity.ReservationItem;
import com.shop.inventory.entity.ReservationItemId;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface ReservationItemRepository extends JpaRepository<ReservationItem, ReservationItemId> {
    List<ReservationItem> findByReservationId(String reservationId);
}
