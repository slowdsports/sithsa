<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();
$pagina = 'Recibos por Retención';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $_act = $_POST['_action'] ?? '';
    if ($_act === 'guardar') {
        $rid = (int)($_POST['id'] ?? 0);
        $d = [
            'fecha'=>$_POST['fecha'],'cliente_id'=>(int)$_POST['cliente_id'],
            'concepto'=>trim($_POST['concepto']),'monto'=>(float)$_POST['monto'],
            'forma_recepcion'=>in_array($_POST['forma_recepcion']??'',['efectivo','cheque','transferencia'])?$_POST['forma_recepcion']:'efectivo',
            'numero_cheque'=>trim($_POST['numero_cheque']??'')?:null,
            'cuenta_bancaria_id'=>$_POST['cuenta_bancaria_id']?:null,
            'cuenta_id'=>$_POST['cuenta_id']?:null,
            'proyecto_id'=>$_POST['proyecto_id']?:null,
            'recibido_por_id'=>$_POST['recibido_por_id']?:null,
            'notas'=>trim($_POST['notas']??''),
        ];
        if (!$d['cliente_id'])          jsonErr('Debe seleccionar un cliente.');
        if ($d['monto'] <= 0)            jsonErr('El monto debe ser mayor a cero.');
        if ($d['concepto'] === '')       jsonErr('Debe indicar el concepto / motivo de la retención.');
        try {
            if ($rid) {
                $pdo->prepare("UPDATE recibos_retencion SET fecha=?,cliente_id=?,concepto=?,monto=?,forma_recepcion=?,numero_cheque=?,cuenta_bancaria_id=?,cuenta_id=?,proyecto_id=?,recibido_por_id=?,notas=? WHERE id=? AND estado='activo'")
                    ->execute([$d['fecha'],$d['cliente_id'],$d['concepto'],$d['monto'],$d['forma_recepcion'],$d['numero_cheque'],$d['cuenta_bancaria_id'],$d['cuenta_id'],$d['proyecto_id'],$d['recibido_por_id'],$d['notas'],$rid]);
                jsonOk(['id'=>$rid],'Recibo actualizado.');
            } else {
                $num = generarNumero($pdo,'recibos_retencion','numero',getConfig($pdo,'prefijo_rr','RR'));
                $pdo->prepare("INSERT INTO recibos_retencion (numero,fecha,cliente_id,concepto,monto,forma_recepcion,numero_cheque,cuenta_bancaria_id,cuenta_id,proyecto_id,recibido_por_id,notas,estado)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'activo')")
                    ->execute([$num,$d['fecha'],$d['cliente_id'],$d['concepto'],$d['monto'],$d['forma_recepcion'],$d['numero_cheque'],$d['cuenta_bancaria_id'],$d['cuenta_id'],$d['proyecto_id'],$d['recibido_por_id'],$d['notas']]);
                jsonOk(['id'=>$pdo->lastInsertId()],'Recibo creado.');
            }
        } catch(\Exception $e){ jsonErr($e->getMessage()); }
    }
    if ($_act === 'cambiar_estado') {
        $rid = (int)$_POST['id'];
        $estado = $_POST['estado'] ?? '';
        if (!in_array($estado, ['devuelto','anulado'])) jsonErr('Estado inválido.');
        $upd = "UPDATE recibos_retencion SET estado=?";
        $params = [$estado];
        if ($estado === 'devuelto') { $upd .= ",fecha_devolucion=CURDATE()"; }
        $upd .= " WHERE id=? AND estado='activo'";
        $params[] = $rid;
        $pdo->prepare($upd)->execute($params);
        jsonOk([],'Estado actualizado.');
    }
    jsonErr('Acción desconocida.');
}

$clientes         = $pdo->query("SELECT id,nombre,rtn FROM clientes WHERE activo=1 ORDER BY nombre")->fetchAll();
$proyectos        = $pdo->query("SELECT id,nombre FROM proyectos WHERE estado='activo' ORDER BY nombre")->fetchAll();
$cuentas          = $pdo->query("SELECT id,codigo,nombre FROM cuentas_contables WHERE activa=1 AND tipo='pasivo' ORDER BY codigo")->fetchAll();
$cuentasBancarias = $pdo->query("SELECT id, CONCAT(nombre,' — ',banco) AS label FROM cuentas_bancarias WHERE activa=1 ORDER BY nombre")->fetchAll();
$empleados        = $pdo->query("SELECT id,CONCAT(nombre,' ',apellidos) AS nombre FROM empleados WHERE activo=1 ORDER BY apellidos")->fetchAll();

