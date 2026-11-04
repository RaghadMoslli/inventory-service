package com.shop.inventory.entity;

import jakarta.persistence.*;
import lombok.*;

@Entity
@Table(name = "stock")
@Getter
@Setter
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class Stock {

    @Id
    @Column(name = "product_id")
    private Long productId;

    @Column(nullable = false)
    private Integer available;

    @Column(nullable = false)
    private Integer reserved;
}