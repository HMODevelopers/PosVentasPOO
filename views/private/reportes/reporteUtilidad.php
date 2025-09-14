<?php
// views/private/reportes/utilidades.php
$titulo    = "Reportes";
$modulo    = "Ventas";
$subtitulo = "Utilidades (detalle por renglón)";

session_start();
require_once __DIR__ . '/../../../includes/config.php';

if (!isset($_SESSION['usuario'])) {
  header('Location: ' . BASE_URL . '/views/public/index.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Utilidades | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

  <!-- CSS base -->
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

  <style>
    .card-header{ border-color:darkgray; border-style:dotted; }
    .clean-filter .input-group-text{ cursor:pointer; }
    .table thead th{ white-space:nowrap; }
    tfoot td{ font-weight:700; background:#eef3ff; }
    /* Evita que la .table-responsive "corte" el dropdown vertical (por si agregas acciones) */
    .table-responsive { overflow-y: visible !important; }
    .table-responsive .dropdown-menu { z-index: 2000; }
    .is-invalid{ border-color:#f1556c; }
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

  <div class="wrapper">
    <!-- Loader -->
    <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
      <div class="loader">
        <div class="loader__figure"></div>
        <p class="loader__label">Cargando...</p>
      </div>
    </div>

    <div class="container-fluid">
      <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

      <!-- =================== Filtros =================== -->
      <div class="card-header">
        <h5>Filtros</h5>

        <div class="row">
          <div class="col-lg-12">
            <div class="row">

              <!-- Desde -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroDesde" class="control-label">Desde</label>
                  <div class="input-group">
                    <input type="date" id="FiltroDesde" class="form-control filtrar">
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text">
                        <i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroDesde')"></i>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Hasta -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroHasta" class="control-label">Hasta</label>
                  <div class="input-group">
                    <input type="date" id="FiltroHasta" class="form-control filtrar">
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text">
                        <i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroHasta')"></i>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

            </div><!-- /row -->

            <div class="row">
              <!-- Solo crédito liquidado (se mantiene tal cual) -->
              <div class="col-md-3">
                <div class="form-group">
                  <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input filtrar" id="ChkSoloLiquidado" checked disabled>
                    <label class="custom-control-label" for="ChkSoloLiquidado">Solo crédito liquidado</label>
                  </div>
                </div>
              </div>

              <div class="col-md-9 d-flex align-items-end justify-content-end">
                <button id="BtnExcel" class="btn btn-success btn-sm" title="Descargar Excel (XLS)">
                  <i class="mdi mdi-file-excel"></i> Exportar Excel (XLS)
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>
      <!-- =================== /Filtros =================== -->

      <!-- =================== Tabla =================== -->
      <div class="row">
        <div class="col-12">
          <div class="card-box">
            <h4 class="header-title mb-2">Utilidades (detalle por renglón)</h4>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-striped mb-0">
                <thead>
                  <tr>
                    <th class="text-center" style="width:120px;">Fecha</th>
                    <th class="text-center" style="width:120px;">Folio</th>
                    <th class="text-center" style="width:130px;">Código</th>
                    <th>Descripción</th>
                    <th class="text-center" style="width:80px;">Unidad</th>
                    <th class="text-center" style="width:90px;">Cantidad</th>
                    <th class="text-center" style="width:120px;">Precio Unit.</th>
                    <th class="text-center" style="width:120px;">Costo Unit.</th>
                    <th class="text-center" style="width:120px;">Ingreso</th>
                    <th class="text-center" style="width:120px;">Costo</th>
                    <th class="text-center" style="width:120px;">Utilidad</th>
                    <th class="text-center" style="width:100px;">Margen</th>
                  </tr>
                </thead>
                <tbody id="tbodyItems">
                  <tr><td colspan="12" class="text-center text-muted">Sin registros</td></tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="8" class="text-right">TOTALES</td>
                    <td class="text-center" id="tIngreso">—</td>
                    <td class="text-center" id="tCosto">—</td>
                    <td class="text-center" id="tUtilidad">—</td>
                    <td class="text-center" id="tMargen">—</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="row align-items-center justify-content-between mt-2">
              <div class="col-md-6">
                <div id="infoTabla" class="dataTables_info" role="status" aria-live="polite">
                  Mostrando 0 a 0 de 0 registros
                </div>
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
      <!-- =================== /Tabla =================== -->

    </div><!-- /container-fluid -->
  </div><!-- /wrapper -->

  <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
  <div class="rightbar-overlay"></div>

  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
  $(function(){
    const URL_REP = '<?= BASE_URL ?>/controllers/ReportesController.php';

    let paginaActual = 1;
    const limitePorPagina = 20;

    const mxn = n => Number(n||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
    const pct = n => `${(Number(n||0)*100).toFixed(2)}%`;
    const num = n => Number(n||0);
    const esc = s => String(s==null?'':s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));

    // Por defecto: hoy
    (function setHoy(){
      const h=new Date(), y=h.getFullYear(), m=String(h.getMonth()+1).padStart(2,'0'), d=String(h.getDate()).padStart(2,'0');
      const hoy = `${y}-${m}-${d}`;
      $('#FiltroDesde').val(hoy);
      $('#FiltroHasta').val(hoy);
    })();

    // ====== UX: validación de rango de fechas ======
    function fechasValidas(){
      const d = $('#FiltroDesde').val(), h = $('#FiltroHasta').val();
      $('#FiltroDesde, #FiltroHasta').removeClass('is-invalid');
      if(!d || !h) return false;
      if(d > h){
        $('#FiltroDesde, #FiltroHasta').addClass('is-invalid');
        toastr.warning('La fecha "Desde" no puede ser mayor que "Hasta".');
        return false;
      }
      return true;
    }

    // Patrón de filtros (igual que en Productos)
    $(".filtrar")
      .on('change', function(){
        const $el=$(this);
        if(($el.is(':checkbox') && $el.is(':checked')) || ($el.val() && $el.val().length>0))
          $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
        else
          $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
        setTimeout(()=> cargarItems(1), 120);
      })
      .on('keypress', function(e){ if(e.charCode==13) cargarItems(1); })
      .on('keyup', function(){
        if($(this).val().length>0) $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
        else $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
      });

    // Util para limpiar un campo
    window.clearField = function(id){
      const el = document.getElementById(id);
      if (!el) return;
      if(el.type==='checkbox'){ el.checked=false; } else { el.value=''; }
      el.dispatchEvent(new Event('change'));
      try{ el.focus(); }catch(e){}
    };

    // ====== Cargar listado + totales ======
    function leerFiltros(){
      return {
        desde: $('#FiltroDesde').val() || '',
        hasta: $('#FiltroHasta').val() || '',
        solo_credito_liquidado: $('#ChkSoloLiquidado').is(':checked') ? 1 : 0
      };
    }

    function cargarItems(pagina){
      if(!fechasValidas()) return;
      paginaActual = pagina;

      const f = leerFiltros();
      $('#LoadingImage').show();

      $.post(URL_REP, {
        accion:'utilidades-listar',
        pagina:pagina,
        limite:limitePorPagina,
        desde:f.desde, hasta:f.hasta,
        solo_credito_liquidado: f.solo_credito_liquidado,
        modo: 'detalle'
      }, null, 'json')
      .done(function(resp){
        const rows = resp?.data || [];
        const total = parseInt(resp?.total||0,10);
        renderTabla(rows);
        renderInfo(total);
        paginar(total);
        cargarTotales(); // sumas globales del rango
      })
      .fail(()=> toastr.error('Error al cargar el reporte.'))
      .always(()=> $('#LoadingImage').hide());
    }

    function renderTabla(rows){
      const $tb = $('#tbodyItems');
      if(!rows.length){
        $tb.html('<tr><td colspan="12" class="text-center text-muted">Sin registros</td></tr>');
        return;
      }
      let html='';
      rows.forEach(r=>{
        html += `
          <tr>
            <td class="text-center">${esc(r.fecha || '')}</td>
            <td class="text-center"><b>${esc(r.folio || '')}</b></td>
            <td class="text-center">${esc(r.codigo || '')}</td>
            <td>${esc(r.descripcion || '')}</td>
            <td class="text-center">${esc(r.unidad || '')}</td>
            <td class="text-center">${num(r.cantidad).toLocaleString('es-MX')}</td>
            <td class="text-center">${mxn(r.precio_unitario)}</td>
            <td class="text-center">${mxn(r.costo_unitario)}</td>
            <td class="text-center">${mxn(r.ingreso)}</td>
            <td class="text-center">${mxn(r.costo)}</td>
            <td class="text-center"><b>${mxn(r.utilidad)}</b></td>
            <td class="text-center">${pct(r.margen)}</td>
          </tr>
        `;
      });
      $tb.html(html);
    }

    function cargarTotales(){
      const f = leerFiltros();
      $.post(URL_REP, {
        accion:'utilidades-totales',
        desde: f.desde,
        hasta: f.hasta,
        solo_credito_liquidado: f.solo_credito_liquidado
      }, null, 'json')
      .done(r=>{
        const t = r?.totales || {};
        $('#tIngreso').text(mxn(t.ingreso||0));
        $('#tCosto').text(mxn(t.costo||0));
        $('#tUtilidad').text(mxn(t.utilidad||0));
        $('#tMargen').text(pct(t.margen||0));
      });
    }

    function renderInfo(total){
      const desde = total? ((paginaActual-1)*limitePorPagina + 1) : 0;
      const hasta = Math.min(paginaActual*limitePorPagina, total);
      $('#infoTabla').text(`Mostrando ${desde} a ${hasta} de ${total} registros`);
    }

    function paginar(totalItems){
      const totalPages = Math.max(1, Math.ceil(totalItems/limitePorPagina));
      const $ul = $('#pagination').empty();
      if(totalPages<=1){ $ul.closest('nav').hide(); return; } else { $ul.closest('nav').show(); }
      const maxVis=7;
      let start=Math.max(1, paginaActual-Math.floor(maxVis/2));
      let end=Math.min(totalPages, start+maxVis-1);
      if(end-start+1<maxVis) start=Math.max(1, end-maxVis+1);

      if(paginaActual>1){
        $ul.append(`<li class="page-item"><a class="page-link" href="#" data-p="1">Primera</a></li>`);
        $ul.append(`<li class="page-item"><a class="page-link" href="#" data-p="${paginaActual-1}">&laquo; Anterior</a></li>`);
      }
      for(let i=start;i<=end;i++){
        $ul.append(`<li class="page-item ${i===paginaActual?'active':''}">
          <a class="page-link" href="#" data-p="${i}">${i}</a></li>`);
      }
      if(paginaActual<totalPages){
        $ul.append(`<li class="page-item"><a class="page-link" href="#" data-p="${paginaActual+1}">Siguiente &raquo;</a></li>`);
        $ul.append(`<li class="page-item"><a class="page-link" href="#" data-p="${totalPages}">Última</a></li>`);
      }

      $ul.off('click','a').on('click','a', function(e){
        e.preventDefault();
        const p = Number($(this).data('p'));
        if(Number.isFinite(p)) cargarItems(p);
      });
    }

    // Exportar XLS (detalle)
    $('#BtnExcel').on('click', function(){
      if(!fechasValidas()) return;
      const f = leerFiltros();
      const qs = $.param({
        accion:'utilidades-xls',
        desde: f.desde,
        hasta: f.hasta,
        solo_credito_liquidado: f.solo_credito_liquidado,
        modo: 'detalle'
      });
      window.location = `${URL_REP}?${qs}`;
    });

    // Inicial
    cargarItems(1);
  });
  </script>
</body>
</html>
