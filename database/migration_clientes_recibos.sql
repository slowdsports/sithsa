-- ============================================================
-- SITHSA - Módulo de Clientes y Recibos por Retención de Dinero
-- (Recibo de ingreso por dinero en custodia/fideicomiso,
--  NO por venta de mercadería ni servicios)
-- ============================================================

-- Catálogo de Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE,
    tipo_cliente ENUM('natural','juridico') DEFAULT 'juridico',
    nombre VARCHAR(200) NOT NULL,
    rtn VARCHAR(20),
    contacto VARCHAR(100),
    telefono VARCHAR(20),
    email VARCHAR(150),
    direccion TEXT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recibos de Ingreso por Retención de Dinero (custodia, no venta)
CREATE TABLE IF NOT EXISTS recibos_retencion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    fecha DATE NOT NULL,
    cliente_id INT NOT NULL,
    concepto VARCHAR(300) NOT NULL,
    monto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    forma_recepcion ENUM('efectivo','cheque','transferencia') DEFAULT 'efectivo',
    numero_cheque VARCHAR(50),
    cuenta_bancaria_id INT,
    cuenta_id INT,
    proyecto_id INT,
    recibido_por_id INT,
    estado ENUM('activo','devuelto','anulado') DEFAULT 'activo',
    fecha_devolucion DATE,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id)          REFERENCES clientes(id),
    FOREIGN KEY (cuenta_bancaria_id)  REFERENCES cuentas_bancarias(id) ON DELETE SET NULL,
    FOREIGN KEY (cuenta_id)           REFERENCES cuentas_contables(id) ON DELETE SET NULL,
    FOREIGN KEY (proyecto_id)         REFERENCES proyectos(id) ON DELETE SET NULL,
    FOREIGN KEY (recibido_por_id)     REFERENCES empleados(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
    ('prefijo_rr', 'RR', 'Prefijo de numeración para Recibos de Retención');

-- Cuenta contable de pasivo sugerida para dinero recibido en custodia
-- (se crea solo como referencia inicial; el usuario puede ajustarla desde Catálogo de Cuentas)
INSERT IGNORE INTO cuentas_contables (codigo, nombre, tipo, subtipo, descripcion)
VALUES ('20105', 'DINERO EN CUSTODIA DE CLIENTES', 'pasivo', 'Depósitos en custodia',
        'Dinero recibido de clientes en calidad de retención/custodia, no reconocido como ingreso por venta.');
