-- AUREA · Migración 001: esquema inicial (MySQL 5.7+ / MariaDB 10.3+, utf8mb4)
CREATE TABLE settings (
  skey VARCHAR(100) NOT NULL PRIMARY KEY,
  svalue MEDIUMTEXT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  address VARCHAR(255) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL DEFAULT '',
  phone VARCHAR(30) NOT NULL DEFAULT '',
  map_url VARCHAR(500) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE professionals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  title VARCHAR(150) NOT NULL DEFAULT '',
  slug VARCHAR(160) NOT NULL,
  bio TEXT NULL,
  specialties VARCHAR(500) NOT NULL DEFAULT '',
  photo VARCHAR(255) NOT NULL DEFAULT '',
  color CHAR(7) NOT NULL DEFAULT '#B8924A',
  email VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(30) NOT NULL DEFAULT '',
  whatsapp VARCHAR(30) NOT NULL DEFAULT '',
  ics_token CHAR(32) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_prof_slug (slug),
  UNIQUE KEY uq_prof_ics (ics_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'reception',
  professional_id INT UNSIGNED NULL,
  totp_secret VARCHAR(64) NULL,
  totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  reset_token CHAR(64) NULL,
  reset_expires DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_user_email (email),
  KEY idx_user_prof (professional_id),
  CONSTRAINT fk_user_prof FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE professional_locations (
  professional_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (professional_id, location_id),
  CONSTRAINT fk_pl_prof FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  CONSTRAINT fk_pl_loc FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  buffer_before SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  buffer_after SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  min_notice_hours INT UNSIGNED NOT NULL DEFAULT 2,
  max_advance_days INT UNSIGNED NOT NULL DEFAULT 60,
  slot_interval SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  deposit_type VARCHAR(10) NOT NULL DEFAULT 'none',
  deposit_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  modality VARCHAR(12) NOT NULL DEFAULT 'presencial',
  meeting_url VARCHAR(500) NOT NULL DEFAULT '',
  auto_confirm TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_svc_cat (category_id),
  CONSTRAINT fk_svc_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE professional_services (
  professional_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  price_override DECIMAL(10,2) NULL,
  PRIMARY KEY (professional_id, service_id),
  CONSTRAINT fk_ps_prof FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_svc FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  professional_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  KEY idx_sched (professional_id, weekday),
  CONSTRAINT fk_sched_prof FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  CONSTRAINT fk_sched_loc FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE time_off (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  professional_id INT UNSIGNED NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  reason VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY idx_off_range (start_at, end_at),
  KEY idx_off_prof (professional_id),
  CONSTRAINT fk_off_prof FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE holidays (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hdate DATE NOT NULL,
  name VARCHAR(150) NOT NULL,
  kind VARCHAR(8) NOT NULL DEFAULT 'full',
  close_time TIME NULL,
  scope VARCHAR(10) NOT NULL DEFAULT 'national',
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_hol (hdate, name),
  KEY idx_hol_date (hdate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone_cc VARCHAR(5) NOT NULL DEFAULT '502',
  phone VARCHAR(20) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  nit VARCHAR(20) NOT NULL DEFAULT '',
  notes TEXT NULL,
  tags VARCHAR(255) NOT NULL DEFAULT '',
  blocked TINYINT(1) NOT NULL DEFAULT 0,
  noshow_count INT UNSIGNED NOT NULL DEFAULT 0,
  consent_at DATETIME NULL,
  consent_version VARCHAR(40) NOT NULL DEFAULT '',
  consent_ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY idx_cli_phone (phone_cc, phone),
  KEY idx_cli_email (email),
  KEY idx_cli_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE packages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  sessions SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  validity_days INT UNSIGNED NOT NULL DEFAULT 180,
  service_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_pkg_svc FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_packages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  package_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  service_id INT UNSIGNED NULL,
  sessions_total SMALLINT UNSIGNED NOT NULL,
  sessions_used SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  expires_at DATE NULL,
  created_at DATETIME NOT NULL,
  KEY idx_cp_client (client_id),
  CONSTRAINT fk_cp_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_pkg FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  kind VARCHAR(10) NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL DEFAULT 0,
  balance DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_uses INT UNSIGNED NOT NULL DEFAULT 0,
  used INT UNSIGNED NOT NULL DEFAULT 0,
  valid_from DATE NULL,
  valid_to DATE NULL,
  service_id INT UNSIGNED NULL,
  note VARCHAR(190) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_coupon_code (code),
  CONSTRAINT fk_cpn_svc FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  token CHAR(32) NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  professional_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  block_start DATETIME NOT NULL,
  block_end DATETIME NOT NULL,
  duration_min SMALLINT UNSIGNED NOT NULL,
  status VARCHAR(15) NOT NULL DEFAULT 'pending',
  source VARCHAR(15) NOT NULL DEFAULT 'web',
  modality VARCHAR(12) NOT NULL DEFAULT 'presencial',
  meeting_url VARCHAR(500) NOT NULL DEFAULT '',
  home_address VARCHAR(255) NOT NULL DEFAULT '',
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  deposit_required DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_status VARCHAR(10) NOT NULL DEFAULT 'unpaid',
  coupon_id INT UNSIGNED NULL,
  client_package_id INT UNSIGNED NULL,
  client_note TEXT NULL,
  internal_note TEXT NULL,
  rescheduled_from INT UNSIGNED NULL,
  pending_expires_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  cancel_reason VARCHAR(255) NOT NULL DEFAULT '',
  client_confirmed_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_appt_token (token),
  KEY idx_appt_block (professional_id, block_start, block_end),
  KEY idx_appt_start (start_at),
  KEY idx_appt_status (status, start_at),
  KEY idx_appt_client (client_id),
  KEY idx_appt_svc (service_id, start_at),
  CONSTRAINT fk_appt_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_appt_prof FOREIGN KEY (professional_id) REFERENCES professionals(id),
  CONSTRAINT fk_appt_svc FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_appt_loc FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
  CONSTRAINT fk_appt_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL,
  CONSTRAINT fk_appt_cpkg FOREIGN KEY (client_package_id) REFERENCES client_packages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointment_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  actor VARCHAR(40) NOT NULL DEFAULT 'sistema',
  action VARCHAR(40) NOT NULL,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY idx_hist_appt (appointment_id),
  CONSTRAINT fk_hist_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE form_fields (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NULL,
  label VARCHAR(190) NOT NULL,
  ftype VARCHAR(12) NOT NULL DEFAULT 'text',
  options TEXT NULL,
  required TINYINT(1) NOT NULL DEFAULT 0,
  help VARCHAR(255) NOT NULL DEFAULT '',
  cond_field_id INT UNSIGNED NULL,
  cond_value VARCHAR(190) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_ff_svc (service_id),
  CONSTRAINT fk_ff_svc FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointment_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL,
  field_id INT UNSIGNED NULL,
  label VARCHAR(190) NOT NULL,
  value TEXT NULL,
  KEY idx_ans_appt (appointment_id),
  CONSTRAINT fk_ans_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_type VARCHAR(15) NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  stored_name CHAR(40) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size INT UNSIGNED NOT NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_file_stored (stored_name),
  KEY idx_file_owner (owner_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method VARCHAR(15) NOT NULL DEFAULT 'efectivo',
  reference VARCHAR(190) NOT NULL DEFAULT '',
  file_id INT UNSIGNED NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'confirmed',
  note VARCHAR(255) NOT NULL DEFAULT '',
  paid_at DATETIME NOT NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pay_appt (appointment_id),
  KEY idx_pay_date (paid_at),
  CONSTRAINT fk_pay_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE waitlist (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone_cc VARCHAR(5) NOT NULL DEFAULT '502',
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  service_id INT UNSIGNED NOT NULL,
  professional_id INT UNSIGNED NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'waiting',
  offer_token CHAR(32) NULL,
  offer_start DATETIME NULL,
  offer_professional_id INT UNSIGNED NULL,
  offer_expires DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_wl_status (status, service_id),
  UNIQUE KEY uq_wl_token (offer_token),
  CONSTRAINT fk_wl_svc FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  CONSTRAINT fk_wl_prof FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL,
  professional_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_rev_appt (appointment_id),
  KEY idx_rev_prof (professional_id, status),
  CONSTRAINT fk_rev_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_prof FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  subject VARCHAR(190) NOT NULL DEFAULT '',
  email_body TEXT NULL,
  wa_body TEXT NULL,
  wa_template_name VARCHAR(100) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_tpl_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NULL,
  channel VARCHAR(10) NOT NULL,
  type VARCHAR(30) NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL DEFAULT '',
  body MEDIUMTEXT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NOT NULL DEFAULT '',
  dedupe_key VARCHAR(120) NULL,
  send_after DATETIME NOT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_queue_dedupe (dedupe_key),
  KEY idx_queue_due (status, channel, send_after),
  KEY idx_queue_appt (appointment_id),
  CONSTRAINT fk_queue_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  user_name VARCHAR(150) NOT NULL DEFAULT '',
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NOT NULL DEFAULT '',
  entity_id INT UNSIGNED NULL,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(190) NOT NULL DEFAULT '',
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_la (ip, username, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(40) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_rl (bucket, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE migrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  applied_at DATETIME NOT NULL,
  UNIQUE KEY uq_mig (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
