-- ============================================================
-- SITHSA - Licenciamiento comercial: suscripción por instalación
-- y usuario master (titular) de la cuenta del cliente.
-- ============================================================

-- Marca qué usuario es el "titular" (dueño) de la cuenta del cliente.
-- Solo administrable desde el Panel de Proveedor (proveedor.php).
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS es_titular TINYINT(1) NOT NULL DEFAULT 0 AFTER rol;

-- Suscripción de esta instalación (fila única, id=1) + acceso al Panel de Proveedor.
CREATE TABLE IF NOT EXISTS licencia (
    id INT PRIMARY KEY DEFAULT 1,
    empresa_nombre VARCHAR(200) NOT NULL DEFAULT '',
    empresa_contacto VARCHAR(150),
    empresa_telefono VARCHAR(20),
    empresa_email VARCHAR(150),
    plan VARCHAR(50) DEFAULT 'estandar',
    fecha_inicio DATE,
    fecha_vencimiento DATE,
    estado ENUM('activa','suspendida') DEFAULT 'activa',
    vendedor_nombre VARCHAR(150) DEFAULT '',
    vendedor_contacto VARCHAR(150) DEFAULT '',
    proveedor_password_hash VARCHAR(255) NOT NULL,
    notas TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila inicial. Contraseña por defecto del Panel de Proveedor: Sithsa2026*
-- CÁMBIALA de inmediato desde el panel tras el primer ingreso.
INSERT IGNORE INTO licencia (id, empresa_nombre, plan, fecha_inicio, fecha_vencimiento, estado, vendedor_contacto, proveedor_password_hash)
VALUES (1, 'Sin configurar', 'estandar', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'activa',
        'dgodoymelendez@gmail.com', '$2y$10$GfmzCU7zesRS0G5LzvyaNecfkMsNAXvROdilU/XybDVsxYEBBaLnu');
