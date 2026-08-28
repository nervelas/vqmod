-- =====================================================================
--  ResidencialPro — Esquema de base de datos
--  MySQL 8.0+ / MariaDB 10.4+   ·   utf8mb4_unicode_ci   ·   InnoDB
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- CONFIG
CREATE TABLE IF NOT EXISTS ajustes (
  clave           VARCHAR(80)  NOT NULL,
  valor           MEDIUMTEXT   NULL,
  grupo           VARCHAR(40)  NOT NULL DEFAULT 'general',
  PRIMARY KEY (clave),
  KEY idx_ajustes_grupo (grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- USUARIOS
CREATE TABLE IF NOT EXISTS usuarios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rol             ENUM('admin','junta','garita','residente','contabilidad') NOT NULL DEFAULT 'residente',
  nombre          VARCHAR(140) NOT NULL,
  usuario         VARCHAR(60)  NOT NULL,
  correo          VARCHAR(160) NULL,
  telefono        VARCHAR(40)  NULL,
  password_hash   VARCHAR(255) NOT NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  tema            VARCHAR(30)  NOT NULL DEFAULT 'verde-oro',
  modo_oscuro     TINYINT(1)   NOT NULL DEFAULT 0,
  dos_factores    TINYINT(1)   NOT NULL DEFAULT 0,
  onboarding      TINYINT(1)   NOT NULL DEFAULT 0,
  ultimo_acceso   DATETIME     NULL,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_usuario (usuario),
  UNIQUE KEY uq_usuarios_correo (correo),
  KEY idx_usuarios_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expira_en   DATETIME     NOT NULL,
  usado_en    DATETIME     NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pr_token (token_hash),
  KEY idx_pr_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intentos_acceso (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  llave       VARCHAR(190) NOT NULL,
  ip          VARCHAR(45)  NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ia_llave (llave, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS codigos_2fa (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED NOT NULL,
  codigo_hash CHAR(64)     NOT NULL,
  expira_en   DATETIME     NOT NULL,
  usado_en    DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_2fa_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- ESTRUCTURA
CREATE TABLE IF NOT EXISTS fases (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(90)  NOT NULL,
  descripcion VARCHAR(255) NULL,
  orden       INT          NOT NULL DEFAULT 0,
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calles (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase_id     INT UNSIGNED NOT NULL,
  nombre      VARCHAR(90)  NOT NULL,
  orden       INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_calles_fase (fase_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS casas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fase_id       INT UNSIGNED NOT NULL,
  calle_id      INT UNSIGNED NULL,
  codigo        VARCHAR(30)  NOT NULL,
  tipo          ENUM('casa','apartamento','lote','local') NOT NULL DEFAULT 'casa',
  metros        DECIMAL(10,2) NOT NULL DEFAULT 0,
  coeficiente   DECIMAL(8,5)  NOT NULL DEFAULT 0,
  parqueos      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bodegas       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  estado        ENUM('habitada','desocupada','venta','alquiler') NOT NULL DEFAULT 'habitada',
  restringida   TINYINT(1)   NOT NULL DEFAULT 0,
  mapa_x        DECIMAL(6,2) NULL,
  mapa_y        DECIMAL(6,2) NULL,
  foto          VARCHAR(190) NULL,
  notas         TEXT NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_casas_codigo (codigo),
  KEY idx_casas_fase (fase_id),
  KEY idx_casas_calle (calle_id),
  KEY idx_casas_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- RESIDENTES
CREATE TABLE IF NOT EXISTS residentes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id      INT UNSIGNED NOT NULL,
  usuario_id   INT UNSIGNED NULL,
  nombre       VARCHAR(140) NOT NULL,
  tipo         ENUM('propietario','inquilino','familiar') NOT NULL DEFAULT 'propietario',
  dpi          VARCHAR(30)  NULL,
  nit          VARCHAR(30)  NULL,
  correo       VARCHAR(160) NULL,
  telefono     VARCHAR(40)  NULL,
  whatsapp     VARCHAR(40)  NULL,
  fecha_inicio DATE         NULL,
  fecha_fin    DATE         NULL,
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  notas        TEXT NULL,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_res_casa (casa_id),
  KEY idx_res_usuario (usuario_id),
  KEY idx_res_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS residentes_historial (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id     INT UNSIGNED NOT NULL,
  residente   VARCHAR(140) NOT NULL,
  tipo        VARCHAR(30)  NOT NULL,
  accion      VARCHAR(40)  NOT NULL,
  detalle     TEXT NULL,
  usuario_id  INT UNSIGNED NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rh_casa (casa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehiculos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id     INT UNSIGNED NOT NULL,
  residente_id INT UNSIGNED NULL,
  placa       VARCHAR(20)  NOT NULL,
  marca       VARCHAR(60)  NULL,
  linea       VARCHAR(60)  NULL,
  color       VARCHAR(40)  NULL,
  anio        SMALLINT     NULL,
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_veh_casa (casa_id),
  KEY idx_veh_placa (placa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mascotas (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id   INT UNSIGNED NOT NULL,
  nombre    VARCHAR(60)  NOT NULL,
  especie   VARCHAR(40)  NULL,
  raza      VARCHAR(60)  NULL,
  color     VARCHAR(40)  NULL,
  vacunas   VARCHAR(190) NULL,
  PRIMARY KEY (id),
  KEY idx_mas_casa (casa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empleados_casa (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id    INT UNSIGNED NOT NULL,
  nombre     VARCHAR(140) NOT NULL,
  dpi        VARCHAR(30)  NULL,
  puesto     VARCHAR(60)  NULL,
  telefono   VARCHAR(40)  NULL,
  dias       VARCHAR(30)  NOT NULL DEFAULT '1,2,3,4,5',
  hora_desde TIME         NULL,
  hora_hasta TIME         NULL,
  activo     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_emp_casa (casa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id     INT UNSIGNED NULL,
  titulo      VARCHAR(160) NOT NULL,
  archivo     VARCHAR(190) NOT NULL,
  tipo_mime   VARCHAR(90)  NULL,
  publico     TINYINT(1)   NOT NULL DEFAULT 0,
  usuario_id  INT UNSIGNED NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_doc_casa (casa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- CUOTAS
CREATE TABLE IF NOT EXISTS conceptos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(120) NOT NULL,
  descripcion    VARCHAR(255) NULL,
  calculo        ENUM('fijo','coeficiente','metros') NOT NULL DEFAULT 'fijo',
  monto          DECIMAL(12,2) NOT NULL DEFAULT 0,
  periodicidad   ENUM('mensual','bimestral','trimestral','anual','unico') NOT NULL DEFAULT 'mensual',
  dia_vence      TINYINT UNSIGNED NOT NULL DEFAULT 10,
  mora_tipo      ENUM('ninguna','fijo','porcentaje') NOT NULL DEFAULT 'porcentaje',
  mora_valor     DECIMAL(10,2) NOT NULL DEFAULT 0,
  pronto_pago    DECIMAL(10,2) NOT NULL DEFAULT 0,
  pronto_dias    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  automatico     TINYINT(1)   NOT NULL DEFAULT 1,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  orden          INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cargos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id      INT UNSIGNED NOT NULL,
  concepto_id  INT UNSIGNED NULL,
  periodo      CHAR(7)      NULL,
  descripcion  VARCHAR(190) NOT NULL,
  monto        DECIMAL(12,2) NOT NULL DEFAULT 0,
  mora         DECIMAL(12,2) NOT NULL DEFAULT 0,
  descuento    DECIMAL(12,2) NOT NULL DEFAULT 0,
  pagado       DECIMAL(12,2) NOT NULL DEFAULT 0,
  fecha_emision DATE        NOT NULL,
  fecha_vence  DATE         NOT NULL,
  estado       ENUM('pendiente','parcial','pagado','anulado') NOT NULL DEFAULT 'pendiente',
  origen       VARCHAR(30)  NOT NULL DEFAULT 'manual',
  referencia_id INT UNSIGNED NULL,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cargo_periodo (casa_id, concepto_id, periodo),
  KEY idx_cargo_casa (casa_id),
  KEY idx_cargo_estado (estado),
  KEY idx_cargo_vence (fecha_vence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id       INT UNSIGNED NOT NULL,
  recibo        VARCHAR(20)  NULL,
  fecha         DATE         NOT NULL,
  monto         DECIMAL(12,2) NOT NULL DEFAULT 0,
  metodo        ENUM('efectivo','transferencia','deposito','tarjeta','linea','otro') NOT NULL DEFAULT 'transferencia',
  referencia    VARCHAR(90)  NULL,
  banco         VARCHAR(90)  NULL,
  cuenta_id     INT UNSIGNED NULL,
  comprobante   VARCHAR(190) NULL,
  estado        ENUM('revision','aprobado','rechazado','anulado') NOT NULL DEFAULT 'aprobado',
  motivo_rechazo VARCHAR(255) NULL,
  notas         TEXT NULL,
  registrado_por INT UNSIGNED NULL,
  aprobado_por  INT UNSIGNED NULL,
  aprobado_en   DATETIME     NULL,
  verificacion  CHAR(32)     NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pago_recibo (recibo),
  KEY idx_pago_casa (casa_id),
  KEY idx_pago_estado (estado),
  KEY idx_pago_fecha (fecha),
  KEY idx_pago_verif (verificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos_detalle (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pago_id   INT UNSIGNED NOT NULL,
  cargo_id  INT UNSIGNED NULL,
  concepto  VARCHAR(190) NOT NULL,
  monto     DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pd_pago (pago_id),
  KEY idx_pd_cargo (cargo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cobranza_log (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id    INT UNSIGNED NOT NULL,
  tipo       VARCHAR(40)  NOT NULL,
  canal      VARCHAR(20)  NOT NULL DEFAULT 'correo',
  detalle    VARCHAR(255) NULL,
  saldo      DECIMAL(12,2) NOT NULL DEFAULT 0,
  creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cl_casa (casa_id, tipo, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- EGRESOS
CREATE TABLE IF NOT EXISTS cuentas (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(120) NOT NULL,
  tipo      ENUM('caja','banco') NOT NULL DEFAULT 'banco',
  banco     VARCHAR(90)  NULL,
  numero    VARCHAR(60)  NULL,
  saldo_inicial DECIMAL(14,2) NOT NULL DEFAULT 0,
  activo    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proveedores (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(140) NOT NULL,
  nit       VARCHAR(30)  NULL,
  contacto  VARCHAR(120) NULL,
  telefono  VARCHAR(40)  NULL,
  correo    VARCHAR(160) NULL,
  servicio  VARCHAR(120) NULL,
  activo    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias_egreso (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(120) NOT NULL,
  color   VARCHAR(10)  NOT NULL DEFAULT '#B94E27',
  activo  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS egresos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id INT UNSIGNED NULL,
  proveedor_id INT UNSIGNED NULL,
  cuenta_id    INT UNSIGNED NULL,
  fecha        DATE          NOT NULL,
  monto        DECIMAL(12,2) NOT NULL DEFAULT 0,
  descripcion  VARCHAR(190)  NOT NULL,
  documento    VARCHAR(60)   NULL,
  archivo      VARCHAR(190)  NULL,
  metodo       ENUM('efectivo','transferencia','cheque','tarjeta','otro') NOT NULL DEFAULT 'transferencia',
  estado       ENUM('registrado','anulado') NOT NULL DEFAULT 'registrado',
  usuario_id   INT UNSIGNED  NULL,
  creado_en    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_eg_fecha (fecha),
  KEY idx_eg_cat (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS presupuestos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  anio         SMALLINT     NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  monto        DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pres (anio, categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- GARITA
CREATE TABLE IF NOT EXISTS preregistros (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id      INT UNSIGNED NOT NULL,
  usuario_id   INT UNSIGNED NULL,
  visitante    VARCHAR(140) NOT NULL,
  dpi          VARCHAR(30)  NULL,
  placa        VARCHAR(20)  NULL,
  motivo       VARCHAR(140) NULL,
  codigo       CHAR(6)      NOT NULL,
  firma        CHAR(64)     NOT NULL,
  recurrente   TINYINT(1)   NOT NULL DEFAULT 0,
  dias         VARCHAR(20)  NULL,
  hora_desde   TIME         NULL,
  hora_hasta   TIME         NULL,
  valido_desde DATETIME     NOT NULL,
  valido_hasta DATETIME     NOT NULL,
  usos         INT UNSIGNED NOT NULL DEFAULT 0,
  max_usos     INT UNSIGNED NOT NULL DEFAULT 1,
  estado       ENUM('activo','usado','vencido','cancelado') NOT NULL DEFAULT 'activo',
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prereg_codigo (codigo),
  KEY idx_prereg_casa (casa_id),
  KEY idx_prereg_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitas (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id      INT UNSIGNED NULL,
  prereg_id    INT UNSIGNED NULL,
  tipo         ENUM('visita','proveedor','delivery','servicio','empleado','mudanza') NOT NULL DEFAULT 'visita',
  visitante    VARCHAR(140) NOT NULL,
  dpi          VARCHAR(30)  NULL,
  placa        VARCHAR(20)  NULL,
  vehiculo     VARCHAR(90)  NULL,
  personas     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  motivo       VARCHAR(190) NULL,
  foto         VARCHAR(190) NULL,
  entrada      DATETIME     NOT NULL,
  salida       DATETIME     NULL,
  guardia_in   INT UNSIGNED NULL,
  guardia_out  INT UNSIGNED NULL,
  autorizado   TINYINT(1)   NOT NULL DEFAULT 1,
  uuid         CHAR(32)     NULL,
  notas        TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_visita_uuid (uuid),
  KEY idx_vis_casa (casa_id),
  KEY idx_vis_entrada (entrada),
  KEY idx_vis_placa (placa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS turnos (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  inicio     DATETIME     NOT NULL,
  fin        DATETIME     NULL,
  novedades  TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_tur_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bitacora_garita (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  turno_id   INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NOT NULL,
  tipo       VARCHAR(40)  NOT NULL DEFAULT 'novedad',
  texto      TEXT         NOT NULL,
  creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bg_fecha (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emergencias (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  casa_id    INT UNSIGNED NULL,
  tipo       VARCHAR(40)  NOT NULL DEFAULT 'panico',
  detalle    VARCHAR(255) NULL,
  atendido   TINYINT(1)   NOT NULL DEFAULT 0,
  atendido_por INT UNSIGNED NULL,
  creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_em_fecha (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- AREAS
CREATE TABLE IF NOT EXISTS areas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(120) NOT NULL,
  descripcion   TEXT NULL,
  reglas        TEXT NULL,
  capacidad     INT UNSIGNED NOT NULL DEFAULT 0,
  costo         DECIMAL(12,2) NOT NULL DEFAULT 0,
  deposito      DECIMAL(12,2) NOT NULL DEFAULT 0,
  hora_desde    TIME NOT NULL DEFAULT '08:00:00',
  hora_hasta    TIME NOT NULL DEFAULT '22:00:00',
  duracion_min  INT UNSIGNED NOT NULL DEFAULT 240,
  aprobacion    ENUM('automatica','manual') NOT NULL DEFAULT 'manual',
  bloquea_mora  TINYINT(1)   NOT NULL DEFAULT 1,
  dias          VARCHAR(20)  NOT NULL DEFAULT '0,1,2,3,4,5,6',
  foto          VARCHAR(190) NULL,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservas (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_id    INT UNSIGNED NOT NULL,
  casa_id    INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  fecha      DATE         NOT NULL,
  hora_desde TIME         NOT NULL,
  hora_hasta TIME         NOT NULL,
  personas   INT UNSIGNED NOT NULL DEFAULT 1,
  motivo     VARCHAR(190) NULL,
  costo      DECIMAL(12,2) NOT NULL DEFAULT 0,
  cargo_id   INT UNSIGNED NULL,
  estado     ENUM('pendiente','aprobada','rechazada','cancelada','completada') NOT NULL DEFAULT 'pendiente',
  motivo_rechazo VARCHAR(255) NULL,
  creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_res_area_fecha (area_id, fecha),
  KEY idx_res_casa2 (casa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- COMUNICACION
CREATE TABLE IF NOT EXISTS avisos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo      VARCHAR(190) NOT NULL,
  cuerpo      MEDIUMTEXT   NOT NULL,
  alcance     ENUM('todos','fase','calle','casa') NOT NULL DEFAULT 'todos',
  destino_id  INT UNSIGNED NULL,
  prioridad   ENUM('normal','importante','urgente') NOT NULL DEFAULT 'normal',
  imagen      VARCHAR(190) NULL,
  archivo     VARCHAR(190) NULL,
  publicar_en DATETIME     NOT NULL,
  vence_en    DATETIME     NULL,
  confirmar   TINYINT(1)   NOT NULL DEFAULT 0,
  usuario_id  INT UNSIGNED NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_av_publicar (publicar_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avisos_lecturas (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  aviso_id   INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  casa_id    INT UNSIGNED NULL,
  leido_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_al (aviso_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo    VARCHAR(190) NOT NULL,
  detalle   TEXT NULL,
  tipo      ENUM('asamblea','mantenimiento','social','otro') NOT NULL DEFAULT 'otro',
  inicio    DATETIME NOT NULL,
  fin       DATETIME NULL,
  lugar     VARCHAR(140) NULL,
  publico   TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_ev_inicio (inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS votaciones (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo    VARCHAR(190) NOT NULL,
  detalle   TEXT NULL,
  modo      ENUM('casa','coeficiente') NOT NULL DEFAULT 'casa',
  inicio    DATETIME NOT NULL,
  fin       DATETIME NOT NULL,
  quorum    DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  estado    ENUM('borrador','abierta','cerrada') NOT NULL DEFAULT 'borrador',
  acta      VARCHAR(190) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS votacion_opciones (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  votacion_id  INT UNSIGNED NOT NULL,
  texto        VARCHAR(190) NOT NULL,
  orden        INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_vo_vot (votacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS votos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  votacion_id INT UNSIGNED NOT NULL,
  opcion_id   INT UNSIGNED NOT NULL,
  casa_id     INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED NULL,
  peso        DECIMAL(8,5) NOT NULL DEFAULT 1,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_voto (votacion_id, casa_id),
  KEY idx_voto_op (opcion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incidencias (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  casa_id     INT UNSIGNED NULL,
  usuario_id  INT UNSIGNED NULL,
  categoria   VARCHAR(60)  NOT NULL DEFAULT 'general',
  titulo      VARCHAR(190) NOT NULL,
  descripcion TEXT NOT NULL,
  ubicacion   VARCHAR(190) NULL,
  foto        VARCHAR(190) NULL,
  prioridad   ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  estado      ENUM('recibida','proceso','resuelta','cerrada') NOT NULL DEFAULT 'recibida',
  asignado_a  INT UNSIGNED NULL,
  resolucion  TEXT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resuelto_en DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_inc_estado (estado),
  KEY idx_inc_casa (casa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incidencia_seguimiento (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  incidencia_id INT UNSIGNED NOT NULL,
  usuario_id    INT UNSIGNED NULL,
  texto         TEXT NOT NULL,
  estado        VARCHAR(30) NULL,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_is_inc (incidencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensajes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hilo_id     INT UNSIGNED NULL,
  casa_id     INT UNSIGNED NULL,
  de_usuario  INT UNSIGNED NOT NULL,
  para_rol    VARCHAR(20)  NULL,
  para_usuario INT UNSIGNED NULL,
  asunto      VARCHAR(190) NULL,
  cuerpo      TEXT NOT NULL,
  leido_en    DATETIME NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_msg_hilo (hilo_id),
  KEY idx_msg_casa (casa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contactos_emergencia (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre   VARCHAR(120) NOT NULL,
  telefono VARCHAR(60)  NOT NULL,
  tipo     VARCHAR(60)  NULL,
  orden    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificaciones (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  titulo     VARCHAR(190) NOT NULL,
  cuerpo     VARCHAR(255) NULL,
  url        VARCHAR(190) NULL,
  icono      VARCHAR(40)  NULL DEFAULT 'bell',
  leido_en   DATETIME NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_not_usuario (usuario_id, leido_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  endpoint   VARCHAR(500) NOT NULL,
  p256dh     VARCHAR(190) NOT NULL,
  auth_key   VARCHAR(120) NOT NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_push_ep (endpoint(191)),
  KEY idx_push_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cola_correo (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  para       VARCHAR(190) NOT NULL,
  nombre     VARCHAR(140) NULL,
  asunto     VARCHAR(190) NOT NULL,
  cuerpo     MEDIUMTEXT NOT NULL,
  adjunto    VARCHAR(190) NULL,
  intentos   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  estado     ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  error      VARCHAR(255) NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_en DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_cc_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- SITIO
CREATE TABLE IF NOT EXISTS galeria (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo   VARCHAR(140) NULL,
  archivo  VARCHAR(190) NOT NULL,
  orden    INT NOT NULL DEFAULT 0,
  activo   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amenidades (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo  VARCHAR(140) NOT NULL,
  detalle VARCHAR(255) NULL,
  icono   VARCHAR(40)  NOT NULL DEFAULT 'sparkles',
  orden   INT NOT NULL DEFAULT 0,
  activo  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contactos_web (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(140) NOT NULL,
  correo    VARCHAR(160) NULL,
  telefono  VARCHAR(40)  NULL,
  mensaje   TEXT NOT NULL,
  ip        VARCHAR(45)  NULL,
  atendido  TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- AUDITORIA
CREATE TABLE IF NOT EXISTS auditoria (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NULL,
  usuario    VARCHAR(140) NULL,
  accion     VARCHAR(80)  NOT NULL,
  entidad    VARCHAR(60)  NULL,
  entidad_id INT UNSIGNED NULL,
  detalle    TEXT NULL,
  ip         VARCHAR(45)  NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aud_fecha (creado_en),
  KEY idx_aud_accion (accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_ejecuciones (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tarea     VARCHAR(60) NOT NULL,
  resultado VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cron_tarea (tarea, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
