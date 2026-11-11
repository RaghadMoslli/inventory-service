package com.shop.inventory.repository;

import com.shop.inventory.entity.Reservation;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.Optional;

public interface ReservationRepository extends JpaRepository<Reservation, String> {
    Optional<Reservation> findByRequestId(String requestId);
}
