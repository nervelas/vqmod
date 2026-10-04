-- Agenda Premium · esquema inicial (MySQL 5.7+ / MariaDB 10.3+, utf8mb4)
-- Todas las fechas/horas con sufijo _at o starts/ends se guardan en UTC (DATETIME).
-- Las columnas DATE/TIME de horarios (schedule_rules, overrides, holidays) son hora LOCAL de la zona del horario.

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(100) NOT NULL,
  v MEDIUMTEXT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  role ENUM('admin','host','reception') NOT NULL DEFAULT 'host',
  active TINYINT(1) NOT NULL DEFAULT 1,
  totp_secret VARCHAR(64) NULL,
  totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  avatar_file_id INT UNSIGNED NULL,
  reset_token_hash CHAR(64) NULL,
  reset_expires_at DATETIME NULL,
  invite_token_hash CHAR(64) NULL,
  invite_expires_at DATETIME NULL,
  theme VARCHAR(10) NOT NULL DEFAULT 'dark',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token CHAR(32) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(80) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size INT UNSIGNED NOT NULL DEFAULT 0,
  kind VARCHAR(30) NOT NULL DEFAULT 'attachment',
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  owner_type VARCHAR(30) NULL,
  owner_id INT UNSIGNED NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_files_token (token),
  KEY ix_files_owner (owner_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Guatemala',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- weekday: 1=lunes ... 7=domingo (ISO-8601). Horas en hora local de schedules.timezone.
CREATE TABLE IF NOT EXISTS schedule_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  schedule_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_rules_schedule (schedule_id, weekday),
  CONSTRAINT fk_rules_schedule FOREIGN KEY (schedule_id) REFERENCES schedules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Excepciones por fecha: is_open=0 cierra el día completo; is_open=1 define bloques abiertos (uno o varios renglones) que REEMPLAZAN la regla semanal.
CREATE TABLE IF NOT EXISTS schedule_overrides (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  schedule_id INT UNSIGNED NOT NULL,
  date DATE NOT NULL,
  is_open TINYINT(1) NOT NULL DEFAULT 0,
  start_time TIME NULL,
  end_time TIME NULL,
  note VARCHAR(190) NULL,
  PRIMARY KEY (id),
  KEY ix_overrides (schedule_id, date),
  CONSTRAINT fk_overrides_schedule FOREIGN KEY (schedule_id) REFERENCES schedules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  title VARCHAR(160) NULL,
  bio TEXT NULL,
  photo_file_id INT UNSIGNED NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Guatemala',
  color CHAR(7) NOT NULL DEFAULT '#C9A050',
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  whatsapp VARCHAR(30) NULL,
  ics_token CHAR(32) NOT NULL,
  schedule_id INT UNSIGNED NULL,
  public_profile TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosts_slug (slug),
  UNIQUE KEY uq_hosts_ics (ics_token),
  UNIQUE KEY uq_hosts_user (user_id),
  CONSTRAINT fk_hosts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_hosts_schedule FOREIGN KEY (schedule_id) REFERENCES schedules (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_teams_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_hosts (
  team_id INT UNSIGNED NOT NULL,
  host_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (team_id, host_id),
  CONSTRAINT fk_th_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
  CONSTRAINT fk_th_host FOREIGN KEY (host_id) REFERENCES hosts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- host_id NULL = ausencia de todo el negocio (cierre general)
CREATE TABLE IF NOT EXISTS time_off (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  host_id INT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  reason VARCHAR(190) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_timeoff (host_id, starts_at, ends_at),
  CONSTRAINT fk_timeoff_host FOREIGN KEY (host_id) REFERENCES hosts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- kind=half: abierto solo hasta half_day_end (hora local del horario). scope=capital: solo aplica si setting holidays_capital=1
CREATE TABLE IF NOT EXISTS holidays (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  date DATE NOT NULL,
  name VARCHAR(120) NOT NULL,
  kind ENUM('full','half') NOT NULL DEFAULT 'full',
  half_day_end TIME NULL,
  scope ENUM('national','capital') NOT NULL DEFAULT 'national',
  active TINYINT(1) NOT NULL DEFAULT 1,
  source ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  PRIMARY KEY (id),
  UNIQUE KEY uq_holidays_date_name (date, name),
  KEY ix_holidays_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_calendars (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  host_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  url TEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_fetch_at DATETIME NULL,
  last_ok_at DATETIME NULL,
  last_status VARCHAR(20) NULL,
  last_error VARCHAR(255) NULL,
  fail_count INT NOT NULL DEFAULT 0,
  next_fetch_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_extcal_host (host_id),
  CONSTRAINT fk_extcal_host FOREIGN KEY (host_id) REFERENCES hosts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_busy (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  calendar_id INT UNSIGNED NOT NULL,
  host_id INT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  uid VARCHAR(190) NULL,
  PRIMARY KEY (id),
  KEY ix_extbusy (host_id, starts_at, ends_at),
  KEY ix_extbusy_cal (calendar_id),
  CONSTRAINT fk_extbusy_cal FOREIGN KEY (calendar_id) REFERENCES external_calendars (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  kind ENUM('individual','group','round_robin','collective') NOT NULL DEFAULT 'individual',
  color CHAR(7) NOT NULL DEFAULT '#C9A050',
  duration_options VARCHAR(60) NOT NULL DEFAULT '30',
  default_duration SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  mode ENUM('in_person','video_auto','video_custom','phone','home') NOT NULL DEFAULT 'in_person',
  location VARCHAR(255) NULL,
  video_url VARCHAR(500) NULL,
  min_notice_minutes INT UNSIGNED NOT NULL DEFAULT 120,
  max_advance_days INT UNSIGNED NOT NULL DEFAULT 60,
  window_start DATE NULL,
  window_end DATE NULL,
  slot_interval SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  buffer_before SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  buffer_after SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  travel_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  daily_limit INT UNSIGNED NULL,
  weekly_limit INT UNSIGNED NULL,
  approval TINYINT(1) NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  deposit_type ENUM('none','fixed','percent') NOT NULL DEFAULT 'none',
  deposit_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  cancel_hours INT UNSIGNED NOT NULL DEFAULT 24,
  cancel_policy_text TEXT NULL,
  confirm_message TEXT NULL,
  redirect_url VARCHAR(500) NULL,
  schedule_id INT UNSIGNED NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 1,
  rr_mode ENUM('equitable','weighted','priority') NOT NULL DEFAULT 'equitable',
  series_sessions SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  series_interval_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  single_use TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  visibility ENUM('public','secret') NOT NULL DEFAULT 'public',
  respect_holidays TINYINT(1) NOT NULL DEFAULT 1,
  allow_guests TINYINT(1) NOT NULL DEFAULT 0,
  max_guests TINYINT UNSIGNED NOT NULL DEFAULT 0,
  allow_coupon TINYINT(1) NOT NULL DEFAULT 1,
  require_phone TINYINT(1) NOT NULL DEFAULT 1,
  team_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_slug (slug),
  KEY ix_event_active (active, visibility, sort_order),
  CONSTRAINT fk_event_schedule FOREIGN KEY (schedule_id) REFERENCES schedules (id) ON DELETE SET NULL,
  CONSTRAINT fk_event_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_hosts (
  event_type_id INT UNSIGNED NOT NULL,
  host_id INT UNSIGNED NOT NULL,
  weight INT UNSIGNED NOT NULL DEFAULT 1,
  priority INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (event_type_id, host_id),
  CONSTRAINT fk_eh_event FOREIGN KEY (event_type_id) REFERENCES event_types (id) ON DELETE CASCADE,
  CONSTRAINT fk_eh_host FOREIGN KEY (host_id) REFERENCES hosts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_resources (
  event_type_id INT UNSIGNED NOT NULL,
  resource_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (event_type_id, resource_id),
  CONSTRAINT fk_er_event FOREIGN KEY (event_type_id) REFERENCES event_types (id) ON DELETE CASCADE,
  CONSTRAINT fk_er_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- event_type_id NULL = pregunta global (se muestra en todos los eventos)
-- condition_field/condition_value: se muestra solo si la respuesta de otro campo (name) es igual al valor.
CREATE TABLE IF NOT EXISTS custom_fields (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type_id INT UNSIGNED NULL,
  name VARCHAR(60) NOT NULL,
  label VARCHAR(190) NOT NULL,
  type ENUM('text','textarea','select','radio','checkbox','number','email','phone','date','file','consent') NOT NULL DEFAULT 'text',
  options TEXT NULL,
  help VARCHAR(255) NULL,
  required TINYINT(1) NOT NULL DEFAULT 0,
  condition_field VARCHAR(60) NULL,
  condition_value VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY ix_cf_event (event_type_id, sort_order),
  CONSTRAINT fk_cf_event FOREIGN KEY (event_type_id) REFERENCES event_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  nit VARCHAR(30) NULL,
  tags VARCHAR(255) NULL,
  source VARCHAR(190) NULL,
  noshow_count INT UNSIGNED NOT NULL DEFAULT 0,
  blocked TINYINT(1) NOT NULL DEFAULT 0,
  timezone VARCHAR(64) NULL,
  anonymized_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_clients_email (email),
  KEY ix_clients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_cn_client (client_id),
  CONSTRAINT fk_cn_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- starts_at/ends_at = la cita; blocked_start/blocked_end = la cita + buffers (+ traslado). Estados activos: pending, confirmed.
CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token CHAR(32) NOT NULL,
  event_type_id INT UNSIGNED NOT NULL,
  host_id INT UNSIGNED NOT NULL,
  resource_id INT UNSIGNED NULL,
  client_id INT UNSIGNED NULL,
  series_token CHAR(32) NULL,
  series_index SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  blocked_start DATETIME NOT NULL,
  blocked_end DATETIME NOT NULL,
  duration SMALLINT UNSIGNED NOT NULL,
  seats SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('pending','confirmed','cancelled','completed','no_show','rejected') NOT NULL DEFAULT 'confirmed',
  guest_name VARCHAR(160) NOT NULL,
  guest_email VARCHAR(190) NULL,
  guest_phone VARCHAR(30) NULL,
  guest_timezone VARCHAR(64) NOT NULL DEFAULT 'America/Guatemala',
  mode VARCHAR(20) NOT NULL DEFAULT 'in_person',
  location VARCHAR(255) NULL,
  video_url VARCHAR(500) NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  deposit_due DECIMAL(10,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_status ENUM('none','pending','partial','paid','refunded') NOT NULL DEFAULT 'none',
  coupon_id INT UNSIGNED NULL,
  gift_card_id INT UNSIGNED NULL,
  client_package_id INT UNSIGNED NULL,
  notes TEXT NULL,
  internal_note TEXT NULL,
  cancel_reason VARCHAR(500) NULL,
  cancelled_by VARCHAR(20) NULL,
  cancelled_at DATETIME NULL,
  utm_source VARCHAR(100) NULL,
  utm_medium VARCHAR(100) NULL,
  utm_campaign VARCHAR(100) NULL,
  referrer_host VARCHAR(190) NULL,
  created_via ENUM('public','admin','api','waitlist','poll') NOT NULL DEFAULT 'public',
  routing_log_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bookings_token (token),
  KEY ix_bookings_host_time (host_id, status, blocked_start, blocked_end),
  KEY ix_bookings_event_time (event_type_id, starts_at),
  KEY ix_bookings_resource (resource_id, blocked_start, blocked_end),
  KEY ix_bookings_client (client_id),
  KEY ix_bookings_series (series_token),
  KEY ix_bookings_status_start (status, starts_at),
  CONSTRAINT fk_bookings_event FOREIGN KEY (event_type_id) REFERENCES event_types (id),
  CONSTRAINT fk_bookings_host FOREIGN KEY (host_id) REFERENCES hosts (id),
  CONSTRAINT fk_bookings_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Todos los anfitriones que quedan ocupados por la cita (varios en eventos colectivos)
CREATE TABLE IF NOT EXISTS booking_hosts (
  booking_id INT UNSIGNED NOT NULL,
  host_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (booking_id, host_id),
  KEY ix_bh_host (host_id),
  CONSTRAINT fk_bh_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_host FOREIGN KEY (host_id) REFERENCES hosts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_attendees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  PRIMARY KEY (id),
  KEY ix_ba_booking (booking_id),
  CONSTRAINT fk_ba_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  field_id INT UNSIGNED NULL,
  label VARCHAR(190) NOT NULL,
  value TEXT NULL,
  file_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_banswers_booking (booking_id),
  CONSTRAINT fk_banswers_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  detail VARCHAR(500) NULL,
  actor VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_bhist_booking (booking_id),
  CONSTRAINT fk_bhist_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  description VARCHAR(255) NULL,
  sessions INT UNSIGNED NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  validity_days INT UNSIGNED NOT NULL DEFAULT 180,
  event_type_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT fk_pkg_event FOREIGN KEY (event_type_id) REFERENCES event_types (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_packages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  package_id INT UNSIGNED NOT NULL,
  remaining INT UNSIGNED NOT NULL,
  expires_at DATETIME NULL,
  paid TINYINT(1) NOT NULL DEFAULT 0,
  purchased_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_cpkg_client (client_id),
  CONSTRAINT fk_cpkg_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
  CONSTRAINT fk_cpkg_package FOREIGN KEY (package_id) REFERENCES packages (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_uses INT UNSIGNED NULL,
  used INT UNSIGNED NOT NULL DEFAULT 0,
  valid_from DATETIME NULL,
  valid_to DATETIME NULL,
  event_type_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_cards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  initial_amount DECIMAL(10,2) NOT NULL,
  balance DECIMAL(10,2) NOT NULL,
  buyer_name VARCHAR(160) NULL,
  recipient_name VARCHAR(160) NULL,
  message VARCHAR(255) NULL,
  expires_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gift_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NULL,
  client_id INT UNSIGNED NULL,
  client_package_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('cash','transfer','card_onsite','link','package','gift_card','other') NOT NULL DEFAULT 'cash',
  status ENUM('pending','verified','rejected','refunded') NOT NULL DEFAULT 'verified',
  reference VARCHAR(190) NULL,
  proof_file_id INT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_pay_booking (booking_id),
  KEY ix_pay_client (client_id),
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS waitlist (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type_id INT UNSIGNED NOT NULL,
  host_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Guatemala',
  duration SMALLINT UNSIGNED NOT NULL,
  want_date DATE NULL,
  status ENUM('waiting','offered','booked','expired','cancelled') NOT NULL DEFAULT 'waiting',
  offer_token CHAR(32) NULL,
  offer_starts_at DATETIME NULL,
  offer_host_id INT UNSIGNED NULL,
  offer_expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wait_token (offer_token),
  KEY ix_wait_event (event_type_id, status, created_at),
  CONSTRAINT fk_wait_event FOREIGN KEY (event_type_id) REFERENCES event_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS routing_forms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  questions MEDIUMTEXT NOT NULL,
  default_action ENUM('event','host','team','message','url') NOT NULL DEFAULT 'message',
  default_value VARCHAR(500) NULL,
  default_message TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_routing_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS routing_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id INT UNSIGNED NOT NULL,
  priority INT NOT NULL DEFAULT 10,
  match_mode ENUM('all','any') NOT NULL DEFAULT 'all',
  conditions MEDIUMTEXT NOT NULL,
  action ENUM('event','host','team','message','url') NOT NULL DEFAULT 'event',
  action_value VARCHAR(500) NULL,
  message TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY ix_rr_form (form_id, priority),
  CONSTRAINT fk_rr_form FOREIGN KEY (form_id) REFERENCES routing_forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS routing_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id INT UNSIGNED NOT NULL,
  rule_id INT UNSIGNED NULL,
  answers MEDIUMTEXT NOT NULL,
  action VARCHAR(20) NOT NULL,
  target VARCHAR(500) NULL,
  reason VARCHAR(500) NOT NULL,
  ip_trunc VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_rlog_form (form_id, created_at),
  CONSTRAINT fk_rlog_form FOREIGN KEY (form_id) REFERENCES routing_forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS polls (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token CHAR(32) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  host_id INT UNSIGNED NOT NULL,
  event_type_id INT UNSIGNED NOT NULL,
  duration SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Guatemala',
  status ENUM('open','closed','finalized') NOT NULL DEFAULT 'open',
  final_option_id INT UNSIGNED NULL,
  final_booking_id INT UNSIGNED NULL,
  deadline_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_polls_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poll_options (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  poll_id INT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_po_poll (poll_id),
  CONSTRAINT fk_po_poll FOREIGN KEY (poll_id) REFERENCES polls (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poll_votes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  poll_id INT UNSIGNED NOT NULL,
  option_id INT UNSIGNED NOT NULL,
  voter_name VARCHAR(160) NOT NULL,
  voter_email VARCHAR(190) NOT NULL,
  vote ENUM('yes','maybe','no') NOT NULL DEFAULT 'yes',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pv_unique (option_id, voter_email),
  KEY ix_pv_poll (poll_id),
  CONSTRAINT fk_pv_poll FOREIGN KEY (poll_id) REFERENCES polls (id) ON DELETE CASCADE,
  CONSTRAINT fk_pv_option FOREIGN KEY (option_id) REFERENCES poll_options (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trigger_key: booking.created|booking.approved|booking.rescheduled|booking.cancelled|booking.completed|booking.no_show|booking.before_start|booking.after_end
-- offset_minutes: solo para before_start (minutos antes del inicio) y after_end (minutos después del final)
CREATE TABLE IF NOT EXISTS workflows (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  trigger_key VARCHAR(40) NOT NULL,
  offset_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  event_type_id INT UNSIGNED NULL,
  action ENUM('email','whatsapp','whatsapp_api','webhook','set_status','review_request','add_tag') NOT NULL DEFAULT 'email',
  recipient ENUM('guest','host','admin') NOT NULL DEFAULT 'guest',
  subject VARCHAR(255) NULL,
  template MEDIUMTEXT NULL,
  action_value VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_wf_trigger (trigger_key, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  workflow_id INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED NOT NULL,
  scheduled_at DATETIME NOT NULL,
  status ENUM('pending','done','failed','skipped') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NULL,
  executed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wfr (workflow_id, booking_id, scheduled_at),
  KEY ix_wfr_due (status, scheduled_at),
  CONSTRAINT fk_wfr_wf FOREIGN KEY (workflow_id) REFERENCES workflows (id) ON DELETE CASCADE,
  CONSTRAINT fk_wfr_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mensajes de WhatsApp de un toque (wa.me) y/o enviados por la API opcional
CREATE TABLE IF NOT EXISTS message_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NULL,
  client_id INT UNSIGNED NULL,
  workflow_run_id INT UNSIGNED NULL,
  channel ENUM('whatsapp','whatsapp_api') NOT NULL DEFAULT 'whatsapp',
  phone VARCHAR(30) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('pending','sent','dismissed','failed') NOT NULL DEFAULT 'pending',
  due_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_mq_status (status, due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  to_email VARCHAR(190) NOT NULL,
  to_name VARCHAR(160) NULL,
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  body_text MEDIUMTEXT NULL,
  attachments MEDIUMTEXT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NULL,
  send_after DATETIME NOT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_eq_status (status, send_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhooks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  url VARCHAR(500) NOT NULL,
  secret VARCHAR(80) NOT NULL,
  events VARCHAR(500) NOT NULL DEFAULT '*',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  webhook_id INT UNSIGNED NOT NULL,
  event VARCHAR(60) NOT NULL,
  payload MEDIUMTEXT NOT NULL,
  status ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  response_code SMALLINT NULL,
  response_body VARCHAR(500) NULL,
  next_attempt_at DATETIME NOT NULL,
  delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_wd_due (status, next_attempt_at),
  CONSTRAINT fk_wd_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_keys (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  key_prefix VARCHAR(12) NOT NULL,
  key_hash CHAR(64) NOT NULL,
  scope ENUM('read','write') NOT NULL DEFAULT 'read',
  created_by INT UNSIGNED NULL,
  revoked_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_apikey_hash (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket VARCHAR(190) NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- step: view (vio la página) | slot (eligió horario) | booked (reservó)
CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type_id INT UNSIGNED NULL,
  host_id INT UNSIGNED NULL,
  visit_id CHAR(16) NOT NULL,
  step ENUM('view','slot','booked') NOT NULL,
  utm_source VARCHAR(100) NULL,
  utm_medium VARCHAR(100) NULL,
  utm_campaign VARCHAR(100) NULL,
  referrer_host VARCHAR(190) NULL,
  device VARCHAR(10) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_an_event (event_type_id, created_at),
  KEY ix_an_visit (visit_id, step),
  KEY ix_an_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token CHAR(32) NOT NULL,
  booking_id INT UNSIGNED NULL,
  host_id INT UNSIGNED NULL,
  client_name VARCHAR(160) NOT NULL,
  rating TINYINT UNSIGNED NULL,
  comment TEXT NULL,
  status ENUM('requested','pending','approved','rejected') NOT NULL DEFAULT 'requested',
  created_at DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reviews_token (token),
  KEY ix_reviews_host (host_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NULL,
  booking_id INT UNSIGNED NULL,
  email VARCHAR(190) NULL,
  document VARCHAR(20) NOT NULL,
  version INT UNSIGNED NOT NULL,
  text_hash CHAR(64) NOT NULL,
  ip_trunc VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_consents_client (client_id),
  KEY ix_consents_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  user_label VARCHAR(160) NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NULL,
  entity_id VARCHAR(40) NULL,
  detail VARCHAR(500) NULL,
  ip_trunc VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_audit_created (created_at),
  KEY ix_audit_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_la_email (email, created_at),
  KEY ix_la_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  applied_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_migrations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
