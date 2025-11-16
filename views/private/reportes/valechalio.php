<?php
// views/private/reportes/credito_cliente_items.php
$titulo = "Reportes";
$modulo = "Ventas";
$subtitulo = "";

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
  <title>Ventas a crédito por cliente (detalle) | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

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
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

  <div class="wrapper">
    <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
      <div class="loader">
        <div class="loader__figure"></div>
        <p class="loader__label">Cargando...</p>
      </div>
    </div>

    <div class="container-fluid">
      <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

      <!-- Filtros -->
      <div class="card-header">
        <h5>Filtros</h5>
        <div class="row">
          <div class="col-lg-12">
            <div class="row">
              <!-- Cliente -->
              <div class="col-md-4">
                <div class="form-group">
                  <label for="FiltroCliente" class="control-label">Cliente</label>
                  <div class="input-group">
                    <select id="FiltroCliente" class="form-control filtrar" disabled>
                      <option value="">-- Selecciona cliente --</option>
                    </select>
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text">
                        <i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroCliente')"></i>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Desde -->
              <div class="col-md-4">
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
              <div class="col-md-4">
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
          </div>
        </div>
      </div>
      <!-- /Filtros -->

      <!-- Tabla -->
      <div class="row">
        <div class="col-12">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title mb-0">Ventas a Crédito</h4>
              <button id="BtnExcel" class="btn btn-success btn-sm" title="Descargar Excel (XLS)">
                <i class="mdi mdi-file-excel"></i> Exportar Excel (XLS)
              </button>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-striped mb-0">
                <thead>
                  <tr>
                    <th class="text-center" style="width:120px;">Folio</th>
                    <th class="text-center" style="width:150px;">Estatus Crédito</th>
                    <th class="text-center" style="width:100px;">Cantidad</th>
                    <th class="text-center" style="width:140px;">Código</th>
                    <th class="text-center" style="width:90px;">Unidad</th>
                    <th>Descripción</th>
                    <th class="text-center" style="width:130px;">Precio</th>
                    <th class="text-center" style="width:130px;">Total</th>
                    <th class="text-center" style="width:130px;">Fecha venta</th>
                  </tr>
                </thead>
                <tbody id="tbodyItems">
                  <tr><td colspan="9" class="text-center text-muted">Sin registros</td></tr>
                </tbody>
                <tfoot>
                  <tr>
                    <!-- Suma la columna de Total; la columna de Fecha queda vacía -->
                    <td colspan="7" class="text-right">TOTAL</td>
                    <td class="text-center" id="tImporte">—</td>
                    <td></td>
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
      <!-- /Tabla -->

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
    const URL_REP      = '<?= BASE_URL ?>/controllers/ReportesController.php';
    const URL_CLIENTES = '<?= BASE_URL ?>/controllers/ClientesController.php';

    let paginaActual = 1;
    const limitePorPagina = 20;

    const esc = s => String(s==null?'':s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
    const mxn = n => Number(n||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
    const num = n => Number(n||0);

    const fmtFecha = f => {
      if (!f) return '';
      return String(f).split(' ')[0]; // 2025-11-01 10:20:30 -> 2025-11-01
    };

    // Fecha por defecto: hoy en ambos (desde/hasta)
    (function setHoy(){
      const h=new Date(), y=h.getFullYear(), m=String(h.getMonth()+1).padStart(2,'0'), d=String(h.getDate()).padStart(2,'0');
      const hoy = `${y}-${m}-${d}`;
      $('#FiltroDesde').val(hoy);
      $('#FiltroHasta').val(hoy);
    })();

    // Cargar clientes
    (function cargarClientes(){
      const $sel=$('#FiltroCliente').prop('disabled', true).html('<option value="">-- Selecciona cliente --</option>');
      $.getJSON(URL_CLIENTES, {accion:'listar-min', limite:500})
        .done(r=>{
          (r?.data||[]).forEach(c=>{
            const id=c.id_cliente??c.id, nom=c.nombre??c.razon_social??c.nombre_comercial;
            if(id&&nom) $sel.append(`<option value="${id}">${esc(nom)}</option>`);
          });
        })
        .always(()=> $sel.prop('disabled', false));
    })();

    // Exportar (mismos filtros) -> ahora XLS
    $('#BtnExcel').on('click', function(){
      const idc = $('#FiltroCliente').val();
      const d   = $('#FiltroDesde').val();
      const h   = $('#FiltroHasta').val();
      const q   = ($('#FiltroQ').length ? $('#FiltroQ').val() : '') || '';
      if(!idc || !d || !h){ toastr.info('Selecciona cliente y rango de fechas.'); return; }
      const qs = $.param({ accion:'creditos-cliente-xls', id_cliente:idc, desde:d, hasta:h, q:q });
      window.location = `${URL_REP}?${qs}`;
    });

    // Patrón filtros
    $(".filtrar")
      .on('change', function(){
        const $el=$(this);
        if(($el.is(':checkbox') && $el.is(':checked')) || ($el.val() && $el.val().length>0))
          $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
        else
          $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
        setTimeout(autoCargar, 150);
      })
      .on('keypress', function(e){ if(e.charCode==13) autoCargar(); })
      .on('keyup', function(){
        if($(this).val().length>0) $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
        else $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
      });

    function autoCargar(){
      const idc=$('#FiltroCliente').val(), d=$('#FiltroDesde').val(), h=$('#FiltroHasta').val();
      if(idc && d && h) cargarItems(1);
    }

    // ====== listado + total del rango (solo suma de la columna Total) ======
    function cargarItems(pagina){
      paginaActual = pagina;
      const idc=$('#FiltroCliente').val(), d=$('#FiltroDesde').val(), h=$('#FiltroHasta').val();
      const q = ($('#FiltroQ').length ? $('#FiltroQ').val() : '') || '';
      if(!idc || !d || !h) return;

      $.post(URL_REP, {
        accion:'creditos-cliente-listar',
        pagina:pagina,
        limite:limitePorPagina,
        id_cliente:idc,
        desde:d, hasta:h,
        q:q
      }, null, 'json')
      .done(function(resp){
        const rows=resp?.data||[], total=parseInt(resp?.total||0,10);
        renderTabla(rows);
        renderInfo(total);
        paginar(total);
        cargarTotalRango(idc, d, h, q);
      })
      .fail(()=> toastr.error('Error al cargar el reporte.'));
    }

    function renderTabla(rows){
      const $tb = $('#tbodyItems');
      if(!rows.length){
        $tb.html('<tr><td colspan="9" class="text-center text-muted">Sin registros</td></tr>');
        $('#tImporte').text('—');
        return;
      }
      let html='';
      rows.forEach(r=>{
        const fecha = fmtFecha(r.fecha_venta || r.fecha || '');
        html += `
          <tr>
            <td class="text-center"><b>${esc(r.folio || '')}</b></td>
            <td class="text-center">${esc(r.estatus_credito || '')}</td>
            <td class="text-center">${num(r.cantidad).toLocaleString('es-MX')}</td>
            <td class="text-center">${esc(r.codigo || '')}</td>
            <td class="text-center">${esc(r.unidad || '')}</td>
            <td>${esc(r.descripcion || '')}</td>
            <td class="text-center">${mxn(r.precio_unitario ?? r.precio ?? 0)}</td>
            <td class="text-center"><b>${mxn(r.importe ?? r.total ?? 0)}</b></td>
            <td class="text-center">${esc(fecha)}</td>
          </tr>
        `;
      });
      $tb.html(html);
    }

    // Solo suma del importe total (de TODO el rango, no solo de la página)
    function cargarTotalRango(idCliente, desde, hasta, q){
      $.post(URL_REP, {
        accion:'creditos-cliente-totales',
        id_cliente:idCliente,
        desde:desde, hasta:hasta,
        q:q
      }, null, 'json')
      .done(function(r){
        const totalImporte = Number(r?.totales?.total || 0);
        $('#tImporte').text(mxn(totalImporte));
      })
      .fail(function(){
        $('#tImporte').text('—');
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

    window.clearField = function(id){
      const el = document.getElementById(id);
      if (!el) return;
      if(el.type==='checkbox'){ el.checked=false; } else { el.value=''; }
      el.dispatchEvent(new Event('change'));
    };
  });
  </script>
</body>
</html>
