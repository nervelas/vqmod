-- Admin1: el secreto TOTP se guarda cifrado (más largo que 64 caracteres) y búsquedas por nombre de cliente.
ALTER TABLE users MODIFY totp_secret VARCHAR(255) NULL;
ALTER TABLE clients ADD KEY ix_clients_name (name);
ALTER TABLE client_notes ADD KEY ix_cn_created (client_id, created_at);
