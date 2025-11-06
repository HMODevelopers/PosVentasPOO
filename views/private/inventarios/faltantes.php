<?php
$titulo = "Inventarios";
$modulo = "Faltantes Inventario";
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
  <title>Faltantes Inventario | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <style>
    .clean-filter .input-group-text{ cursor:pointer; }
    .table td, .table th{ vertical-align: middle; }
    .text-right{ text-align: right!important; }
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

      <!-- Filtros -->
      <div class="card-header" style="border-color:darkgray; border-style:dotted;">
        <h5>Filtros</h5>
        <div class="row">
          <div class="col-lg-12">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="Modo" class="control-label">Modo</label>
                  <select id="Modo" class="form-control filtrar">
                    <option value="rango">Faltantes por fecha</option>
                    <option value="mar-sab">Faltantes de Martes a Sábado</option>
                    <option value="lunes">Faltantes Lunes</option>
                    <option value="negativos">Productos con inventario negativo</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="Desde" class="control-label">Desde</label>
                  <div class="input-group">
                    <input type="date" id="Desde" class="form-control filtrar" placeholder="dd/mm/aaaa">
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" title="Limpiar" onclick="clearField('Desde')"></i></span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="Hasta" class="control-label">Hasta</label>
                  <div class="input-group">
                    <input type="date" id="Hasta" class="form-control filtrar" placeholder="dd/mm/aaaa">
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" title="Limpiar" onclick="clearField('Hasta')"></i></span>
                    </div>
                  </div>
                </div>
              </div>
            </div><!--/row-->
          </div>
        </div>
      </div>
      <!-- /Filtros -->

      <!-- Tabla -->
      <div class="row">
        <div class="col-12">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title">Listado de Faltantes</h4>
              <button id="btnExportar"  type="button"
                          class="btn btn-success btn-sm waves-effect waves-light">
                  <i class="mdi mdi-file-excel"></i> Exportar Excel
                </button>
            </div>

            <div class="table-responsive">
              <table id="tablaFaltantes" class="table table-bordered table-hover table-striped">
                <thead>
                  <tr>
                    <th>CÓDIGO</th>
                    <th>UNIDAD</th>
                    <th>DESCRIPCIÓN</th>
                    <th class="text-right">VENDIDO</th>
                    <th class="text-center">ÚLTIMA VENTA</th>
                    <th>COMPRÓ (si crédito)</th> <!-- NUEVO -->
                    <th>PROVEEDOR</th>
                    <th class="text-right">INVENTARIO</th>
                    <th class="text-right">FALTANTE vs VENTAS</th>
                    <th class="text-right">FALTANTE vs MÍNIMO</th>
                  </tr>
                </thead>
                <tbody id="tbodyFaltantes"></tbody>
              </table>
            </div>

            <div class="row align-items-center justify-content-between mt-2">
              <div class="col-md-6"><div id="infoFalt" class="dataTables_info"></div></div>
              <div class="col-md-6 d-flex justify-content-end">
                <nav aria-label="Page navigation"><ul id="pagination" class="pagination justify-content-end mb-0"></ul></nav>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- /Tabla -->
    </div>
  </div>

  <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
  <div class="rightbar-overlay"></div>

  <script>const BASE_URL='<?= BASE_URL ?>';</script>
  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    $(function(){
      let paginaActual = 1;
      const limitePorPagina = 10;

      toggleFechas();
      cargarFaltantes(1);

      function clearField(id){
        const $el = $('#'+id);
        $el.val('');
        $el.closest('.input-group').find('.clean-filter').hide();
        $el.trigger('change');
      }
      window.clearField = clearField;

      const fmt2 = v => Number(v||0).toFixed(2);
      const fmtDT = (iso)=>{
        if(!iso) return '—';
        const d = new Date(String(iso).replace(' ','T'));
        if(Number.isNaN(d.getTime())) return iso;
        return d.toLocaleString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true});
      };

      function toggleFechas(){
        const modo = $('#Modo').val();
        const usarFechas = (modo === 'rango');
        $('#Desde,#Hasta').prop('disabled', !usarFechas);
        $('#Desde,#Hasta').each(function(){
          if (!usarFechas) $(this).val('');
          const $wrap = $(this).closest('.input-group').find('.clean-filter');
          $wrap.toggle(usarFechas && !!$(this).val());
        });
      }

      function cargarFaltantes(pagina){
        const modo = $('#Modo').val() || 'rango';
        const usarFechas = (modo === 'rango');

        const filtros = {
          accion: 'listar',
          pagina,
          limite: limitePorPagina,
          Modo:  modo,
          Desde: usarFechas ? ($('#Desde').val() || '') : '',
          Hasta: usarFechas ? ($('#Hasta').val() || '') : ''
        };

        if (usarFechas && (!filtros.Desde || !filtros.Hasta)){
          $('#tbodyFaltantes').html('<tr><td colspan="10" class="text-center text-muted">Selecciona un rango de fechas.</td></tr>');
          $('#infoFalt').text('');
          $('#pagination').empty().closest('nav').hide();
          return;
        }

        $('#LoadingImage').show();
        $.ajax({
          url: `${BASE_URL}/controllers/FaltantesController.php`,
          method: 'POST', dataType: 'json', data: filtros
        })
        .done(function(resp){
          const rows  = resp?.data || [];
          const total = parseInt(resp?.total || 0, 10);

          let tbody = '';
          if (!rows.length){
            tbody = '<tr><td colspan="10" class="text-center">Sin resultados</td></tr>';
          } else {
            rows.forEach(r=>{
              tbody += `
                <tr>
                  <td><b>${r.codigo || ''}</b></td>
                  <td>${r.unidad || ''}</td>
                  <td>${r.descripcion || ''}</td>
                  <td class="text-right">${fmt2(r.cantidad)}</td>
                  <td class="text-center">${fmtDT(r.fecha_venta)}</td>
                  <td>${r.compro_credito || '—'}</td> <!-- NUEVO -->
                  <td>${r.proveedor || '—'}</td>
                  <td class="text-right">${fmt2(r.inventario)}</td>
                  <td class="text-right">${fmt2(r.faltante_sobre_ventas)}</td>
                  <td class="text-right">${fmt2(r.faltante_vs_minimo)}</td>
                </tr>`;
            });
          }
          $('#tbodyFaltantes').html(tbody);

          const desde = (pagina - 1) * limitePorPagina + 1;
          const hasta = Math.min(pagina * limitePorPagina, total);
          $('#infoFalt').text(`Mostrando ${total === 0 ? 0 : desde} a ${hasta} de ${total} registros`);

          configurarPaginacion(pagina, total, limitePorPagina);
        })
        .fail(()=> toastr.error('Error al cargar la información.'))
        .always(()=> $('#LoadingImage').hide());
      }
      window.cargarFaltantes = cargarFaltantes;

      function configurarPaginacion(currentPage, totalItems, itemsPerPage=10){
        const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
        const $ul = $('#pagination');
        const maxVisiblePages = 5;
        $ul.empty();

        if (totalPages <= 1){ $ul.closest('nav').hide(); return; }
        else { $ul.closest('nav').show(); }

        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages/2));
        let endPage   = Math.min(totalPages, startPage + maxVisiblePages - 1);
        if (endPage - startPage + 1 < maxVisiblePages) startPage = Math.max(1, endPage - maxVisiblePages + 1);

        if (currentPage > 1){
          $ul.append(`<li class="page-item"><a class="page-link" data-page="1">Primera</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);
        }
        for (let i=startPage; i<=endPage; i++){
          $ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" data-page="${i}">${i}</a></li>`);
        }
        if (currentPage < totalPages){
          $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" data-page="${totalPages}">Última</a></li>`);
        }

        $ul.off('click','a.page-link').on('click','a.page-link', function(e){
          e.preventDefault();
          const page = Number($(this).data('page'));
          if (Number.isFinite(page)) { paginaActual = page; cargarFaltantes(paginaActual); }
        });
      }

      $("#Modo").on('change', function(){
        toggleFechas();
        setTimeout(()=>cargarFaltantes(1), 150);
      });

      $("#Desde, #Hasta")
        .on('change', function(){
          const $wrap = $(this).closest('.input-group').find('.clean-filter');
          $wrap.toggle(!!$(this).val());
          setTimeout(()=>cargarFaltantes(1), 150);
        })
        .on('keyup', function(){
          const $wrap = $(this).closest('.input-group').find('.clean-filter');
          $wrap.toggle(!!$(this).val());
        })
        .on('keypress', function(e){
          if (e.charCode === 13) cargarFaltantes(1);
        });

      // Exportar Excel
      $('#btnExportar').on('click', function(){
        const modo = $('#Modo').val() || 'rango';
        const usarFechas = (modo === 'rango');
        const desde = $('#Desde').val() || '';
        const hasta = $('#Hasta').val() || '';

        if (usarFechas && (!desde || !hasta)) {
          toastr.warning('Selecciona el rango de fechas para exportar.');
          return;
        }

        const params = new URLSearchParams({
          accion: 'exportar-excel',
          Modo: modo,
          Desde: usarFechas ? desde : '',
          Hasta: usarFechas ? hasta : ''
        });

        window.location = `${BASE_URL}/controllers/FaltantesController.php?${params.toString()}`;
      });
    });
  </script>
</body>
</html>
