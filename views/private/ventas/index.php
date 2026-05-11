<?php
$titulo = "Ventas";
$modulo = "Gestionar Ventas";
$subtitulo = "";
session_start();

// ================================
    // Duración lógica de la sesión
    // ================================
    $SESSION_LIFETIME = 10 * 60 * 60; // 10 horas en segundos

    // Iniciar sesión solo si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once __DIR__ . '/../../../includes/config.php';
    require_once __DIR__ . '/../../../includes/constants.php';
    $ID_GRUPO_ACUMULADOR = defined('ID_GRUPO_ACUMULADOR') ? ID_GRUPO_ACUMULADOR : null;

    // ================================
    // Validar que haya usuario logueado
    // ================================
    if (!isset($_SESSION['usuario'])) {
        header('Location: ' . BASE_URL . '/views/public/index.php');
        exit();
    }

    // ================================
    // Control de tiempo de sesión (10h)
    // ================================
    $sessionStart = $_SESSION['SESSION_START'] ?? 0;
    $sessionTTL   = $_SESSION['SESSION_TTL']   ?? $SESSION_LIFETIME;

    // Si no hay marca de inicio o ya se pasó el tiempo, forzamos re-login
    if ($sessionStart === 0 || (time() - $sessionStart) > $sessionTTL) {
        session_unset();
        session_destroy();
        // Mandamos al index público con flag de expirado
        header('Location: ' . BASE_URL . '/views/public/index.php?expired=1');
        exit();
    }

    // Si la sesión sigue vigente, actualizamos banderas
    $_SESSION['SESION_VIGENTE'] = true;
    $_SESSION['LAST_ACTIVITY']  = time();

?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <title>Ventas | REFASOFT-V4</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core CSS -->
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/ticket.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/libs/select2/select2.min.css" rel="stylesheet" />

    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

    <style>
      .ed-search-wrap { position: relative; }
      .ed-sug-panel {
        position:absolute; left:0; right:0; top:100%;
        z-index: 3000;
        max-height: 320px; overflow:auto; display:none;
        box-shadow: 0 8px 22px rgba(0,0,0,.15);
      }
      .ed-sug-panel .list-group-item { cursor:pointer; }
      .ed-sug-panel .list-group-item.disabled,
      .ed-sug-panel .list-group-item.disabled * { cursor:not-allowed!important; opacity:1; }

      #ed-tabla .table td, #ed-tabla .table th { vertical-align: middle; }
      .badge-stock { font-weight: 600; }
      .w-70px { width: 70px; }

      .text-right{ text-align:right!important; }
      .text-center{ text-align:center!important; }
      /* Línea de Fecha / Folio */
      .tk-meta{
        display:flex;
        justify-content:space-between;
        gap:8px;
        white-space:nowrap;
        font-family: inherit;
        font-size: inherit;
      }

      /* Estatus en su propio renglón, tipografía igual que el resto */
      .tk-meta-line{
        display:block;         /* fuerza nueva línea */
        margin-top:2px;
        white-space:nowrap;
        font-family: inherit;
        font-size: inherit;
      }

      /* Por si #tk-estatus traía clases de badge del tema, las neutralizamos */
      #tk-estatus{
        background:transparent !important;
        color:inherit !important;
        border:0 !important;
        padding:0 !important;
        border-radius:0 !important;
        font-weight:inherit !important;
      }
      /* ==== INVOICE: Preview en pantalla ==== */
      .print-area.inv{
        background:#fff;
        padding:16px;
        border:1px solid #e5e5e5;
        border-radius:6px;
        font-size:13px;
        line-height:1.3;
        max-width: 900px;           /* preview cómoda en pantalla */
        margin: 0 auto;
      }

      /* PREVIEW en pantalla (igual que tenías) */
      .print-area.inv{
        background:#fff; padding:16px; border:1px solid #e5e5e5; border-radius:6px;
        font-size:13px; line-height:1.3; max-width:900px; margin:0 auto;
      }

      /* ===== SOLO imprime el elemento que tenga .print-this ===== */
      @media print {
        body * { visibility: hidden !important; }

        /* Sólo el área marcada como print-this y su contenido */
        .print-this, .print-this * { visibility: visible !important; }

        /* Quitar restricciones del modal */
        .modal, .modal-dialog, .modal-content { 
          position: static !important; display:block !important;
          width:auto !important; max-width:none !important; margin:0 !important;
          box-shadow:none !important; border:0 !important; background:transparent !important;
        }

        /* Ancho de página y márgenes — HOJA CARTA */
        @page { size: Letter; margin: 12mm; }
        /* Si prefieres A4, comenta la línea de arriba y descomenta esta: */
        /* @page { size: A4; margin: 12mm; } */

        /* El área impresa debe ocupar el ancho imprimible */
        .print-this{ border:0 !important; padding:0 !important; width:100% !important; max-width:none !important; }

        /* Quitar scrolls internos */
        .print-area.inv .table-responsive{ overflow:visible !important; }
        .print-area.inv .inv-table{ width:100% !important; table-layout:auto !important; }

        /* Ocultar barras/botones del modal */
        .no-print, .modal-header, .modal-footer, .modal-backdrop { display:none !important; }
      }

      /* ===== Fix: dropdown del botón de acciones NO se recorte ni genere scroll raro ===== */
      .ventas-wrapper .card-box .table-responsive{
        overflow-x: auto;
        overflow-y: visible;  /* deja que el menú se salga por arriba/abajo */
      }
      .ventas-wrapper #tablaVentas .dropdown-menu{
        z-index: 2050 !important;   /* por encima de la tabla y contenedores */
      }

      
    </style>
  </head>

  <body>
    <!-- Topbar -->
    <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

    <div class="wrapper">
      <!-- Loader -->
      <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
        <div class="loader">
          <div class="loader__figure"></div>
          <p class="loader__label">Cargando...</p>
        </div>
      </div>

      <!-- agrega clase ventas-wrapper para limitar el CSS del fix -->
      <div class="container-fluid ventas-wrapper">
        <!-- Breadcrumb -->
        <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>
        <!-- /Breadcrumb -->

        <!-- ========================= MÓDULO: FILTROS ========================= -->
        <div class="card-header" style="border-color:darkgray; border-style:dotted;">
          <h5>Filtros</h5>
          <div class="row">
            <div class="col-lg-12">
              <div class="row">
                <!-- Folio -->
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="Folio" class="control-label">Folio</label>
                    <div class="input-group">
                      <input type="text" id="Folio" class="form-control filtrar" autocomplete="off">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Folio')"></i></span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Estatus -->
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="FEstatus" class="control-label">Estatus</label>
                    <select id="FEstatus" class="form-control filtrar">
                      <option value="">Todos</option>
                      <option value="Activa">Activa</option>
                      <option value="Guardada">Guardada</option>
                      <option value="Credito">Crédito</option>
                      <option value="Cancelada">Cancelada</option>
                    </select>
                  </div>
                </div>

                <!-- Fecha (SIN valor por defecto para NO filtrar de inicio) -->
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="Fecha" class="control-label">Fecha</label>
                    <div class="input-group">
                      <!-- ANTES tenía value="<?php echo date('Y-m-d'); ?>" -->
                      <input type="date" id="Fecha" class="form-control filtrar" value="">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Fecha')"></i></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div><!-- /row -->
            </div>
          </div>
        </div>
        <!-- /Filtros -->

        <!-- ========================= MÓDULO: TABLA LISTADO ========================= -->
        <div class="row">
          <div class="col-12">
            <div class="card-box">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="header-title">Listado de Ventas</h4>
              </div>

              <div class="table-responsive">
                <table id="tablaVentas" class="table table-bordered table-hover table-striped">
                  <thead>
                    <tr>
                      <th>Folio</th>
                      <th>Cajero</th>
                      <th>Caja</th>
                      <th>Forma de Pago</th>
                      <th>Tipo de Precio</th>
                      <th class="text-end">Total</th>
                      <th class="text-end">Saldo</th>
                      <th>Estatus crédito</th>
                      <th>Estatus</th>
                      <th>Fiscal</th>
                      <th>Cliente</th>
                      <th>Fecha</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="row align-items-center justify-content-between mt-2">
                <div class="col-md-6">
                  <div id="infoVentas" class="dataTables_info"></div>
                </div>
                <div class="col-md-6 d-flex justify-content-end">
                  <nav aria-label="Page navigation">
                    <ul id="pagination" class="pagination justify-content-end mb-0"></ul>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- /Tabla Ventas -->

        <!-- ========================= MÓDULO: MODALES (SOLO INCLUDES) ========================= -->
        <?php include_once __DIR__ . '/../ventas/modales/detalle.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/ticket.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/eliminar.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/editar.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/abono.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/activar.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/facturar.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/invoice.php'; ?>
      </div> <!-- /container-fluid -->
    </div> <!-- /wrapper -->

    <!-- Footer -->
    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
    <div class="rightbar-overlay"></div>

    <!-- ========================= CORE JS ========================= -->
    <script>const BASE_URL='<?= BASE_URL ?>';</script>
    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/libs/select2/select2.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- QZ Tray (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.5/qz-tray.js"></script>


    <!-- ========================= APP JS: ORGANIZADO POR MÓDULOS ========================= -->
 <script>
/* ==========================================================================
   MÓDULO: Helpers y utilidades compartidas
   ========================================================================== */
function clearField(id){ try { $('#'+id).val('').trigger('change'); } catch(e){} }
const norm = s => (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();

// Formateadores comunes
const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
const fix2 = v => (Number(v||0)).toFixed(2);
const num  = v => parseFloat(v ?? 0) || 0;
const fechaMx = dt => {
  try{
    const d=new Date(String(dt).replace(' ','T'));
    return d.toLocaleString('es-MX',{
      day:'2-digit',month:'2-digit',year:'numeric',
      hour:'2-digit',minute:'2-digit',hour12:true
    });
  } catch { return dt||'—'; }
};

// Endpoints (centralizados)
const BASE = BASE_URL;
const ID_GRUPO_ACUMULADOR = <?= $ID_GRUPO_ACUMULADOR !== null ? (int)$ID_GRUPO_ACUMULADOR : 'null' ?>;
const VENTAS_URL     = `${BASE}/controllers/VentasController.php`;
const PRODUCTOS_URL  = `${BASE}/controllers/ProductosController.php`;
const CLIENTES_URL   = `${BASE}/controllers/ClientesController.php`;
const FORMASPAGO_URL = `${BASE}/controllers/FormasPagoController.php`;

// Badges de estatus (UI)
function getBadge(estatus){
  switch(estatus){
    case 'Activa':     return '<span class="badge badge-light-success badge-pill">Activa</span>';
    case 'Cancelada':  return '<span class="badge badge-light-danger badge-pill">Cancelada</span>';
    case 'Devuelta':   return '<span class="badge badge-light-warning badge-pill">Devuelta</span>';
    case 'Guardada':   return '<span class="badge badge-light-primary badge-pill">Guardada</span>';
    case 'Credito':    return '<span class="badge badge-light-info badge-pill">Crédito</span>';
    default:           return `<span class="badge badge-light-secondary badge-pill">${estatus||'—'}</span>`;
  }
}

function accionesVenta(v){
  let out = `
    <a class="dropdown-item accion-ver-detalle" href="#" data-toggle="modal" data-target="#modalDetalle" data-id="${v.id_venta}">
      <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver Detalle
    </a>`;

  if (v.estatus === 'Activa' || v.estatus === 'Guardada' || v.estatus === 'Credito'){
    out += `
      <a class="dropdown-item" href="javascript:void(0);" onclick="abrirTicket(${v.id_venta});">
        <i class="mdi mdi-printer mr-2 text-muted font-18 vertical-middle"></i>Ticket / Imprimir
      </a>
      <a class="dropdown-item" href="javascript:void(0);" onclick="abrirInvoice(${v.id_venta});">
        <i class="mdi mdi-file-document-outline mr-2 text-muted font-18 vertical-middle"></i>Nota Venta
      </a>
      <a class="dropdown-item" href="javascript:void(0);" onclick="abrirEditarVenta(${v.id_venta});">
        <i class="mdi mdi-pencil mr-2 text-muted font-18 vertical-middle"></i>Editar
      </a>`;
  }

  if (v.estatus === 'Guardada'){
    out += `
      <a class="dropdown-item accion-activar" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
        <i class="mdi mdi-check-circle mr-2 text-muted font-18 vertical-middle"></i>Activar (contabilizar)
      </a>`;
  }

  const saldo = num(v.saldo ?? (num(v.total) - num(v.abonado)));
  if (v.estatus === 'Credito' && saldo > 0.0001){
    out += `
      <a class="dropdown-item accion-abonar-venta" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
        <i class="mdi mdi-cash mr-2 text-muted font-18 vertical-middle"></i>Abonar
      </a>`;
  }

  if ((v.estatus === 'Activa' || v.estatus === 'Credito') && String(v.estatus_fiscal || '').toUpperCase() !== 'TIMBRADO') {
    out += `
      <a class="dropdown-item accion-facturar" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
        <i class="mdi mdi-receipt mr-2 text-muted font-18 vertical-middle"></i>Facturar
      </a>`;
  }

  if (v.estatus === 'Activa' || v.estatus === 'Credito'){
    out += `
      <a class="dropdown-item accion-eliminar" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
        <i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Cancelar
      </a>`;
  }
  return out;
}

function getBadgeFiscal(st){
  switch((st||'').toUpperCase()){
    case 'TIMBRADO': return '<span class="badge badge-light-success badge-pill">TIMBRADO</span>';
    case 'PENDIENTE': return '<span class="badge badge-light-warning badge-pill">PENDIENTE</span>';
    case 'ERROR': return '<span class="badge badge-light-danger badge-pill">ERROR</span>';
    case 'CANCELADO': return '<span class="badge badge-light-secondary badge-pill">CANCELADO</span>';
    default: return '<span class="badge badge-light-secondary badge-pill">SIN FACTURAR</span>';
  }
}

function getBadgeCredito(st){
  switch(st){
    case 'Pendiente':  return '<span class="badge badge-light-danger badge-pill">Pendiente</span>';
    case 'En Proceso': return '<span class="badge badge-light-warning badge-pill">En Proceso</span>';
    case 'Liquidado':  return '<span class="badge badge-light-success badge-pill">Liquidado</span>';
    case 'N/A':        return '<span class="badge badge-light-secondary badge-pill">N/A</span>';
    default:           return `<span class="badge badge-light-secondary badge-pill">${st||'N/A'}</span>`;
  }
}

/* ==========================================================================
   MÓDULO: Listado y paginación de ventas
   ========================================================================== */
let paginaActual=1; const limitePorPagina=10;

function cargarVentas(pagina){
  const folio  = $('#Folio').val() || '';
  const fecha  = ($('#Fecha').val() || '').trim();
  const estatus= $('#FEstatus').val() || '';

  $.post(VENTAS_URL,{
    accion:'listar',
    pagina,
    limite:limitePorPagina,
    folio,
    fecha: fecha || null,
    estatus
  }, function(resp){
    const ventas = resp?.data || [];
    const total  = parseInt(resp?.total||0,10);
    let tbody='';

    if (!ventas.length){
      tbody = '<tr><td colspan="13" class="text-center">No hay ventas disponibles</td></tr>';
    } else {
      ventas.forEach(v=>{
       tbody += `
            <tr>
              <td class="text-center"><b>${v.folio}</b></td>
              <td class="text-center">${v.usuario}</td>
              <td class="text-center">${v.caja}</td>
              <td class="text-center">${v.forma_pago}</td>
              <td class="text-center">${v.tipo_precio}</td>
              <td class="text-right"><b>${mxn(v.total)}</b></td>
              <td class="text-right">${mxn(v.saldo ?? (num(v.total) - num(v.abonado)))}</td>
              <td class="text-center">${getBadgeCredito(v.estatus_credito || 'N/A')}</td>
              <td class="text-center">${getBadge(v.estatus)}</td>
              <td class="text-center">${getBadgeFiscal(v.estatus_fiscal)}</td>
              <td class="text-center">${v.cliente || 'Público en general'}</td>
              <td class="text-center">${fechaMx(v.fecha)}</td>
              <td class="text-center">
                <div class="btn-group dropdown">
                  <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown">
                    <i class="mdi mdi-dots-horizontal"></i>
                  </a>
                  <div class="dropdown-menu dropdown-menu-right">
                    ${accionesVenta(v)}
                  </div>
                </div>
              </td>
            </tr>`;
      });
    }
    $('#tablaVentas tbody').html(tbody);

    configurarPaginacion(pagina, total, limitePorPagina);
    const desde=(pagina-1)*limitePorPagina+1, hasta=Math.min(pagina*limitePorPagina,total);
    $('#infoVentas').text(`Mostrando ${total===0?0:desde} a ${hasta} de ${total} ventas`);
  },'json');
}

function configurarPaginacion(currentPage,totalItems,itemsPerPage){
  const totalPages=Math.max(1,Math.ceil(totalItems/itemsPerPage));
  const $ul=$('#pagination').empty();
  const maxVisible=5;
  if (totalPages<=1){ $ul.closest('nav').hide(); return; } else { $ul.closest('nav').show(); }
  let start=Math.max(1,currentPage-Math.floor(maxVisible/2));
  let end=Math.min(totalPages,start+maxVisible-1);
  if (end-start+1<maxVisible) start=Math.max(1,end-maxVisible+1);
  if (currentPage>1){
    $ul.append(`<li class="page-item"><a class="page-link" data-page="1">Primera</a></li>`);
    $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);
  }
  for(let i=start;i<=end;i++){
    $ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" data-page="${i}">${i}</a></li>`);
  }
  if (currentPage<totalPages){
    $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);
    $ul.append(`<li class="page-item"><a class="page-link" data-page="${totalPages}">Última</a></li>`);
  }
}

// Paginación (click)
$('#pagination').on('click','a.page-link',function(e){
  e.preventDefault(); const p=Number($(this).data('page'));
  if (Number.isFinite(p)){ paginaActual=p; cargarVentas(paginaActual); }
});

// Filtros (change/enter/borrar)
$(".filtrar")
  .on('change keyup', function(){
    const $el=$(this);
    if ($el.val().length>0) $el.siblings(".clean-filter").css({display:"flex"});
    else $el.siblings(".clean-filter").css({display:"none"});
  })
  .on('change', ()=> setTimeout(()=>cargarVentas(1),200))
  .on('keypress', e=>{ if(e.charCode===13) cargarVentas(1); });

$(".clean-filter").click(function(){
  const $el=$(this).parent().find(".filtrar"); $el.val("").trigger("change");
  if ($el.hasClass("select2")) $el.select2("val", 0);
  cargarVentas(1);
});


/* ==========================================================================
  MÓDULO: Detalle de venta (modal #modalDetalle) + Garantías
  ========================================================================== */

/* ===== Util: asegurar bloque de Garantías dentro del modal ===== */
function ensureGarantiasBlock() {
  if ($('#wrap-det-garantias').length) return;

  const html = `
    <hr id="hr-garantias" style="display:none;">
    <div id="wrap-det-garantias" class="d-none">
      <h5 class="mb-2">Garantías</h5>
      <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered mb-0">
          <thead>
            <tr>
              <th style="min-width:160px">Producto</th>
              <th>Núm. Póliza</th>
              <th>Núm. Serie</th>
              <th>Fabricante</th>
              <th class="text-center">Vigencia (meses)</th>
              <th class="text-center">Inicio</th>
            </tr>
          </thead>
          <tbody id="det-garantias-body">
            <tr><td colspan="6" class="text-center text-muted">Sin garantías</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  `;

  const $abonos = $('#wrap-det-abonos');
  if ($abonos.length) {
    $(html).insertBefore($abonos);
  } else {
    $('#det-contenido').append(html);
  }
}

