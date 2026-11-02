package com.shop.inventory.entity;

import jakarta.persistence.*;
import lombok.*;

@Entity
@Table(name = "reservation_items")
@IdClass(ReservationItemId.class)
@Getter
@Setter
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class ReservationItem {

    @Id
    @Column(name = "reservation_id")
    private String reservationId;

    @Id
    @Column(name = "product_id")
    private Long productId;

    @Column(nullable = false)
    private Integer quantity;
}