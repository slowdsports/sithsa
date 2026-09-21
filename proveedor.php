<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
// Panel del Proveedor: NO usa requireLogin(). Tiene su propia contraseña,
// independiente de la tabla usuarios del cliente, para que el vendedor
// siempre pueda entrar aunque el cliente borre o bloquee sus usuarios.

function proveedorAutenticado(): bool {
    return !empty($_SESSION['proveedor_auth']);
}

if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $_act = $_POST['_action'] ?? '';

    if ($_act === 'login') {
        $lic = licenciaInfo($pdo);
        if ($lic && password_verify($_POST['password'] ?? '', $lic['proveedor_password_hash'])) {
            $_SESSION['proveedor_auth'] = true;
            jsonOk([], 'Bienvenido.');
        }
        jsonErr('Contraseña incorrecta.', 403);
    }

    if (!proveedorAutenticado()) jsonErr('No autorizado.', 403);

    if ($_act === 'logout') {
        unset($_SESSION['proveedor_auth']);
        jsonOk([], 'Sesión cerrada.');
    }

    if ($_act === 'guardar_empresa') {
        $pdo->prepare("UPDATE licencia SET empresa_nombre=?,empresa_contacto=?,empresa_telefono=?,empresa_email=? WHERE id=1")
            ->execute([trim($_POST['empresa_nombre']??''),trim($_POST['empresa_contacto']??''),trim($_POST['empresa_telefono']??''),trim($_POST['empresa_email']??'')]);
        jsonOk([], 'Datos de la empresa actualizados.');
    }

    if ($_act === 'guardar_suscripcion') {
        $estado = in_array($_POST['estado']??'', ['activa','suspendida']) ? $_POST['estado'] : 'activa';
        $pdo->prepare("UPDATE licencia SET plan=?,fecha_inicio=?,fecha_vencimiento=?,estado=?,vendedor_nombre=?,vendedor_contacto=?,notas=? WHERE id=1")
            ->execute([trim($_POST['plan']??'estandar')?:'estandar',$_POST['fecha_inicio']?:null,$_POST['fecha_vencimiento']?:null,$estado,
                       trim($_POST['vendedor_nombre']??''),trim($_POST['vendedor_contacto']??''),trim($_POST['notas']??'')]);
        jsonOk([], 'Suscripción actualizada.');
    }

    if ($_act === 'guardar_master') {
        $nombre = trim($_POST['nombre']??'');
        $email  = trim($_POST['email']??'');
        $pass   = $_POST['password'] ?? '';
        if (!$nombre || !$email) jsonErr('Nombre y correo son requeridos.');
        $actualId = (int)($_POST['id'] ?? 0);
        try {
            if ($actualId) {
                if ($pass) {
                    $pdo->prepare("UPDATE usuarios SET nombre=?,email=?,password=?,rol='admin',es_titular=1,activo=1 WHERE id=?")
                        ->execute([$nombre,$email,password_hash($pass,PASSWORD_BCRYPT),$actualId]);
                } else {
                    $pdo->prepare("UPDATE usuarios SET nombre=?,email=?,rol='admin',es_titular=1,activo=1 WHERE id=?")
                        ->execute([$nombre,$email,$actualId]);
                }
                jsonOk([],'Usuario master actualizado.');
            } else {
                if (!$pass) jsonErr('La contraseña es requerida para crear el usuario master.');
                $pdo->beginTransaction();
                $pdo->exec("UPDATE usuarios SET es_titular=0");
                $pdo->prepare("INSERT INTO usuarios (nombre,email,password,rol,es_titular,activo) VALUES (?,?,?,'admin',1,1)")
                    ->execute([$nombre,$email,password_hash($pass,PASSWORD_BCRYPT)]);
                $nuevoId = $pdo->lastInsertId();
                $pdo->commit();
                jsonOk(['id'=>$nuevoId],'Usuario master creado.');
            }
        } catch(PDOException $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonErr($e->getCode()==23000 ? 'Ese correo ya está en uso.' : $e->getMessage());
        }
    }

    if ($_act === 'transferir_titular') {
        $uid = (int)($_POST['id'] ?? 0);
        if (!$uid) jsonErr('Usuario inválido.');
        $pdo->beginTransaction();
        $pdo->exec("UPDATE usuarios SET es_titular=0");
        $pdo->prepare("UPDATE usuarios SET es_titular=1, rol='admin', activo=1 WHERE id=?")->execute([$uid]);
        $pdo->commit();
        jsonOk([], 'Titularidad transferida.');
    }

    if ($_act === 'cambiar_password_panel') {
        $lic = licenciaInfo($pdo);
        if (!$lic || !password_verify($_POST['actual']??'', $lic['proveedor_password_hash'])) jsonErr('Contraseña actual incorrecta.');
        $nueva = $_POST['nueva'] ?? '';
        if (strlen($nueva) < 8) jsonErr('La nueva contraseña debe tener al menos 8 caracteres.');
        $pdo->prepare("UPDATE licencia SET proveedor_password_hash=? WHERE id=1")->execute([password_hash($nueva,PASSWORD_BCRYPT)]);
        jsonOk([], 'Contraseña del panel actualizada.');
    }

    jsonErr('Acción desconocida.');
}

