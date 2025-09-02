<?php
$titulo = "Inventarios";
$modulo = "Movimientos Inventario";
$subtitulo = ""; // puedes dejarlo vacío si no se necesita
session_start();

// Incluye la configuración con BASE_URL
require_once __DIR__ . '/../../../includes/config.php';

// Verifica si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
  header('Location: ' . BASE_URL . '/views/public/index.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Movimientos Inventario | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
  <meta content="Coderthemes" name="author" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

  <!-- plugin css -->
  <link href="<?= BASE_URL ?>/assets/libs/jquery-vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet" type="text/css" />

  <!-- App css -->
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <style>
    .clean-filter .input-group-text { cursor:pointer; }
    .badge-pill { border-radius: 50rem; }
    .table-responsive { overflow-y: visible !important; }
    .table-responsive .dropdown-menu { z-index: 2000; }
  </style>
</head>

<body>
  <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

  <div class="wrapper">
    <!-- Loader -->
    <div class="wrapper-loader fade" id="LoadingImage" style="display: none;">
      <div class="loader">
        <div class="loader__figure"></div>
        <p class="loader__label">Cargando...</p>
      </div>
    </div>
    <!-- /Loader -->

    <div class="container-fluid">
      <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

      <!-- =================== Filtros =================== -->
      <div class="card-header" style="border-color:darkgray; border-style:dotted;">
        <h5>Filtros</h5>
        <div class="row">
          <div class="col-lg-12">
            <div class="row">

              <!-- Código -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="Codigo" class="control-label">Código</label>
                  <div class="input-group">
                    <input type="text" id="Codigo" class="form-control filtrar" placeholder="SKU">
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Codigo')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Descripción del producto -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="Descripcion" class="control-label">Descripción</label>
                  <div class="input-group">
                    <input type="text" id="Descripcion" class="form-control filtrar" placeholder="Filtro de aceite, etc.">
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Descripcion')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Usuario -->
              <div class="col-md-2">
                <div class="form-group">
                  <label for="Usuario" class="control-label">Usuario</label>
                  <div class="input-group">
                    <select id="Usuario" class="form-control filtrar" disabled>
                      <option value="">-- Todos --</option>
                    </select>
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Usuario')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Desde -->
              <div class="col-md-2">
                <div class="form-group">
                  <label for="Desde" class="control-label">Desde</label>
                  <input type="date" id="Desde" class="form-control filtrar">
                </div>
              </div>

              <!-- Hasta -->
              <div class="col-md-2">
                <div class="form-group">
                  <label for="Hasta" class="control-label">Hasta</label>
                  <input type="date" id="Hasta" class="form-control filtrar">
                </div>
              </div>

            </div><!--/row-->
          </div>
        </div>
      </div>
      <!-- =================== /Filtros =================== -->

      <!-- =================== Tabla Movimientos =================== -->
      <div class="row">
        <div class="col-12">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title">Listado de Movimientos</h4>
            </div>

            <div class="table-responsive">
              <table id="tablaMovimientos" class="table table-bordered table-hover table-striped">
                <thead>
                <tr>
                  <th class="text-center" style="width:170px;">Fecha</th>
                  <th class="text-center" style="width:120px;">Tipo</th>
                  <th class="text-center" style="width:120px;">Código</th>
                  <th>Producto</th>
                  <th class="text-center" style="width:110px;">Cantidad</th>
                  <th class="text-center" style="width:150px;">Sucursal</th>
                  <th class="text-center" style="width:150px;">Usuario</th>
                  <th class="text-center" style="width:140px;">Referencia</th>
                  <th>Motivo</th>
                  <th class="text-center" style="width:90px;">Acciones</th>
                </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <div class="row align-items-center justify-content-between mt-2">
              <div class="col-md-6">
                <div id="infoMovs" class="dataTables_info" role="status" aria-live="polite"></div>
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
      <!-- =================== /Tabla Movimientos =================== -->

      <!-- =================== Modal Detalle =================== -->
      <div class="modal fade" id="modalDetalleMovimiento" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="mdi mdi-eye mr-1"></i> Detalle de Movimiento</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">×</span>
              </button>
            </div>
            <div class="modal-body">
              <div id="det-loader" class="my-3 text-center">
                <div class="spinner-border" role="status"></div>
                <p class="mt-2">Cargando…</p>
              </div>
              <div id="det-error" class="alert alert-danger" style="display:none;"></div>

              <div id="det-contenido" style="display:none;">
                <div class="row">
                  <div class="col-md-4 mb-2">
                    <small class="text-primary font-weight-bold">Fecha</small>
                    <div id="det-fecha" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-4 mb-2">
                    <small class="text-primary font-weight-bold">Tipo</small>
                    <div id="det-tipo" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-4 mb-2">
                    <small class="text-primary font-weight-bold">Cantidad</small>
                    <div id="det-cantidad" class="h5 mb-0">—</div>
                  </div>

                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Código</small>
                    <div id="det-codigo" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Producto</small>
                    <div id="det-producto" class="h5 mb-0">—</div>
                  </div>

                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Sucursal</small>
                    <div id="det-sucursal" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Usuario</small>
                    <div id="det-usuario" class="h5 mb-0">—</div>
                  </div>

                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Referencia</small>
                    <div id="det-referencia" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Motivo</small>
                    <div id="det-motivo" class="h5 mb-0">—</div>
                  </div>

                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Estatus</small>
                    <div id="det-estatus" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Creado</small>
                    <div id="det-creado" class="h5 mb-0">—</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-light" data-dismiss="modal">Cerrar</button>
            </div>
          </div>
        </div>
      </div>
      <!-- =================== /Modal Detalle =================== -->

    </div><!--/container-->
  </div><!--/wrapper-->

  <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>

  <div class="rightbar-overlay"></div>
  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    $(function(){
      let paginaActual = 1;
      const limitePorPagina = 10;

      cargarUsuariosSelectFiltro();    // desde UsuariosController (listar-min)
      cargarMovimientos(paginaActual);

      const fmtInt = v => Number(v||0).toLocaleString('es-MX');
      function ymdHisToEs(dt){
        if(!dt) return '—';
        const d = new Date(dt.replace(' ', 'T'));
        if(Number.isNaN(d.getTime())) return dt;
        return d.toLocaleString('es-MX');
      }

      function badgeTipo(tipo){
        let cls = 'secondary';
        if (tipo === 'Entrada' || tipo === 'Devolucion Compra') cls = 'success';
        else if (tipo === 'Salida' || tipo === 'Devolucion Venta') cls = 'danger';
        else if (tipo === 'Ajuste') cls = 'warning';
        return `<span class="badge badge-${cls} badge-pill">${tipo||'—'}</span>`;
      }

      // =========== Cargar usuarios para el filtro ===========
      function cargarUsuariosSelectFiltro({selectId='Usuario'} = {}){
        const $sel = $('#'+selectId);
        if (!$sel.length) return;
        $sel.prop('disabled', true).html('<option value="">-- Todos --</option>');
        $.ajax({
          url: '<?= BASE_URL ?>/controllers/UsuariosController.php',
          method: 'GET', dataType: 'json',
          data: { accion:'listar-min', limite: 300 }
        })
        .done(resp => {
          const arr = resp?.data || [];
          let html = '<option value="">-- Todos --</option>';
          arr.forEach(u => {
            const id = u.id_usuario || u.id;
            const nom = u.nombre || (u.usuario ? u.usuario : '');
            if (id && nom) html += `<option value="${id}">${nom}</option>`;
          });
          $sel.html(html).prop('disabled', false);
        })
        .fail(()=> $sel.prop('disabled', false));
      }

      // =========== Cargar movimientos (solo filtros solicitados) ===========
      function cargarMovimientos(pagina){
        const filtros = {
          accion: 'listar',
          pagina: pagina,
          limite: limitePorPagina,
          q: $('#Buscar').val(),
          codigo: $('#Codigo').val(),
          descripcion: $('#Descripcion').val(),
          id_usuario:  $('#Usuario').val() || '',
          desde: $('#Desde').val() || '',
          hasta: $('#Hasta').val() || ''
        };

        $.ajax({
          url: '<?= BASE_URL ?>/controllers/InventarioMovimientosController.php',
          method: 'POST', dataType: 'json',
          data: filtros
        })
        .done(function(resp){
          const movs  = resp?.data || [];
          const total = parseInt(resp?.total || 0, 10);
          renderizarTabla(movs);

          let desde = (pagina - 1) * limitePorPagina + 1;
          let hasta = Math.min(pagina * limitePorPagina, total);
          $('#infoMovs').text(`Mostrando ${total === 0 ? 0 : desde} a ${hasta} de ${total} movimientos`);

          configurarPaginacion(pagina, total, limitePorPagina);
        })
        .fail(function(){
          toastr.error('Error al cargar los movimientos.');
        });
      }
      window.cargarMovimientos = cargarMovimientos;

      function renderizarTabla(rows){
        let tbody = '';
        if (!rows.length){
          tbody = '<tr><td colspan="10" class="text-center">No hay movimientos</td></tr>';
        } else {
          rows.forEach(v => {
            const fecha = ymdHisToEs(v.fecha || v.fecha_creacion);
            const tipo  = v.tipo || '—';
            const cod   = v.codigo || ('#'+(v.id_producto||''));
            const prod  = v.descripcion || v.producto || '—';
            const cant  = (v.signo ? v.signo * Number(v.cantidad||0) : Number(v.cantidad||0));
            const cantFmt = (cant>0? '+' : '') + fmtInt(cant);
            const suc   = v.sucursal || (v.id_sucursal ? ('#'+v.id_sucursal) : '—');
            const usr   = v.usuario || (v.id_usuario  ? ('#'+v.id_usuario ) : '—');
            const ref   = v.referencia || '—';
            const mot   = v.motivo || '—';

            tbody += `
              <tr>
                <td class="text-center">${fecha}</td>
                <td class="text-center">${badgeTipo(tipo)}</td>
                <td class="text-center"><b>${cod}</b></td>
                <td>${prod}</td>
                <td class="text-center"><b>${cantFmt}</b></td>
                <td class="text-center">${suc}</td>
                <td class="text-center">${usr}</td>
                <td class="text-center">${ref}</td>
                <td>${mot}</td>
                <td class="text-center">
                  <div class="btn-group dropdown">
                    <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                      <i class="mdi mdi-dots-horizontal"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                      <a class="dropdown-item accion-ver-detalle" href="#" data-id="${v.id_movimiento}">
                        <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver Detalle
                      </a>
                    </div>
                  </div>
                </td>
              </tr>`;
          });
        }
        $('#tablaMovimientos tbody').html(tbody);
      }

      function configurarPaginacion(currentPage, totalItems, itemsPerPage=10){
        const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
        const $ul = $('#pagination');
        const maxVisiblePages = 5;
        $ul.empty();
        if (totalPages <= 1){ $ul.closest('nav').hide(); return; } else { $ul.closest('nav').show(); }

        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages/2));
        let endPage   = Math.min(totalPages, startPage + maxVisiblePages - 1);
        if (endPage - startPage + 1 < maxVisiblePages) startPage = Math.max(1, endPage - maxVisiblePages + 1);

        if (currentPage > 1){
          $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="1">Primera</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);
        }
        for (let i=startPage; i<=endPage; i++){
          const active = (i===currentPage)? 'active' : '';
          $ul.append(`<li class="page-item ${active}"><a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a></li>`);
        }
        if (currentPage < totalPages){
          $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${totalPages}">Última</a></li>`);
        }

        $ul.off('click','a.page-link').on('click','a.page-link', function(e){
          e.preventDefault();
          const page = Number($(this).data('page'));
          if (Number.isFinite(page)) { paginaActual = page; cargarMovimientos(paginaActual); }
        });
      }

      // Filtros (patrón estándar)
      $(".filtrar")
        .change(function(){
          const $el = $(this);
          if(($el.is(':checkbox') && $el.is(':checked')) || ($el.val() && $el.val().length>0))
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
          $el.blur();
          setTimeout(()=> cargarMovimientos(1), 200);
        })
        .keypress(function(e){ if (e.charCode == 13) cargarMovimientos(1); })
        .keyup(function(){
          if ($(this).val().length > 0) $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
        });

      $(".clean-filter").click(function(){
        const $el = $(this).closest('.input-group, .form-group').find('.filtrar');
        if ($el.is(':checkbox')){ $el.prop('checked', false).trigger('change'); }
        else { $el.val('').trigger('change'); if ($el.hasClass('select2')) $el.select2('val', 0); }
        cargarMovimientos(1);
      });

      // Detalle
      $(document).on('click', 'a.accion-ver-detalle', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;

        $('#det-error').hide().text('');
        $('#det-contenido').hide();
        $('#det-loader').show();
        $('#modalDetalleMovimiento').modal('show');

        $.ajax({
          url: '<?= BASE_URL ?>/controllers/InventarioMovimientosController.php',
          method: 'GET', dataType: 'json',
          data: { accion: 'detalle', id_movimiento: id }
        })
        .done(function(resp){
          const v = resp?.data || null;
          if (!v){
            $('#det-loader').hide();
            $('#det-error').show().text('No se encontró el movimiento.');
            return;
          }

          $('#det-fecha').text(ymdHisToEs(v.fecha || v.fecha_creacion));
          $('#det-tipo').html(badgeTipo(v.tipo));
          const cant = (v.signo ? v.signo * Number(v.cantidad||0) : Number(v.cantidad||0));
          $('#det-cantidad').text((cant>0? '+' : '') + fmtInt(cant));
          $('#det-codigo').text(v.codigo || ('#'+(v.id_producto||'')));
          $('#det-producto').text(v.descripcion || v.producto || '—');
          $('#det-sucursal').text(v.sucursal || (v.id_sucursal? '#'+v.id_sucursal : '—'));
          $('#det-usuario').text(v.usuario || (v.id_usuario ? '#'+v.id_usuario : '—'));
          $('#det-referencia').text(v.referencia || '—');
          $('#det-motivo').text(v.motivo || '—');
          $('#det-estatus').text(String(v.activo)==='1' ? 'Activo' : 'Inactivo');
          $('#det-creado').text(ymdHisToEs(v.fecha_creacion));

          $('#det-loader').hide();
          $('#det-contenido').show();
        })
        .fail(function(){
          $('#det-loader').hide();
          $('#det-error').show().text('Error al cargar el detalle.');
        });
      });

    }); // ready

    // util: limpiar filtros
    function clearField(id){
      const el = document.getElementById(id);
      if (!el) return;
      if (el.type === 'checkbox'){ el.checked=false; } else { el.value=''; }
      el.dispatchEvent(new Event('change'));
    }
  </script>
</body>
</html>
