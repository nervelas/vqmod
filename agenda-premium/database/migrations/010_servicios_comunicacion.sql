-- Servicios A (comunicación): columnas auxiliares para calendarios externos y flujos.
-- etag/last_modified permiten peticiones condicionales; last_parse_at fuerza una lectura completa cada cierto tiempo.
ALTER TABLE external_calendars
  ADD COLUMN etag VARCHAR(255) NULL,
  ADD COLUMN last_modified VARCHAR(80) NULL,
  ADD COLUMN last_parse_at DATETIME NULL;

-- next_attempt_at: hora del siguiente reintento (NULL = usar scheduled_at)
ALTER TABLE workflow_runs
  ADD COLUMN next_attempt_at DATETIME NULL;

ALTER TABLE workflow_runs
  ADD KEY ix_wfr_booking (booking_id, status);

ALTER TABLE email_queue
  ADD KEY ix_eq_cleanup (status, sent_at);
