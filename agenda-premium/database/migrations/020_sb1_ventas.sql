-- Servicios B1 · ventas y crecimiento
-- Bitácora de recursos consumidos por una reserva (cupón, certificado, paquete). Hace idempotentes consume() y release().
CREATE TABLE IF NOT EXISTS pricing_ledger (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  kind ENUM('coupon','gift','package') NOT NULL,
  ref_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  state ENUM('consumed','released') NOT NULL DEFAULT 'consumed',
  created_at DATETIME NOT NULL,
  released_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ledger_booking_kind (booking_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monto mínimo de compra para que un cupón aplique (0 = sin mínimo)
ALTER TABLE coupons ADD COLUMN min_amount DECIMAL(10,2) NOT NULL DEFAULT 0;

-- Los reembolsos son filas con status 'refunded' que apuntan al pago original
ALTER TABLE payments ADD COLUMN refund_of INT UNSIGNED NULL, ADD KEY ix_pay_refund (refund_of);

-- Cita creada a partir de una oferta de lista de espera
ALTER TABLE waitlist ADD COLUMN booking_id INT UNSIGNED NULL;