function esVentaMixta(detVenta) {
  const idFp  = Number(detVenta?.id_forma_pago ?? 0);
  const desc  = String(detVenta?.forma_pago || '').toLowerCase();
  return idFp === 3 || desc.startsWith('mixt');
}

function renderDesglosePagos(detVenta, pagos = [], totalVenta = 0) {
  const $wrap  = $('#wrap-det-desglose');
  const $items = $('#det-desglose-items').empty();
  const $total = $('#det-desglose-total');
  const $val   = $('#det-desglose-validacion');

  $val.removeClass('text-success text-danger').text('');

  const arr    = Array.isArray(pagos) ? pagos.filter(p => p && p.monto != null) : [];
  const esMix  = esVentaMixta(detVenta) || arr.length;

  if (!esMix) {
    $wrap.addClass('d-none');
    return;
  }

  if (!arr.length) {
    $items.append('<div class="text-muted">Sin desglose de pagos.</div>');
  }

  let totalPagos = 0;
  arr.forEach(p => {
    const nombre = p.nombre_forma_pago || p.descripcion || '—';
    const monto  = num(p.monto);
    totalPagos  += monto;
    $items.append(`
      <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
        <span>${nombre}</span>
        <span class="font-weight-bold">${mxn(monto)}</span>
      </div>
    `);
  });

  $total.text(mxn(totalPagos));
  const totVenta = num(totalVenta);
  if (arr.length) {
    const coincide = Math.abs(totalPagos - totVenta) < 0.01;
    $val
      .text(coincide
        ? 'El total del desglose coincide con el total de la venta.'
        : `El total del desglose (${mxn(totalPagos)}) difiere del total de la venta (${mxn(totVenta)}).`)
      .addClass(coincide ? 'text-success' : 'text-danger');
  }

  $wrap.removeClass('d-none');
}

function renderGarantiasEnDetalle(list) {
  ensureGarantiasBlock();

  const $wrap = $('#wrap-det-garantias');
  const $hr   = $('#hr-garantias');
  const $tb   = $('#det-garantias-body').empty();

  const arr = Array.isArray(list) ? list : [];

  if (!arr.length) {
    $tb.append('<tr><td colspan="6" class="text-center text-muted">Sin garantías</td></tr>');
    $wrap.addClass('d-none');
    $hr.hide();
    return;
  }

  arr.forEach(g => {
    const prod = (g.codigo ? `[${g.codigo}] ` : '') + (g.producto || '');
    const pol  = g.numero_poliza || '—';
    const ser  = g.numero_serie  || '—';
    const fab  = g.fabricante    || '—';
    const vig  = (g.vigencia_meses != null && g.vigencia_meses !== '') ? g.vigencia_meses : '—';
    const ini  = g.fecha_inicio ? fechaMx(g.fecha_inicio).split(',')[0] : '—'; // solo fecha

    $tb.append(`
      <tr>
        <td>${prod || '—'}</td>
        <td>${pol}</td>
        <td>${ser}</td>
        <td>${fab}</td>
        <td class="text-center">${vig}</td>
        <td class="text-center">${ini}</td>
      </tr>
    `);
  });

  $wrap.removeClass('d-none');
  $hr.show();
}

function resetDetalleCfdiState() {
  $('#det-cfdi-head-row').empty();
  $('#det-cfdi-body-row').empty();
  $('#det-cfdi-empty').removeClass('d-none');
  $('#det-cfdi-card').addClass('d-none');
  $('#det-cfdi-xml').attr('href', '#').addClass('d-none');
  $('#det-cfdi-pdf').attr('href', '#').addClass('d-none');
}

function resetDetalleVentaModalState() {
  const $wrapsCredito = $('#wrap-det-estatus-credito, #wrap-det-abonado, #wrap-det-saldo, #wrap-det-abonos, #det-btn-abonar');

  $('#det-error').hide().text('No se pudo cargar el detalle.');
  $('#det-loader').show();
  $('#det-contenido').hide();
  $('#modalDetalle').removeData('idVentaActual');

  $('#det-folio, #det-fecha, #det-estatus, #det-cliente, #det-usuario, #det-caja, #det-forma, #det-tipo').text('—');
  $('#det-total').text('$0.00');
  $('#det-header-folio').text('—');
  $('#det-tbody').empty();
  $('#det-abonos-body').html('<tr><td colspan="4" class="text-center text-muted">Sin abonos</td></tr>');
  $wrapsCredito.addClass('d-none');

  renderDesglosePagos({}, [], 0);
  renderGarantiasEnDetalle([]);
  resetDetalleCfdiState();
}

/* ===== NUEVO: construir garantías desde los detalles (acumuladores/baterías) ===== */
function construirGarantiasDesdeDetalles(dets){
  const out = [];
  (dets || []).forEach(d=>{
    const desc  = (d.producto || d.descripcion || '').toString().toLowerCase();
    const cat   = (d.categoria || d.familia || d.grupo || '').toString().toLowerCase();

    // Heurística: si es acumulador/batería por nombre o categoría
    const esAcumulador = /\bacumulador\b|\bbateri(a|ía)\b/.test(desc) || /\bacumulador\b|\bbateri(a|ía)\b/.test(cat);

    // Campos posibles en diversas BD
    const poliza = d.numero_poliza ?? d.no_poliza ?? d.num_poliza ?? d.poliza ?? d.garantia_poliza ?? null;
    const serie  = d.numero_serie  ?? d.no_serie  ?? d.num_serie  ?? d.serie  ?? null;
    const marca  = d.fabricante ?? d.marca ?? d.proveedor ?? null;
    const meses  = d.vigencia_meses ?? d.meses_garantia ?? d.garantia_meses ?? d.garantia ?? null;
    const fIni = d.fecha_inicio_garantia
          ?? d.fecha_garantia
          ?? d.fecha_compra
          ?? d.fecha
          ?? null;

    if (esAcumulador && (poliza || serie || meses)) {
      out.push({
        codigo: d.codigo || d.clave || d.sku || '',
        producto: d.producto || d.descripcion || '',
        numero_poliza: poliza || '—',
        numero_serie:  serie  || '—',
        fabricante:    marca  || '—',
        vigencia_meses: Number.isFinite(Number(meses)) ? Number(meses) : (meses || '—'),
        fecha_inicio:  fIni
      });
    }
  });
  return out;
}

/* ===== Click: abrir modal de Detalle ===== */
$(document).on('click','a.accion-ver-detalle',function(e){
  e.preventDefault();
  const id=$(this).data('id'); if(!id) return;

  // Estado inicial
  resetDetalleVentaModalState();
  $('#modalDetalle').data('idVentaActual', id).modal('show');
  const $wrapsCredito = $('#wrap-det-estatus-credito, #wrap-det-abonado, #wrap-det-saldo, #wrap-det-abonos, #det-btn-abonar');

  // Obtiene detalle
  $.get(typeof VENTAS_URL!=='undefined'?VENTAS_URL:'/controllers/VentasController.php',{accion:'detalle',id_venta:id},function(resp){
    if(!resp || !resp.venta){
      $('#det-loader').hide();
      $('#det-error').show().text('No se encontró la venta.');
      return;
    }

    const v       = resp.venta;
    const dets    = resp.detalles || [];
    const abonado = num(resp.abonado ?? v.abonado ?? 0);
    const saldo   = num(resp.saldo   ?? v.saldo   ?? (num(v.total) - abonado));
    const estCred = (v.estatus_credito || resp.estatus_credito || 'N/A');

    // Encabezado
    $('#det-folio').text(v.folio || '—');
    $('#det-header-folio').text(v.folio || '—');
    $('#det-fecha').text(fechaMx(v.fecha));
    $('#det-estatus').html(getBadge(v.estatus || '—'));
    $('#det-cliente').text(v.cliente || 'Público en general');
    $('#det-usuario').text(v.usuario || '—');
    $('#det-caja').text(v.caja || '—');
    $('#det-forma').text(v.forma_pago || '—');
    $('#det-tipo').text(v.tipo_precio || '—');
    renderCfdiDetalle(resp.cfdi || null, id);

    // Productos
    let tb='', total=0;
    if (!dets.length){
      tb = '<tr><td colspan="6" class="text-center text-muted">Sin productos</td></tr>';
    } else {
      dets.forEach(d=>{
        const c = Number(d.cantidad || 0);
        const u = Number(d.precio_unitario || 0);
        const s = Number(d.subtotal ?? (c * u));
        const esAcum = esProductoAcumulador(d);
        const poliza = esAcum ? (d.numero_poliza || d.no_poliza || '') : '';
        total += s;
        tb += `<tr>
          <td>${d.codigo || ('#'+(d.id_producto||''))}</td>
          <td>${d.producto || ('#'+(d.id_producto||''))}</td>
          <td class="text-center">${poliza || ''}</td>
          <td class="text-center">${c}</td>
          <td class="text-right">${mxn(u)}</td>
          <td class="text-right">${mxn(s)}</td>
        </tr>`;
      });
    }
    $('#det-tbody').html(tb);
    $('#det-total').text(mxn(total || v.total || 0));

    const pagosMixto = Array.isArray(resp.pagos_venta) ? resp.pagos_venta : resp.pagos;
    renderDesglosePagos(v, pagosMixto, v.total || total);

    // Exclusivos de crédito
    if (String(v.estatus).trim() === 'Credito') {
      $('#wrap-det-estatus-credito, #wrap-det-abonado, #wrap-det-saldo, #wrap-det-abonos').removeClass('d-none');

      if ($('#det-estatus-credito').length) $('#det-estatus-credito').html(getBadgeCredito(estCred));
      if ($('#det-abonado').length)         $('#det-abonado').text(mxn(abonado));
      if ($('#det-saldo').length)           $('#det-saldo').text(mxn(saldo));

      if ($('#det-abonos-body').length){
        const $ab = $('#det-abonos-body').empty();
        const abonos = Array.isArray(resp.abonos) ? resp.abonos : (Array.isArray(v.abonos) ? v.abonos : []);
        if (!abonos.length){
          $ab.append('<tr><td colspan="4" class="text-center text-muted">Sin abonos</td></tr>');
        } else {
          abonos.forEach(a=>{
            $ab.append(`
              <tr>
                <td>${fechaMx(a.fecha_abono)}</td>
                <td>${a.forma_pago_desc || '—'}</td>
                <td class="text-right">${mxn(a.monto)}</td>
                <td>${a.usuario_nombre || '—'}</td>
              </tr>
            `);
          });
        }
      }

      // Botón “Abonar” (si existe en el modal)
      if ($('#det-btn-abonar').length){
        if (saldo > 0.0001) {
          $('#det-btn-abonar').removeClass('d-none');
        } else {
          $('#det-btn-abonar').addClass('d-none');
        }
        $('#det-btn-abonar').off('click').on('click', function(){
          $('#modalDetalle').modal('hide');
          $(`a.accion-abonar-venta[data-id="${v.id_venta}"]`).trigger('click');
        });
      }
    } else {
      $wrapsCredito.addClass('d-none');
    }

    // ===== Garantías (con fallback para acumuladores/baterías) =====
    let garantias = [];
    if (Array.isArray(resp.garantias) && resp.garantias.length) {
      garantias = resp.garantias;
    } else if (Array.isArray(v.garantias) && v.garantias.length) {
      garantias = v.garantias;
    } else {
      garantias = construirGarantiasDesdeDetalles(dets); // <- NUEVO fallback
    }
    renderGarantiasEnDetalle(garantias);

    // Mostrar contenido
    $('#det-loader').hide();
    $('#det-contenido').show();

  },'json').fail(()=>{
    $('#det-loader').hide();
    $('#det-error').show().text('Error al cargar el detalle.');
  });
});

$(document).on('hidden.bs.modal', '#modalDetalle', function(){
  resetDetalleVentaModalState();
});


/* ==========================================================================
   MÓDULO: Ticket (modal Ticket)
   ========================================================================== */

// ====== Mismo límite que en PHP ======
const MAX_ART_CHARS = 60;

function limitarTextoJS(text = '', max = MAX_ART_CHARS){
  let t = (text || '').trim();
  if (!t || max <= 0 || t.length <= max) return t;

  if (typeof Intl !== 'undefined' && Intl.Segmenter){
    const seg = new Intl.Segmenter('es', { granularity: 'word' });
    let out = '';
    for (const { segment } of seg.segment(t)){
      if ((out + segment).length > max - 1) break;
      out += segment;
    }
    return (out.trim() || t.slice(0, max - 1)).trimEnd() + '…';
  }

  let cut = t.slice(0, Math.max(1, max - 1));
  cut = cut.replace(/\s+\S*$/, '');
  return (cut.trim() || t.slice(0, max - 1)).trimEnd() + '…';
}

function armarArticuloLineaJS(codigo, producto, descripcion, max = MAX_ART_CHARS){
  const cod  = (codigo ?? '').toString().trim();
  const prod = (producto ?? '').toString().trim();
  const desc = (descripcion ?? '').toString().trim();
  const base = desc !== '' ? desc : prod;
  const prefix = cod !== '' ? `[${cod}] - ` : '';
  return limitarTextoJS(prefix + base, max);
}

function formatCantidadJS(value){
  const raw = (value ?? '').toString().trim();
  if (!raw) return '0';
  const normalized = raw.replace(',', '.');
  if (!Number.isNaN(Number(normalized))) {
    return normalized.includes('.')
      ? normalized.replace(/0+$/, '').replace(/\.$/, '') || '0'
      : normalized;
  }
  // Último recurso: usa el número nativo sin redondear la representación de cadena
  const num = Number(value || 0);
  const txt = num.toString();
  return txt.includes('.') ? txt.replace(/0+$/, '').replace(/\.$/, '') : txt;
}

function resumirPagosTicket(venta, pagos){
  const formaPagoVenta = (venta?.forma_pago || '').toString().trim();
  const idFp = Number(venta?.id_forma_pago || 0);

  // Para NO mixto: usar siempre la forma de pago principal de ventas y no consultar pagos
  if (idFp !== 22) {
    return { forma: formaPagoVenta || '—', desglose: '' };
  }

  // Mixto: tomar etiqueta principal de ventas y el desglose de pagos_venta
  const activos = Array.isArray(pagos) ? pagos.filter(p => (p?.activo ?? 1) !== 0) : [];
  const sumas = new Map();

  activos.forEach(p => {
    const desc = (p.descripcion || '').toString().trim() || 'Pago';
    const monto = Number(p.monto || 0);
    sumas.set(desc, (sumas.get(desc) || 0) + monto);
  });

  if (sumas.size > 0) {
    const partes = [];
    sumas.forEach((monto, desc) => partes.push(`${desc}: ${mxn(monto)}`));
    return { forma: formaPagoVenta || 'Mixto', desglose: partes.join('  ') };
  }

  return { forma: formaPagoVenta || 'Mixto', desglose: '' };
}

function renderTkItem({ cantidad, articulo, precio_unitario, subtotal }){
  const cant   = formatCantidadJS(cantidad);
  const precio = mxn(precio_unitario || 0);
  const total  = mxn(subtotal || 0);
  return `
    <div class="tk-item">
      <div class="c-cant">${cant}</div>
      <div class="c-art">${articulo}</div>
      <div class="c-precio">${precio}</div>
      <div class="c-total">${total}</div>
    </div>`;
}

function inferirEstatus(v){
  let est = (v.estatus || '').toString().trim();
  if (est) return est;
  const cancelada = Number(v.cancelada || v.cancelado || 0) === 1;
  if (cancelada) return 'Cancelada';
  const fp = Number(v.id_forma_pago || 0);
  if (fp === 21) return 'Credito';
  return 'Activa';
}

window.abrirTicket = function(idVenta){
  $('#tk-items').empty();
  $('#tk-folio').text('—');
  $('#tk-fecha').text('—');
  $('#tk-total').text('$0.00');
  $('#tk-estatus').text('—');
  // ← reset cliente siempre
  $('#tk-cliente').text('—');
  $('#wrap-tk-cliente').addClass('d-none');
  // ← reset forma de pago
  $('#tk-fp').text('—');
  $('#tk-fp-det').text('—');
  $('#wrap-tk-fp-det').addClass('d-none');

  $('#tk-idventa').val(idVenta);

  $.get(VENTAS_URL, { accion:'detalle', id_venta:idVenta }, function(resp){
    if(!resp || !resp.venta){ alert('No se encontró la venta.'); return; }
    const v   = resp.venta || {};
    const det = resp.detalles || [];

    $('#tk-folio').text(v.folio || '—');
    $('#tk-fecha').text(fechaMx(v.fecha));

    const estatus = inferirEstatus(v);
    $('#tk-estatus').text(estatus);

    // ====== CLIENTE SOLO SI ES CRÉDITO ======
    const fp        = Number(v.id_forma_pago || 0);
    const esCredito = (estatus.toLowerCase() === 'credito') || fp === 21;

    const nombreCliente = (
      v.nombre_cliente ||
      v.cliente ||
      v.cliente_nombre ||
      'Público en general'
    ).toString().trim();

    if (esCredito && nombreCliente) {
      $('#tk-cliente').text(nombreCliente);
      $('#wrap-tk-cliente').removeClass('d-none');
    } else {
      $('#tk-cliente').text('—');
      $('#wrap-tk-cliente').addClass('d-none');
    }
    // =======================================

    const infoPagos = resumirPagosTicket(v, resp.pagos || []);
    $('#tk-fp').text(infoPagos.forma || '—');
    if (infoPagos.desglose) {
      $('#tk-fp-det').text(infoPagos.desglose);
      $('#wrap-tk-fp-det').removeClass('d-none');
    } else {
      $('#tk-fp-det').text('—');
      $('#wrap-tk-fp-det').addClass('d-none');
    }

    let html = '', total = 0;
    det.forEach(d=>{
      const cantNum = Number((d.cantidad ?? 0).toString().replace(',','.'));
      const u = Number(d.precio_unitario || 0);
      const s = Number(d.subtotal ?? (cantNum * u));
      total += s;

      const codigo      = d.codigo || d.clave || d.sku || '';
      const producto    = d.producto || d.nombre || '';
      const descripcion = d.descripcion || '';
      const articulo    = armarArticuloLineaJS(codigo, producto, descripcion, MAX_ART_CHARS);

      html += renderTkItem({
        cantidad: d.cantidad,
        articulo,
        precio_unitario: u,
        subtotal: s
      });
    });

    $('#tk-items').html(html);
    $('#tk-total').text(mxn(v.total != null ? v.total : total));
    $('#modalTicket').modal('show');
  }, 'json').fail(()=> alert('Error al cargar el ticket.'));
};

// Imprimir (usa el PDF que ya tiene el mismo formato)
$(document).on('click', '#btnImprimirTicket', function(){
  const id = $('#tk-idventa').val();
  if (!id){ toastr.error('No hay venta seleccionada'); return; }
  window.open(`${BASE_URL}/utils/ticket_pdf.php?id_venta=${encodeURIComponent(id)}`, '_blank');
});


/* ==========================================================================
   MÓDULO: Cancelación (modal Eliminar)
   ========================================================================== */
$(document).on('click','a.accion-eliminar',function(e){
  e.preventDefault();
  const id=$(this).data('id'), folio=$(this).data('folio'); if(!id) return;
  $('#el-id-venta').val(id); $('#el-folio').text(folio); $('#modalEliminar').modal('show');
});

