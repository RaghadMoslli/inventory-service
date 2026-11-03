package com.shop.inventory.entity;

import java.io.Serializable;
import java.util.Objects;
import lombok.*;

@Getter
@Setter
@NoArgsConstructor
@AllArgsConstructor
public class ReservationItemId implements Serializable {
    private String reservationId;
    private Long productId;

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (!(o instanceof ReservationItemId)) return false;
        ReservationItemId that = (ReservationItemId) o;
        return Objects.equals(reservationId, that.reservationId) && Objects.equals(productId, that.productId);
    }

    @Override
    public int hashCode() {
        return Objects.hash(reservationId, productId);
    }
}