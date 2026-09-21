<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();
$pagina = 'Clientes';

if (isAjax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $_act = $_POST['_action'] ?? '';
    if ($_act === 'guardar') {
        $cid = (int)($_POST['id'] ?? 0);
        $d = ['tipo_cliente'=>in_array($_POST['tipo_cliente']??'',['natural','juridico'])?$_POST['tipo_cliente']:'juridico',
              'nombre'=>trim($_POST['nombre']),
              'rtn'=>trim($_POST['rtn']??''),'contacto'=>trim($_POST['contacto']??''),
              'telefono'=>trim($_POST['telefono']??''),'email'=>trim($_POST['email']??''),
              'direccion'=>trim($_POST['direccion']??''),
              'activo'=>isset($_POST['activo'])?1:0];
        try {
            if ($cid) {
                $pdo->prepare("UPDATE clientes SET tipo_cliente=?,nombre=?,rtn=?,contacto=?,telefono=?,email=?,direccion=?,activo=? WHERE id=?")
                    ->execute([...array_values($d), $cid]);
                jsonOk([],'Cliente actualizado.');
            } else {
                $codigo = generarNumero($pdo, 'clientes', 'codigo', 'CLI');
                $pdo->prepare("INSERT INTO clientes (codigo,tipo_cliente,nombre,rtn,contacto,telefono,email,direccion,activo) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$codigo, ...array_values($d)]);
                jsonOk(['id'=>$pdo->lastInsertId()],'Cliente creado.');
            }
        } catch(PDOException $e){ jsonErr($e->getMessage()); }
    }
    if ($_act === 'eliminar') { $pdo->prepare("UPDATE clientes SET activo=0 WHERE id=?")->execute([(int)$_POST['id']]); jsonOk([],'Cliente dado de baja.'); }
    jsonErr('Acción desconocida.');
}

$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nombre")->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <h1><i class="fas fa-address-book"></i> Clientes</h1>
  <button class="btn-sithsa" data-bs-toggle="modal" data-bs-target="#modal-cli"><i class="fas fa-plus"></i> Nuevo Cliente</button>
</div>
<div class="card"><div class="card-body p-0">
  <table id="tbl-cli" class="table-sithsa w-100">
    <thead><tr><th>Código</th><th>Nombre / Razón Social</th><th>Tipo</th><th>RTN</th><th>Contacto</th><th>Teléfono</th><th>Email</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach($clientes as $c): ?>
      <tr>
        <td class="font-mono"><?= htmlspecialchars($c['codigo']??'') ?></td>
        <td><?= htmlspecialchars($c['nombre']) ?></td>
        <td><span class="badge bg-secondary"><?= $c['tipo_cliente']==='natural'?'Natural':'Jurídico' ?></span></td>
        <td><?= htmlspecialchars($c['rtn']??'') ?></td>
        <td><?= htmlspecialchars($c['contacto']??'') ?></td>
        <td><?= htmlspecialchars($c['telefono']??'') ?></td>
        <td><?= htmlspecialchars($c['email']??'') ?></td>
        <td><?= $c['activo']?'<span class="badge bg-success">Activo</span>':'<span class="badge bg-danger">Inactivo</span>' ?></td>
        <td>
          <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick='editCli(<?= json_encode($c) ?>)'><i class="fas fa-edit"></i></button>
          <button class="btn btn-sm btn-outline-danger py-0 px-2 ms-1" onclick="deleteRecord('clientes.php',<?= $c['id'] ?>,()=>location.reload())"><i class="fas fa-ban"></i></button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div>

<div class="modal fade" id="modal-cli" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-address-book"></i> <span id="cli-modal-title">Nuevo Cliente</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="form-cli">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="_action" value="guardar">
          <input type="hidden" name="id" id="c-id" value="0">
          <div id="c-codigo-wrap" style="display:none" class="mb-2">
            <span style="font-size:.75rem;color:var(--text-3)">Código:</span>
            <span id="c-codigo-display" class="font-mono ms-1" style="font-size:.82rem;color:var(--text-2)"></span>
          </div>
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Nombre / Razón Social *</label><input type="text" name="nombre" id="c-nombre" class="form-control" required></div>
            <div class="col-md-4">
              <label class="form-label">Tipo de Cliente</label>
              <select name="tipo_cliente" id="c-tipo" class="form-select">
                <option value="juridico" selected>Jurídico</option>
                <option value="natural">Natural</option>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">RTN</label><input type="text" name="rtn" id="c-rtn" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Contacto</label><input type="text" name="contacto" id="c-contacto" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Teléfono</label><input type="text" name="telefono" id="c-tel" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="c-email" class="form-control"></div>
            <div class="col-md-6 d-flex align-items-end pb-1">
              <div class="form-check"><input type="checkbox" name="activo" class="form-check-input" id="c-activo" checked><label class="form-check-label" for="c-activo">Activo</label></div>
            </div>
            <div class="col-12"><label class="form-label">Dirección</label><input type="text" name="direccion" id="c-dir" class="form-control"></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn-sithsa" onclick="saveCli()"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = "
initDataTable('#tbl-cli');
function editCli(c){
  document.getElementById('c-id').value=c.id;
  document.getElementById('c-codigo-display').textContent=c.codigo||'';
  document.getElementById('c-codigo-wrap').style.display='';
  document.getElementById('c-nombre').value=c.nombre;
  document.getElementById('c-tipo').value=c.tipo_cliente;
  document.getElementById('c-rtn').value=c.rtn||'';
  document.getElementById('c-contacto').value=c.contacto||'';
  document.getElementById('c-tel').value=c.telefono||'';
  document.getElementById('c-email').value=c.email||'';
  document.getElementById('c-dir').value=c.direccion||'';
  document.getElementById('c-activo').checked=c.activo==1;
  document.getElementById('cli-modal-title').textContent='Editar: '+c.nombre;
  new bootstrap.Modal(document.getElementById('modal-cli')).show();
}
document.getElementById('modal-cli').addEventListener('show.bs.modal', function(e){
  if (!e.relatedTarget) return; // abierto vía editCli(), ya poblado
  document.getElementById('c-id').value=0;
  document.getElementById('form-cli').reset();
  document.getElementById('c-codigo-wrap').style.display='none';
  document.getElementById('cli-modal-title').textContent='Nuevo Cliente';
});
async function saveCli(){
  const r=await post('clientes.php',new FormData(document.getElementById('form-cli')));
  if(r.success){Toast.show(r.message,'success');setTimeout(()=>location.reload(),1000);}
  else Toast.show(r.message,'error');
}
";
include __DIR__ . '/includes/footer.php';
?>