$(document).off('click','#btnConfirmarEliminar').on('click','#btnConfirmarEliminar',function(){
  const id=$('#el-id-venta').val(); if(!id) return;
  const $b=$(this), txt=$b.html();
  $b.prop('disabled',true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Eliminando...');
  $.post(VENTAS_URL,{accion:'eliminar', id_venta:id},function(r){
    if (r&&(r.ok===true||r.resultado==='ok')){ toastr.success(r?.msg||'Venta eliminada con éxito'); cargarVentas(paginaActual); }
    else { toastr.error(r?.msg||r?.resultado||'Error al eliminar.'); }
  },'json').fail(()=> toastr.error('No se pudo conectar con el servidor.'))
  .always(()=>{ $('#modalEliminar').modal('hide'); $b.prop('disabled',false).html(txt); });
});


/* ==========================================================================
   MÓDULO: Activar venta guardada (modal Activar)
   ========================================================================== */
let AC_TIENE_CLIENTE   = false;
let AC_FP_CREDITO_ID   = 21;
let AC_FP_MIXTO_ID     = null;
let AC_CLIENTES_CARGADOS = false;

/* ------------ CLIENTES ------------ */
function ac_cargarClientes(preselectId) {
  const $sel = $('#ac-selCliente');
  if (AC_CLIENTES_CARGADOS && !preselectId) return;

  $.post(CLIENTES_URL, {accion:'listar-min', limite:200})
    .done(r=>{
      const data = r?.data || (Array.isArray(r)?r:[]);
      $sel.empty().append(`<option value="">-- Selecciona cliente --</option>`);
      data.forEach(c=>{
        const id = c.id_cliente ?? c.id;
        const nombre = c.nombre ?? c.razon_social ?? c.nombre_comercial ?? 'Cliente';
        if (id!=null && id!=='') $sel.append(`<option value="${id}">${nombre}</option>`);
      });
      if (preselectId) $sel.val(String(preselectId));
      AC_CLIENTES_CARGADOS = true;
    })
    .fail(()=>{ 
      $sel.empty().append(`<option value="">(No se pudieron cargar clientes)</option>`); 
    });
}

/* ------------ DETECTAR CRÉDITO / MIXTO ------------ */
function ac_esCreditoSeleccionado(){
  const $sel = $('#ac-selFormaPago');
  const val  = ($sel.val() ?? '').toString().trim();

  if (AC_FP_CREDITO_ID != null && val === String(AC_FP_CREDITO_ID)) return true;

  const txt = norm($sel.find('option:selected').text());
  return txt.includes('credito') && !txt.includes('tarjeta');
}

function ac_esMixtoSeleccionado(){
  const $sel = $('#ac-selFormaPago');
  const val  = ($sel.val() ?? '').toString().trim();

  if (AC_FP_MIXTO_ID != null && val === String(AC_FP_MIXTO_ID)) return true;

  const txt = norm($sel.find('option:selected').text());
  return txt.includes('mixto');
}

/* ------------ UI: Cliente y bloque mixto ------------ */
function ac_toggleMixtoUI(){
  const $wrap = $('#ac-wrapMixto');
  if (!$wrap.length) return;

  const esMixto = ac_esMixtoSeleccionado();
  if (esMixto){
    $wrap.removeClass('d-none');
    ac_recalcMixto();
  } else {
    $wrap.addClass('d-none');
  }
}

function ac_toggleClienteRequired(){
  const $wrap = $('#ac-wrapCliente');
  const $help = $('#ac-helpCliente');

  if (ac_esCreditoSeleccionado()){
    $wrap.removeClass('d-none').show();
    $help.text(AC_TIENE_CLIENTE
      ? 'La venta ya tiene cliente; puedes cambiarlo si es necesario.'
      : 'Selecciona el cliente para activar como Crédito.'
    );
    ac_cargarClientes(null);
  } else {
    $wrap.addClass('d-none').hide();
  }

  // además de cliente, actualizamos bloque de pago mixto
  ac_toggleMixtoUI();
}

/* ------------ Mixto: helpers ------------ */
function ac_getOpcionesMixtoHTML(){
  // clona opciones del select principal, excluyendo Crédito y Mixto
  const $sel = $('#ac-selFormaPago');
  let html = '<option value="">-- Selecciona --</option>';

  $sel.find('option').each(function(){
    const val = $(this).val();
    const txt = $(this).text();
    const normTxt = norm(txt);

    if (!val) return;
    if (AC_FP_CREDITO_ID != null && val === String(AC_FP_CREDITO_ID)) return;
    if (AC_FP_MIXTO_ID   != null && val === String(AC_FP_MIXTO_ID))   return;
    if (normTxt.includes('credito') && !normTxt.includes('tarjeta'))  return;
    if (normTxt.includes('mixto'))                                    return;

    html += `<option value="${val}">${txt}</option>`;
  });

  return html;
}

function ac_addPagoMixtoRow(){
  const $tb = $('#ac-tbMixto');
  if (!$tb.length) return;

  const opts = ac_getOpcionesMixtoHTML();
  const row = `
    <tr>
      <td>
        <select class="form-control form-control-sm ac-mix-fp">
          ${opts}
        </select>
      </td>
      <td>
        <input type="number" min="0" step="0.01"
               class="form-control form-control-sm ac-mix-monto"
               placeholder="0.00">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger ac-mix-del">
          <i class="mdi mdi-close"></i>
        </button>
      </td>
    </tr>`;

  $tb.append(row);

  // seleccionar EFECTIVO por default en la fila recién agregada
  const $lastRow = $tb.find('tr').last();
  const $sel     = $lastRow.find('.ac-mix-fp');
  const $optEf   = $sel.find('option').filter(function(){
    return norm($(this).text()).includes('efectivo');
  }).first();
  if ($optEf.length) {
    $sel.val($optEf.val());
  }

  ac_recalcMixto();
}

function ac_recalcMixto(){
  const totalVenta = num($('#ac-total-venta').val());
  let suma = 0;

  $('#ac-tbMixto tr').each(function(){
    const monto = num($(this).find('.ac-mix-monto').val());
    suma += monto;
  });

  $('#ac-totalVentaTexto').text(mxn(totalVenta));
  $('#ac-sumaPagosTexto').text(mxn(suma));

  const $help = $('#ac-helpSumaPagos');
  const diff  = Math.abs(suma - totalVenta);

  if (totalVenta > 0 && diff > 0.05){
    $help.removeClass('d-none');
  } else {
    $help.addClass('d-none');
  }
}

function ac_getPagosMixtoValidados(){
  const totalVenta = num($('#ac-total-venta').val());
  const $help = $('#ac-helpSumaPagos');
  const pagos = [];
  let suma = 0;
  let error = null;

  $('#ac-tbMixto tr').each(function(){
    const id_fp = Number($(this).find('.ac-mix-fp').val() || 0);
    const monto = num($(this).find('.ac-mix-monto').val());

    if (!id_fp && !monto) return; // fila vacía, se ignora

    if (!id_fp || monto <= 0){
      error = 'Cada renglón de pago mixto debe tener forma de pago y monto mayor a 0.';
      return false; // break
    }

    suma += monto;
    pagos.push({ id_forma_pago: id_fp, monto });
  });

  if (error) return { ok:false, msg:error };

  if (!pagos.length){
    return { ok:false, msg:'Agrega al menos un pago en el esquema mixto.' };
  }

  const diff = Math.abs(suma - totalVenta);
  if (totalVenta > 0 && diff > 0.05){
    $help.removeClass('d-none');
    return { ok:false, msg:'La suma de los pagos no coincide con el total de la venta.' };
  }

  $help.addClass('d-none');
  return { ok:true, pagos, suma };
}

/* ------------ Abrir modal Activar ------------ */
$(document).on('click','a.accion-activar', function(e){
  e.preventDefault();
  const id = $(this).data('id');
  const folio = $(this).data('folio') || '—';
  if (!id) return;

  $('#ac-id-venta').val(id);
  $('#ac-folio').text(folio);
  $('#ac-error').addClass('d-none').empty();
  $('#ac-fechaAhora').prop('checked', true);
  AC_TIENE_CLIENTE = false;
  $('#ac-wrapCliente').addClass('d-none').hide();
  $('#ac-wrapMixto').addClass('d-none');
  $('#ac-tbMixto').empty();
  $('#ac-total-venta').val(0);
  $('#ac-totalVentaTexto').text('$0.00');
  $('#ac-sumaPagosTexto').text('$0.00');
  $('#ac-helpSumaPagos').addClass('d-none');

  $.get(VENTAS_URL, {accion:'detalle', id_venta:id})
    .done(r=>{
      const v = r?.venta || {};
      AC_TIENE_CLIENTE = !!(v.id_cliente);
      const preIdCliente = v.id_cliente || '';

      // guardamos total de la venta guardada para validar mixto
      const totalVenta = num(v.total || 0);
      $('#ac-total-venta').val(totalVenta);
      $('#ac-totalVentaTexto').text(mxn(totalVenta));

      $.get(FORMASPAGO_URL, {accion:'listar_select'})
        .done(rr=>{
          const arr = rr?.data || (Array.isArray(rr)?rr:[]);
          const $sel = $('#ac-selFormaPago').empty();

          if (!arr.length){
            $sel.append(
              '<option value="1">Efectivo</option>'+
              '<option value="2">Tarjeta</option>'+
              '<option value="3">Mixto</option>'+
              '<option value="21">Crédito (PPD)</option>'
            );
            AC_FP_CREDITO_ID = 21;
            AC_FP_MIXTO_ID   = 3;
          } else {
            arr.forEach(fp=>{
              const opt = $('<option/>', { value: fp.id_forma_pago, text: fp.descripcion });
              $sel.append(opt);

              const t = norm(fp.descripcion || '');
              if (t.includes('credito') && !t.includes('tarjeta')) {
                AC_FP_CREDITO_ID = fp.id_forma_pago;
              }
              if (t.includes('mixto')) {
                AC_FP_MIXTO_ID = fp.id_forma_pago;
              }
            });

            // seleccionar Efectivo por defecto si existe
            const $ef = $sel.find('option').filter(function(){
              return norm($(this).text()).includes('efectivo');
            });
            if ($ef.length) $ef.prop('selected', true);
          }

          if (AC_TIENE_CLIENTE) {
            $(document).one('change','#ac-selFormaPago', () => ac_cargarClientes(preIdCliente));
          }

          ac_toggleClienteRequired();
          $('#modalActivarVenta').modal('show');
        })
        .fail(()=>{
          const $sel = $('#ac-selFormaPago').empty();
          $sel.append(
            '<option value="1">Efectivo</option>'+
            '<option value="2">Tarjeta</option>'+
            '<option value="3">Mixto</option>'+
            '<option value="21">Crédito (PPD)</option>'
          );
          AC_FP_CREDITO_ID = 21;
          AC_FP_MIXTO_ID   = 3;
          ac_toggleClienteRequired();
          $('#modalActivarVenta').modal('show');
        });
    })
    .fail(()=>{
      $('#ac-error').removeClass('d-none').text('No se pudo verificar la venta. Intenta de nuevo.');
      $('#modalActivarVenta').modal('show');
    });
});

/* cambio de forma de pago */
$(document)
  .off('change.acfp', '#ac-selFormaPago')
  .on('change.acfp',  '#ac-selFormaPago', ac_toggleClienteRequired);

/* agregar/eliminar/editar pagos mixtos */
$(document).on('click', '#ac-btnAddPago', function(){
  ac_addPagoMixtoRow();
});

$(document).on('click', '.ac-mix-del', function(){
  $(this).closest('tr').remove();
  ac_recalcMixto();
});

$(document).on('input change', '.ac-mix-monto, .ac-mix-fp', function(){
  ac_recalcMixto();
});

/* ------------ Confirmar Activar ------------ */
$(document)
  .off('click','#btnConfirmarActivar')
  .on('click','#btnConfirmarActivar', function(){
    const idVenta    = Number($('#ac-id-venta').val());
    const idFormaSel = $('#ac-selFormaPago').val() ? Number($('#ac-selFormaPago').val()) : null;
    const fechaAhora = $('#ac-fechaAhora').is(':checked') ? 1 : 0;

    if (!idVenta){ return; }

    const esCredito = ac_esCreditoSeleccionado();
    const esMixto   = ac_esMixtoSeleccionado();

    const idClienteSel = $('#ac-selCliente').val() ? Number($('#ac-selCliente').val()) : null;

    if (!esMixto && !idFormaSel){
      $('#ac-error').removeClass('d-none').text('Selecciona una forma de pago.');
      return;
    }

    if (esCredito && !AC_TIENE_CLIENTE && !idClienteSel){
      $('#ac-error').removeClass('d-none').text('Para activar como Crédito debes seleccionar un cliente.');
      return;
    }

    const $btn = $(this);
    const html = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Activando...');

    const payload = {
      accion: 'activar-guardada',
      id_venta: idVenta,
      id_cliente: idClienteSel || '',
      actualizar_fecha: fechaAhora
    };

    if (esMixto){
      const mix = ac_getPagosMixtoValidados();
      if (!mix.ok){
        $('#ac-error').removeClass('d-none').text(mix.msg);
        $btn.prop('disabled', false).html(html);
        return;
      }

      payload.tipo_pago     = 'mixto';
      payload.id_forma_pago = idFormaSel || '';          // guardar "Mixto" en ventas.id_forma_pago
      payload.pagos         = JSON.stringify(mix.pagos); // renglones de pagos_venta
    } else {
      payload.id_forma_pago = idFormaSel;
    }

    $.post(VENTAS_URL, payload, function(r){
      if (r && r.ok){
        toastr.success(r.msg || 'Venta activada.');
        $('#modalActivarVenta').modal('hide');
        cargarVentas(paginaActual);
      } else {
        $('#ac-error').removeClass('d-none').text(r?.msg || 'No se pudo activar la venta.');
      }
    }, 'json')
    .fail(()=>{
      $('#ac-error').removeClass('d-none').text('Error de comunicación con el servidor.');
    })
    .always(()=>{
      $btn.prop('disabled', false).html(html);
    });
});


/* ==========================================================================
   MÓDULO: Editor de venta (modal Editar)
   ========================================================================== */
const $modalEd = $('#modalEditarVenta');
const $edFolio = $('#ed-folio');
const $edEst   = $('#ed-estatus');
const $edFechaInfo = $('#ed-fecha');
const $edUsrCaja   = $('#ed-usr-caja');

const $selCliente = $('#ed-selCliente');
const $selForma   = $('#ed-selFormaPago');
const $tpPrecio   = $('#ed-tpPrecio');
const $fechaVenta = $('#ed-fechaVenta');

const $buscar = $('#ed-buscar');
const $sug    = $('#ed-sug');

const $wrapEmpty = $('#ed-wrapCarritoVacio');
const $wrapTable = $('#ed-wrapCarritoTabla');
const $tbody     = $('#ed-tbody');
const $totalEd   = $('#ed-total');
const $btnSave   = $('#btnGuardarEdicion');
const $errEd     = $('#ed-error');
const $wrapMixto = $('#ed-wrapMixto');
const $mixEfec   = $('#ed-mixto-efectivo');
const $mixTar    = $('#ed-mixto-tarjeta');
const $mixTarSel = $('#ed-mixto-tarjeta-tipo');
const $mixTarWrap= $('#ed-wrapMixtoTarjetaTipo');
const $mixHelp   = $('#ed-helpMixto');

let edVentaId = 0;
let carrito   = [];
let debTimer = null;
let pagosVenta = [];
let edIdsPago = { efectivo:null, tarjeta:null, mixto:null };
let formasPagoTarjeta = [];

function vendibleDe(det){ return Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo)); }
const esProductoAcumulador = (it) => {
  if (!ID_GRUPO_ACUMULADOR) return false;
  return Number(it?.id_grupo ?? 0) === Number(ID_GRUPO_ACUMULADOR);
};
function mapTipoPrecioId(slug){ const m={publico:1, taller:2, proveedor:3}; return m[slug]||1; }

function precioDeItem(it){
  if (typeof it.override_unit === 'number' && !isNaN(it.override_unit)) return Number(it.override_unit);
  const t = $tpPrecio.val() || 'publico';
  if (t==='taller') return Number(it.precio_taller||0);
  if (t==='proveedor') return Number(it.precio_proveedor||0);
  return Number(it.precio_publico||0);
}

function setClientesOptions(arr, selected){
  $selCliente.empty().append(`<option value="">-- Seleccionar Opción --</option>`);
  (arr||[]).forEach(c=>{
    const id=c.id_cliente;
    const nombre = c.nombre ?? c.razon_social ?? c.nombre_comercial ?? 'Cliente';
    if (id!=null && id!=='') $selCliente.append(`<option value="${id}">${nombre}</option>`);
  });
  if (selected!=null) $selCliente.val(String(selected));
}

function cargarClientes(selected){
  const LIM=200;
  $.post(`${CLIENTES_URL}`,{accion:'listar-min', limite:LIM})
    .done(r=>{
      const data=r?.data || (Array.isArray(r)?r:[]);
      if (data.length) setClientesOptions(data, selected);
      else {
        $.post(`${CLIENTES_URL}`,{accion:'listar', pagina:1, limite:LIM})
         .done(r2=> setClientesOptions(r2?.data||[], selected))
         .fail(()=> setClientesOptions([], selected));
      }
    }).fail(()=>{
      $.post(`${CLIENTES_URL}`,{accion:'listar', pagina:1, limite:LIM})
       .done(r2=> setClientesOptions(r2?.data||[], selected))
       .fail(()=> setClientesOptions([], selected));
    });
}

function ed_setIdsPago(){
  edIdsPago = {efectivo:null, tarjeta:null, mixto:null};
  $selForma.find('option').each(function(){
    const val = $(this).val();
    const txt = norm($(this).text());
    if (!val) return;
    if (txt.includes('mixto')) edIdsPago.mixto = Number(val);
    if (txt.includes('efectivo')) edIdsPago.efectivo = Number(val);
    if (txt.includes('tarjeta')) edIdsPago.tarjeta = Number(val);
  });
}

