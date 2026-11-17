-- جدول تخزين المنتجات والكميات المتاحة والمحجوزة
CREATE TABLE stock (
    product_id BIGINT PRIMARY KEY,
    available INTEGER NOT NULL DEFAULT 0,
    reserved INTEGER NOT NULL DEFAULT 0
);

-- جدول الحجوزات لتتبع العمليات (idempotency)
CREATE TABLE reservations (
    id VARCHAR(36) PRIMARY KEY,
    request_id VARCHAR(255) UNIQUE NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول تفاصيل عناصر الحجز
CREATE TABLE reservation_items (
    reservation_id VARCHAR(36) REFERENCES reservations(id) ON DELETE CASCADE,
    product_id BIGINT NOT NULL,
    quantity INTEGER NOT NULL,
    PRIMARY KEY (reservation_id, product_id)
);