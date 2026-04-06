<?php
$titulo = "Reportes";
$modulo = "Ventas";
$subtitulo = "Historial de crédito de clientes";

$SESSION_LIFETIME = 10 * 60 * 60;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/acl.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/views/public/index.php');
    exit();
}

if (!can('ventas.creditos_historial')) {
    die('No autorizado.');
}

$sessionStart = $_SESSION['SESSION_START'] ?? 0;
$sessionTTL   = $_SESSION['SESSION_TTL']   ?? $SESSION_LIFETIME;
if ($sessionStart === 0 || (time() - $sessionStart) > $sessionTTL) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/views/public/index.php?expired=1');
    exit();
}

$_SESSION['SESION_VIGENTE'] = true;
$_SESSION['LAST_ACTIVITY']  = time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Historial de crédito de clientes | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>
  <style>
    .card-header{ border-color:darkgray; border-style:dotted; }
    .clean-filter .input-group-text{ cursor:pointer; }
    .table thead th{ white-space:nowrap; }
    .badge-status{ font-size:11px; }
  </style>
</head>
<body>
<?php include_once __DIR__ . '/../../../includes/header.php'; ?>

<div class="wrapper">
  <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
    <div class="loader"><div class="loader__figure"></div><p class="loader__label">Cargando...</p></div>
  </div>

  <div class="container-fluid">
    <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

    <div class="card-header">
      <h5>Filtros</h5>
      <div class="row">
        <div class="col-md-3"><div class="form-group"><label>Fecha inicial</label><input type="date" id="FiltroDesde" class="form-control filtrar"></div></div>
        <div class="col-md-3"><div class="form-group"><label>Fecha final</label><input type="date" id="FiltroHasta" class="form-control filtrar"></div></div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Cliente</label>
            <select id="FiltroCliente" class="form-control filtrar"><option value="">Todos</option></select>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Estatus de crédito</label>
            <select id="FiltroEstatus" class="form-control filtrar">
              <option value="">Todos</option>
              <option value="pendiente">Con saldo pendiente</option>
              <option value="liquidado">Liquidado</option>
            </select>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 d-flex align-items-end justify-content-end">
          <button id="BtnBuscar" class="btn btn-primary btn-sm mr-2"><i class="mdi mdi-magnify"></i> Buscar</button>
          <button id="BtnLimpiar" class="btn btn-light btn-sm"><i class="mdi mdi-broom"></i> Limpiar filtros</button>
        </div>
      </div>
    </div>

    <div class="row mt-2">
      <div class="col-12">
        <div class="card-box">
          <h4 class="header-title">Historial de crédito de clientes</h4>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped mb-0">
              <thead>
                <tr>
                  <th>Cliente</th><th class="text-center">Ventas a crédito en el periodo</th><th class="text-center">Total vendido a crédito en el periodo</th>
                  <th class="text-center">Total abonado</th><th class="text-center">Saldo pendiente actual</th><th class="text-center">Último movimiento</th><th class="text-center">Estatus general</th><th class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody id="tbodyResumen"><tr><td colspan="8" class="text-center text-muted">Sin registros</td></tr></tbody>
            </table>
          </div>
          <div class="row align-items-center justify-content-between mt-2">
            <div class="col-md-6"><div id="infoTabla">Mostrando 0 a 0 de 0 registros</div></div>
            <div class="col-md-6 d-flex justify-content-end"><ul id="pagination" class="pagination mb-0"></ul></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalDetalleCliente" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detalle de crédito por cliente</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <div id="resumenCliente" class="mb-3"></div>
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead>
              <tr><th>Folio</th><th>Fecha de venta</th><th class="text-right">Total venta</th><th class="text-right">Total abonado</th><th class="text-right">Saldo actual</th><th>Estatus del crédito</th><th>Abonos</th></tr>
            </thead>
            <tbody id="tbodyDetalleVentas"><tr><td colspan="7" class="text-center text-muted">Sin registros</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
