<?php
// ============================================================
// DIAGNÓSTICO TEMPORAL — súbelo a la carpeta sithsa en tu servidor,
// ábrelo en el navegador, copia el resultado y BÓRRALO después
// (junto con diagnostico_licencia.php si sigue ahí).
// ============================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo "\n[FATAL DETECTADO POR SHUTDOWN] " . $err['message'] . " en " . $err['file'] . ":" . $err['line'] . "\n";
    }
});

echo "=== Diagnóstico SITHSA / Simulación de Dashboard ===\n";
echo "PHP version: " . PHP_VERSION . "\n\n";

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$u = $pdo->query("SELECT id,nombre,email,rol FROM usuarios WHERE activo=1 ORDER BY id LIMIT 1")->fetch();
if (!$u) { echo "[ERROR] No hay usuarios activos en la base de datos.\n"; exit; }
echo "[INFO] Simulando sesión de: {$u['nombre']} ({$u['email']}) rol={$u['rol']}\n\n";

$_SESSION['usuario_id']     = $u['id'];
$_SESSION['usuario_nombre'] = $u['nombre'];
$_SESSION['usuario_rol']    = $u['rol'];
$_SESSION['usuario_email']  = $u['email'];

echo "--- Intentando renderizar dashboard.php ---\n";
ob_start();
try {
    include __DIR__ . '/dashboard.php';
    $out = ob_get_clean();
    echo "[OK] dashboard.php se ejecutó sin lanzar excepciones.\n";
    echo "Longitud del HTML generado: " . strlen($out) . " caracteres.\n";
    echo "Primeros 400 caracteres:\n" . substr($out, 0, 400) . "\n";
} catch (\Throwable $e) {
    $out = ob_get_clean();
    echo "[ERROR] dashboard.php lanzó una excepción:\n";
    echo get_class($e) . ": " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTraza:\n" . $e->getTraceAsString() . "\n";
    echo "\nContenido parcial generado antes del error (" . strlen($out) . " caracteres):\n" . substr($out, 0, 800) . "\n";
}

echo "\n=== Fin ===\n";
echo ">>> BORRA ESTE ARCHIVO DEL SERVIDOR AHORA. <<<\n";
