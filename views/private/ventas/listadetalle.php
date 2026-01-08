<?php
$titulo = "Ventas";
$modulo = "Listado detalle ventas";
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
    <title>Listado detalle ventas | REFASOFT-V4</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core CSS -->
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />

    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

    <style>
      .text-right{ text-align:right!important; }
      .text-center{ text-align:center!important; }
      /* ===== Fix: dropdown del botón de acciones NO se recorte ni genere scroll raro ===== */
      .ventas-wrapper .card-box .table-responsive{
        overflow-x: auto;
        overflow-y: visible;  /* deja que el menú se salga por arriba/abajo */
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
                <h4 class="header-title">Listado detalle de ventas</h4>
              </div>

              <div class="table-responsive">
                <table id="tablaVentasDetalle" class="table table-bordered table-hover table-striped">
                  <thead>
                    <tr>
                      <th>Folio</th>
                      <th>Cajero</th>
                      <th>Caja</th>
                      <th>Forma de Pago</th>
                      <th>Tipo de Precio</th>
                      <th>Código</th>
                      <th>Producto</th>
                      <th class="text-end">Cantidad</th>
                      <th class="text-end">Precio Unitario</th>
                      <th class="text-end">Total renglón</th>
                      <th>Estatus crédito</th>
                      <th>Estatus</th>
                      <th>Cliente</th>
                      <th>Fecha</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="row align-items-center justify-content-between mt-2">
                <div class="col-md-6">
                  <div id="infoVentasDetalle" class="dataTables_info"></div>
                </div>
                <div class="col-md-6 d-flex justify-content-end">
                  <nav aria-label="Page navigation">
                    <ul id="paginationDetalle" class="pagination justify-content-end mb-0"></ul>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- /Tabla Ventas -->
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

    <!-- ========================= APP JS ========================= -->
    <script>
    function clearField(id){ try { $('#'+id).val('').trigger('change'); } catch(e){} }

    const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
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

    const BASE = BASE_URL;
    const VENTAS_URL = `${BASE}/controllers/VentasController.php`;

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

    function getBadgeCredito(st){
      switch(st){
        case 'Pendiente':  return '<span class="badge badge-light-danger badge-pill">Pendiente</span>';
        case 'En Proceso': return '<span class="badge badge-light-warning badge-pill">En Proceso</span>';
        case 'Liquidado':  return '<span class="badge badge-light-success badge-pill">Liquidado</span>';
        case 'N/A':        return '<span class="badge badge-light-secondary badge-pill">N/A</span>';
        default:           return `<span class="badge badge-light-secondary badge-pill">${st||'N/A'}</span>`;
      }
    }

    let paginaActual=1; const limitePorPagina=10;

    function cargarVentasDetalle(pagina){
      const folio  = $('#Folio').val() || '';
      const fecha  = ($('#Fecha').val() || '').trim();
      const estatus= $('#FEstatus').val() || '';

      $.post(VENTAS_URL,{
        accion:'listar-detalle',
        pagina,
        limite:limitePorPagina,
        folio,
        fecha: fecha || null,
        estatus
      }, function(resp){
        const items = resp?.data || [];
        const total  = parseInt(resp?.total||0,10);
        let tbody='';

        if (!items.length){
          tbody = '<tr><td colspan="14" class="text-center">No hay ventas disponibles</td></tr>';
        } else {
          items.forEach(v=>{
            tbody += `
              <tr>
                <td class="text-center"><b>${v.folio || ''}</b></td>
                <td class="text-center">${v.usuario || ''}</td>
                <td class="text-center">${v.caja || ''}</td>
                <td class="text-center">${v.forma_pago || '—'}</td>
                <td class="text-center">${v.tipo_precio || ''}</td>
                <td class="text-center">${v.codigo_producto || ''}</td>
                <td>${v.producto || ''}</td>
                <td class="text-right">${num(v.cantidad).toFixed(2)}</td>
                <td class="text-right">${mxn(v.precio_unitario)}</td>
                <td class="text-right"><b>${mxn(v.total_renglon)}</b></td>
                <td class="text-center">${getBadgeCredito(v.estatus_credito || 'N/A')}</td>
                <td class="text-center">${getBadge(v.estatus)}</td>
                <td class="text-center">${v.cliente || 'Público en general'}</td>
                <td class="text-center">${fechaMx(v.fecha)}</td>
              </tr>`;
          });
        }
        $('#tablaVentasDetalle tbody').html(tbody);

        configurarPaginacion(pagina, total, limitePorPagina);
        const desde=(pagina-1)*limitePorPagina+1, hasta=Math.min(pagina*limitePorPagina,total);
        $('#infoVentasDetalle').text(`Mostrando ${total===0?0:desde} a ${hasta} de ${total} renglones`);
      },'json');
    }

    function configurarPaginacion(currentPage,totalItems,itemsPerPage){
      const totalPages=Math.max(1,Math.ceil(totalItems/itemsPerPage));
      const $ul=$('#paginationDetalle').empty();
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

    $('#paginationDetalle').on('click','a.page-link',function(e){
      e.preventDefault(); const p=Number($(this).data('page'));
      if (Number.isFinite(p)){ paginaActual=p; cargarVentasDetalle(paginaActual); }
    });

    $(function(){
      cargarVentasDetalle(paginaActual);

      $(".filtrar")
        .on('change keyup', function(){
          const $el=$(this);
          if ($el.val().length>0) $el.siblings(".clean-filter").css({display:"flex"});
          else $el.siblings(".clean-filter").css({display:"none"});
        })
        .on('change', ()=> setTimeout(()=>cargarVentasDetalle(1),200))
        .on('keypress', e=>{ if(e.charCode===13) cargarVentasDetalle(1); });

      $(".clean-filter").click(function(){
        const $el=$(this).parent().find(".filtrar"); $el.val("").trigger("change");
        if ($el.hasClass("select2")) $el.select2("val", 0);
        cargarVentasDetalle(1);
      });
    });
    </script>
  </body>
</html>
