<?php
$titulo = "Sistema";
$modulo = "Bitácora";
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
  <title>Bitácora | REFASOFT-V4</title>
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

  <!-- Toastr -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

  <style>
    .clean-filter .input-group-text { cursor:pointer; }
    .table-responsive { overflow-y: visible !important; }
    .table-responsive .dropdown-menu { z-index: 2000; }
    .badge-pill { border-radius: 50rem; }
    pre.json-view { background:#f8f9fa; border:1px solid #e9ecef; padding:.75rem; border-radius:.25rem; max-height:300px; overflow:auto; }
  </style>
</head>

<body>

  <!-- Navigation Bar-->
  <?php include_once __DIR__ . '/../../../includes/header.php'; ?>
  <!-- End Navigation Bar-->

  <div class="wrapper">

    <!-- Loader (markup; tu lógica vive en loader.js) -->
    <div class="wrapper-loader fade" id="LoadingImage" style="display: none;">
      <div class="loader">
        <div class="loader__figure"></div>
        <p class="loader__label">Cargando...</p>
      </div>
    </div>
    <!-- Fin Loader -->

    <div class="container-fluid">

      <!-- start page title -->
      <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>    
      <!-- end page title --> 

      <!-- =================== Filtros =================== -->
      <div class="card-header" style="border-color:darkgray; border-style:dotted;">
        <h5>Filtros</h5>

        <div class="row">
          <div class="col-lg-12">
            <div class="row">

              <!-- Usuario -->
              <div class="col-md-4">
                <div class="form-group">
                  <label for="FiltroUsuario" class="control-label">Usuario</label>
                  <div class="input-group">
                    <select id="FiltroUsuario" class="form-control filtrar">
                      <option value="">-- Todos --</option>
                    </select>
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroUsuario')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tabla -->
              <div class="col-md-4">
                <div class="form-group">
                  <label for="FiltroTabla" class="control-label">Tabla</label>
                  <div class="input-group">
                    <input type="text" id="FiltroTabla" class="form-control filtrar" placeholder="ventas, proveedores, cajas...">
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroTabla')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Acciones -->
             <div class="col-md-4">
                <div class="form-group">
                    <label for="FiltroAccion" class="control-label">Acción</label>
                    <div class="input-group">
                    <select id="FiltroAccion" class="form-control filtrar">
                        <option value="">-- Todas --</option>
                        <option value="INSERT">INSERT</option>
                        <option value="UPDATE">UPDATE</option>
                        <option value="DELETE">DELETE</option>
                        <option value="LOGIN">LOGIN</option>
                        <option value="LOGOUT">LOGOUT</option>
                        <option value="CANCEL">CANCEL</option>
                        <option value="PRINT">PRINT</option>
                        <option value="ERROR">ERROR</option>
                    </select>
                    <div class="input-group-append clean-filter">
                        <span class="input-group-text">
                        <i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroAccion')"></i>
                        </span>
                    </div>
                    </div>
                </div>
                </div>


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


            </div><!--/row-->
          </div>
        </div>
      </div>
      <!-- =================== /Filtros =================== -->

      <!-- =================== Tabla Bitácora =================== -->
      <div class="row mt-3">
        <div class="col-12">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title">Registros de Bitácora</h4>
            </div>

            <div id="emptyState" class="alert alert-warning d-none mb-2">
              No hay registros que coincidan con los filtros.
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-striped">
                <thead>
                  <tr>
                    <th class="text-center" style="width:170px;">Fecha</th>
                    <th class="text-center" style="width:160px;">Usuario</th>
                    <th class="text-center" style="width:110px;">Acción</th>
                    <th class="text-center" style="width:140px;">Tabla</th>
                    <th class="text-center" style="width:110px;">Registro ID</th>
                    <th>Descripción</th>
                    <th class="text-center" style="width:120px;">IP</th>
                    <th class="text-center" style="width:90px;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="tbodyBitacora"></tbody>
              </table>
            </div>

            <div class="row align-items-center justify-content-between mt-2">
              <div class="col-md-6">
                <div id="infoBitacora" class="dataTables_info" role="status" aria-live="polite"></div>
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

    </div> <!-- end container -->
  </div>
  <!-- end wrapper -->

  <!-- ============= Modal Detalle ============= -->
  <div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalle de bitácora</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="mb-0 text-muted">Fecha</label>
              <div id="det-fecha" class="font-weight-bold">—</div>
            </div>
            <div class="col-md-3">
              <label class="mb-0 text-muted">Usuario</label>
              <div id="det-usuario" class="font-weight-bold">—</div>
            </div>
            <div class="col-md-3">
              <label class="mb-0 text-muted">Acción</label>
              <div id="det-accion" class="font-weight-bold">—</div>
            </div>
            <div class="col-md-3">
              <label class="mb-0 text-muted">Tabla / Registro</label>
              <div id="det-tabla" class="font-weight-bold">—</div>
            </div>

            <div class="col-md-12 mt-2">
              <label class="mb-0 text-muted">Descripción</label>
              <div id="det-descripcion">—</div>
            </div>

            <div class="col-md-6 mt-3">
              <label class="mb-1 text-muted">Valor Anterior</label>
              <pre class="json-view" id="det-valor-anterior">—</pre>
            </div>
            <div class="col-md-6 mt-3">
              <label class="mb-1 text-muted">Valor Nuevo</label>
              <pre class="json-view" id="det-valor-nuevo">—</pre>
            </div>

            <div class="col-md-6 mt-2">
              <label class="mb-0 text-muted">Campo Modificado</label>
              <div id="det-campo" class="font-weight-bold">—</div>
            </div>
            <div class="col-md-6 mt-2">
              <label class="mb-0 text-muted">IP Origen</label>
              <div id="det-ip" class="font-weight-bold">—</div>
            </div>

          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
  <!-- ============= /Modal Detalle ============= -->

  <!-- Footer Start -->
  <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
  <!-- End Footer -->

  <div class="rightbar-overlay"></div>

  <!-- Vendor js -->
  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <!-- App js-->
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    $(function(){
      let paginaActual = 1;
      const limitePorPagina = 10;
      const URL_CTRL = '<?= BASE_URL ?>/controllers/BitacoraController.php';
      const URL_USR  = '<?= BASE_URL ?>/controllers/UsuariosController.php';

      // Inicial
      cargarUsuariosSelect();
      cargarBitacora(paginaActual);

      // ===== Utils =====
      const escapeHtml = (s) => (s==null? '' : String(s)
        .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'","&#039;"));
      function ymdHisToEs(dt){
        if(!dt) return '—';
        const d = new Date((dt||'').replace(' ', 'T'));
        return isNaN(d.getTime()) ? dt : d.toLocaleString('es-MX');
      }
      const badgeAccion = (a) => {
        const map = {
          'INSERT': {cls:'success',  text:'INSERT'},
          'UPDATE': {cls:'warning',  text:'UPDATE'},
          'DELETE': {cls:'danger',   text:'DELETE'},
          'LOGIN' : {cls:'primary',  text:'LOGIN'},
          'LOGOUT': {cls:'secondary',text:'LOGOUT'},
          'CANCEL': {cls:'info',     text:'CANCEL'},
          'PRINT' : {cls:'dark',     text:'PRINT'},
          'ERROR' : {cls:'danger',   text:'ERROR'}
        };
        const x = map[(a||'').toUpperCase()] || {cls:'light', text:(a||'—')};
        return `<span class="badge badge-${x.cls} badge-pill">${x.text}</span>`;
      };

      // ===== Filtros =====
      $(".filtrar")
        .change(function(){
          const $el = $(this);
          if(($el.is(':checkbox') && $el.is(':checked')) || ($el.val() && $el.val().length>0))
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
          $el.blur();
          setTimeout(()=> cargarBitacora(1), 200);
        })
        .keypress(function(e){ if (e.charCode == 13) cargarBitacora(1); })
        .keyup(function(){
          if ($(this).val().length > 0) $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
        });

      $(".clean-filter").click(function(){
        const $el = $(this).closest('.input-group, .form-group').find('.filtrar');
        if ($el.is(':checkbox')){ $el.prop('checked', false).trigger('change'); }
        else { $el.val('').trigger('change'); if ($el.hasClass('select2')) $el.select2('val', 0); }
        cargarBitacora(1);
      });

      // ===== Usuarios (filtro) =====
      function cargarUsuariosSelect(){
        const $sel = $('#FiltroUsuario');
        $sel.prop('disabled', true).html('<option value="">-- Todos --</option>');
        $.ajax({
          url: URL_USR,
          method: 'GET',
          dataType: 'json',
          data: { accion: 'listar-min', limite: 500 }
        })
        .done(function(resp){
          const arr = resp?.data || [];
          let html = '<option value="">-- Todos --</option>';
          arr.forEach(u => {
            const id = u.id_usuario || u.id;
            const nom = u.nombre || (u.usuario ? u.usuario : '');
            if (id && nom) html += `<option value="${id}">${escapeHtml(nom)}</option>`;
          });
          $sel.html(html);
        })
        .always(function(){ $sel.prop('disabled', false); });
      }

      // ===== Cargar bitácora =====
      function cargarBitacora(pagina){
        paginaActual = pagina;

        // Acciones seleccionadas -> arreglo
        const accionesSel = [];
        $('#FiltroTabla').closest('.card-header').find('input[type=checkbox].custom-control-input').each(function(){
          if ($(this).is(':checked')) accionesSel.push($(this).val());
        });

        const filtros = {
          accion: 'listar',
          pagina,
          limite: limitePorPagina,
          q:                $('#FiltroQ').val(),
          id_usuario:       $('#FiltroUsuario').val() || '',
          tabla:            $('#FiltroTabla').val(),
          registro_id:      $('#FiltroRegistro').val() || '',
          campo_modificado: $('#FiltroCampo').val(),
          ip_origen:        $('#FiltroIP').val(),
          desde:            $('#FiltroDesde').val() || '',
          hasta:            $('#FiltroHasta').val() || '',
          // OJO: el controller espera 'accion_b' para no chocar con 'accion'
          accion_b:         $('#FiltroAccion').val() || ''   // <- aquí
        };

        $.ajax({
          url: URL_CTRL,
          method: 'POST',
          dataType: 'json',
          data: filtros
        })
        .done(function(resp){
          const arr = resp?.data || [];
          const total = parseInt(resp?.total || 0, 10);

          renderizarTabla(arr);

          if (total === 0) {
            $('#infoBitacora').text('No hay registros para mostrar');
          } else {
            const desde = (pagina - 1) * limitePorPagina + 1;
            const hasta = Math.min(pagina * limitePorPagina, total);
            $('#infoBitacora').text(`Mostrando ${desde} a ${hasta} de ${total} registros`);
          }

          configurarPaginacion(pagina, total, limitePorPagina);
        })
        .fail(function(){
          toastr.error('Error al cargar la bitácora.');
        });
      }

      function renderizarTabla(rows){
        let tbody = '';
        if (!rows.length){
          $('#emptyState').removeClass('d-none');
          tbody = '<tr><td colspan="8" class="text-center text-muted">— No hay registros —</td></tr>';
        } else {
          $('#emptyState').addClass('d-none');
          rows.forEach(v => {
            const fecha = ymdHisToEs(v.fecha);
            const usr   = v.usuario_nombre || v.usuario_login || (v.id_usuario ? ('#'+v.id_usuario) : '—');
            const acc   = v.accion || '—';
            const tbl   = v.tabla || '—';
            const rid   = v.registro_id || '—';
            const desc  = v.descripcion || '—';
            const ip    = v.ip_origen || '—';

            tbody += `
              <tr>
                <td class="text-center">${fecha}</td>
                <td class="text-center">${escapeHtml(usr)}</td>
                <td class="text-center">${badgeAccion(acc)}</td>
                <td class="text-center">${escapeHtml(tbl)}</td>
                <td class="text-center">${escapeHtml(rid)}</td>
                <td>${escapeHtml(desc)}</td>
                <td class="text-center">${escapeHtml(ip)}</td>
                <td class="text-center">
                  <div class="btn-group dropdown">
                    <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                      <i class="mdi mdi-dots-horizontal"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                      <a class="dropdown-item accion-ver" href="#" data-id="${v.id_bitacora}">
                        <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver detalle
                      </a>
                    </div>
                  </div>
                </td>
              </tr>`;
          });
        }
        $('#tbodyBitacora').html(tbody);
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
          if (Number.isFinite(page)) { paginaActual = page; cargarBitacora(paginaActual); }
        });
      }

      // ===== Detalle =====
      $(document).on('click', 'a.accion-ver', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;

        $.ajax({
          url: URL_CTRL, method: 'GET', dataType: 'json',
          data: { accion: 'detalle', id_bitacora: id }
        })
        .done(function(resp){
          const v = resp?.data || null;
          if (!v){ toastr.error('No se encontró el registro.'); return; }

          $('#det-fecha').text( ymdHisToEs(v.fecha) );
          $('#det-usuario').text( v.usuario_nombre || v.usuario_login || (v.id_usuario ? ('#'+v.id_usuario) : '—') );
          $('#det-accion').html( badgeAccion(v.accion) );
          $('#det-tabla').text( `${v.tabla || '—'} / ${v.registro_id || '—'}` );
          $('#det-descripcion').text( v.descripcion || '—' );
          $('#det-campo').text( v.campo_modificado || '—' );
          $('#det-ip').text( v.ip_origen || '—' );

          function pretty(v){
            if (v==null || v==='') return '—';
            try { return JSON.stringify(JSON.parse(v), null, 2); } catch(e){ return String(v); }
          }
          $('#det-valor-anterior').text( pretty(v.valor_anterior) );
          $('#det-valor-nuevo').text( pretty(v.valor_nuevo) );

          $('#modalDetalle').modal('show');
        })
        .fail(function(){ toastr.error('Error al cargar el detalle.'); });
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