$recibo = null;
if (in_array($action, ['ver','editar']) && $id) {
    $s = $pdo->prepare("SELECT r.*, c.nombre AS cli_nombre, c.rtn AS cli_rtn, c.direccion AS cli_direccion,
        cb.nombre AS banco_nombre, cc.nombre AS cuenta_nombre, p.nombre AS proyecto_nombre,
        CONCAT(e.nombre,' ',e.apellidos) AS recibido_por_nombre
        FROM recibos_retencion r
        JOIN clientes c ON r.cliente_id = c.id
        LEFT JOIN cuentas_bancarias cb ON r.cuenta_bancaria_id = cb.id
        LEFT JOIN cuentas_contables cc ON r.cuenta_id = cc.id
        LEFT JOIN proyectos p ON r.proyecto_id = p.id
        LEFT JOIN empleados e ON r.recibido_por_id = e.id
        WHERE r.id = ?");
    $s->execute([$id]); $recibo = $s->fetch();
    if (!$recibo) { $action = 'list'; }
}

$lista = [];
if ($action === 'list') {
    $lista = $pdo->query("SELECT r.*, c.nombre AS cli_nombre FROM recibos_retencion r JOIN clientes c ON r.cliente_id=c.id ORDER BY r.id DESC")->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1><i class="fas fa-hand-holding-dollar"></i> Recibos por Retención de Dinero</h1>
  <?php if ($action === 'list'): ?>
  <a href="?action=nuevo" class="btn-sithsa"><i class="fas fa-plus"></i> Nuevo Recibo</a>
  <?php else: ?>
  <a href="?" class="btn-sithsa-outline"><i class="fas fa-arrow-left"></i> Volver</a>
  <?php endif; ?>
</div>

<?php if ($action === 'list'): ?>
<div class="card"><div class="card-body p-0">
  <table id="tbl-rr" class="table-sithsa w-100">
    <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Concepto</th><th>Monto</th><th>Forma</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach($lista as $r): ?>
      <tr>
        <td class="font-mono"><?= htmlspecialchars($r['numero']) ?></td>
        <td><?= fmtFecha($r['fecha']) ?></td>
        <td><?= htmlspecialchars($r['cli_nombre']) ?></td>
        <td><?= htmlspecialchars(substr($r['concepto'],0,50)) ?></td>
        <td class="font-mono fw-bold"><?= lps($r['monto']) ?></td>
        <td><?= ucfirst($r['forma_recepcion']) ?></td>
        <td><?= estadoBadge($r['estado']) ?></td>
        <td>
          <a href="?action=ver&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="fas fa-eye"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$lista): ?><tr><td colspan="8" class="text-center text-muted py-4">Sin recibos registrados</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div>

<?php elseif ($action === 'ver' && $recibo): ?>

<div id="print-area">
<div class="card">
  <div class="card-header">
    <i class="fas fa-hand-holding-dollar"></i> RECIBO <?= htmlspecialchars($recibo['numero']) ?> — <?= estadoBadge($recibo['estado']) ?>
    <div class="ms-auto d-flex gap-2 no-print">
      <?php if ($recibo['estado'] === 'activo'): ?>
      <button class="btn btn-sm btn-info text-white" onclick="cambiarEstado('recibos_retencion.php',<?= $recibo['id'] ?>,'devuelto','recibos_retencion',()=>location.reload())"><i class="fas fa-rotate-left"></i> Marcar Devuelto</button>
      <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('¿Anular este recibo?')) cambiarEstado('recibos_retencion.php',<?= $recibo['id'] ?>,'anulado','recibos_retencion',()=>location.reload())"><i class="fas fa-ban"></i> Anular</button>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>print.php?tipo=rr&id=<?= $recibo['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf"></i> PDF</a>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-3" style="font-size:.85rem;">
      <div class="col-md-3"><strong>Fecha:</strong> <?= fmtFecha($recibo['fecha']) ?></div>
      <div class="col-md-5"><strong>Cliente:</strong> <?= htmlspecialchars($recibo['cli_nombre']) ?></div>
      <div class="col-md-4"><strong>RTN Cliente:</strong> <?= htmlspecialchars($recibo['cli_rtn'] ?: '—') ?></div>
      <div class="col-md-3"><strong>Forma de Recepción:</strong> <?= ucfirst($recibo['forma_recepcion']) ?></div>
      <?php if($recibo['numero_cheque']): ?>
      <div class="col-md-3"><strong>No. Cheque:</strong> <?= htmlspecialchars($recibo['numero_cheque']) ?></div>
      <?php endif; ?>
      <div class="col-md-3"><strong>Cuenta Bancaria:</strong> <?= htmlspecialchars($recibo['banco_nombre'] ?? '—') ?></div>
      <div class="col-md-3"><strong>Cuenta Contable:</strong> <?= htmlspecialchars($recibo['cuenta_nombre'] ?? '—') ?></div>
      <div class="col-md-6"><strong>Proyecto:</strong> <?= htmlspecialchars($recibo['proyecto_nombre'] ?? '—') ?></div>
      <div class="col-md-6"><strong>Recibido por:</strong> <?= htmlspecialchars($recibo['recibido_por_nombre'] ?? '—') ?></div>
      <?php if($recibo['fecha_devolucion']): ?>
      <div class="col-md-6"><strong>Fecha de Devolución:</strong> <?= fmtFecha($recibo['fecha_devolucion']) ?></div>
      <?php endif; ?>
      <div class="col-12"><strong>Concepto / Motivo de la Retención:</strong> <?= htmlspecialchars($recibo['concepto']) ?></div>
      <?php if($recibo['notas']): ?><div class="col-12"><strong>Notas:</strong> <?= htmlspecialchars($recibo['notas']) ?></div><?php endif; ?>
    </div>
    <div class="mt-3 p-3 bg-light rounded text-center">
      <div class="text-muted" style="font-size:.8rem;">MONTO RETENIDO EN CUSTODIA (NO ES INGRESO POR VENTA)</div>
      <div style="font-size:2rem;font-weight:700;color:var(--ah-blue);"><?= lps($recibo['monto']) ?></div>
    </div>
    <div class="row mt-4 text-center d-print-flex">
      <div class="col-4 border-top pt-2 mx-auto">Recibido por</div>
      <div class="col-4 border-top pt-2 mx-auto">Autorizado por</div>
      <div class="col-4 border-top pt-2 mx-auto">Entregado por (Cliente)</div>
    </div>
  </div>
</div>
</div>

<?php else: ?>
<div class="card"><div class="card-header"><i class="fas fa-plus"></i> Nuevo Recibo por Retención</div>
<div class="card-body">
  <form id="form-rr" action="recibos_retencion.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="_action" value="guardar">
    <input type="hidden" name="id" value="0">
    <div class="alert alert-info" style="font-size:.85rem">
      <i class="fas fa-circle-info"></i> Este recibo documenta dinero recibido de un cliente en calidad de
      <strong>custodia/retención</strong>, no como ingreso por venta de mercadería o servicios.
    </div>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label">Fecha *</label><input type="text" name="fecha" class="form-control date-input" required value="<?= date('Y-m-d') ?>"></div>
      <div class="col-md-6">
        <label class="form-label">Cliente *</label>
        <select name="cliente_id" class="form-select" required>
          <option value="">-- Seleccionar --</option>
          <?php foreach($clientes as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?><?= $c['rtn'] ? ' — '.htmlspecialchars($c['rtn']) : '' ?></option>
          <?php endforeach; ?>
        </select>
        <?php if(!$clientes): ?><small class="text-danger">No hay clientes activos. <a href="<?= BASE_URL ?>clientes.php">Cree uno primero</a>.</small><?php endif; ?>
      </div>
      <div class="col-md-3"><label class="form-label">Monto (L.) *</label><input type="number" name="monto" class="form-control" step="0.01" min="0.01" required></div>
      <div class="col-md-3">
        <label class="form-label">Forma de Recepción *</label>
        <select name="forma_recepcion" class="form-select" id="sel-forma-rr" onchange="toggleChequeRR()">
          <option value="efectivo">Efectivo</option>
          <option value="cheque">Cheque</option>
          <option value="transferencia">Transferencia</option>
        </select>
      </div>
      <div class="col-md-3" id="cheque-wrap-rr" style="display:none"><label class="form-label">Número de Cheque</label><input type="text" name="numero_cheque" class="form-control"></div>
      <div class="col-md-4">
        <label class="form-label">Cuenta Bancaria (Tesorería)</label>
        <select name="cuenta_bancaria_id" class="form-select">
          <option value="">— Sin asignar —</option>
          <?php foreach($cuentasBancarias as $cb): ?>
          <option value="<?= $cb['id'] ?>"><?= htmlspecialchars($cb['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Cuenta Contable (Pasivo)</label>
        <select name="cuenta_id" class="form-select">
          <option value="">--</option>
          <?php foreach($cuentas as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['codigo']) ?> <?= htmlspecialchars($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Recibido por</label>
        <select name="recibido_por_id" class="form-select">
          <option value="">--</option>
          <?php foreach($empleados as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Proyecto</label>
        <select name="proyecto_id" class="form-select"><option value="">--</option><?php foreach($proyectos as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="col-12"><label class="form-label">Concepto / Motivo de la Retención *</label><textarea name="concepto" class="form-control" rows="2" required placeholder="Ej: Fondo de garantía en custodia mientras se resuelve el contrato XYZ"></textarea></div>
      <div class="col-12"><label class="form-label">Notas</label><input type="text" name="notas" class="form-control"></div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn-sithsa"><i class="fas fa-save"></i> Crear Recibo</button>
      <a href="?" class="btn btn-outline-secondary btn-sm">Cancelar</a>
    </div>
  </form>
</div></div>
<?php endif; ?>

<?php
$extraJs = "
initDataTable('#tbl-rr');
function toggleChequeRR(){
  const v=document.getElementById('sel-forma-rr')?.value;
  const w=document.getElementById('cheque-wrap-rr');
  if(w) w.style.display=v==='cheque'?'':'none';
}
bindAjaxForm('form-rr',r=>{ Toast.show(r.message,'success'); setTimeout(()=>location.href='recibos_retencion.php',1200); });
";
include __DIR__ . '/includes/footer.php';
?>