function ed_setFormasTarjeta(arr, selected = null){
  formasPagoTarjeta = Array.isArray(arr) ? arr : [];
  $mixTarSel.empty().append('<option value="">Seleccione tipo…</option>');
  formasPagoTarjeta.forEach(fp => {
    if (!fp || fp.id_forma_pago === undefined) return;
    $mixTarSel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`);
  });
  if (selected !== null) {
    $mixTarSel.val(String(selected));
  }
}

function cargarFormasPago(selected, fallbackText){
  $.get(`${FORMASPAGO_URL}`,{accion:'listar_select'})
    .done(r=>{
      const arr=r?.data || (Array.isArray(r)?r:[]);
      $selForma.empty();

      if (!arr.length){
        $selForma.append(`<option value="">(sin formas de pago)</option>`);
        return;
      }

      arr.forEach(fp=>{
        const opt = $('<option/>', { value: fp.id_forma_pago, text: fp.descripcion });
        $selForma.append(opt);
      });

      ed_setIdsPago();

      if (selected!=null) {
        $selForma.val(String(selected));
      } else if (fallbackText){
        $selForma.find('option').filter(function(){return $(this).text()===fallbackText;}).prop('selected',true);
      }
      ed_toggleMixtoUI();
    })
    .fail(()=>{
      $selForma.empty()
        .append('<option value="1">Efectivo</option>')
        .append('<option value="2">Tarjeta</option>')
        .append('<option value="3">Mixto</option>')
        .append('<option value="99">Crédito</option>');
      ed_setIdsPago();
      if (selected!=null) $selForma.val(String(selected));
      ed_toggleMixtoUI();
    });
}

function isCreditoByOption($opt){
  const txtN = norm($opt.text());
  return !txtN.includes('tarjeta') && /^\s*credito(?:\s*\(.*\))?\s*$/.test(txtN);
}

function sugHTMLBasico(p){
  return `
    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
       data-id="${p.id_producto}">
      <div class="me-2" style="min-width:0">
        <div class="text-truncate"><strong>${p.codigo}</strong> — ${p.descripcion||p.nombre||''}</div>
        <div class="small text-muted" data-slot="extra">Cargando detalles…</div>
      </div>
      <i class="mdi mdi-plus-circle-outline"></i>
    </a>`;
}

function renderSugerencias(arr){
  $sug.empty();
  if(!arr.length){ $sug.hide(); return; }
  arr.forEach(p=>$sug.append(sugHTMLBasico(p)));
  $sug.show();

  $sug.find('a.list-group-item').each(function(){
    const id=Number($(this).data('id')), $row=$(this);
    $.post(PRODUCTOS_URL,{accion:'detalle', id_producto:id})
     .done(r=>{
       const det=r?.data||{};
       const pub = Number(det.precio_publico ?? 0);
       const tal = Number(det.precio_taller ?? 0);
       const stk = Number(det.stock_actual ?? det.existencia ?? 0);
       const extra = `<span>Taller: ${mxn(tal)}</span> · <span>Público: ${mxn(pub)}</span> · <span>Exist: ${fix2(stk)}</span>`;
       const sinStock = Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo)) <= 0;
       $row.find('[data-slot="extra"]').html(extra);
       $row.toggleClass('disabled', sinStock).attr('aria-disabled', sinStock ? 'true' : null);
     });
  });
}

function buscar(q){
  if(!q||q.length<2){ $sug.hide().empty(); return; }
  $.post(PRODUCTOS_URL,{accion:'buscar-min', q, limite:20})
   .done(r=>renderSugerencias(r?.data||[]))
   .fail(()=> $sug.hide().empty());
}

$('#ed-buscar').on('input', function(){
  const val=this.value.trim(); clearTimeout(debTimer);
  debTimer = setTimeout(()=>buscar(val), 220);
});

$sug.on('click','.list-group-item',function(e){
  e.preventDefault(); if($(this).hasClass('disabled')) return;
  seleccionarPorId(Number($(this).data('id')));
});

$(document).on('click', e=>{ if(!$(e.target).closest('#ed-buscar,#ed-sug').length){ $sug.hide().empty(); } });

function seleccionarPorId(idProd){
  $.post(PRODUCTOS_URL,{accion:'detalle', id_producto:idProd})
   .done(r=>{
     const det=r?.data;
     if(!det){ toastr.error('No se encontró el detalle del producto'); return; }
     const vendible=Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo));
     if (vendible<=0){ toastr.warning('Sin stock disponible.'); return; }
     agregarDesdeDetalle(det);
     $('#ed-buscar').val(''); $sug.hide().empty();
   })
   .fail(()=> toastr.error('No se pudo obtener el detalle del producto'));
}

function agregarDesdeDetalle(p, originalCant=0){
  const idx = carrito.findIndex(x=>x.id_producto==p.id_producto);
  const itemBase = {
    id_producto:p.id_producto, codigo:p.codigo, descripcion:p.descripcion,
    id_grupo: p.id_grupo ?? null,
    stock_actual:Number(p.stock_actual ?? p.existencia ?? 0),
    stock_minimo:Number(p.stock_minimo ?? 0),
    precio_publico:Number(p.precio_publico ?? 0),
    precio_taller:Number(p.precio_taller ?? 0),
    precio_proveedor:Number(p.precio_proveedor ?? 0),
    proveedor:p.proveedor ?? null,
    original: Number(originalCant||0),
    numero_poliza: (p.numero_poliza ?? '').toString().trim()
  };
  const vendible=Math.max(0, itemBase.stock_actual - itemBase.stock_minimo);
  if(vendible<=0 && itemBase.original<=0){ toastr.warning('Sin stock disponible para vender.'); return; }
  if (esProductoAcumulador(itemBase)) {
    if (idx>=0) {
      carrito[idx].cantidad = 1;
      if (!carrito[idx].numero_poliza) carrito[idx].numero_poliza = itemBase.numero_poliza;
    } else {
      carrito.push({...itemBase, cantidad: 1});
    }
    pintarCarrito();
    return;
  }

  if(idx>=0){
    const max = itemBase.original + vendible;
    const next= Math.min(max, Number(carrito[idx].cantidad)+1);
    carrito[idx].cantidad = next;
  } else {
    carrito.push({...itemBase, cantidad: Math.max(1, itemBase.original || 1)});
  }
  pintarCarrito();
}

function precioDeItemCarrito(it){
  if (typeof it.override_unit === 'number' && !isNaN(it.override_unit)) return Number(it.override_unit);
  const t = $tpPrecio.val() || 'publico';
  if (t==='taller') return Number(it.precio_taller||0);
  if (t==='proveedor') return Number(it.precio_proveedor||0);
  return Number(it.precio_publico||0);
}

function ed_totalCarrito(){
  return carrito.reduce((acc,it)=>{
    const qty = Number(it.cantidad) || 0;
    return acc + qty * precioDeItemCarrito(it);
  },0);
}

function ed_esMixtoSeleccionado(){
  const val = ($selForma.val() ?? '').toString().trim();
  if (edIdsPago.mixto && val === String(edIdsPago.mixto)) return true;
  const txt = norm($selForma.find('option:selected').text());
  return txt.includes('mixto');
}

function ed_obtenerIdPagoPorTexto(buscar){
  const target = norm(buscar);
  let found = null;
  $selForma.find('option').each(function(){
    const txt = norm($(this).text());
    if (txt.includes(target)) { found = Number($(this).val()); return false; }
  });
  return found;
}

function ed_poblarPagosMixtoDesdeBD(){
  if (!ed_esMixtoSeleccionado()) { return; }
  let ef=0, tar=0, idTarjetaBD=null;
  pagosVenta.forEach(p=>{
    const txt = norm(p.descripcion || p.forma_pago || '');
    if (txt.includes('efectivo')) ef += Number(p.monto||0);
    if (txt.includes('tarjeta'))  {
      tar += Number(p.monto||0);
      if (p.id_forma_pago) idTarjetaBD = Number(p.id_forma_pago);
    }
  });
  $mixEfec.val(fix2(ef));
  $mixTar.val(fix2(tar));
  if (idTarjetaBD && formasPagoTarjeta.some(fp => Number(fp.id_forma_pago) === idTarjetaBD)) {
    $mixTarSel.val(String(idTarjetaBD));
  } else {
    $mixTarSel.val('');
  }
  ed_toggleTarjetaTipo();
  ed_validarMixto(false);
}

function ed_toggleTarjetaTipo(){
  const esMixto = ed_esMixtoSeleccionado();
  const tar = num($mixTar.val());
  const mostrar = esMixto && (tar > 0 || ($mixTarSel.val() || '') !== '');
  $mixTarWrap.toggleClass('d-none', !mostrar);
  if (!mostrar) {
    $mixTarSel.val('');
  }
}

function ed_toggleMixtoUI(){
  if (ed_esMixtoSeleccionado()){
    $wrapMixto.removeClass('d-none');
    ed_poblarPagosMixtoDesdeBD();
  } else {
    $wrapMixto.addClass('d-none');
    $mixTarSel.val('');
    ed_toggleTarjetaTipo();
  }
}

function ed_validarMixto(showMsg = true){
  if (!ed_esMixtoSeleccionado()) return {ok:true, pagos:[]};

  const total = ed_totalCarrito();
  const ef = num($mixEfec.val());
  const tar = num($mixTar.val());
  const suma = ef + tar;
  const diff = Math.abs(suma - total);

  if (total > 0 && diff > 0.05){
    if (showMsg) {
      $mixHelp.removeClass('text-muted').text('La suma del pago mixto debe coincidir con el total.');
    }
    return {ok:false, msg:'La suma del pago mixto no coincide con el total.'};
  }

  if (suma <= 0){
    if (showMsg) {
      $mixHelp.removeClass('text-muted').text('Captura al menos un monto para el pago mixto.');
    }
    return {ok:false, msg:'Captura al menos un monto para el pago mixto.'};
  }

  const pagos = [];
  const idEf = edIdsPago.efectivo || ed_obtenerIdPagoPorTexto('efectivo');
  let idTarjeta = $mixTarSel.val() ? Number($mixTarSel.val()) : null;

  if (tar > 0) {
    const esTarjetaValida = formasPagoTarjeta.some(fp => Number(fp.id_forma_pago) === idTarjeta);
    if (!idTarjeta || !esTarjetaValida) {
      if (showMsg) {
        toastr.error('Selecciona el tipo de tarjeta (crédito o débito) para el pago con tarjeta.');
      }
      $mixHelp.removeClass('text-muted').text('Selecciona el tipo de tarjeta para el pago con tarjeta.');
      return {ok:false, msg:'Selecciona el tipo de tarjeta (crédito o débito) para el pago con tarjeta.'};
    }
  } else {
    idTarjeta = null;
  }

  if (ef > 0 && idEf) pagos.push({ id_forma_pago:idEf, monto:ef });
  if (tar > 0 && idTarjeta) pagos.push({ id_forma_pago:idTarjeta, monto:tar });

  if (!pagos.length){
    return {ok:false, msg:'No se pudieron armar los pagos mixtos.'};
  }

  $mixHelp.addClass('text-muted').text('La suma debe coincidir con el total de la venta.');
  return {ok:true, pagos, suma};
}

function pintarCarrito(){
  const tb=$tbody.empty();
  if(!carrito.length){
    $wrapEmpty.removeClass('d-none');
    $wrapTable.addClass('d-none');
    $totalEd.text('$0.00');
    return;
  }
  $wrapEmpty.addClass('d-none');
  $wrapTable.removeClass('d-none');

  let total=0;
  carrito.forEach((it,idx)=>{
    const unit = precioDeItemCarrito(it);
    const cantidad = Number(it.cantidad) || 0;
    const subtotal = cantidad * unit;
    total += subtotal;
    const vendible = Math.max(0, Number(it.stock_actual) - Number(it.stock_minimo));
    const max = Number(it.original||0) + vendible;
    const requierePoliza = esProductoAcumulador(it);
    const polizaHtml = requierePoliza ? `
      <div class="mt-1">
        <label class="form-label mb-0"><small>Número de póliza *</small></label>
        <input type="text" class="form-control form-control-sm" data-ed-poliza="${idx}" maxlength="80"
               pattern="[A-Za-z0-9-]+" value="${it.numero_poliza ? it.numero_poliza : ''}" placeholder="Captura póliza">
      </div>` : '';
    const qtyAttrs = requierePoliza ? 'min="1" step="1" readonly' : 'min="0.01" step="0.01"';

    tb.append(`
      <tr>
        <td>
          <div class="d-flex align-items-center">
            <div>
              <div class="fw-semibold">${it.descripcion}</div>
              <div class="small text-muted">
                Cod: ${it.codigo} ${it.proveedor?`· Prov: ${it.proveedor}`:``}
                · Exist: <span class="badge ${Number(it.stock_actual)>0?'bg-success':'bg-secondary'} badge-stock">${fix2(it.stock_actual)}</span>
                · Máx vendible: ${fix2(max)}
                ${requierePoliza ? '· Requiere póliza' : ''}
              </div>
              ${polizaHtml}
            </div>
          </div>
        </td>
        <td class="text-center">
          <div class="btn-group btn-group-sm" role="group">
            <button class="btn btn-outline-danger" data-ed-dec="${idx}" ${requierePoliza ? 'disabled' : ''}><i class="mdi mdi-minus"></i></button>
            <input type="number" ${qtyAttrs} class="form-control form-control-sm text-center w-70px" value="${fix2(it.cantidad)}" data-ed-qty="${idx}" data-max="${max}">
            <button class="btn btn-outline-success" data-ed-inc="${idx}" ${requierePoliza ? 'disabled' : ''}><i class="mdi mdi-plus"></i></button>
          </div>
        </td>
        <td class="text-end">
          <input type="number" min="0" step="0.01"
                 class="form-control form-control-sm text-end"
                 value="${fix2(unit)}" data-ed-unit="${idx}"
                 title="Editar costo unitario">
        </td>
        <td class="text-end">
          <input type="number" min="0" step="0.01"
                 class="form-control form-control-sm text-end"
                 value="${fix2(subtotal)}" data-ed-sub="${idx}"
                 title="Editar subtotal (ajusta el precio unitario automáticamente)">
        </td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger" data-ed-del="${idx}"><i class="mdi mdi-delete"></i></button>
        </td>
      </tr>`);
  });
  $totalEd.text(mxn(total));
  if (!$wrapMixto.hasClass('d-none')) ed_validarMixto(false);
}

$tbody.on('click','button[data-ed-inc]', function(){
  const i=Number(this.dataset.edInc); if(isNaN(i)||!carrito[i]) return;
  if (esProductoAcumulador(carrito[i])) { carrito[i].cantidad = 1; pintarCarrito(); return; }
  const vendible=Math.max(0, Number(carrito[i].stock_actual) - Number(carrito[i].stock_minimo));
  const max = Number(carrito[i].original||0) + vendible;
  const actual = parseFloat(carrito[i].cantidad) || 0;
  const next = actual + 1;
  carrito[i].cantidad = next>max ? (toastr.info('Se alcanzó el máximo vendible.'), max) : next;
  pintarCarrito();
});
$tbody.on('click','button[data-ed-dec]', function(){
  const i=Number(this.dataset.edDec); if(isNaN(i)||!carrito[i]) return;
  if (esProductoAcumulador(carrito[i])) { carrito[i].cantidad = 1; pintarCarrito(); return; }
  const actual = parseFloat(carrito[i].cantidad) || 0;
  carrito[i].cantidad=Math.max(0.01, actual-1);
  pintarCarrito();
});
$tbody.on('change','input[data-ed-qty]', function(){
  const i=Number(this.dataset.edQty); if(isNaN(i)||!carrito[i]) return;
  if (esProductoAcumulador(carrito[i])) { carrito[i].cantidad = 1; this.value='1.00'; return; }
  const raw = String(this.value || '').replace(',', '.');
  let val=parseFloat(raw);
  if (!Number.isFinite(val)) val = parseFloat(carrito[i].cantidad) || 0.01;
  val=Math.max(0.01, val);
  const max=Number(this.dataset.max||0);
  if (val>max){ val=max; toastr.info('Se ajustó a máximo vendible.'); }
  carrito[i].cantidad=val; pintarCarrito();
});
$tbody.on('input change','input[data-ed-poliza]', function(){
  const i=Number(this.dataset.edPoliza); if(isNaN(i)||!carrito[i]) return;
  carrito[i].numero_poliza = (this.value || '').trim();
});
$tbody.on('change','input[data-ed-unit]', function(){
  const i=Number(this.dataset.edUnit); if(isNaN(i)||!carrito[i]) return;
  const raw = String(this.value || '').replace(',', '.');
  let unit=parseFloat(raw); if(!Number.isFinite(unit)||unit<0) unit=0;
  carrito[i].override_unit = unit;
  pintarCarrito();
});
$tbody.on('change','input[data-ed-sub]', function(){
  const i=Number(this.dataset.edSub); if(isNaN(i)||!carrito[i]) return;
  const raw = String(this.value || '').replace(',', '.');
  let sub=parseFloat(raw); if(!Number.isFinite(sub)||sub<0) sub=0;
  const qty=Math.max(0.01, parseFloat(carrito[i].cantidad)||0.01);
  carrito[i].override_unit = Number((sub/qty).toFixed(2));
  pintarCarrito();
});
$tbody.on('click','button[data-ed-del]', function(){
  const i=Number(this.dataset.edDel); if(isNaN(i)) return;
  carrito.splice(i,1); pintarCarrito();
});

$tpPrecio.on('change', pintarCarrito);
$selForma.on('change', ed_toggleMixtoUI);
$mixEfec.add($mixTar).on('input change', ()=> { ed_toggleTarjetaTipo(); ed_validarMixto(); });
$mixTarSel.on('change', ()=> ed_validarMixto());

function validarPolizasEdicion(){
  if (!ID_GRUPO_ACUMULADOR) return true;
  const faltante = carrito.find(it => esProductoAcumulador(it) && !(it.numero_poliza || '').trim());
  if (faltante) {
    toastr.error(`Captura el número de póliza para ${faltante.descripcion || 'la batería/acumulador'}.`);
    return false;
  }
  return true;
}

$('#btnGuardarEdicion').on('click', function(){
  if (!edVentaId){ toastr.error('No hay venta cargada.'); return; }
  if (!carrito.length){ toastr.warning('Agrega productos a la orden'); return; }
  if (!validarPolizasEdicion()) return;

  const $opt = $selForma.find('option:selected');
  const esCredito = isCreditoByOption($opt);
  const esMixto = ed_esMixtoSeleccionado();

  const idClienteSel = $selCliente.val() ? Number($selCliente.val()) : null;
  if (esCredito && !idClienteSel){
    toastr.error('Para ventas a crédito es obligatorio seleccionar un cliente.');
    $selCliente.focus();
    return;
  }

  const id_forma_pago = $selForma.val() ? Number($selForma.val()) : null;
  if (!id_forma_pago){
    toastr.error('Selecciona una forma de pago.');
    return;
  }
  const nuevoEstatus = esCredito ? 'Credito' : 'Activa';
  const id_tipo_precio = mapTipoPrecioId($tpPrecio.val());

  let pagos = [];
  if (esMixto){
    const mix = ed_validarMixto(true);
    if (!mix.ok){ toastr.error(mix.msg); return; }
    pagos = mix.pagos;
  }

  const venta = {
    id_venta: edVentaId,
    fecha: $('#ed-fechaVenta').val(),
    id_cliente: idClienteSel ?? null,
    id_forma_pago: id_forma_pago ?? null,
    id_tipo_precio: id_tipo_precio,
    estatus: nuevoEstatus,
    tipo_pago: esMixto ? 'mixto' : undefined
  };
  const detalles = carrito.map(it=>{
    const unit = precioDeItem(it);
    const baseCant = Math.max(0.01, parseFloat(it.cantidad)||0.01);
    const cant = esProductoAcumulador(it) ? 1 : baseCant;
    return {
      id_producto: it.id_producto,
      cantidad: cant,
      precio_unitario: unit,
      subtotal: cant*unit,
      numero_poliza: it.numero_poliza ? it.numero_poliza.trim() : null
    };
  });

  const payload = { venta, detalles, pagos };

  const $b=$(this), txt=$b.html();
  $b.prop('disabled',true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');
  $.ajax({
    url: `${VENTAS_URL}?accion=actualizar`,
    method: 'POST',
    contentType: 'application/json; charset=utf-8',
    data: JSON.stringify(payload),
    dataType: 'json'
  }).done(r=>{
    if (r && r.ok){ toastr.success(r.msg||'Venta actualizada.'); $('#modalEditarVenta').modal('hide'); cargarVentas(paginaActual); }
    else { toastr.error(r?.msg||'No se pudo actualizar la venta.'); }
  }).fail(()=> toastr.error('Error de comunicación con el servidor.'))
    .always(()=> $b.prop('disabled',false).html(txt));
});

window.abrirEditarVenta = function(idVenta){
  if(!idVenta) return;
  edVentaId = Number(idVenta);
  $errEd.addClass('d-none').empty();
  $('#ed-buscar').val(''); $sug.hide().empty();
  carrito=[]; pagosVenta=[]; formasPagoTarjeta=[];
  $mixEfec.val('0'); $mixTar.val('0'); $mixTarSel.val('');
  $wrapMixto.addClass('d-none'); ed_setFormasTarjeta([]); pintarCarrito();

  $.get(VENTAS_URL,{accion:'detalle', id_venta: edVentaId}, function(r){
    if (!r || !r.venta){ $errEd.removeClass('d-none').text('No se encontró la venta.'); return; }
    const v=r.venta, det=r.detalles||[];
    pagosVenta = r.pagos || [];
    ed_setFormasTarjeta(r.formas_tarjeta || [], null);

    $edFolio.text(v.folio||'—');
    $edEst.html(' '+getBadge(v.estatus||'—'));
    $edFechaInfo.text(fechaMx(v.fecha));
    $edUsrCaja.text(`${v.usuario||'—'} / ${v.caja||'—'}`);

    const slug = (function(id){ const m={1:'publico',2:'taller',3:'proveedor'}; return m[id||1]||'publico'; })(v.id_tipo_precio||1);
    $tpPrecio.val(slug);
    const ymd = (v.fecha || '').slice(0,10);
    $fechaVenta.val( ymd || '<?= date('Y-m-d') ?>' );

    cargarClientes(v.id_cliente||'');
    cargarFormasPago(v.id_forma_pago||null, v.forma_pago||null);

    const proms = det.map(d=>{
      const idp=Number(d.id_producto);
      return $.post(PRODUCTOS_URL,{accion:'detalle', id_producto:idp})
        .then(rr=>{
          const p=rr?.data||{};
          const item = {
            id_producto:idp,
            codigo: p.codigo || d.codigo || '',
            descripcion: p.descripcion || d.producto || '',
            id_grupo: p.id_grupo ?? d.id_grupo ?? null,
            stock_actual:Number(p.stock_actual ?? p.existencia ?? 0),
            stock_minimo:Number(p.stock_minimo ?? 0),
            precio_publico:Number(p.precio_publico ?? 0),
            precio_taller:Number(p.precio_taller ?? 0),
            precio_proveedor:Number(p.precio_proveedor ?? 0),
            proveedor:p.proveedor ?? null,
            original:parseFloat(d.cantidad||0),
            cantidad:parseFloat(d.cantidad||0),
            numero_poliza:(d.numero_poliza ?? '').toString().trim()
          };
          if (esProductoAcumulador(item)) item.cantidad = 1;
          const unitVenta = Number(d.precio_unitario||0);
          const unitTipo  = (function(){
            const t=slug;
            if (t==='taller') return item.precio_taller;
            if (t==='proveedor') return item.precio_proveedor;
            return item.precio_publico;
          })();
          if (Math.abs(unitVenta - unitTipo) > 0.009) item.override_unit = unitVenta;

          carrito.push(item);
        });
    });

    Promise.all(proms).then(()=>{
      pintarCarrito();
      ed_toggleMixtoUI();
      $('#modalEditarVenta').modal('show');
    });
  },'json').fail(()=> $errEd.removeClass('d-none').text('Error al cargar la venta.'));
};


/* ==========================================================================
   MÓDULO: Abonos a ventas (modal Abono)
   ========================================================================== */

   
// Ids de forma de pago a excluir si fuera necesario (ej. cosas internas)
const EXCLUDE_FP_IDS_ABONO = [];

// Mantener en memoria las formas de pago que vienen del backend
window.FORMASPAGO_ABONO = window.FORMASPAGO_ABONO || [];

// Normaliza texto (quita acentos, minúsculas, trim)
const normTxt = (t) => String(t || '')
  .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  .toLowerCase().trim();

// Detecta si una descripción es "Crédito" (pero no "crédito tarjeta" etc.)
const esCreditoPuro = (desc) => {
  const txt = normTxt(desc);
  return /^credito\b/.test(txt) && !/tarjeta/.test(txt);
};

// Devuelve el id_forma_pago cuya descripción contenga cierta palabra
function obtenerIdFormaPagoPorTexto(buscar) {
  const txtBuscar = normTxt(buscar);
  const fps = window.FORMASPAGO_ABONO || [];
  const fp = fps.find(f => normTxt(f.descripcion || '').includes(txtBuscar));
  return fp ? Number(fp.id_forma_pago) : null;
}

// Verifica si un id_forma_pago corresponde a "Mixto"
function esFormaPagoMixta(id_fp) {
  const fps = window.FORMASPAGO_ABONO || [];
  const fp = fps.find(f => Number(f.id_forma_pago) === Number(id_fp));
  if (!fp) return false;
  return /^mixto\b/.test(normTxt(fp.descripcion || ''));
}

// Muestra/oculta la sección de detalle mixto
function toggleAbonoMixto(esMixto) {
  if (esMixto) {
    $('#wrapAbonoMixto').removeClass('d-none');
    $('#ab-monto').prop('readonly', true);
    recalcularTotalMixto();
  } else {
    $('#wrapAbonoMixto').addClass('d-none');
    $('#ab-monto').prop('readonly', false);
  }
}

// Recalcula el total del abono cuando es mixto (efectivo + tarjeta)
function recalcularTotalMixto() {
  const efe = Number($('#ab-monto-efe').val()) || 0;
  const tar = Number($('#ab-monto-tar').val()) || 0;
  const total = efe + tar;

  if (total > 0) {
    $('#ab-monto').val(total.toFixed(2));
  } else {
    $('#ab-monto').val('');
  }

  $('#ab-total-mixto').text(
    total.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
  );
}

// ------------------------------------------------------------
// Cargar formas de pago (incluye Mixto, excluye Crédito puro)
// ------------------------------------------------------------
function cargarFormasPagoAbono(selected) {
  const $sel = $('#ab-forma');
  if (!$sel.length) return;

  $sel.prop('disabled', true)
      .empty()
      .append('<option value="">Cargando…</option>');

  $.get(FORMASPAGO_URL, { accion: 'listar_select' })
    .done(r => {
      const arr = r?.data || (Array.isArray(r) ? r : []);

      const filtradas = arr.filter(fp => {
        const id   = Number(fp.id_forma_pago);
        const desc = fp.descripcion ?? '';
        // sacamos crédito puro y lo que tengas en EXCLUDE_FP_IDS_ABONO
        return !EXCLUDE_FP_IDS_ABONO.includes(id) && !esCreditoPuro(desc);
      });

      // Guardamos la lista global para usarla al armar el JSON de pagos mixtos
      window.FORMASPAGO_ABONO = filtradas;

      $sel.empty();

      if (!filtradas.length) {
        $sel.append('<option value="">(sin formas de pago)</option>');
      } else {
        filtradas.forEach(fp => {
          $sel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`);
        });

        // Si nos pasaron un seleccionado
        if (selected != null && $sel.find(`option[value="${String(selected)}"]`).length) {
          $sel.val(String(selected));
        }

        // Si no hay selección aún, intentar poner "Efectivo" por defecto
        if (!$sel.val()) {
          const opEfe = $sel.find('option').filter(function () {
            return normTxt($(this).text()) === 'efectivo';
          }).first().val();
          if (opEfe) $sel.val(opEfe);
        }
      }

      // Disparar el change para activar/ocultar sección Mixto si corresponde
      $sel.trigger('change');
    })
    .fail(() => {
      // Fallback duro si no responde el backend
      $sel.empty()
        .append('<option value="1">Efectivo</option>')
        .append('<option value="2">Tarjeta de crédito</option>')
        .append('<option value="3">Tarjeta de débito</option>')
        .append('<option value="4">Transferencia electrónica de fondos</option>');

      window.FORMASPAGO_ABONO = [
        { id_forma_pago: 1, descripcion: 'Efectivo' },
        { id_forma_pago: 2, descripcion: 'Tarjeta de crédito' },
        { id_forma_pago: 3, descripcion: 'Tarjeta de débito' },
        { id_forma_pago: 4, descripcion: 'Transferencia electrónica de fondos' }
      ];

      $sel.trigger('change');
    })
    .always(() => $sel.prop('disabled', false));
}