$lic = licenciaInfo($pdo);
if (!$lic) { http_response_code(500); die('La tabla de licencia no está migrada. Ejecute database/migration_licencia_titular.sql'); }

$titular = $pdo->query("SELECT id,nombre,email FROM usuarios WHERE es_titular=1 LIMIT 1")->fetch();
$admins  = $pdo->query("SELECT id,nombre,email FROM usuarios WHERE rol='admin' AND (es_titular=0 OR es_titular IS NULL) ORDER BY nombre")->fetchAll();
$csrf    = csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de Proveedor | SITHSA</title>
<meta name="csrf" content="<?= $csrf ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body{background:#0f172a;font-family:system-ui,-apple-system,sans-serif;min-height:100vh}
  .pv-wrap{max-width:760px;margin:2.5rem auto;padding:0 1rem}
  .pv-login{max-width:380px;margin:8rem auto;background:#fff;border-radius:14px;padding:2rem;box-shadow:0 10px 40px rgba(0,0,0,.3)}
  .card{border:none;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.06);margin-bottom:1.25rem}
  .card-header{background:#0D3F6A;color:#fff;border-radius:12px 12px 0 0 !important;font-weight:600}
  .pv-title{color:#fff;text-align:center;margin-bottom:1.5rem}
  .badge-titular{background:#0D3F6A}
</style>
</head>
<body>

<?php if (!proveedorAutenticado()): ?>
<div class="pv-login">
  <h4 class="text-center mb-3"><i class="fas fa-user-shield"></i> Panel de Proveedor</h4>
  <p class="text-muted text-center" style="font-size:.85rem">Acceso exclusivo para administrar la suscripción de esta instalación.</p>
  <form id="form-login">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="_action" value="login">
    <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Contraseña del panel" required autofocus></div>
    <button type="submit" class="btn btn-primary w-100">Entrar</button>
  </form>
</div>

<?php else: ?>
<div class="pv-wrap">
  <h3 class="pv-title"><i class="fas fa-user-shield"></i> Panel de Proveedor</h3>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-building"></i> Empresa Cliente</span>
      <button class="btn btn-sm btn-light" onclick="logoutPv()"><i class="fas fa-sign-out-alt"></i> Salir</button>
    </div>
    <div class="card-body">
      <form id="form-empresa">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="_action" value="guardar_empresa">
        <div class="row g-3">
          <div class="col-md-8"><label class="form-label">Nombre de la empresa</label><input type="text" name="empresa_nombre" class="form-control" value="<?= htmlspecialchars($lic['empresa_nombre']) ?>"></div>
          <div class="col-md-4"><label class="form-label">Contacto</label><input type="text" name="empresa_contacto" class="form-control" value="<?= htmlspecialchars($lic['empresa_contacto']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label">Teléfono</label><input type="text" name="empresa_telefono" class="form-control" value="<?= htmlspecialchars($lic['empresa_telefono']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="empresa_email" class="form-control" value="<?= htmlspecialchars($lic['empresa_email']??'') ?>"></div>
        </div>
        <button type="submit" class="btn btn-sm btn-primary mt-3"><i class="fas fa-save"></i> Guardar</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><i class="fas fa-file-signature"></i> Suscripción</div>
    <div class="card-body">
      <form id="form-suscripcion">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="_action" value="guardar_suscripcion">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Plan</label><input type="text" name="plan" class="form-control" value="<?= htmlspecialchars($lic['plan']??'') ?>"></div>
          <div class="col-md-4">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
              <option value="activa" <?= $lic['estado']==='activa'?'selected':'' ?>>Activa</option>
              <option value="suspendida" <?= $lic['estado']==='suspendida'?'selected':'' ?>>Suspendida</option>
            </select>
          </div>
          <div class="col-md-4"></div>
          <div class="col-md-4"><label class="form-label">Fecha de inicio</label><input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($lic['fecha_inicio']??'') ?>"></div>
          <div class="col-md-4"><label class="form-label">Fecha de vencimiento</label><input type="date" name="fecha_vencimiento" class="form-control" value="<?= htmlspecialchars($lic['fecha_vencimiento']??'') ?>"></div>
          <div class="col-md-4">
            <?php $dias = licenciaDiasRestantes($pdo); ?>
            <label class="form-label">Estado actual</label>
            <div class="form-control-plaintext">
              <?php if ($dias === null): ?><span class="badge bg-secondary">Sin fecha</span>
              <?php elseif ($dias < 0): ?><span class="badge bg-danger">Vencida hace <?= abs($dias) ?> días</span>
              <?php elseif ($dias <= 15): ?><span class="badge bg-warning text-dark">Vence en <?= $dias ?> días</span>
              <?php else: ?><span class="badge bg-success">Vigente (<?= $dias ?> días)</span><?php endif; ?>
            </div>
          </div>
          <div class="col-md-6"><label class="form-label">Nombre del vendedor / soporte</label><input type="text" name="vendedor_nombre" class="form-control" value="<?= htmlspecialchars($lic['vendedor_nombre']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label">Contacto de renovación (email/tel)</label><input type="text" name="vendedor_contacto" class="form-control" value="<?= htmlspecialchars($lic['vendedor_contacto']??'') ?>"></div>
          <div class="col-12"><label class="form-label">Notas internas</label><textarea name="notas" class="form-control" rows="2"><?= htmlspecialchars($lic['notas']??'') ?></textarea></div>
        </div>
        <button type="submit" class="btn btn-sm btn-primary mt-3"><i class="fas fa-save"></i> Guardar Suscripción</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><i class="fas fa-user-crown"></i> Usuario Master (Titular)</div>
    <div class="card-body">
      <?php if ($titular): ?>
      <p style="font-size:.85rem" class="text-muted">Titular actual: <strong><?= htmlspecialchars($titular['nombre']) ?></strong> (<?= htmlspecialchars($titular['email']) ?>) <span class="badge badge-titular">Titular</span></p>
      <?php else: ?>
      <p style="font-size:.85rem" class="text-danger">Esta instalación aún no tiene un usuario master asignado.</p>
      <?php endif; ?>
      <form id="form-master">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="_action" value="guardar_master">
        <input type="hidden" name="id" value="<?= $titular['id'] ?? 0 ?>">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Nombre completo</label><input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($titular['nombre']??'') ?>" required></div>
          <div class="col-md-6"><label class="form-label">Correo electrónico</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($titular['email']??'') ?>" required></div>
          <div class="col-md-6"><label class="form-label">Contraseña <?= $titular ? '(dejar vacío para no cambiar)' : '' ?></label><input type="password" name="password" class="form-control" minlength="8" <?= $titular ? '' : 'required' ?>></div>
        </div>
        <button type="submit" class="btn btn-sm btn-primary mt-3"><i class="fas fa-save"></i> <?= $titular ? 'Actualizar Master' : 'Crear Master' ?></button>
      </form>

      <?php if ($admins): ?>
      <hr>
      <p style="font-size:.82rem" class="text-muted mb-2">Transferir titularidad a un administrador existente:</p>
      <?php foreach($admins as $a): ?>
      <button class="btn btn-sm btn-outline-secondary me-1 mb-1" onclick="transferirTitular(<?= $a['id'] ?>,'<?= htmlspecialchars($a['nombre'],ENT_QUOTES) ?>')"><?= htmlspecialchars($a['nombre']) ?></button>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><i class="fas fa-key"></i> Seguridad del Panel</div>
    <div class="card-body">
      <form id="form-pass">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="_action" value="cambiar_password_panel">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Contraseña actual</label><input type="password" name="actual" class="form-control" required></div>
          <div class="col-md-4"><label class="form-label">Nueva contraseña</label><input type="password" name="nueva" class="form-control" minlength="8" required></div>
        </div>
        <button type="submit" class="btn btn-sm btn-outline-primary mt-3"><i class="fas fa-key"></i> Cambiar Contraseña</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<div id="pv-toast" style="position:fixed;top:1rem;right:1rem;z-index:9999"></div>
<script>
function toast(msg, ok){
  const box = document.getElementById('pv-toast');
  const el = document.createElement('div');
  el.className = 'alert alert-' + (ok?'success':'danger');
  el.style.cssText='min-width:260px;box-shadow:0 4px 16px rgba(0,0,0,.15)';
  el.textContent = msg;
  box.appendChild(el);
  setTimeout(()=>el.remove(), 3500);
}
async function send(form){
  const fd = new FormData(form);
  const res = await fetch('proveedor.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd });
  return res.json();
}
document.querySelectorAll('form').forEach(f=>{
  f.addEventListener('submit', async e=>{
    e.preventDefault();
    try {
      const r = await send(f);
      toast(r.message, r.success);
      if (r.success && f.id === 'form-login') setTimeout(()=>location.reload(), 400);
      if (r.success && f.id === 'form-pass') f.reset();
    } catch { toast('Error de conexión.', false); }
  });
});
async function logoutPv(){
  const fd = new FormData();
  fd.append('_action','logout');
  fd.append('csrf_token', document.querySelector('meta[name=csrf]').content);
  await fetch('proveedor.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd });
  location.reload();
}
async function transferirTitular(id, nombre){
  if (!confirm('¿Transferir la titularidad de la cuenta a ' + nombre + '?')) return;
  const fd = new FormData();
  fd.append('_action','transferir_titular');
  fd.append('id', id);
  fd.append('csrf_token', document.querySelector('meta[name=csrf]').content);
  const res = await fetch('proveedor.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd });
  const r = await res.json();
  toast(r.message, r.success);
  if (r.success) setTimeout(()=>location.reload(), 600);
}
</script>
</body>
</html>
