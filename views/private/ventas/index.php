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

  if (v.estatus === 'Activa' || v.estatus === 'Credito'){
    out += `
      <a class="dropdown-item accion-eliminar" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
        <i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Cancelar
      </a>`;
  }
  return out;
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
      tbody = '<tr><td colspan="12" class="text-center">No hay ventas disponibles</td></tr>';
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
  $('#det-error').hide(); 
  $('#det-contenido').hide(); 
  $('#det-loader').show();
  $('#modalDetalle').modal('show');

  // Oculta bloques de crédito hasta saber si aplica
  const $wrapsCredito = $('#wrap-det-estatus-credito, #wrap-det-abonado, #wrap-det-saldo, #wrap-det-abonos, #det-btn-abonar');
  $wrapsCredito.addClass('d-none');

  // Limpia tablas
  $('#det-tbody').empty();
  $('#det-total').text('$0.00');
  $('#det-abonos-body').html('<tr><td colspan="4" class="text-center text-muted">Sin abonos</td></tr>');

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
    $('#det-fecha').text(fechaMx(v.fecha));
    $('#det-estatus').html(getBadge(v.estatus || '—'));
    $('#det-cliente').text(v.cliente || 'Público en general');
    $('#det-usuario').text(v.usuario || '—');
    $('#det-caja').text(v.caja || '—');
    $('#det-forma').text(v.forma_pago || '—');
    $('#det-tipo').text(v.tipo_precio || '—');

    // Productos
    let tb='', total=0;
    if (!dets.length){
      tb = '<tr><td colspan="5" class="text-center text-muted">Sin productos</td></tr>';
    } else {
      dets.forEach(d=>{
        const c = Number(d.cantidad || 0);
        const u = Number(d.precio_unitario || 0);
        const s = Number(d.subtotal ?? (c * u));
        total += s;
        tb += `<tr>
          <td>${d.codigo || ('#'+(d.id_producto||''))}</td>
          <td>${d.producto || ('#'+(d.id_producto||''))}</td>
          <td class="text-center">${c}</td>
          <td class="text-right">${mxn(u)}</td>
          <td class="text-right">${mxn(s)}</td>
        </tr>`;
      });
    }
    $('#det-tbody').html(tb);
    $('#det-total').text(mxn(total || v.total || 0));

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

let edVentaId = 0;
let carrito   = [];
let debTimer = null;

function vendibleDe(det){ return Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo)); }
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

      if (selected!=null) {
        $selForma.val(String(selected));
      } else if (fallbackText){
        $selForma.find('option').filter(function(){return $(this).text()===fallbackText;}).prop('selected',true);
      }
    })
    .fail(()=>{
      $selForma.empty()
        .append('<option value="1">Efectivo</option>')
        .append('<option value="2">Tarjeta</option>')
        .append('<option value="3">Mixto</option>')
        .append('<option value="99">Crédito</option>');
      if (selected!=null) $selForma.val(String(selected));
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
    stock_actual:Number(p.stock_actual ?? p.existencia ?? 0),
    stock_minimo:Number(p.stock_minimo ?? 0),
    precio_publico:Number(p.precio_publico ?? 0),
    precio_taller:Number(p.precio_taller ?? 0),
    precio_proveedor:Number(p.precio_proveedor ?? 0),
    proveedor:p.proveedor ?? null,
    original: Number(originalCant||0)
  };
  const vendible=Math.max(0, itemBase.stock_actual - itemBase.stock_minimo);
  if(vendible<=0 && itemBase.original<=0){ toastr.warning('Sin stock disponible para vender.'); return; }
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
              </div>
            </div>
          </div>
        </td>
        <td class="text-center">
          <div class="btn-group btn-group-sm" role="group">
            <button class="btn btn-outline-danger" data-ed-dec="${idx}"><i class="mdi mdi-minus"></i></button>
            <input type="number" min="1" step="1" class="form-control form-control-sm text-center w-70px" value="${fix2(it.cantidad)}" data-ed-qty="${idx}" data-max="${max}">
            <button class="btn btn-outline-success" data-ed-inc="${idx}"><i class="mdi mdi-plus"></i></button>
          </div>
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
}

