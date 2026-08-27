-- ============================================================
--  EduPortal - Sistema Integral de Gestion para Colegios
--  Esquema base  (MySQL 8 / MariaDB 10.4+)  utf8mb4
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Configuracion ----------
CREATE TABLE IF NOT EXISTS settings (
  clave        VARCHAR(80) NOT NULL,
  valor        TEXT NULL,
  grupo        VARCHAR(40) NOT NULL DEFAULT 'general',
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Usuarios ----------
CREATE TABLE IF NOT EXISTS users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(120) NOT NULL,
  email          VARCHAR(160) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  rol            ENUM('superadmin','secretaria','docente','padre') NOT NULL DEFAULT 'padre',
  telefono       VARCHAR(40) NULL,
  foto           VARCHAR(180) NULL,
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  tema           VARCHAR(30) NOT NULL DEFAULT 'default',
  modo_oscuro    TINYINT(1) NOT NULL DEFAULT 0,
  twofa          TINYINT(1) NOT NULL DEFAULT 0,
  twofa_codigo   VARCHAR(255) NULL,
  twofa_expira   DATETIME NULL,
  sesion_serie   VARCHAR(64) NULL,
  debe_cambiar   TINYINT(1) NOT NULL DEFAULT 0,
  ultimo_acceso  DATETIME NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expira_en   DATETIME NOT NULL,
  usado       TINYINT(1) NOT NULL DEFAULT 0,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pr_token (token_hash),
  KEY ix_pr_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  llave      VARCHAR(190) NOT NULL,
  exito      TINYINT(1) NOT NULL DEFAULT 0,
  ip         VARCHAR(45) NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_la_llave (llave, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Estructura academica ----------
CREATE TABLE IF NOT EXISTS ciclos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(60) NOT NULL,
  fecha_inicio DATE NULL,
  fecha_fin    DATE NULL,
  activo       TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ciclo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS niveles (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  orden  SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grados (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nivel_id  INT UNSIGNED NOT NULL,
  nombre    VARCHAR(60) NOT NULL,
  orden     SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_grados_nivel (nivel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secciones (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  grado_id        INT UNSIGNED NOT NULL,
  ciclo_id        INT UNSIGNED NOT NULL,
  nombre          VARCHAR(30) NOT NULL,
  capacidad       SMALLINT NOT NULL DEFAULT 30,
  docente_guia_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_sec_grado (grado_id),
  KEY ix_sec_ciclo (ciclo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materias (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre   VARCHAR(90) NOT NULL,
  codigo   VARCHAR(20) NULL,
  nivel_id INT UNSIGNED NULL,
  activo   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asignaciones (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ciclo_id   INT UNSIGNED NOT NULL,
  seccion_id INT UNSIGNED NOT NULL,
  materia_id INT UNSIGNED NOT NULL,
  docente_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asig (ciclo_id, seccion_id, materia_id),
  KEY ix_asig_doc (docente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS periodos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ciclo_id     INT UNSIGNED NOT NULL,
  nombre       VARCHAR(60) NOT NULL,
  orden        SMALLINT NOT NULL DEFAULT 1,
  fecha_inicio DATE NULL,
  fecha_fin    DATE NULL,
  cerrado      TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_per_ciclo (ciclo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Alumnos ----------
CREATE TABLE IF NOT EXISTS alumnos (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo            VARCHAR(30) NOT NULL,
  nombres           VARCHAR(120) NOT NULL,
  apellidos         VARCHAR(120) NOT NULL,
  foto              VARCHAR(180) NULL,
  dpi               VARCHAR(30) NULL,
  partida           VARCHAR(60) NULL,
  fecha_nacimiento  DATE NULL,
  genero            ENUM('M','F','O') NULL,
  direccion         VARCHAR(255) NULL,
  alergias          TEXT NULL,
  observaciones     TEXT NULL,
  emergencia_nombre VARCHAR(120) NULL,
  emergencia_tel    VARCHAR(40) NULL,
  estado            ENUM('activo','retirado','graduado') NOT NULL DEFAULT 'activo',
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alu_codigo (codigo),
  KEY ix_alu_nombre (apellidos, nombres)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscripciones (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alumno_id  INT UNSIGNED NOT NULL,
  ciclo_id   INT UNSIGNED NOT NULL,
  seccion_id INT UNSIGNED NOT NULL,
  fecha      DATE NULL,
  beca_pct   DECIMAL(5,2) NOT NULL DEFAULT 0,
  estado     ENUM('activo','retirado','graduado') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (id),
  UNIQUE KEY uq_ins (alumno_id, ciclo_id),
  KEY ix_ins_sec (seccion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS encargados (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alumno_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  nombre     VARCHAR(140) NOT NULL,
  parentesco VARCHAR(40) NULL,
  telefono   VARCHAR(40) NULL,
  email      VARCHAR(160) NULL,
  dpi        VARCHAR(30) NULL,
  principal  TINYINT(1) NOT NULL DEFAULT 0,
  orden      SMALLINT NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY ix_enc_alumno (alumno_id),
  KEY ix_enc_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alumno_id INT UNSIGNED NOT NULL,
  nombre    VARCHAR(160) NOT NULL,
  archivo   VARCHAR(190) NOT NULL,
  mime      VARCHAR(90) NULL,
  tamano    INT UNSIGNED NULL,
  subido_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_doc_alumno (alumno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cobranza ----------
CREATE TABLE IF NOT EXISTS conceptos (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ciclo_id         INT UNSIGNED NOT NULL,
  nombre           VARCHAR(120) NOT NULL,
  tipo             ENUM('inscripcion','colegiatura','transporte','uniforme','actividad','otro') NOT NULL DEFAULT 'otro',
  monto            DECIMAL(10,2) NOT NULL DEFAULT 0,
  recurrente       TINYINT(1) NOT NULL DEFAULT 0,
  dia_vencimiento  TINYINT UNSIGNED NOT NULL DEFAULT 5,
  mora_tipo        ENUM('fijo','porcentaje') NOT NULL DEFAULT 'fijo',
  mora_valor       DECIMAL(10,2) NOT NULL DEFAULT 0,
  mora_gracia      SMALLINT NOT NULL DEFAULT 0,
  aplica_beca      TINYINT(1) NOT NULL DEFAULT 1,
  aplica_hermanos  TINYINT(1) NOT NULL DEFAULT 1,
  nivel_id         INT UNSIGNED NULL,
  activo           TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY ix_con_ciclo (ciclo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cargos (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alumno_id        INT UNSIGNED NOT NULL,
  ciclo_id         INT UNSIGNED NOT NULL,
  concepto_id      INT UNSIGNED NULL,
  descripcion      VARCHAR(160) NOT NULL,
  anio             SMALLINT NOT NULL,
  mes              TINYINT NOT NULL DEFAULT 0,
  monto            DECIMAL(10,2) NOT NULL DEFAULT 0,
  descuento        DECIMAL(10,2) NOT NULL DEFAULT 0,
  mora             DECIMAL(10,2) NOT NULL DEFAULT 0,
  pagado           DECIMAL(10,2) NOT NULL DEFAULT 0,
  fecha_vencimiento DATE NOT NULL,
  estado           ENUM('pendiente','parcial','pagado','anulado') NOT NULL DEFAULT 'pendiente',
  creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cargo (alumno_id, concepto_id, anio, mes),
  KEY ix_cargo_estado (estado, fecha_vencimiento),
  KEY ix_cargo_alumno (alumno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recibo_no      VARCHAR(20) NULL,
  alumno_id      INT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED NULL,
  metodo         ENUM('efectivo','transferencia','tarjeta','deposito','linea') NOT NULL DEFAULT 'efectivo',
  monto          DECIMAL(10,2) NOT NULL DEFAULT 0,
  referencia     VARCHAR(90) NULL,
  notas          VARCHAR(255) NULL,
  fecha          DATE NOT NULL,
  estado         ENUM('revision','aprobado','rechazado','anulado') NOT NULL DEFAULT 'aprobado',
  comprobante    VARCHAR(190) NULL,
  motivo_rechazo VARCHAR(255) NULL,
  revisado_por   INT UNSIGNED NULL,
  revisado_en    DATETIME NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recibo (recibo_no),
  KEY ix_pago_alumno (alumno_id),
  KEY ix_pago_estado (estado),
  KEY ix_pago_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pago_detalle (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pago_id  INT UNSIGNED NOT NULL,
  cargo_id INT UNSIGNED NOT NULL,
  monto    DECIMAL(10,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_pd_pago (pago_id),
  KEY ix_pd_cargo (cargo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS correlativos (
  tipo  VARCHAR(30) NOT NULL,
  valor INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recordatorios (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cargo_id  INT UNSIGNED NOT NULL,
  tipo      VARCHAR(30) NOT NULL,
  canal     VARCHAR(20) NOT NULL DEFAULT 'correo',
  enviado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rec_cargo (cargo_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Notas ----------
CREATE TABLE IF NOT EXISTS actividades (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asignacion_id INT UNSIGNED NOT NULL,
  periodo_id    INT UNSIGNED NOT NULL,
  nombre        VARCHAR(120) NOT NULL,
  tipo          ENUM('zona','examen') NOT NULL DEFAULT 'zona',
  ponderacion   DECIMAL(6,2) NOT NULL DEFAULT 10,
  fecha         DATE NULL,
  PRIMARY KEY (id),
  KEY ix_act_asig (asignacion_id, periodo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  actividad_id  INT UNSIGNED NOT NULL,
  alumno_id     INT UNSIGNED NOT NULL,
  punteo        DECIMAL(6,2) NULL,
  comentario    VARCHAR(255) NULL,
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nota (actividad_id, alumno_id),
  KEY ix_nota_alumno (alumno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notas_periodo (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alumno_id     INT UNSIGNED NOT NULL,
  asignacion_id INT UNSIGNED NOT NULL,
  periodo_id    INT UNSIGNED NOT NULL,
  zona          DECIMAL(6,2) NOT NULL DEFAULT 0,
  examen        DECIMAL(6,2) NOT NULL DEFAULT 0,
  total         DECIMAL(6,2) NOT NULL DEFAULT 0,
  conducta      DECIMAL(6,2) NULL,
  comentario    VARCHAR(255) NULL,
  actualizado_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_np (alumno_id, asignacion_id, periodo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Asistencia ----------
CREATE TABLE IF NOT EXISTS asistencia (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alumno_id  INT UNSIGNED NOT NULL,
  seccion_id INT UNSIGNED NOT NULL,
  fecha      DATE NOT NULL,
  estado     ENUM('presente','ausente','tarde','justificado') NOT NULL DEFAULT 'presente',
  nota       VARCHAR(180) NULL,
  registrado_por INT UNSIGNED NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asis (alumno_id, fecha),
  KEY ix_asis_sec (seccion_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Comunicacion ----------
CREATE TABLE IF NOT EXISTS avisos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo      VARCHAR(180) NOT NULL,
  contenido   MEDIUMTEXT NULL,
  autor_id    INT UNSIGNED NULL,
  destino     ENUM('todos','nivel','grado','seccion','alumno','rol') NOT NULL DEFAULT 'todos',
  destino_id  INT UNSIGNED NULL,
  destino_rol VARCHAR(20) NULL,
  imagen      VARCHAR(190) NULL,
  adjunto     VARCHAR(190) NULL,
  publicar_en DATETIME NULL,
  caduca_en   DATETIME NULL,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_avi_pub (activo, publicar_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aviso_lecturas (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  aviso_id INT UNSIGNED NOT NULL,
  user_id  INT UNSIGNED NOT NULL,
  leido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_al (aviso_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo       VARCHAR(160) NOT NULL,
  descripcion  TEXT NULL,
  tipo         ENUM('evento','feriado','examen','entrega','otro') NOT NULL DEFAULT 'evento',
  fecha_inicio DATE NOT NULL,
  fecha_fin    DATE NULL,
  publico      TINYINT(1) NOT NULL DEFAULT 1,
  color        VARCHAR(12) NULL,
  PRIMARY KEY (id),
  KEY ix_ev_fecha (fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tareas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asignacion_id INT UNSIGNED NOT NULL,
  titulo        VARCHAR(180) NOT NULL,
  descripcion   TEXT NULL,
  adjunto       VARCHAR(190) NULL,
  fecha_entrega DATE NULL,
  puntos        DECIMAL(6,2) NOT NULL DEFAULT 0,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_tar_asig (asignacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tarea_entregas (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tarea_id     INT UNSIGNED NOT NULL,
  alumno_id    INT UNSIGNED NOT NULL,
  estado       ENUM('pendiente','entregado','revisado') NOT NULL DEFAULT 'entregado',
  comentario   VARCHAR(255) NULL,
  archivo      VARCHAR(190) NULL,
  entregado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_te (tarea_id, alumno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensajes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  de_id      INT UNSIGNED NOT NULL,
  para_id    INT UNSIGNED NOT NULL,
  alumno_id  INT UNSIGNED NULL,
  cuerpo     TEXT NOT NULL,
  leido_en   DATETIME NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_msg_par (de_id, para_id),
  KEY ix_msg_para (para_id, leido_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificaciones (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id   INT UNSIGNED NOT NULL,
  titulo    VARCHAR(160) NOT NULL,
  cuerpo    VARCHAR(255) NULL,
  url       VARCHAR(190) NULL,
  leido_en  DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_not_user (user_id, leido_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subs (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id   INT UNSIGNED NOT NULL,
  endpoint  VARCHAR(500) NOT NULL,
  p256dh    VARCHAR(255) NULL,
  auth_key  VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ps_user (user_id),
  KEY ix_ps_ep (endpoint(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Sitio publico ----------
CREATE TABLE IF NOT EXISTS paginas (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug      VARCHAR(60) NOT NULL,
  titulo    VARCHAR(160) NOT NULL,
  contenido MEDIUMTEXT NULL,
  activo    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pag_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS galeria (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo  VARCHAR(160) NULL,
  archivo VARCHAR(190) NOT NULL,
  orden   SMALLINT NOT NULL DEFAULT 0,
  activo  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preinscripciones (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alumno_nombre   VARCHAR(160) NOT NULL,
  fecha_nacimiento DATE NULL,
  grado_id        INT UNSIGNED NULL,
  encargado       VARCHAR(160) NOT NULL,
  telefono        VARCHAR(40) NOT NULL,
  email           VARCHAR(160) NULL,
  mensaje         TEXT NULL,
  estado          ENUM('nueva','contactada','inscrita','descartada') NOT NULL DEFAULT 'nueva',
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pre_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contactos (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(160) NOT NULL,
  email     VARCHAR(160) NULL,
  telefono  VARCHAR(40) NULL,
  mensaje   TEXT NOT NULL,
  leido     TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Auditoria ----------
CREATE TABLE IF NOT EXISTS bitacora (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id   INT UNSIGNED NULL,
  accion    VARCHAR(60) NOT NULL,
  entidad   VARCHAR(60) NULL,
  entidad_id INT UNSIGNED NULL,
  detalle   VARCHAR(500) NULL,
  ip        VARCHAR(45) NULL,
  agente    VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_bit_user (user_id, creado_en),
  KEY ix_bit_fecha (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_log (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tarea     VARCHAR(60) NOT NULL,
  detalle   VARCHAR(500) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_cl_tarea (tarea, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Datos iniciales ----------
INSERT INTO settings (clave, valor, grupo) VALUES
 ('colegio_nombre','EduPortal','general'),
 ('colegio_lema','Formacion integral con excelencia','general'),
 ('colegio_direccion','','general'),
 ('colegio_telefono','','general'),
 ('colegio_whatsapp','','general'),
 ('colegio_email','','general'),
 ('colegio_nit','C/F','general'),
 ('colegio_logo','','general'),
 ('colegio_favicon','','general'),
 ('tema','default','apariencia'),
 ('color_personalizado','','apariencia'),
 ('moneda','Q','general'),
 ('zona_horaria','America/Guatemala','general'),
 ('nota_minima','60','academico'),
 ('nota_maxima','100','academico'),
 ('pond_zona','60','academico'),
 ('pond_examen','40','academico'),
 ('ranking_boleta','0','academico'),
 ('director_nombre','','general'),
 ('director_firma','','general'),
 ('recibo_texto','Gracias por su pago puntual.','cobranza'),
 ('recibo_prefijo','R','cobranza'),
 ('descuento_hermanos','0','cobranza'),
 ('meta_ingresos','0','cobranza'),
 ('pago_link','','cobranza'),
 ('recordatorio_previo_dias','3','cobranza'),
 ('recordatorio_mora_cada','7','cobranza'),
 ('plantilla_wa','Estimado/a {encargado}, le recordamos el saldo pendiente de {alumno} por {monto} con vencimiento {vence}. Gracias. {colegio}','cobranza'),
 ('plantilla_correo','<p>Estimado/a {encargado},</p><p>Le recordamos que {alumno} tiene un saldo pendiente de <strong>{monto}</strong> con fecha de vencimiento {vence}.</p><p>{colegio}</p>','cobranza'),
 ('smtp_host','','correo'),
 ('smtp_puerto','587','correo'),
 ('smtp_usuario','','correo'),
 ('smtp_password','','correo'),
 ('smtp_seguridad','tls','correo'),
 ('smtp_remitente','','correo'),
 ('smtp_nombre','EduPortal','correo'),
 ('smtp_activo','0','correo'),
 ('seo_title','EduPortal | Colegio','sitio'),
 ('seo_description','Formacion academica de excelencia.','sitio'),
 ('seo_og','','sitio'),
 ('sitio_activo','1','sitio'),
 ('sitio_hero_titulo','Educamos para la vida','sitio'),
 ('sitio_hero_texto','Un colegio donde cada estudiante encuentra su mejor version.','sitio'),
 ('sitio_hero_imagen','','sitio'),
 ('sitio_mision','','sitio'),
 ('sitio_vision','','sitio'),
 ('sitio_mapa','','sitio'),
 ('sitio_inscripcion','1','sitio'),
 ('subida_max_mb','8','seguridad'),
 ('vapid_public','','push'),
 ('vapid_private','','push'),
 ('cron_token','','seguridad'),
 ('backup_semanal','1','seguridad'),
 ('version','1.0.0','general')
ON DUPLICATE KEY UPDATE valor = valor;

INSERT INTO correlativos (tipo, valor) VALUES ('recibo', 0)
ON DUPLICATE KEY UPDATE valor = valor;

INSERT INTO paginas (slug, titulo, contenido, activo) VALUES
 ('mision','Nuestra Mision','<p>Formar personas integras, criticas y solidarias.</p>',1),
 ('vision','Nuestra Vision','<p>Ser el colegio de referencia por su excelencia academica y humana.</p>',1)
ON DUPLICATE KEY UPDATE titulo = titulo;
