package com.shop.inventory.repository;

import com.shop.inventory.entity.ReservationItem;
import com.shop.inventory.entity.ReservationItemId;
import org.springframework.data.jpa.repository.JpaRepository;

public interface ReservationItemRepository extends JpaRepository<ReservationItem, ReservationItemId> {
}