$tbody.on('click','button[data-ed-inc]', function(){
  const i=Number(this.dataset.edInc); if(isNaN(i)||!carrito[i]) return;
  const vendible=Math.max(0, Number(carrito[i].stock_actual) - Number(carrito[i].stock_minimo));
  const max = Number(carrito[i].original||0) + vendible;
  const next=Number(carrito[i].cantidad)+1;
  carrito[i].cantidad = next>max ? (toastr.info('Se alcanzó el máximo vendible.'), max) : next;
  pintarCarrito();
});
$tbody.on('click','button[data-ed-dec]', function(){
  const i=Number(this.dataset.edDec); if(isNaN(i)||!carrito[i]) return;
  carrito[i].cantidad=Math.max(1,Number(carrito[i].cantidad)-1);
  pintarCarrito();
});
$tbody.on('change','input[data-ed-qty]', function(){
  const i=Number(this.dataset.edQty); if(isNaN(i)||!carrito[i]) return;
  let val=Math.max(1, Number(this.value||1));
  const max=Number(this.dataset.max||0);
  if (val>max){ val=max; toastr.info('Se ajustó a máximo vendible.'); }
  carrito[i].cantidad=val; pintarCarrito();
});
$tbody.on('change','input[data-ed-sub]', function(){
  const i=Number(this.dataset.edSub); if(isNaN(i)||!carrito[i]) return;
  let sub=Number(this.value); if(isNaN(sub)||sub<0) sub=0;
  const qty=Math.max(1, Number(carrito[i].cantidad)||1);
  carrito[i].override_unit = Number((sub/qty).toFixed(2));
  pintarCarrito();
});
$tbody.on('click','button[data-ed-del]', function(){
  const i=Number(this.dataset.edDel); if(isNaN(i)) return;
  carrito.splice(i,1); pintarCarrito();
});

$tpPrecio.on('change', pintarCarrito);

$('#btnGuardarEdicion').on('click', function(){
  if (!edVentaId){ toastr.error('No hay venta cargada.'); return; }
  if (!carrito.length){ toastr.warning('Agrega productos a la orden'); return; }

  const $opt = $selForma.find('option:selected');
  const esCredito = isCreditoByOption($opt);

  const idClienteSel = $selCliente.val() ? Number($selCliente.val()) : null;
  if (esCredito && !idClienteSel){
    toastr.error('Para ventas a crédito es obligatorio seleccionar un cliente.');
    $selCliente.focus();
    return;
  }

  const id_forma_pago = $selForma.val() ? Number($selForma.val()) : null;
  const nuevoEstatus = esCredito ? 'Credito' : 'Activa';
  const id_tipo_precio = mapTipoPrecioId($tpPrecio.val());

  const venta = {
    id_venta: edVentaId,
    fecha: $('#ed-fechaVenta').val(),
    id_cliente: idClienteSel ?? null,
    id_forma_pago: id_forma_pago ?? null,
    id_tipo_precio: id_tipo_precio,
    estatus: nuevoEstatus
  };
  const detalles = carrito.map(it=>{
    const unit = precioDeItem(it);
    const cant = Math.max(1, Number(it.cantidad)||1);
    return { id_producto: it.id_producto, cantidad: cant, precio_unitario: unit, subtotal: cant*unit };
  });

  const payload = { venta, detalles };

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
  carrito=[]; pintarCarrito();

  $.get(VENTAS_URL,{accion:'detalle', id_venta: edVentaId}, function(r){
    if (!r || !r.venta){ $errEd.removeClass('d-none').text('No se encontró la venta.'); return; }
    const v=r.venta, det=r.detalles||[];

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
            stock_actual:Number(p.stock_actual ?? p.existencia ?? 0),
            stock_minimo:Number(p.stock_minimo ?? 0),
            precio_publico:Number(p.precio_publico ?? 0),
            precio_taller:Number(p.precio_taller ?? 0),
            precio_proveedor:Number(p.precio_proveedor ?? 0),
            proveedor:p.proveedor ?? null,
            original:Number(d.cantidad||0),
            cantidad:Number(d.cantidad||0)
          };
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
$(function(){
  cargarVentas(paginaActual);
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