// ------------------------------------------------------------
// Abrir modal de abono
// ------------------------------------------------------------
$(document).on('click', 'a.accion-abonar-venta', function (e) {
  e.preventDefault();
  const id    = Number($(this).data('id'));
  const folio = $(this).data('folio') || '—';
  if (!id) return;

  $('#ab-id-venta').val(id);
  $('#ab-folio').text(folio);
  $('#ab-monto').val('');

  // 👉 Referencia por defecto = folio
  const refDefault = (folio && folio !== '—') ? folio : '';
  $('#ab-ref').val(refDefault);

  // 👉 Fecha por defecto = hoy (YYYY-MM-DD)
  const hoy = new Date();
  const yyyy = hoy.getFullYear();
  const mm   = String(hoy.getMonth() + 1).padStart(2, '0');
  const dd   = String(hoy.getDate()).padStart(2, '0');
  $('#ab-fecha').val(`${yyyy}-${mm}-${dd}`);

  $('#ab-error').addClass('d-none').empty();

  // Reset sección mixta
  $('#ab-monto-efe, #ab-monto-tar').val('');
  $('#ab-total-mixto').text('$0.00');
  toggleAbonoMixto(false);

  // Cargar formas de pago
  cargarFormasPagoAbono();

  // Obtener saldo
  $.get(VENTAS_URL, { accion: 'detalle', id_venta: id }, function (resp) {
    if (!resp || !resp.venta) {
      toastr.error('No se encontró la venta.');
      return;
    }

    const saldo = Number(
      resp?.saldo ?? ((Number(resp?.venta?.total || 0) - Number(resp?.abonado || 0)))
    );

    $('#ab-saldo').text(
      Number.isFinite(saldo)
        ? saldo.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
        : '$0.00'
    );

    $('#ab-monto').attr('max', Math.max(0, saldo || 0));
    $('#modalAbonarVenta').modal('show');
  }, 'json').fail(() => toastr.error('No se pudo obtener el saldo.'));
});

// ------------------------------------------------------------
// Cambio de forma de pago -> activar / desactivar modo mixto
// ------------------------------------------------------------
$(document).on('change', '#ab-forma', function () {
  const id_fp = Number($(this).val()) || 0;
  const esMixto = esFormaPagoMixta(id_fp);
  toggleAbonoMixto(esMixto);
});

// Cuando se capturan montos de efectivo / tarjeta en mixto
$(document).on('input', '#ab-monto-efe, #ab-monto-tar', function () {
  recalcularTotalMixto();
});

// ------------------------------------------------------------
// Envío del formulario de abono
// ------------------------------------------------------------
$('#formAbonoVenta').on('submit', function (e) {
  e.preventDefault();
  const id_venta = Number($('#ab-id-venta').val());
  const monto    = Number($('#ab-monto').val());
  const id_fp    = Number($('#ab-forma').val()) || 0;
  const fecha    = $('#ab-fecha').val() || '';
  const ref      = $('#ab-ref').val().trim();

  if (!id_venta) { return; }
  if (!monto || monto <= 0) {
    $('#ab-error').removeClass('d-none').text('Captura un monto válido.');
    return;
  }
  if (!id_fp) {
    $('#ab-error').removeClass('d-none').text('Selecciona una forma de pago.');
    return;
  }

  const esMixto = esFormaPagoMixta(id_fp);

  // Validaciones extra para mixto
  let pagos = null;
  if (esMixto) {
    const montoEfe = Number($('#ab-monto-efe').val()) || 0;
    const montoTar = Number($('#ab-monto-tar').val()) || 0;
    const suma     = montoEfe + montoTar;

    if (montoEfe <= 0 && montoTar <= 0) {
      $('#ab-error').removeClass('d-none').text(
        'Captura al menos un monto en efectivo o tarjeta para el pago mixto.'
      );
      return;
    }

    if (!suma || suma <= 0) {
      $('#ab-error').removeClass('d-none').text(
        'La suma del pago mixto debe ser mayor a cero.'
      );
      return;
    }

    // Permitimos una pequeña tolerancia de centavos
    if (Math.abs(suma - monto) > 0.01) {
      $('#ab-error').removeClass('d-none').text(
        'La suma de efectivo y tarjeta debe coincidir con el monto total del abono.'
      );
      return;
    }

    const idEfe = obtenerIdFormaPagoPorTexto('efectivo');
    const idTar = obtenerIdFormaPagoPorTexto('tarjeta');

    pagos = [];

    if (montoEfe > 0 && idEfe) {
      pagos.push({
        id_forma_pago: idEfe,
        monto: montoEfe,
        referencia: ref || ('ABONO ' + id_venta)
      });
    }
    if (montoTar > 0 && idTar) {
      pagos.push({
        id_forma_pago: idTar,
        monto: montoTar,
        referencia: ref || ('ABONO ' + id_venta)
      });
    }

    if (!pagos.length) {
      $('#ab-error').removeClass('d-none').text(
        'No se pudo armar el detalle del pago mixto (revisa formas de pago).'
      );
      return;
    }
  }

  $('#ab-error').addClass('d-none').empty();
  const $btn = $('#btnConfirmarAbono');
  const bak = $btn.html();
  $btn.prop('disabled', true)
      .html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando…');

  const payload = {
    accion: 'abonar-venta',
    id_venta: id_venta,
    monto: monto,
    id_forma_pago: id_fp,
    fecha_abono: fecha,
    referencia_pago: ref
  };

  if (esMixto && pagos) {
    payload.pagos = JSON.stringify(pagos);
  }

  $.post(VENTAS_URL, payload, function (r) {
    if (r && r.ok) {
      toastr.success('Abono registrado.');
      $('#modalAbonarVenta').modal('hide');
      if (typeof cargarVentas === 'function') {
        cargarVentas(paginaActual || 1);
      }
    } else {
      $('#ab-error').removeClass('d-none').text(r?.msg || 'No se pudo registrar el abono.');
    }
  }, 'json').fail(() => {
    $('#ab-error').removeClass('d-none').text('Error de comunicación con el servidor.');
  }).always(() => {
    $btn.prop('disabled', false).html(bak);
  });
});

/* ==========================================================================
   MÓDULO: Inicialización
   ========================================================================== */
