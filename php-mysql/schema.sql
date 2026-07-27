-- Cobranza La Roca — esquema MySQL (reemplazo de Supabase/Postgres)
-- Importar en phpMyAdmin (cPanel) sobre una base de datos vacía.

-- ══ USUARIOS (PIN + rol + carteras asignadas) ══
-- Reemplaza el objeto USUARIOS que antes estaba fijo dentro del JS.
CREATE TABLE IF NOT EXISTS usuarios (
  pin VARCHAR(10) PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  rol ENUM('admin','gerente','cobrador') NOT NULL DEFAULT 'cobrador',
  carteras JSON NOT NULL -- array de strings, p.ej. ["ROCA_COMERCIAL","MOTORS"]. Vacio [] = todas (admin/gerente).
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO usuarios (pin, nombre, rol, carteras) VALUES
  ('12345', 'Administrador', 'admin', JSON_ARRAY()),
  ('7777',  'Jorge',          'gerente', JSON_ARRAY()),
  ('1111',  'Equipo Roca',    'cobrador', JSON_ARRAY('ROCA_COMERCIAL','MOTORS','LIBERTAD','BARRIO')),
  ('2222',  'Equipo Su Mueble','cobrador', JSON_ARRAY('MUEBLE','MOTO','DANLI'))
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ══ CARTERAS ══
-- clientes/load_history se guardan igual que en Supabase: arreglos JSON completos,
-- reescritos enteros cada vez que se sube un PDF nuevo o se edita algo del cliente.
CREATE TABLE IF NOT EXISTS carteras (
  id VARCHAR(80) PRIMARY KEY,
  empresa VARCHAR(150) NOT NULL,
  fecha_emision VARCHAR(30) DEFAULT NULL,
  clientes JSON NOT NULL,
  load_history JSON NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══ PROMESAS ══
-- Estado de gestión vigente por cliente (promesa, pago, abono, visita...).
CREATE TABLE IF NOT EXISTS promesas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cartera_id VARCHAR(80) NOT NULL,
  cliente_nombre VARCHAR(200) NOT NULL,
  estado VARCHAR(30) DEFAULT NULL,
  fecha DATE DEFAULT NULL,
  monto DECIMAL(14,2) DEFAULT NULL,
  nota TEXT,
  mora_pendiente TINYINT(1) NOT NULL DEFAULT 0,
  adelantado TINYINT(1) NOT NULL DEFAULT 0,
  cero_prima TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_prom (cartera_id, cliente_nombre),
  INDEX idx_prom_cartera (cartera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══ HISTORIAL DE CLIENTES ══
-- Bitácora: notas, visitas, promesas, pagos, abonos por cliente.
CREATE TABLE IF NOT EXISTS historial_clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cartera_id VARCHAR(80) NOT NULL,
  cliente_nombre VARCHAR(200) NOT NULL,
  tipo VARCHAR(30) NOT NULL, -- promesa|pago|abono|nota|visita_agenda|visita_realizada|promesa_cumplida|promesa_incumplida
  monto DECIMAL(14,2) DEFAULT NULL,
  nota TEXT,
  fecha_accion DATE DEFAULT NULL,
  fecha_visita DATE DEFAULT NULL,
  gestor VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hist_cartera_cliente (cartera_id, cliente_nombre),
  INDEX idx_hist_tipo (tipo),
  INDEX idx_hist_fecha_visita (fecha_visita)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══ SNAPSHOTS ══
-- Fotografía de tramos de mora por cartera cada vez que se sube un PDF (usado por Pulso ECG).
CREATE TABLE IF NOT EXISTS snapshots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cartera_id VARCHAR(80) NOT NULL,
  fecha VARCHAR(30) DEFAULT NULL,
  tramos JSON NOT NULL,
  clientes_por_tramo JSON NOT NULL,
  gestion JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_snap_cartera (cartera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══ ALERTAS MANUALES ══
-- Mensajes que el admin/gerente envía al equipo (no relacionado con IA).
CREATE TABLE IF NOT EXISTS alertas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(20) NOT NULL DEFAULT 'info', -- info|animo|urgente
  titulo VARCHAR(200) NOT NULL,
  mensaje TEXT NOT NULL,
  cliente VARCHAR(200) DEFAULT NULL,
  autor VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_alertas_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alertas_lecturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alerta_id INT NOT NULL,
  usuario VARCHAR(100) NOT NULL,
  leido_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_lectura (alerta_id, usuario),
  FOREIGN KEY (alerta_id) REFERENCES alertas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══ PROYECCIONES ══
-- Proyección semanal de cobro editada por los asesores (mes en curso / próximo mes).
CREATE TABLE IF NOT EXISTS proyecciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cartera_id VARCHAR(80) NOT NULL,
  cliente_nombre VARCHAR(200) NOT NULL,
  semana_inicio VARCHAR(20) NOT NULL,
  monto_proyectado DECIMAL(14,2) DEFAULT NULL,
  fecha_proyectada DATE DEFAULT NULL,
  comentario_cierre TEXT,
  gestor VARCHAR(100) DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_proy (cartera_id, cliente_nombre, semana_inicio),
  INDEX idx_proy_cartera (cartera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══ CIERRE DE PROYECCIÓN ══
-- Snapshot mensual bloqueado ("cierre de mes") que usa el panel gerente
-- para comparar Sistema vs Asesores (columna "Proyectado").
CREATE TABLE IF NOT EXISTS cierre_proyeccion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cartera_id VARCHAR(80) NOT NULL,
  mes_key VARCHAR(10) NOT NULL, -- 'YYYY-MM'
  cerrado_por VARCHAR(100) DEFAULT NULL,
  cerrado_at DATETIME DEFAULT NULL,
  datos JSON NOT NULL, -- [{nombre,saldo,cuota,cuotasTrans,saldoEsperado,diaAsesor,montoAsesor,mora,moraVal}]
  pin_editor VARCHAR(10) DEFAULT NULL,
  UNIQUE KEY uk_cierre (cartera_id, mes_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══ MORA_OVERRIDES ══
-- Corrección manual de refinanciamiento (num. de cuotas / crédito total real) por cliente.
CREATE TABLE IF NOT EXISTS mora_overrides (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cartera_id VARCHAR(80) NOT NULL,
  cliente_nombre VARCHAR(200) NOT NULL,
  num_cuotas_real INT DEFAULT NULL,
  total_credito_real DECIMAL(14,2) DEFAULT NULL,
  nota TEXT,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mora_ov (cartera_id, cliente_nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
