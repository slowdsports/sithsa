<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$lic = licenciaInfo($pdo);
// Si la licencia volvió a estar vigente (renovada desde el Panel de Proveedor), regresa al sistema.
if (licenciaVigente($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}
$suspendida = $lic && $lic['estado'] !== 'activa';
$empresa    = $lic['empresa_nombre'] ?? '';
$vencio     = $lic['fecha_vencimiento'] ?? null;
$contacto   = trim($lic['vendedor_contacto'] ?? '') ?: 'al proveedor del sistema';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suscripción no disponible | SITHSA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sithsa.css">
  <style>
    body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg-2,#f4f6f9);padding:1rem}
    .lic-card{max-width:460px;width:100%;background:var(--bg-1,#fff);border-radius:14px;box-shadow:0 10px 40px rgba(0,0,0,.12);padding:2.5rem 2rem;text-align:center}
    .lic-card i.fa-triangle-exclamation{font-size:2.6rem;color:#d97706;margin-bottom:1rem}
    .lic-card h1{font-size:1.25rem;margin-bottom:.5rem}
    .lic-card p{color:var(--text-3,#64748b);font-size:.92rem;line-height:1.5}
    .lic-meta{margin:1.25rem 0;padding:.9rem;border-radius:8px;background:var(--bg-2,#f4f6f9);font-size:.85rem}
  </style>
</head>
<body>
  <div class="lic-card">
    <i class="fas fa-triangle-exclamation"></i>
    <h1><?= $suspendida ? 'Suscripción suspendida' : 'Suscripción vencida' ?></h1>
    <p>El acceso a SITHSA para <strong><?= htmlspecialchars($empresa) ?></strong> está temporalmente deshabilitado.</p>
    <div class="lic-meta">
      <?php if ($vencio): ?><div>Fecha de vencimiento: <strong><?= fmtFecha($vencio) ?></strong></div><?php endif; ?>
      <div style="margin-top:.4rem">Para reactivar el servicio, comuníquese con <strong><?= htmlspecialchars($contacto) ?></strong>.</div>
    </div>
    <a href="<?= BASE_URL ?>logout.php" class="btn-sithsa-outline" style="display:inline-block;text-decoration:none;padding:.5rem 1.25rem">
      <i class="fas fa-sign-out-alt"></i> Cerrar sesión
    </a>
  </div>
</body>
</html>