<div class="rightbar-overlay"></div>
<script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
$(function(){
  const URL_CTRL = '<?= BASE_URL ?>/controllers/HistorialCreditoClientesController.php';
  const URL_CLIENTES = '<?= BASE_URL ?>/controllers/ClientesController.php';
  let paginaActual = 1; const limite = 20;
  const mxn = n => Number(n||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
  const esc = s => String(s==null?'':s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
  const fFecha = f => f ? String(f).substring(0,10) : '—';

  (function setMesActual(){
    const h = new Date(), y=h.getFullYear(), m=String(h.getMonth()+1).padStart(2,'0');
    const ini = `${y}-${m}-01`; const fin = new Date(y, h.getMonth()+1, 0).toISOString().slice(0,10);
    $('#FiltroDesde').val(ini); $('#FiltroHasta').val(fin);
  })();

  (function cargarClientes(){
    $.getJSON(URL_CLIENTES,{accion:'listar-min',limite:500}).done(r=>{
      (r?.data||[]).forEach(c=>$('#FiltroCliente').append(`<option value="${c.id_cliente}">${esc(c.nombre||'')}</option>`));
    });
  })();

  function filtros(){ return {
    accion:'listar-resumen', pagina:paginaActual, limite:limite,
    fecha_inicial:$('#FiltroDesde').val(), fecha_final:$('#FiltroHasta').val(), id_cliente:$('#FiltroCliente').val(),
    estatus_credito:$('#FiltroEstatus').val()
  };}

  function renderPag(total){
    const totalPag = Math.max(1, Math.ceil(total/limite));
    let h='';
    for(let p=1;p<=totalPag;p++) h += `<li class="page-item ${p===paginaActual?'active':''}"><a class="page-link" href="#" data-p="${p}">${p}</a></li>`;
    $('#pagination').html(h);
  }

  function cargarResumen(){
    $('#LoadingImage').show();
    $.getJSON(URL_CTRL, filtros()).done(r=>{
      const rows = r?.data||[]; const total = Number(r?.total||0);
      if(!rows.length){ $('#tbodyResumen').html('<tr><td colspan="8" class="text-center text-muted">Sin resultados para los filtros seleccionados</td></tr>'); }
      else {
        $('#tbodyResumen').html(rows.map(it=>{
          const badge = Number(it.saldo_pendiente_actual)>0 ? '<span class="badge badge-warning badge-status">Con saldo pendiente</span>' : '<span class="badge badge-success badge-status">Liquidado</span>';
          return `<tr>
            <td>${esc(it.cliente||'')}</td>
            <td class="text-center">${Number(it.ventas_credito_periodo||0)}</td>
            <td class="text-right">${mxn(it.total_vendido_periodo)}</td>
            <td class="text-right">${mxn(it.total_abonado)}</td>
            <td class="text-right">${mxn(it.saldo_pendiente_actual)}</td>
            <td class="text-center">${fFecha(it.ultimo_movimiento)}</td>
            <td class="text-center">${badge}</td>
            <td class="text-center"><button class="btn btn-sm btn-info btn-detalle" data-id="${Number(it.id_cliente)}">Ver detalle</button></td>
          </tr>`;
        }).join(''));
      }
      const ini = total ? ((paginaActual-1)*limite)+1 : 0; const fin = Math.min(paginaActual*limite,total);
      $('#infoTabla').text(`Mostrando ${ini} a ${fin} de ${total} registros`);
      renderPag(total);
    }).fail(()=>toastr.error('No se pudo cargar el historial de crédito.')).always(()=>$('#LoadingImage').hide());
  }

  function cargarDetalle(idCliente){
    $('#LoadingImage').show();
    $.getJSON(URL_CTRL,{accion:'detalle-cliente', id_cliente:idCliente, fecha_inicial:$('#FiltroDesde').val(), fecha_final:$('#FiltroHasta').val(), estatus_credito:$('#FiltroEstatus').val()})
      .done(r=>{
        const data = r?.data||{}; const resumen = data.resumen; const ventas = data.ventas||[];
        if(!resumen){
          $('#resumenCliente').html('<div class="alert alert-info mb-2">El cliente no tiene ventas a crédito en el periodo seleccionado.</div>');
          $('#tbodyDetalleVentas').html('<tr><td colspan="7" class="text-center text-muted">Sin registros</td></tr>');
          $('#modalDetalleCliente').modal('show'); return;
        }
        $('#resumenCliente').html(`<div class="row">
          <div class="col-md-4"><strong>Cliente:</strong> ${esc(resumen.cliente)}</div>
          <div class="col-md-4"><strong>Total vendido a crédito:</strong> ${mxn(resumen.total_vendido_periodo)}</div>
          <div class="col-md-4"><strong>Total abonado:</strong> ${mxn(resumen.total_abonado)}</div>
          <div class="col-md-4"><strong>Saldo pendiente actual:</strong> ${mxn(resumen.saldo_pendiente_actual)}</div>
          <div class="col-md-4"><strong>Cantidad de ventas:</strong> ${Number(resumen.ventas_credito_periodo||0)}</div>
          <div class="col-md-4"><strong>Último movimiento:</strong> ${fFecha(resumen.ultimo_movimiento)}</div>
        </div>`);

        $('#tbodyDetalleVentas').html(ventas.map(v=>{
          const abRows = (v.abonos||[]).map(a=>`<tr>
            <td>${fFecha(a.fecha_abono)}</td>
            <td class="text-right">${mxn(a.monto)}</td>
            <td>${esc(a.forma_pago||'—')}</td>
            <td>${esc(a.referencia_pago||'—')}</td>
            <td class="text-right">${mxn(a.saldo_antes)}</td>
            <td class="text-right">${mxn(a.saldo_despues)}</td>
            <td>${esc(a.usuario_nombre||'—')}</td>
          </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">Sin abonos</td></tr>';

          return `<tr>
            <td>${esc(v.folio||'')}</td>
            <td>${fFecha(v.fecha)}</td>
            <td class="text-right">${mxn(v.total_venta)}</td>
            <td class="text-right">${mxn(v.abonado_total)}</td>
            <td class="text-right">${mxn(v.saldo_actual)}</td>
            <td>${esc(v.estatus_credito_calculado||'')}</td>
            <td><center><button class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#ab_${v.id_venta}">Ver abonos</button></center></td>
          </tr>
          <tr class="collapse" id="ab_${v.id_venta}">
            <td colspan="7">
              <table class="table table-bordered table-sm mb-0">
                <thead><tr><th>Fecha abono</th><th class="text-right">Monto</th><th>Forma de pago</th><th>Referencia</th><th class="text-right">Saldo antes</th><th class="text-right">Saldo después</th><th>Usuario</th></tr></thead>
                <tbody>${abRows}</tbody>
              </table>
            </td>
          </tr>`;
        }).join(''));

        $('#modalDetalleCliente').modal('show');
      })
      .fail(()=>toastr.error('No se pudo cargar el detalle del cliente.'))
      .always(()=>$('#LoadingImage').hide());
  }

  $('#BtnBuscar').on('click', ()=>{ paginaActual=1; cargarResumen(); });
  $('#BtnLimpiar').on('click', ()=>{
    $('#FiltroCliente,#FiltroEstatus').val('');
    const h=new Date(), y=h.getFullYear(), m=String(h.getMonth()+1).padStart(2,'0');
    $('#FiltroDesde').val(`${y}-${m}-01`); $('#FiltroHasta').val(new Date(y,h.getMonth()+1,0).toISOString().slice(0,10));
    paginaActual=1; cargarResumen();
  });
  $('#pagination').on('click','a',function(e){ e.preventDefault(); paginaActual=Number($(this).data('p')||1); cargarResumen(); });
  $('#tbodyResumen').on('click','.btn-detalle',function(){ cargarDetalle(Number($(this).data('id')||0)); });

  cargarResumen();
});
</script>
</body>
</html>
