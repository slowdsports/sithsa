<?php
// ============================================================
// DIAGNÓSTICO TEMPORAL — súbelo a la carpeta sithsa en tu servidor,
// ábrelo en el navegador, copia el resultado y BÓRRALO después.
// ============================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "=== Diagnóstico SITHSA / Licencia ===\n";
echo "PHP version: " . PHP_VERSION . "\n\n";

try {
    require_once __DIR__ . '/config/db.php';
    echo "[OK] config/db.php cargado y conexión a la base de datos exitosa.\n";
} catch (\Throwable $e) {
    echo "[ERROR] Falló config/db.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit;
}

try {
    require_once __DIR__ . '/config/auth.php';
    echo "[OK] config/auth.php cargado.\n";
} catch (\Throwable $e) {
    echo "[ERROR] Falló config/auth.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit;
}

try {
    require_once __DIR__ . '/config/functions.php';
    echo "[OK] config/functions.php cargado.\n";
} catch (\Throwable $e) {
    echo "[ERROR] Falló config/functions.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit;
}

echo "\n--- Verificación de esquema ---\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'es_titular'")->fetchAll();
    echo $cols ? "[OK] usuarios.es_titular existe.\n" : "[FALTA] usuarios.es_titular NO existe (falta correr la migración).\n";
} catch (\Throwable $e) {
    echo "[ERROR] revisando usuarios: " . $e->getMessage() . "\n";
}

try {
    $t = $pdo->query("SHOW TABLES LIKE 'licencia'")->fetchAll();
    if ($t) {
        echo "[OK] tabla licencia existe.\n";
        $row = $pdo->query("SELECT * FROM licencia WHERE id=1")->fetch();
        if ($row) {
            echo "[OK] fila id=1 encontrada:\n";
            foreach ($row as $k => $v) {
                if (is_string($k)) echo "    $k = " . var_export($v, true) . "\n";
            }
        } else {
            echo "[FALTA] no hay fila id=1 en licencia.\n";
        }
    } else {
        echo "[FALTA] tabla licencia NO existe (falta correr la migración).\n";
    }
} catch (\Throwable $e) {
    echo "[ERROR] revisando licencia: " . $e->getMessage() . "\n";
}

echo "\n--- Simulación de licenciaVigente() / licenciaDiasRestantes() ---\n";
try {
    $v = licenciaVigente($pdo);
    echo "[OK] licenciaVigente() = " . var_export($v, true) . "\n";
} catch (\Throwable $e) {
    echo "[ERROR] licenciaVigente() lanzó: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "\n";
}
try {
    $d = licenciaDiasRestantes($pdo);
    echo "[OK] licenciaDiasRestantes() = " . var_export($d, true) . "\n";
} catch (\Throwable $e) {
    echo "[ERROR] licenciaDiasRestantes() lanzó: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n--- Simulación de una sesión de login normal ---\n";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['usuario_id']     = 999999;
$_SESSION['usuario_nombre'] = 'Diagnóstico';
$_SESSION['usuario_rol']    = 'admin';
$_SESSION['usuario_email']  = 'diagnostico@example.com';
try {
    $csrf = csrfToken();
    echo "[OK] csrfToken() = $csrf\n";
} catch (\Throwable $e) {
    echo "[ERROR] csrfToken() lanzó: " . $e->getMessage() . "\n";
}
unset($_SESSION['usuario_id'], $_SESSION['usuario_nombre'], $_SESSION['usuario_rol'], $_SESSION['usuario_email']);

echo "\n=== Fin del diagnóstico ===\n";
echo ">>> BORRA ESTE ARCHIVO (diagnostico_licencia.php) DEL SERVIDOR AHORA. <<<\n";