function escapeHtml(value){
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

const FACTURA_BLOCK_LABELS = {
  venta: 'Venta',
  emisor: 'Emisor',
  receptor: 'Receptor',
  comprobante: 'Comprobante',
  conceptos: 'Conceptos'
};

let facturaDraftState = null;

function createEmptyFacturaDraft(){
  return {
    venta: {
      id_venta: 0,
      folio: '',
      fecha: '',
      referencia_interna: '',
      conceptos: [],
      detalle_origen: [],
      subtotal: 0,
      descuento: 0,
      impuestos: 0,
      total: 0
    },
    emisor: {
      rfc: '',
      nombre: '',
      regimen_fiscal: '',
      lugar_expedicion: '',
      serie: '',
      sucursal: ''
    },
    receptor: {
      id_cliente_fiscal: 0,
      rfc: '',
      nombre: '',
      nombre_comercial: '',
      correo: '',
      codigo_postal: '',
      regimen_fiscal: '',
      uso_cfdi: '',
      residencia_fiscal: '',
      numero_registro_tributario: '',
      es_publico_general: false
    },
    comprobante: {
      moneda: 'MXN',
      metodo_pago: '',
      forma_pago: '',
      tipo_cambio: '1',
      exportacion: '01',
      tipo_comprobante: 'I',
      condiciones_pago: ''
    },
    catalogos: {
      monedas: [],
      formas_pago: [],
      metodos_pago: [],
      regimenes_fiscales: [],
      usos_cfdi: [],
      tipos_comprobante: [],
      exportaciones: []
    },
    cfdi: {},
    validaciones: {
      ventaValida: false,
      receptorCompleto: false,
      comprobanteCompleto: false,
      conceptosValidos: false,
      emisorValido: false,
      bloques: {},
      listaErrores: []
    },
    listoParaTimbrar: false
  };
}

function deepMerge(target, source){
  const base = Array.isArray(target) ? [...target] : { ...(target || {}) };
  if (!source || typeof source !== 'object') return base;
  Object.keys(source).forEach(key => {
    const value = source[key];
    if (Array.isArray(value)) {
      base[key] = value.map(item => (
        item && typeof item === 'object' ? deepMerge(Array.isArray(item) ? [] : {}, item) : item
      ));
      return;
    }
    if (value && typeof value === 'object') {
      const current = base[key] && typeof base[key] === 'object' ? base[key] : {};
      base[key] = deepMerge(current, value);
      return;
    }
    base[key] = value;
  });
  return base;
}

function getCatalogValues(items, key){
  return (Array.isArray(items) ? items : []).map(item => String(item?.[key] ?? '').trim().toUpperCase()).filter(Boolean);
}

function formatValidationMessage(message){
  return String(message ?? '')
    .replace(/^Debes seleccionar\s+/i, 'Selecciona ')
    .replace(/^Debe existir\s+/i, 'Debe existir ')
    .replace(/^La venta no tiene\s+/i, 'La venta no tiene ')
    .replace(/^Este flujo solo soporta\s+/i, '')
    .replace(/\.\s*$/,'')
    .trim();
}

function validateFacturaDraft(draft){
  const errors = [];
  const blocks = {};
  const ventaErrors = [];
  const emisorErrors = [];
  const receptorErrors = [];
  const comprobanteErrors = [];
  const conceptosErrors = [];

  if (!Number(draft?.venta?.id_venta || 0)) ventaErrors.push('Falta la referencia de la venta.');
  if (!Array.isArray(draft?.venta?.conceptos) || !draft.venta.conceptos.length) ventaErrors.push('La venta no tiene conceptos para facturar.');

  ['rfc', 'nombre', 'regimen_fiscal', 'lugar_expedicion'].forEach(field => {
    const labels = {
      rfc: 'RFC del emisor',
      nombre: 'Nombre del emisor',
      regimen_fiscal: 'Régimen fiscal del emisor',
      lugar_expedicion: 'Lugar de expedición del emisor'
    };
    if (!String(draft?.emisor?.[field] ?? '').trim()) emisorErrors.push(`Falta ${labels[field]}.`);
  });

  ['rfc', 'nombre', 'codigo_postal', 'regimen_fiscal', 'uso_cfdi'].forEach(field => {
    const labels = {
      rfc: 'RFC del receptor',
      nombre: 'Razón social del receptor',
      codigo_postal: 'Código postal fiscal del receptor',
      regimen_fiscal: 'Régimen fiscal del receptor',
      uso_cfdi: 'Uso CFDI del receptor'
    };
    if (!String(draft?.receptor?.[field] ?? '').trim()) receptorErrors.push(`Falta ${labels[field]}.`);
  });

  const moneda = String(draft?.comprobante?.moneda ?? '').trim().toUpperCase();
  const metodoPago = String(draft?.comprobante?.metodo_pago ?? '').trim().toUpperCase();
  const formaPago = String(draft?.comprobante?.forma_pago ?? '').trim().toUpperCase();
  const tipoComprobante = String(draft?.comprobante?.tipo_comprobante ?? '').trim().toUpperCase();
  const exportacion = String(draft?.comprobante?.exportacion ?? '').trim();
  const tipoCambio = String(draft?.comprobante?.tipo_cambio ?? '').trim();

  if (!moneda) comprobanteErrors.push('Debes seleccionar la moneda.');
  if (!metodoPago) comprobanteErrors.push('Debes seleccionar el método de pago.');
  if (!formaPago) comprobanteErrors.push('Debes seleccionar la forma de pago SAT.');
  if (!tipoComprobante) comprobanteErrors.push('Debes seleccionar el tipo de comprobante.');
  if (!exportacion) comprobanteErrors.push('Debes seleccionar la clave de exportación.');

  const monedasValidas = getCatalogValues(draft?.catalogos?.monedas, 'ClaveMoneda');
  const metodosValidos = getCatalogValues(draft?.catalogos?.metodos_pago, 'clave');
  const formasValidas = getCatalogValues(draft?.catalogos?.formas_pago, 'clave_sat');
  const exportacionesValidas = (Array.isArray(draft?.catalogos?.exportaciones) ? draft.catalogos.exportaciones : []).map(item => String(item?.clave ?? '').trim()).filter(Boolean);

  if (moneda && monedasValidas.length && !monedasValidas.includes(moneda)) comprobanteErrors.push('La moneda seleccionada no existe en el catálogo SAT cargado.');
  if (metodoPago && metodosValidos.length && !metodosValidos.includes(metodoPago)) comprobanteErrors.push('El método de pago seleccionado no es válido.');
  if (formaPago && formasValidas.length && !formasValidas.includes(formaPago)) comprobanteErrors.push('La forma de pago seleccionada no es válida.');
  if (exportacion && exportacionesValidas.length && !exportacionesValidas.includes(exportacion)) comprobanteErrors.push('La clave de exportación seleccionada no es válida.');

  if (moneda === 'MXN') {
    if (!tipoCambio || Number(tipoCambio) !== 1) comprobanteErrors.push('Para moneda MXN el tipo de cambio debe ser 1.');
  } else if (moneda === 'XXX') {
    if (tipoCambio !== '') comprobanteErrors.push('Para moneda XXX no debe enviarse tipo de cambio.');
  } else if (moneda) {
    if (!tipoCambio) comprobanteErrors.push('Para moneda distinta de MXN/XXX el tipo de cambio es obligatorio.');
    else if (Number(tipoCambio) <= 0 || Number.isNaN(Number(tipoCambio))) comprobanteErrors.push('El tipo de cambio debe ser mayor a 0.');
  }

  if (tipoComprobante && tipoComprobante !== 'I') comprobanteErrors.push('Este flujo solo soporta tipo de comprobante I (Ingreso).');

  const conceptos = Array.isArray(draft?.venta?.conceptos) ? draft.venta.conceptos : [];
  if (!conceptos.length) conceptosErrors.push('Debe existir al menos un concepto válido.');
  conceptos.forEach((concepto, index) => {
    const line = index + 1;
    const required = {
      Cantidad: 'cantidad',
      ClaveProdServ: 'clave producto/servicio',
      ClaveUnidad: 'clave unidad',
      Descripcion: 'descripción',
      ValorUnitario: 'valor unitario',
      Importe: 'importe',
      ObjetoImp: 'objeto impuesto'
    };
    Object.keys(required).forEach(key => {
      if (concepto?.[key] === undefined || concepto?.[key] === null || String(concepto?.[key]).trim() === '') {
        conceptosErrors.push(`Falta ${required[key]} en el concepto ${line}.`);
      }
    });
  });

  blocks.venta = { completo: ventaErrors.length === 0, errores: ventaErrors };
  blocks.emisor = { completo: emisorErrors.length === 0, errores: emisorErrors };
  blocks.receptor = { completo: receptorErrors.length === 0, errores: receptorErrors };
  blocks.comprobante = { completo: comprobanteErrors.length === 0, errores: comprobanteErrors };
  blocks.conceptos = { completo: conceptosErrors.length === 0, errores: conceptosErrors };

  errors.push(...ventaErrors, ...emisorErrors, ...receptorErrors, ...comprobanteErrors, ...conceptosErrors);

  return {
    ventaValida: blocks.venta.completo,
    emisorValido: blocks.emisor.completo,
    receptorCompleto: blocks.receptor.completo,
    comprobanteCompleto: blocks.comprobante.completo,
    conceptosValidos: blocks.conceptos.completo,
    bloques: blocks,
    listaErrores: errors,
    listoParaTimbrar: errors.length === 0
  };
}

function getFacturaDraft(){
  if (!facturaDraftState) facturaDraftState = createEmptyFacturaDraft();
  return facturaDraftState;
}

function ensureTipoCambioDefault(draft){
  const moneda = String(draft?.comprobante?.moneda ?? '').trim().toUpperCase();
  const tipoCambioActual = String(draft?.comprobante?.tipo_cambio ?? '').trim();
  if (moneda === 'MXN' && !tipoCambioActual) {
    draft.comprobante.tipo_cambio = '1';
  }
  return draft;
}

function renderFacturaDraftUI(){
  const draft = getFacturaDraft();
  const validaciones = draft.validaciones || validateFacturaDraft(draft);
  const bloques = validaciones.bloques || {};
  const blockOrder = ['venta', 'emisor', 'receptor', 'comprobante', 'conceptos'];

  const bloquesHtml = blockOrder.map(key => {
    const block = bloques[key] || { completo: false, errores: ['Sin información.'] };
    const desc = block.completo
      ? 'Información completa.'
      : (formatValidationMessage(block.errores[0]) || 'Faltan datos por capturar.');
    return `
      <div class="cfdi-block-status__item ${block.completo ? 'is-complete' : 'is-incomplete'}">
        <div class="cfdi-block-status__title">
          <span>${FACTURA_BLOCK_LABELS[key] || key}</span>
          <span class="badge ${block.completo ? 'badge-success' : 'badge-danger'}">${block.completo ? 'Completo' : 'Pendiente'}</span>
        </div>
        <p class="cfdi-block-status__desc mb-0">${escapeHtml(desc)}</p>
      </div>`;
  }).join('');
  $('#fac-validacion-bloques').html(bloquesHtml);

  if (validaciones.listaErrores.length) {
    const mensajesPorBloque = blockOrder
      .map(key => {
        const errores = Array.isArray(bloques[key]?.errores) ? bloques[key].errores : [];
        if (!errores.length) return '';
        const faltantes = errores.map(formatValidationMessage).filter(Boolean).join(', ');
        if (!faltantes) return '';
        return `
          <li class="cfdi-validation-list__item">
            <strong>${escapeHtml(FACTURA_BLOCK_LABELS[key] || key)}</strong>
            ${escapeHtml(faltantes)}
          </li>`;
      })
      .filter(Boolean)
      .join('');

    $('#fac-validaciones').html(mensajesPorBloque || `
      <li class="cfdi-validation-list__item">
        <strong>Validación</strong>
        Revisa la información faltante para continuar.
      </li>`);
  } else {
    $('#fac-validaciones').html(`
      <li class="cfdi-validation-list__item is-success">
        <strong>Validación</strong>
        La información está completa. Ya puedes facturar la venta.
      </li>`);
  }

  $('#btnConfirmarFacturar').prop('disabled', !draft.listoParaTimbrar || String(draft?.cfdi?.estatus || '').toUpperCase() === 'TIMBRADO');

  $('#fac-publico-note')
    .toggleClass('d-none', !draft?.receptor?.es_publico_general)
    .html(draft?.receptor?.es_publico_general ? 'El receptor fiscal capturado corresponde a <strong>Público en general</strong>.' : '');

  $('#fac-info-global')
    .toggleClass('alert-warning', !!draft?.receptor?.es_publico_general)
    .toggleClass('alert-light', !draft?.receptor?.es_publico_general)
    .text(draft?.receptor?.es_publico_general
      ? 'El receptor capturado corresponde a público en general; revisa la información global antes de emitir el CFDI.'
      : 'No aplica información global para esta venta individual.');
}

function applyDraftToForm(){
  const draft = getFacturaDraft();
  ensureTipoCambioDefault(draft);
  $('#fac-input-rfc').val(draft.receptor.rfc || '');
  $('#fac-input-razon-social').val(draft.receptor.nombre || '');
  $('#fac-input-nombre-comercial').val(draft.receptor.nombre_comercial || '');
  $('#fac-input-correo').val(draft.receptor.correo || '');
  $('#fac-input-cp').val(draft.receptor.codigo_postal || '');
  $('#fac-select-regimen').val(draft.receptor.regimen_fiscal || '');
  $('#fac-select-uso-cfdi').val(draft.receptor.uso_cfdi || '');
  $('#fac-input-residencia-fiscal').val(draft.receptor.residencia_fiscal || '');
  $('#fac-input-num-reg-id-trib').val(draft.receptor.numero_registro_tributario || '');
  $('#fac-select-moneda').val(draft.comprobante.moneda || 'MXN');
  $('#fac-select-metodo-pago').val(draft.comprobante.metodo_pago || '');
  $('#fac-select-forma-pago').val(draft.comprobante.forma_pago || '');
  $('#fac-input-tipo-cambio').val(draft.comprobante.tipo_cambio ?? '').prop('readonly', true);
  console.debug('[FACTURACION][tipo_cambio][form-visible]', {
    moneda: String(draft?.comprobante?.moneda || '').trim().toUpperCase(),
    tipo_cambio_visible: String(draft?.comprobante?.tipo_cambio ?? '').trim()
  });
  $('#fac-input-condiciones-pago').val(draft.comprobante.condiciones_pago || '').prop('readonly', true);
  $('#fac-select-tipo-comprobante').val(draft.comprobante.tipo_comprobante || 'I');
  $('#fac-select-exportacion').val(draft.comprobante.exportacion || '01');
  syncTipoCambioFacturacion();
  updateFormaPagoAlert();
}

function updateFacturaDraft(path, value, options = {}){
  const draft = getFacturaDraft();
  const segments = String(path || '').split('.').filter(Boolean);
  if (!segments.length) return draft;

  let cursor = draft;
  for (let i = 0; i < segments.length - 1; i += 1) {
    const key = segments[i];
    if (!cursor[key] || typeof cursor[key] !== 'object') cursor[key] = {};
    cursor = cursor[key];
  }
  cursor[segments[segments.length - 1]] = value;
  ensureTipoCambioDefault(draft);

  draft.receptor.es_publico_general = ['XAXX010101000'].includes(String(draft.receptor.rfc || '').trim().toUpperCase())
    || /PUBLICO|MOSTRADOR/.test(String(draft.receptor.nombre || '').trim().toUpperCase());

  draft.validaciones = validateFacturaDraft(draft);
  draft.listoParaTimbrar = !!draft.validaciones.listoParaTimbrar;

  if (!options.skipRender) {
    applyDraftToForm();
    renderFacturaDraftUI();
  }

  return draft;
}

function hydrateFacturaDraftFromContext(ctx = {}){
  const backendDraft = ctx.factura_draft || {};
  facturaDraftState = deepMerge(createEmptyFacturaDraft(), backendDraft);
  facturaDraftState.catalogos = deepMerge(createEmptyFacturaDraft().catalogos, ctx.catalogos || facturaDraftState.catalogos || {});
  facturaDraftState.cfdi = ctx.cfdi_actual || {};
  facturaDraftState.venta.id_venta = Number(facturaDraftState.venta.id_venta || ctx?.venta?.id_venta || $('#fac-id-venta').val() || 0);
  ensureTipoCambioDefault(facturaDraftState);
  facturaDraftState.validaciones = validateFacturaDraft(facturaDraftState);
  facturaDraftState.listoParaTimbrar = !!facturaDraftState.validaciones.listoParaTimbrar;
  return facturaDraftState;
}

function initFacturacionClienteSelect(){
  const $select = $('#fac-select-cliente');
  const $modal = $('#modalFacturarVenta');

  if (!$select.length || typeof $.fn.select2 !== 'function') return;
  if ($select.hasClass('select2-hidden-accessible')) {
    $select.select2('destroy');
  }

  $select.select2({
    width: '100%',
    dropdownParent: $modal,
    placeholder: $select.data('placeholder') || 'Buscar cliente SAT',
    allowClear: true,
    minimumInputLength: 1,
    ajax: {
      url: VENTAS_URL,
      dataType: 'json',
      delay: 250,
      data: params => ({
        accion: 'facturacion-buscar-clientes',
        q: params.term || '',
        limite: 20
      }),
      processResults: resp => ({
        results: Array.isArray(resp?.results) ? resp.results : []
      }),
      cache: true
    },
    templateResult: function(item){
      if (item.loading) return item.text;
      const razon = item.nombre || item.razon_social || item.text || 'Sin nombre fiscal';
      const comercial = item.nombre_comercial || '';
      const rfc = item.rfc || 'Sin RFC';
      const correo = item.correo || '';
      const razonSocial = item.razon_social && item.razon_social !== razon ? item.razon_social : '';
      return $(`
        <div class="cfdi-select2-option">
          <span class="cfdi-select2-option__title">${escapeHtml(razon)}</span>
          <span class="cfdi-select2-option__meta">${escapeHtml(rfc)}${correo ? ' · ' + escapeHtml(correo) : ''}</span>
          ${comercial || razonSocial ? `<span class="cfdi-select2-option__secondary">${escapeHtml(comercial || razonSocial)}</span>` : ''}
        </div>
      `);
    },
    templateSelection: function(item){
      return item.text || item.nombre || item.razon_social || 'Buscar cliente SAT';
    },
    language: {
      inputTooShort: () => 'Escribe al menos 1 carácter para buscar en clientes_sat.',
      searching: () => 'Buscando clientes SAT...',
      noResults: () => 'No se encontraron clientes SAT.',
      errorLoading: () => 'No se pudieron cargar los clientes SAT.'
    }
  });
}

function setFacturacionClienteSeleccionado(cliente){
  const $select = $('#fac-select-cliente');
  if (!$select.length) return;

  const idCliente = Number(cliente?.id || cliente?.id_cliente_sat || cliente?.id_cliente || 0);
  if (!idCliente) {
    $select.empty();
    $select.val(null);
    return;
  }

  const texto = cliente.text || [cliente.nombre || 'Cliente', cliente.rfc || null].filter(Boolean).join(' · ');
  const option = new Option(texto, String(idCliente), true, true);
  $(option).data('clienteFacturacion', cliente);
  $select.empty().append(option).trigger({ type: 'change', params: { data: cliente } });
}

function resetModalFacturacion(){
  facturaDraftState = createEmptyFacturaDraft();
  initFacturacionClienteSelect();
  $('#fac-id-venta').val('');
  $('#fac-id-cliente-sat').val('');
  $('#fac-loader').show();
  $('#fac-contenido').addClass('d-none');
  $('#fac-error, #fac-warning, #fac-success').addClass('d-none').empty();
  $('#fac-validaciones').html(`
    <li class="cfdi-validation-list__item">
      <strong>Validación</strong>
      Sin validaciones disponibles.
    </li>`);
  $('#fac-validacion-bloques').html('<div class="cfdi-block-status__item is-incomplete"><div class="cfdi-block-status__title"><span>Sin estado</span><span class="badge badge-secondary">Pendiente</span></div><p class="cfdi-block-status__desc mb-0">Abre una venta para calcular el draft de facturación.</p></div>');
  $('#fac-detalles-body').html('<tr><td colspan="10" class="text-center text-muted">Sin conceptos</td></tr>');
  $('#fac-folio, #fac-fecha, #fac-cliente, #fac-emisor-rfc, #fac-emisor-nombre, #fac-emisor-sucursal, #fac-emisor-regimen, #fac-emisor-lugar, #fac-emisor-serie, #fac-emisor-tipo, #fac-emisor-exportacion').text('—');
  $('#fac-input-rfc, #fac-input-razon-social, #fac-input-correo, #fac-input-cp, #fac-input-residencia-fiscal, #fac-input-num-reg-id-trib, #fac-input-nombre-comercial').val('').prop('readonly', false).prop('disabled', false);
  $('#fac-input-condiciones-pago').val('').prop('readonly', true).prop('disabled', false);
  $('#fac-input-tipo-cambio').val('1').prop('readonly', true).prop('disabled', false);
  $('#fac-select-regimen, #fac-select-uso-cfdi').html('<option value="">Seleccione…</option>').prop('disabled', false);
  $('#fac-select-moneda, #fac-select-metodo-pago, #fac-select-forma-pago, #fac-select-tipo-comprobante, #fac-select-exportacion').html('<option value="">Seleccione…</option>').prop('disabled', false);
  $('#fac-select-cliente').empty().val(null).trigger('change');
  $('#fac-publico-note').addClass('d-none').empty();
  $('#fac-info-global').removeClass('alert-warning').addClass('alert-light').text('No aplica información global para esta venta.');
  $('#fac-forma-pago-alerta').removeClass('alert-warning alert-danger').addClass('alert-light').text('Captura los datos del comprobante fiscal que se enviarán para construir el CFDI 4.0.');
  $('#fac-tipo-cambio-help').text('Se ajusta automáticamente según la moneda seleccionada.');
  $('#fac-total').text(mxn(0));
  $('#fac-total-subtotal, #fac-total-descuento, #fac-total-impuestos').text(mxn(0));
  $('#fac-importe-letra').text('No disponible en el flujo actual.');
  $('#fac-estatus-fiscal').html(getBadgeFiscal(''));
  $('#fac-archivos').addClass('d-none');
  $('#fac-link-xml, #fac-link-pdf').attr('href', '#');
  $('#btnConfirmarFacturar').prop('disabled', true).data('idVenta', 0);
}

function fillFacturacionSelect($select, items, valueKey, textKey, currentValue, placeholder = 'Seleccione…'){
  let html = `<option value="">${placeholder}</option>`;
  (items || []).forEach(item => {
    const val = item?.[valueKey] ?? '';
    const text = item?.label ?? item?.[textKey] ?? val;
    const selected = String(currentValue || '') === String(val) ? ' selected' : '';
    html += `<option value="${escapeHtml(String(val))}"${selected}>${escapeHtml(String(text))}</option>`;
  });
  $select.html(html);
  $select.val(currentValue !== undefined && currentValue !== null ? String(currentValue) : '');
}

function fillFacturacionSelectFromPairs($select, items, currentValue, placeholder = 'Seleccione…'){
  let html = `<option value="">${placeholder}</option>`;
  (items || []).forEach(item => {
    const val = item?.clave ?? '';
    const descripcion = item?.descripcion ?? val;
    const selected = String(currentValue || '') === String(val) ? ' selected' : '';
    html += `<option value="${escapeHtml(String(val))}"${selected}>${escapeHtml(String(val))}${descripcion ? ' · ' + escapeHtml(String(descripcion)) : ''}</option>`;
  });
  $select.html(html);
  $select.val(currentValue !== undefined && currentValue !== null ? String(currentValue) : '');
}

function formatClaveDescripcion(clave, descripcion, emptyText = '—'){
  const claveTxt = String(clave ?? '').trim();
  const descripcionTxt = String(descripcion ?? '').trim();
  if (!claveTxt && !descripcionTxt) return emptyText;
  if (claveTxt && descripcionTxt) return `${claveTxt} - ${descripcionTxt}`;
  return claveTxt || descripcionTxt || emptyText;
}

function syncTipoCambioFacturacion(){
  const draft = getFacturaDraft();
  const moneda = String(draft?.comprobante?.moneda || $('#fac-select-moneda').val() || '').trim().toUpperCase();
  const $tipoCambio = $('#fac-input-tipo-cambio');
  const $help = $('#fac-tipo-cambio-help');

  $tipoCambio.prop('readonly', true).prop('disabled', false);

  if (moneda === 'MXN') {
    if (!String($tipoCambio.val() || '').trim()) {
      $tipoCambio.val('1');
      updateFacturaDraft('comprobante.tipo_cambio', '1', { skipRender: true });
    }
    $help.text('Moneda MXN: el tipo de cambio se envía fijo en 1.');
    return;
  }

  if (moneda === 'XXX') {
    $help.text('Moneda XXX: deja vacío el tipo de cambio en el formulario.');
    return;
  }

  $help.text('Para monedas distintas de MXN y XXX el tipo de cambio es obligatorio.');
}

function updateFormaPagoAlert(){
  const draft = getFacturaDraft();
  const moneda = String(draft?.comprobante?.moneda || '').trim().toUpperCase();
  const metodo = String(draft?.comprobante?.metodo_pago || '').trim().toUpperCase();
  const tipo = String(draft?.comprobante?.tipo_comprobante || '').trim().toUpperCase();
  const exportacion = String(draft?.comprobante?.exportacion || '').trim();
  const $alert = $('#fac-forma-pago-alerta');

  let messages = [];
  let alertClass = 'alert-light';

  if (moneda === 'MXN') {
    messages.push('Moneda MXN: el tipo de cambio se enviará como 1.');
  } else if (moneda === 'XXX') {
    messages.push('Moneda XXX: el tipo de cambio no se enviará al PAC.');
  } else if (moneda) {
    messages.push('Moneda distinta de MXN/XXX: captura un tipo de cambio válido.');
    alertClass = 'alert-warning';
  }

  if (metodo === 'PPD') messages.push('Método PPD seleccionado: valida que la forma de pago represente la clave SAT real del cobro.');
  if (tipo && tipo !== 'I') {
    messages.push('Este flujo solo soporta comprobantes tipo I (Ingreso).');
    alertClass = 'alert-danger';
  }
  if (!exportacion) {
    messages.push('Selecciona una clave de exportación.');
    alertClass = 'alert-warning';
  }
  if (!messages.length) messages.push('Captura los datos del comprobante fiscal que se enviarán para construir el CFDI 4.0.');

  $alert.removeClass('alert-light alert-warning alert-danger').addClass(alertClass).html(messages.join('<br>'));
}

function fillFacturacionComprobante(draft){
  fillFacturacionSelect($('#fac-select-moneda'), draft.catalogos.monedas || [], 'ClaveMoneda', 'Descripcion', draft.comprobante.moneda || 'MXN');
  fillFacturacionSelectFromPairs($('#fac-select-metodo-pago'), draft.catalogos.metodos_pago || [], draft.comprobante.metodo_pago || '');
  fillFacturacionSelect($('#fac-select-forma-pago'), draft.catalogos.formas_pago || [], 'clave_sat', 'descripcion', draft.comprobante.forma_pago || '');
  fillFacturacionSelectFromPairs($('#fac-select-tipo-comprobante'), draft.catalogos.tipos_comprobante || [], draft.comprobante.tipo_comprobante || 'I');
  fillFacturacionSelectFromPairs($('#fac-select-exportacion'), draft.catalogos.exportaciones || [], draft.comprobante.exportacion || '01');
  applyDraftToForm();
}

function normalizeObjetoImpValue(value){
  const raw = String(value ?? '').trim();
  if (!raw) return '02';
  if (/^\d$/.test(raw)) return `0${raw}`;
  return raw;
}

function getDetalleFiscalNumbers(detalle = {}){
  const cantidad = Number(detalle?.cantidad ?? 0);
  const precioUnitario = Number(detalle?.precio_unitario ?? 0);
  const subtotalComercial = Number(detalle?.subtotal ?? (cantidad * precioUnitario));
  const objetoImp = normalizeObjetoImpValue(detalle?.objeto_imp ?? detalle?.producto_objeto_imp ?? '02');
  const tasaIva = Number(detalle?.tasa_iva ?? detalle?.producto_tasa_iva ?? 0);
  const usaIva = objetoImp === '02' && tasaIva > 0;
  const baseIvaRaw = detalle?.base_iva;
  const importeIvaRaw = detalle?.importe_iva;
  const hasBase = baseIvaRaw !== null && baseIvaRaw !== undefined && baseIvaRaw !== '' && !Number.isNaN(Number(baseIvaRaw));
  const hasImpuesto = importeIvaRaw !== null && importeIvaRaw !== undefined && importeIvaRaw !== '' && !Number.isNaN(Number(importeIvaRaw));
  const subtotalFiscalBase = usaIva
    ? (hasBase ? Number(baseIvaRaw) : Number((subtotalComercial / (1 + tasaIva)).toFixed(2)))
    : subtotalComercial;
  const impuestosCalculados = usaIva
    ? (hasImpuesto ? Number(importeIvaRaw) : Number((subtotalComercial - subtotalFiscalBase).toFixed(2)))
    : 0;
  return {
    subtotalComercial,
    subtotalFiscalBase,
    impuestosCalculados,
    totalFinal: subtotalComercial,
    objetoImp,
    tasaIva
  };
}

function computeFacturaPreviewTotals(draft, detalles){
  const conceptos = Array.isArray(draft?.venta?.conceptos) ? draft.venta.conceptos : [];
  if (conceptos.length) {
    const subtotalFiscalBase = conceptos.reduce((acc, concepto) => acc + Number(concepto?.Importe ?? 0), 0);
    const impuestosCalculados = conceptos.reduce((acc, concepto) => {
      const traslados = Array.isArray(concepto?.Traslados) ? concepto.Traslados : [];
      return acc + traslados.reduce((subAcc, traslado) => subAcc + Number(traslado?.Importe ?? 0), 0);
    }, 0);
    const descuento = Number(draft?.venta?.descuento ?? 0);
    const totalFinal = Number(draft?.venta?.total ?? ((subtotalFiscalBase - descuento) + impuestosCalculados));
    return { subtotalFiscalBase, impuestosCalculados, descuento, totalFinal, source: 'conceptos' };
  }

  const detalleRows = Array.isArray(detalles) ? detalles : [];
  const subtotalFiscalBase = detalleRows.reduce((acc, detalle) => acc + getDetalleFiscalNumbers(detalle).subtotalFiscalBase, 0);
  const impuestosCalculados = detalleRows.reduce((acc, detalle) => acc + getDetalleFiscalNumbers(detalle).impuestosCalculados, 0);
  const descuento = Number(draft?.venta?.descuento ?? 0);
  const totalFinal = Number(draft?.venta?.total ?? ((subtotalFiscalBase - descuento) + impuestosCalculados));
  return { subtotalFiscalBase, impuestosCalculados, descuento, totalFinal, source: 'detalles' };
}

function renderFacturacionPreview(resp, idVenta){
  const ctx = resp?.contexto || {};
  const draft = hydrateFacturaDraftFromContext(ctx);
  const venta = ctx.venta || {};
  const emisor = ctx.emisor || {};
  const clienteSeleccionado = ctx.cliente_seleccionado || null;
  const cfdi = ctx.cfdi_actual || {};
  const advertencias = Array.isArray(resp?.advertencias) ? resp.advertencias : [];
  const estatusFiscal = String(cfdi.estatus || venta.estatus_fiscal || '').toUpperCase();
  const detalles = Array.isArray(ctx.detalles) ? ctx.detalles : [];
  const totalesPreview = computeFacturaPreviewTotals(draft, detalles);

  $('#fac-emisor-rfc').text(emisor.rfc || '—');
  $('#fac-emisor-nombre').text(emisor.nombre || '—');
  $('#fac-emisor-sucursal').text(emisor.sucursal || venta.sucursal_nombre || '—');
  $('#fac-emisor-regimen').text(
    emisor.regimen_fiscal_label
    || formatClaveDescripcion(emisor.regimen_fiscal, emisor.regimen_fiscal_descripcion)
  );
  $('#fac-emisor-lugar').text(emisor.lugar_expedicion || '—');
  $('#fac-emisor-serie').text(String(emisor.serie || '').trim() || 'Sin serie');
  $('#fac-emisor-tipo').text(
    emisor.tipo_comprobante_label
    || formatClaveDescripcion(
      emisor.tipo_comprobante || draft.comprobante.tipo_comprobante,
      emisor.tipo_comprobante_descripcion
    )
  );
  $('#fac-emisor-exportacion').text(
    emisor.exportacion_label
    || formatClaveDescripcion(
      emisor.exportacion || draft.comprobante.exportacion,
      emisor.exportacion_descripcion
    )
  );
  $('#fac-folio').text(venta.folio || ('#' + idVenta));
  $('#fac-fecha').text(fechaMx(venta.fecha));
  $('#fac-cliente').text(venta.cliente_nombre || venta.cliente || 'Venta sin cliente ligado');
  $('#fac-total-subtotal').text(mxn(totalesPreview.subtotalFiscalBase || 0));
  $('#fac-total-descuento').text(mxn(totalesPreview.descuento || 0));
  $('#fac-total-impuestos').text(mxn(totalesPreview.impuestosCalculados || 0));
  $('#fac-total').text(mxn(totalesPreview.totalFinal || 0));
  console.debug('[FACTURACION][modal-resumen]', {
    id_venta: idVenta,
    subtotal_comercial_origen: Number(venta?.subtotal_factura ?? 0),
    subtotal_fiscal_base: Number(totalesPreview.subtotalFiscalBase || 0),
    descuento: Number(totalesPreview.descuento || 0),
    impuestos_calculados: Number(totalesPreview.impuestosCalculados || 0),
    total_final: Number(totalesPreview.totalFinal || 0),
    fuente_totales: totalesPreview.source,
    campos_detalle: detalles.map(d => ({
      subtotal: d?.subtotal ?? null,
      base_iva: d?.base_iva ?? null,
      importe_iva: d?.importe_iva ?? null,
      objeto_imp: d?.objeto_imp ?? d?.producto_objeto_imp ?? null,
      tasa_iva: d?.tasa_iva ?? d?.producto_tasa_iva ?? null
    }))
  });
  $('#fac-importe-letra').text(ctx?.totales?.importe_letra || 'No disponible en el flujo actual.');
  $('#fac-estatus-fiscal').html(getBadgeFiscal(estatusFiscal));

  fillFacturacionSelect($('#fac-select-regimen'), draft.catalogos.regimenes_fiscales || [], 'ClaveRegimenFiscal', 'Descripcion', draft.receptor.regimen_fiscal || '');
  fillFacturacionSelect($('#fac-select-uso-cfdi'), draft.catalogos.usos_cfdi || [], 'ClaveUsoCFDI', 'Descripcion', draft.receptor.uso_cfdi || '');
  fillFacturacionComprobante(draft);
  setFacturacionClienteSeleccionado(clienteSeleccionado);

  const conceptosPreview = Array.isArray(draft?.venta?.conceptos) ? draft.venta.conceptos : [];
  let detalleHtml = '';
  if (!conceptosPreview.length && !detalles.length) {
    detalleHtml = '<tr><td colspan="10" class="text-center text-muted">Sin conceptos</td></tr>';
  } else {
    const rows = conceptosPreview.length ? conceptosPreview.map((c, idx) => ({ concepto: c, detalle: detalles[idx] || {} })) : detalles.map((d, idx) => ({ concepto: null, detalle: d, index: idx }));
    rows.forEach(({ concepto, detalle }) => {
      const cantidad = Number(concepto?.Cantidad ?? detalle?.cantidad ?? 0);
      const detalleFiscal = getDetalleFiscalNumbers(detalle || {});
      const pu = Number(concepto?.ValorUnitario ?? (cantidad > 0 ? (detalleFiscal.subtotalFiscalBase / cantidad) : (detalle?.precio_unitario || 0)));
      const importe = Number(concepto?.Importe ?? detalleFiscal.subtotalFiscalBase ?? (cantidad * pu));
      const objetoImp = concepto?.ObjetoImp || detalle?.objeto_imp || detalle?.producto_objeto_imp || '—';
      const traslado = Array.isArray(concepto?.Traslados) ? concepto.Traslados[0] : null;
      const tasaIva = Number(traslado?.TasaOCuota ?? detalleFiscal.tasaIva ?? 0);
      const ivaImporte = Number(traslado?.Importe ?? detalleFiscal.impuestosCalculados ?? 0);
      const impuestos = ivaImporte > 0 ? `IVA ${((tasaIva || 0) * 100).toFixed(2)}%: ${mxn(ivaImporte)}` : '—';
      detalleHtml += `
        <tr>
          <td class="text-center">${fix2(cantidad)}</td>
          <td>${concepto?.ClaveProdServ || detalle?.clave_prod_serv_sat || '—'}</td>
          <td>${concepto?.NoIdentificacion || detalle?.producto_codigo || detalle?.codigo || '—'}</td>
          <td>${concepto?.ClaveUnidad || detalle?.clave_unidad_sat || '—'}</td>
          <td>${concepto?.Unidad || detalle?.unidad_sat_descripcion || '—'}</td>
          <td>${concepto?.Descripcion || detalle?.descripcion || detalle?.producto_descripcion || detalle?.producto || '—'}</td>
          <td class="text-right">${mxn(pu)}</td>
          <td class="text-right">${mxn(importe)}</td>
          <td class="text-center">${objetoImp}</td>
          <td class="text-right">${impuestos}</td>
        </tr>`;
    });
  }
  $('#fac-detalles-body').html(detalleHtml);

  const xmlUrl = `${VENTAS_URL}?accion=descargar-cfdi-archivo&id_venta=${idVenta}&tipo=xml`;
  const pdfUrl = `${VENTAS_URL}?accion=descargar-cfdi-archivo&id_venta=${idVenta}&tipo=pdf`;
  const tieneArchivos = !!(cfdi.xml_timbrado || cfdi.pdf_base64 || estatusFiscal === 'TIMBRADO');
  $('#fac-link-xml').attr('href', xmlUrl);
  $('#fac-link-pdf').attr('href', pdfUrl);
  $('#fac-archivos').toggleClass('d-none', !tieneArchivos);

  $('#btnConfirmarFacturar').data('idVenta', idVenta);
  $('#fac-warning').toggleClass('d-none', !advertencias.length).html(advertencias.join('<br>'));

  applyDraftToForm();
  renderFacturaDraftUI();
  $('#fac-loader').hide();
  $('#fac-contenido').removeClass('d-none');
}

function cargarPreviewFacturacion(idVenta){
  $.get(VENTAS_URL, { accion:'facturacion-preview', id_venta:idVenta }, function(resp){
    if (!resp?.ok) {
      $('#fac-loader').hide();
      $('#fac-error').removeClass('d-none').text(resp?.msg || 'No fue posible cargar la información de facturación.');
      return;
    }
    renderFacturacionPreview(resp, idVenta);
  }, 'json').fail(function(xhr){
    $('#fac-loader').hide();
    $('#fac-error').removeClass('d-none').text(xhr?.responseJSON?.msg || 'Error al cargar la información de facturación.');
  });
}

function getPayloadFacturacion(){
  const draft = getFacturaDraft();
  const payload = {
    id_venta: Number(draft.venta.id_venta || $('#fac-id-venta').val() || 0),
    id_cliente_sat: Number(draft.receptor.id_cliente_fiscal || $('#fac-id-cliente-sat').val() || 0),
    emisor: {
      rfc: String(draft.emisor.rfc || '').trim().toUpperCase(),
      nombre: String(draft.emisor.nombre || '').trim(),
      regimen_fiscal: String(draft.emisor.regimen_fiscal || '').trim(),
      lugar_expedicion: String(draft.emisor.lugar_expedicion || '').trim(),
      serie: String(draft.emisor.serie || '').trim()
    },
    receptor: {
      id_cliente_sat: Number(draft.receptor.id_cliente_fiscal || $('#fac-id-cliente-sat').val() || 0),
      rfc: String(draft.receptor.rfc || '').trim().toUpperCase(),
      nombre: String(draft.receptor.nombre || '').trim(),
      nombre_comercial: String(draft.receptor.nombre_comercial || '').trim(),
      correo: String(draft.receptor.correo || '').trim(),
      domicilio_fiscal_receptor: String(draft.receptor.codigo_postal || '').trim(),
      regimen_fiscal: String(draft.receptor.regimen_fiscal || '').trim(),
      uso_cfdi: String(draft.receptor.uso_cfdi || '').trim(),
      residencia_fiscal: String(draft.receptor.residencia_fiscal || '').trim().toUpperCase(),
      numero_registro_tributario: String(draft.receptor.numero_registro_tributario || '').trim()
    },
    comprobante: {
      moneda: String(draft.comprobante.moneda || '').trim().toUpperCase(),
      metodo_pago: String(draft.comprobante.metodo_pago || '').trim().toUpperCase(),
      forma_pago: String(draft.comprobante.forma_pago || '').trim().toUpperCase(),
      tipo_cambio: String(draft.comprobante.tipo_cambio || '').trim(),
      condiciones_pago: String(draft.comprobante.condiciones_pago || '').trim(),
      tipo_comprobante: String(draft.comprobante.tipo_comprobante || '').trim().toUpperCase(),
      exportacion: String(draft.comprobante.exportacion || '').trim()
    },
    totales: {
      subtotal: Number(draft.venta.subtotal || 0),
      descuento: Number(draft.venta.descuento || 0),
      impuestos: Number(draft.venta.impuestos || 0),
      total: Number(draft.venta.total || 0)
    },
    conceptos: Array.isArray(draft.venta.conceptos) ? draft.venta.conceptos : [],
    draft_snapshot: {
      emisor: draft.emisor || {},
      receptor: draft.receptor || {},
      comprobante: draft.comprobante || {}
    }
  };
  console.debug('[FACTURACION][payload-js-enviado]', payload);
  console.debug('[FACTURACION][trazabilidad-campos]', {
    'emisor.rfc <- draft.emisor.rfc': payload?.emisor?.rfc,
    'receptor.rfc <- draft.receptor.rfc': payload?.receptor?.rfc,
    'receptor.regimen_fiscal <- draft.receptor.regimen_fiscal': payload?.receptor?.regimen_fiscal,
    'comprobante.forma_pago <- draft.comprobante.forma_pago': payload?.comprobante?.forma_pago,
    'comprobante.moneda <- draft.comprobante.moneda': payload?.comprobante?.moneda
  });
  return payload;
}

function abrirModalFacturacion(idVenta, folio){
  resetModalFacturacion();
  $('#fac-id-venta').val(idVenta);
  updateFacturaDraft('venta.id_venta', Number(idVenta), { skipRender: true });
  updateFacturaDraft('venta.folio', folio || ('#' + idVenta), { skipRender: true });
  $('#fac-folio').text(folio || ('#' + idVenta));
  $('#modalFacturarVenta').modal('show');
  cargarPreviewFacturacion(idVenta);
}

$(document).on('click', '.accion-facturar', function(e){
  e.preventDefault();
  const idVenta = Number($(this).data('id') || 0);
  const folio = $(this).data('folio') || '';
  if (!idVenta) return;
  abrirModalFacturacion(idVenta, folio);
});

$(document).off('select2:select', '#fac-select-cliente').on('select2:select', '#fac-select-cliente', function(e){
  const cliente = e?.params?.data || {};
  if (!cliente?.id && !cliente?.id_cliente_sat && !cliente?.id_cliente) return;
  const idClienteSat = Number(cliente.id || cliente.id_cliente_sat || cliente.id_cliente || 0);
  $('#fac-error, #fac-success').addClass('d-none').empty();
  $('#fac-id-cliente-sat').val(idClienteSat > 0 ? String(idClienteSat) : '');
  updateFacturaDraft('receptor.id_cliente_fiscal', idClienteSat, { skipRender: true });
  updateFacturaDraft('receptor.rfc', cliente.rfc || '', { skipRender: true });
  updateFacturaDraft('receptor.nombre', cliente.nombre || cliente.razon_social || '', { skipRender: true });
  updateFacturaDraft('receptor.nombre_comercial', cliente.nombre_comercial || '', { skipRender: true });
  updateFacturaDraft('receptor.correo', cliente.correo || '', { skipRender: true });
  updateFacturaDraft('receptor.codigo_postal', cliente.domicilio_fiscal_receptor || '', { skipRender: true });
  updateFacturaDraft('receptor.regimen_fiscal', cliente.regimen_fiscal_receptor || '', { skipRender: true });
  updateFacturaDraft('receptor.uso_cfdi', cliente.uso_cfdi || '', { skipRender: true });
  updateFacturaDraft('receptor.residencia_fiscal', cliente.residencia_fiscal || '', { skipRender: true });
  updateFacturaDraft('receptor.numero_registro_tributario', cliente.num_reg_id_trib || '', { skipRender: false });
});

$(document).off('select2:clear', '#fac-select-cliente').on('select2:clear', '#fac-select-cliente', function(){
  $('#fac-id-cliente-sat').val('');
  updateFacturaDraft('receptor.id_cliente_fiscal', 0, { skipRender: true });
  updateFacturaDraft('receptor.rfc', '', { skipRender: true });
  updateFacturaDraft('receptor.nombre', '', { skipRender: true });
  updateFacturaDraft('receptor.nombre_comercial', '', { skipRender: true });
  updateFacturaDraft('receptor.correo', '', { skipRender: true });
  updateFacturaDraft('receptor.codigo_postal', '', { skipRender: true });
  updateFacturaDraft('receptor.regimen_fiscal', '', { skipRender: true });
  updateFacturaDraft('receptor.uso_cfdi', '', { skipRender: true });
  updateFacturaDraft('receptor.residencia_fiscal', '', { skipRender: true });
  updateFacturaDraft('receptor.numero_registro_tributario', '', { skipRender: false });
});

$(document).off('change', '#fac-select-cliente').on('change', '#fac-select-cliente', function(e){
  if ($(this).val() || e?.params?.data) return;
  $('#fac-id-cliente-sat').val('');
});

$(document)
  .off('input', '#fac-input-rfc, #fac-input-razon-social, #fac-input-nombre-comercial, #fac-input-correo, #fac-input-cp, #fac-input-residencia-fiscal, #fac-input-num-reg-id-trib')
  .on('input', '#fac-input-rfc, #fac-input-razon-social, #fac-input-nombre-comercial, #fac-input-correo, #fac-input-cp, #fac-input-residencia-fiscal, #fac-input-num-reg-id-trib', function(){
    const map = {
      'fac-input-rfc': 'receptor.rfc',
      'fac-input-razon-social': 'receptor.nombre',
      'fac-input-nombre-comercial': 'receptor.nombre_comercial',
      'fac-input-correo': 'receptor.correo',
      'fac-input-cp': 'receptor.codigo_postal',
      'fac-input-residencia-fiscal': 'receptor.residencia_fiscal',
      'fac-input-num-reg-id-trib': 'receptor.numero_registro_tributario'
    };
    const path = map[this.id];
    if (!path) return;
    const raw = String($(this).val() || '').trim();
    const value = (this.id === 'fac-input-rfc' || this.id === 'fac-input-residencia-fiscal') ? raw.toUpperCase() : raw;
    updateFacturaDraft(path, value);
  });

$(document)
  .off('change', '#fac-select-regimen, #fac-select-uso-cfdi')
  .on('change', '#fac-select-regimen, #fac-select-uso-cfdi', function(){
    const path = this.id === 'fac-select-regimen' ? 'receptor.regimen_fiscal' : 'receptor.uso_cfdi';
    updateFacturaDraft(path, String($(this).val() || '').trim());
  });

$(document)
  .off('change', '#fac-select-moneda, #fac-select-metodo-pago, #fac-select-forma-pago, #fac-select-tipo-comprobante, #fac-select-exportacion')
  .on('change', '#fac-select-moneda, #fac-select-metodo-pago, #fac-select-forma-pago, #fac-select-tipo-comprobante, #fac-select-exportacion', function(){
    const map = {
      'fac-select-moneda': 'comprobante.moneda',
      'fac-select-metodo-pago': 'comprobante.metodo_pago',
      'fac-select-forma-pago': 'comprobante.forma_pago',
      'fac-select-tipo-comprobante': 'comprobante.tipo_comprobante',
      'fac-select-exportacion': 'comprobante.exportacion'
    };
    const path = map[this.id];
    if (!path) return;
    const normalized = ['fac-select-moneda', 'fac-select-metodo-pago', 'fac-select-forma-pago', 'fac-select-tipo-comprobante'].includes(this.id)
      ? String($(this).val() || '').trim().toUpperCase()
      : String($(this).val() || '').trim();
    updateFacturaDraft(path, normalized);
  });

$(document)
  .off('input', '#fac-input-tipo-cambio, #fac-input-condiciones-pago')
  .on('input', '#fac-input-tipo-cambio, #fac-input-condiciones-pago', function(){
    const path = this.id === 'fac-input-tipo-cambio' ? 'comprobante.tipo_cambio' : 'comprobante.condiciones_pago';
    const value = String($(this).val() || '').trim();
    if (this.id === 'fac-input-tipo-cambio') {
      console.debug('[FACTURACION][tipo_cambio][js-read]', {
        moneda: String($('#fac-select-moneda').val() || '').trim().toUpperCase(),
        tipo_cambio_leido: value
      });
    }
    updateFacturaDraft(path, value);
  });

$(document).off('shown.bs.modal', '#modalFacturarVenta').on('shown.bs.modal', '#modalFacturarVenta', function(){
  initFacturacionClienteSelect();
  const modalBody = this.querySelector('.modal-body');
  if (modalBody) modalBody.scrollTop = 0;
});

$(document).off('select2:open', '#fac-select-cliente').on('select2:open', '#fac-select-cliente', function(){
  window.setTimeout(function(){
    const $search = $('.select2-container--open .select2-search__field');
    if ($search.length) $search.trigger('focus');
  }, 0);
});

$(document).off('submit', '#formFacturarVenta').on('submit', '#formFacturarVenta', function(e){
  e.preventDefault();
  const idVenta = Number($('#fac-id-venta').val() || $('#btnConfirmarFacturar').data('idVenta') || 0);
  if (!idVenta) return;
  const payload = getPayloadFacturacion();
  payload.id_venta = idVenta;

  const $btn = $('#btnConfirmarFacturar');
  const original = $btn.html();
  $('#fac-error, #fac-success').addClass('d-none').empty();

  if (!payload.id_cliente_sat) {
    $('#fac-error').removeClass('d-none').text('Debe seleccionar un receptor existente.');
    return;
  }

  const draft = getFacturaDraft();
  draft.validaciones = validateFacturaDraft(draft);
  draft.listoParaTimbrar = !!draft.validaciones.listoParaTimbrar;
  renderFacturaDraftUI();
  if (!draft.listoParaTimbrar) {
    const validationMessage = draft.validaciones.listaErrores[0] || 'El draft de factura aún no está completo.';
    $('#fac-error').removeClass('d-none').text(validationMessage);
    return;
  }

  $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Facturando...');

  console.debug('[FACTURACION][tipo_cambio][ajax-send]', {
    moneda: payload?.comprobante?.moneda,
    tipo_cambio: payload?.comprobante?.tipo_cambio
  });

  $.post(VENTAS_URL, Object.assign({ accion:'facturar' }, payload), function(resp){
      $('#fac-loader').show();
      $('#fac-contenido').addClass('d-none');
      if (resp?.ok) {
        toastr.success(resp.msg || 'CFDI timbrado correctamente.');
        renderCfdiDetalle(resp.cfdi || null, idVenta);
        cargarVentas(paginaActual || 1);
        if ($('#modalDetalle').hasClass('show') && Number($('#modalDetalle').data('idVentaActual') || 0) === idVenta) {
          $(`a.accion-ver-detalle[data-id="${idVenta}"]`).trigger('click');
        }
        cargarPreviewFacturacion(idVenta);
        setTimeout(function(){
          $('#fac-success').removeClass('d-none').text(resp.msg || 'CFDI timbrado correctamente.');
        }, 250);
      } else {
        $('#fac-error').removeClass('d-none').text(resp?.msg || 'No fue posible facturar la venta.');
        toastr.error(resp?.msg || 'No fue posible facturar la venta.');
        cargarPreviewFacturacion(idVenta);
      }
    }, 'json').fail(function(xhr){
      const msg = xhr?.responseJSON?.msg || 'Error al facturar la venta.';
      $('#fac-error').removeClass('d-none').text(msg);
      toastr.error(msg);
    }).always(function(){
      $btn.prop('disabled', false).html(original);
    });
});

$(function(){
  cargarVentas(paginaActual);
});

function renderCfdiDetalle(cfdi, idVenta){
  resetDetalleCfdiState();

  const data = cfdi || {};
  const estatus = String(data.estatus || '').toUpperCase();
  const columnasCfdi = [
    {
      key: 'estatus',
      label: 'Estatus',
      value: () => estatus ? `<span class="badge badge-fiscal ${estatus === 'TIMBRADO' ? 'badge-success' : 'badge-secondary'}">${escapeHtml(estatus)}</span>` : ''
    },
    {
      key: 'uuid',
      label: 'UUID',
      value: () => String(data.uuid || '').trim()
    },
    {
      key: 'referencia',
      label: 'Referencia',
      value: () => String(data.referencia || '').trim()
    },
    {
      key: 'fecha_timbrado',
      label: 'Fecha timbrado',
      value: () => data.fecha_timbrado ? escapeHtml(fechaMx(data.fecha_timbrado)) : ''
    },
    {
      key: 'mensaje_respuesta',
      label: 'Mensaje respuesta',
      value: () => String(data.mensaje_respuesta || '').trim()
    },
    {
      key: 'codigo_respuesta',
      label: 'Código respuesta',
      value: () => String(data.codigo_respuesta || '').trim()
    }
  ];
  const columnasVisibles = columnasCfdi
    .map(col => ({ ...col, rendered: col.value() }))
    .filter(col => col.rendered !== '' && col.rendered != null);

  const hasCfdi = columnasVisibles.length > 0;

  $('#det-cfdi-empty').toggleClass('d-none', hasCfdi);
  $('#det-cfdi-card').toggleClass('d-none', !hasCfdi);

  if (!hasCfdi) {
    $('#det-cfdi-head-row').empty();
    $('#det-cfdi-body-row').empty();
    return;
  }

  const headHtml = columnasVisibles.map(col => `<th>${escapeHtml(col.label)}</th>`).join('');
  const bodyHtml = columnasVisibles.map(col => `<td>${col.rendered}</td>`).join('');
  $('#det-cfdi-head-row').html(headHtml);
  $('#det-cfdi-body-row').html(bodyHtml);

  const xmlUrl = `${VENTAS_URL}?accion=descargar-cfdi-archivo&id_venta=${idVenta}&tipo=xml`;
  const pdfUrl = `${BASE_URL}/utils/cfdi_pdf.php?id_venta=${encodeURIComponent(idVenta)}`;
  const mostrarXml = !!(data.xml_timbrado || estatus === 'TIMBRADO');
  const mostrarPdf = !!(data.xml_timbrado || estatus === 'TIMBRADO');

  $('#det-cfdi-xml').attr('href', xmlUrl).toggleClass('d-none', !mostrarXml);
  $('#det-cfdi-pdf').attr('href', pdfUrl).toggleClass('d-none', !mostrarPdf);
}

$(document).off('click', '#det-cfdi-pdf').on('click', '#det-cfdi-pdf', function(e){
  e.preventDefault();
  const $btn = $(this);
  const url = String($btn.attr('href') || '').trim();
  if (!url || $btn.hasClass('d-none') || $btn.prop('disabled')) return;

  $btn.prop('disabled', true);
  window.open(url, '_blank');
  setTimeout(function(){
    $btn.prop('disabled', false);
  }, 1200);
});

/* ======================= INVOICE (A4/Carta) ======================= */
function renderInvRow({cantidad, clave, descripcion, pu, importe}) {
  const cant = (Number(cantidad||0)).toFixed(2);
  const unit = mxn(pu||0);
  const imp  = mxn(importe||0);
  const clv  = clave || '';
  const desc = descripcion || '';
  return `
    <tr>
      <td class="c text-center">${cant}</td>
      <td class="clv">${clv}</td>
      <td class="desc">${desc}</td>
      <td class="num">${unit}</td>
      <td class="num">${imp}</td>
    </tr>`;
}

window.abrirInvoice = function(idVenta){
  if (!idVenta){ return; }

  // Placeholders
  $('#inv-folio').text('—');
  $('#inv-fecha').text('—');
  $('#inv-forma').text('—');
  $('#inv-tipo').text('—');
  $('#inv-estatus').html('');
  $('#inv-cliente').text('Público en general');
  $('#inv-rfc').text('N/A');
  $('#inv-dom').text('N/A');
  $('#inv-tel').text('N/A');
  $('#inv-polizas').text('');
  $('#inv-polizas-wrap').hide();
  $('#inv-tbody').html('<tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>');
  $('#inv-subtotal').text('$0.00');
  $('#inv-descuento').text('$0.00');
  $('#inv-iva').text('$0.00');
  $('#inv-total').text('$0.00');
  $('#inv-row-descuento').hide();
  $('#inv-row-iva').hide();

  $('#modalInvoice').modal('show');

  $.get(VENTAS_URL, { accion:'detalle', id_venta:idVenta }, function(resp){
    if(!resp || !resp.venta){
      $('#inv-tbody').html('<tr><td colspan="5" class="text-center text-danger">No se encontró la venta.</td></tr>');
      return;
    }

    const v     = resp.venta || {};
    const dets  = resp.detalles || [];

    // Encabezado
    $('#inv-folio').text(v.folio || '—');
    $('#inv-fecha').text(fechaMx(v.fecha));
    $('#inv-forma').text(v.forma_pago || '—');
    $('#inv-tipo').text(v.tipo_precio || '—');
    $('#inv-estatus').html(getBadge(v.estatus || '—'));

    // Cliente
    const nombreCli = v.cliente || v.cliente_nombre || v.nombre_cliente || 'Público en general';
    const rfcCli    = v.cliente_rfc || v.rfc || v.rfc_cliente || 'N/A';
    const domCli    = v.cliente_domicilio || v.domicilio || v.direccion || 'N/A';
    const telCli    = v.cliente_telefono || v.telefono || 'N/A';
    $('#inv-cliente').text(nombreCli);
    $('#inv-rfc').text(rfcCli);
    $('#inv-dom').text(domCli);
    $('#inv-tel').text(telCli);

    // Pólizas de acumuladores (únicas)
    const polizasAcum = dets
      .filter(d => esProductoAcumulador(d))
      .map(d => (d.numero_poliza || d.no_poliza || d.num_poliza || '').toString().trim())
      .filter(p => !!p);

    const polizasUnicas = Array.from(new Set(polizasAcum));
    if (polizasUnicas.length) {
      const prefijo = polizasUnicas.length > 1 ? 'Numero de Póliza(s): ' : 'Numero de Póliza: ';
      $('#inv-polizas').text(`${prefijo}${polizasUnicas.join(', ')}`);
      $('#inv-polizas-wrap').show();
    } else {
      $('#inv-polizas-wrap').hide();
    }

    // Detalle
    if (!dets.length){
      $('#inv-tbody').html('<tr><td colspan="5" class="text-center text-muted">Sin productos</td></tr>');
    } else {
      let html = '';
      let subtotalCalc = 0;

      dets.forEach(d=>{
        const c = Number(d.cantidad||0);
        const u = Number(d.precio_unitario||0);
        const s = Number(d.subtotal ?? (c*u));
        subtotalCalc += s;

        html += renderInvRow({
          cantidad: c,
          clave: d.codigo || d.clave || ('#'+(d.id_producto||'')),
          descripcion: d.producto || d.descripcion || d.nombre || '',
          pu: u,
          importe: s
        });
      });

      $('#inv-tbody').html(html);

      const subtotal  = Number(v.subtotal ?? subtotalCalc);
      const descuento = Number(v.descuento || v.total_descuento || 0);
      const iva       = Number(v.iva || v.total_iva || 0);
      const total     = Number(v.total != null ? v.total : (subtotal - descuento + iva));

      $('#inv-subtotal').text(mxn(subtotal));

      if (descuento > 0) {
        $('#inv-descuento').text(mxn(descuento));
        $('#inv-row-descuento').show();
      } else { $('#inv-row-descuento').hide(); }

      if (iva > 0) {
        $('#inv-iva').text(mxn(iva));
        $('#inv-row-iva').show();
      } else { $('#inv-row-iva').hide(); }

      $('#inv-total').text(mxn(total));
    }

  }, 'json').fail(()=>{
    $('#inv-tbody').html('<tr><td colspan="5" class="text-center text-danger">Error al cargar la venta.</td></tr>');
  });
};

/* ===== Imprimir en IFRAME con estilos “bonitos”, iguales al modal ===== */
function printAreaInIframe(el, { page = 'Letter', margin = '12mm' } = {}) {
  const html = el.outerHTML;

  const css = `
    @page { size: ${page}; margin: ${margin}; }
    :root{
      --text:#333;
      --muted:#6c757d;
      --line:#dfe3e7;
      --head:#f5f6f8;
      --accent:#2d6cdf;
    }
    *{ box-sizing:border-box; }
    html, body { padding:0; margin:0; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, "Noto Sans", "Liberation Sans", sans-serif; color:var(--text); }
    .inv{
      padding: 6mm 4mm;
      font-size: 12px;
      line-height: 1.35;
    }
    .inv-header{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
    .inv-emisor{ max-width: 62%; }
    .inv-emisor .h5{ font-size:16px; font-weight:700; letter-spacing:.2px; }
    .inv-meta{ text-align:right; }
    .inv-meta .h4{ font-size:18px; font-weight:700; letter-spacing:.3px; margin-bottom:4px; }
    hr{ border:0; border-top:1px solid var(--line); margin:8px 0; }
    .inv-seccion .h6{ font-size:13px; font-weight:700; margin:8px 0 6px; }
    .muted{ color:var(--muted); }
    .table-responsive{ overflow:visible; }
    table{ width:100%; border-collapse:collapse; }
    thead th{
      background:var(--head);
      border:1px solid var(--line);
      font-weight:600;
      font-size:12px;
      padding:6px 8px;
    }
    tbody td{
      border:1px solid var(--line);
      font-size:12px;
      padding:6px 8px;
    }
    td.c{ width:70px; }
    td.clv{ width:120px; word-break:break-all; }
    td.desc{ }
    td.num{ width:130px; text-align:right; white-space:nowrap; }
    .totals-wrap{ display:flex; justify-content:flex-end; }
    .totals{ width: 260px; }
    .totals th{ text-align:right; padding:6px 10px; font-weight:600; border:none; }
    .totals td{ text-align:right; padding:6px 8px; border:none; }
    .totals tr.total-row th,
    .totals tr.total-row td{
      font-size:14px; font-weight:700; border-top:1px solid var(--line);
    }
    .inv-notas{ margin-top:10px; }
    .inv-notas .small{ font-size:11px; color:var(--muted); }
    .badge{ font-size:11px; font-weight:600; padding:.15rem .4rem; border-radius:.25rem; }
  `;

  const iframe = document.createElement('iframe');
  iframe.style.position = 'fixed';
  iframe.style.right = '0';
  iframe.style.bottom = '0';
  iframe.style.width = '0';
  iframe.style.height = '0';
  iframe.style.border = '0';
  document.body.appendChild(iframe);

  const doc = iframe.contentWindow.document;
  doc.open();
  doc.write(`<!doctype html>
  <html>
    <head>
      <meta charset="utf-8">
      <title>Nota Venta</title>
      <style>${css}</style>
    </head>
    <body>${html}</body>
  </html>`);
  doc.close();

  iframe.onload = () => {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
    setTimeout(() => document.body.removeChild(iframe), 500);
  };
}

// Click imprimir (Carta por defecto)
$(document).off('click','#btnImprimirInvoice').on('click','#btnImprimirInvoice', function(){
  const area = document.getElementById('invArea');
  if (!area) return;
  printAreaInIframe(area, { page: 'Letter', margin: '12mm' });
});
</script>

  </body>
</html>
