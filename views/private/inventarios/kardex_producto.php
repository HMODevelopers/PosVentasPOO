<?php
$titulo = "Inventarios";
$modulo = "Movimientos por producto (Kárdex)";
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
  <title>Movimientos por producto (Kárdex) | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
  <meta content="Coderthemes" name="author" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

  <!-- App css -->
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <style>
    .clean-filter .input-group-text { cursor:pointer; }
    .table-responsive { overflow-y: visible !important; }
    .kardex-wrapper .card-box .table-responsive{ overflow-x: auto; overflow-y: visible; }
    .ac-sug { position:absolute; left:0; right:0; top:100%; z-index:1050; background:#fff; border:1px solid #e0e0e0; max-height:220px; overflow:auto; }
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

    <div class="container-fluid kardex-wrapper">
      <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

      <!-- =================== Filtros =================== -->
      <div class="card-header" style="border-color:darkgray; border-style:dotted;">
        <h5>Filtros</h5>
        <div class="row">
          <div class="col-lg-12">
            <div class="row">

              <!-- Producto -->
              <div class="col-md-6">
                <div class="form-group">
                  <label for="ProductoBuscar" class="control-label">Producto</label>
                  <div class="input-group" style="position:relative;">
                    <input type="hidden" id="ProductoId" class="filtrar">
                    <input type="text" id="ProductoBuscar" class="form-control" placeholder="Buscar por código o nombre…" autocomplete="off">
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearProducto()"></i></span>
                    </div>
                    <div id="ProductoSug" class="ac-sug" style="display:none;"></div>
                  </div>
                </div>
              </div>

              <!-- Desde -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="Desde" class="control-label">Desde</label>
                  <div class="input-group">
                    <input type="date" id="Desde" class="form-control filtrar">
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Desde')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Hasta -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="Hasta" class="control-label">Hasta</label>
                  <div class="input-group">
                    <input type="date" id="Hasta" class="form-control filtrar">
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Hasta')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

            </div><!--/row-->
          </div>
        </div>
      </div>
      <!-- =================== /Filtros =================== -->

      <!-- =================== Tabla Compras =================== -->
      <div class="row mt-3">
        
      </div>
      <!-- =================== /Tabla Compras =================== -->

      <!-- =================== Tabla Ventas =================== -->
      <div class="row">
        <div class="col-6">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title">Ventas</h4>
            </div>

            <div class="table-responsive">
              <table id="tablaVentasProducto" class="table table-bordered table-hover table-striped">
                <thead>
                  <tr>
                    <th>Ticket/Folio</th>
                    <th>Fecha</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Precio</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-6">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title">Compras</h4>
            </div>

            <div class="table-responsive">
              <table id="tablaComprasProducto" class="table table-bordered table-hover table-striped">
                <thead>
                  <tr>
                    <th>Folio compra</th>
                    <th>Fecha</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Costo</th>
                    <th>Proveedor</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <!-- =================== /Tabla Ventas =================== -->

    </div> <!-- /container-fluid -->
  </div> <!-- /wrapper -->

  <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
  <div class="rightbar-overlay"></div>

  <script>const BASE_URL='<?= BASE_URL ?>';</script>
  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    $(function(){
      const URL_KARDEX = '<?= BASE_URL ?>/controllers/KardexProductoController.php';
      const URL_PROD   = '<?= BASE_URL ?>/controllers/ProductosController.php';

      const mxn = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
      const num = v => Number(v||0);
      const fechaMx = dt => {
        try{
          const d=new Date(String(dt).replace(' ','T'));
          return d.toLocaleString('es-MX',{
            day:'2-digit',month:'2-digit',year:'numeric',
            hour:'2-digit',minute:'2-digit',hour12:true
          });
        } catch { return dt||'—'; }
      };

      function clearField(id){ try { $('#'+id).val('').trigger('change'); } catch(e){} }
      window.clearField = clearField;

      window.clearProducto = function(){
        $('#ProductoBuscar').val('');
        $('#ProductoId').val('').trigger('change');
        $('#ProductoSeleccionado').text('—');
        $('#ProductoSug').hide().empty();
        $('.input-group-append.clean-filter').hide();
        renderCompras([]);
        renderVentas([]);
      };

      function fechasValidas(){
        const d = $('#Desde').val();
        const h = $('#Hasta').val();
        $('#Desde, #Hasta').removeClass('is-invalid');
        if ((d && h) && d > h){
          $('#Desde, #Hasta').addClass('is-invalid');
          toastr.warning('La fecha "Desde" no puede ser mayor que "Hasta".');
          return false;
        }
        return true;
      }

      function cargarKardex(){
        const idProducto = Number($('#ProductoId').val() || 0);
        if (!idProducto){
          renderCompras([]);
          renderVentas([]);
          return;
        }
        if (!fechasValidas()) return;

        $('#LoadingImage').show();
        $.post(URL_KARDEX, {
          accion: 'listar',
          id_producto: idProducto,
          desde: $('#Desde').val() || '',
          hasta: $('#Hasta').val() || ''
        }, null, 'json')
        .done(function(resp){
          const compras = resp?.compras || [];
          const ventas = resp?.ventas || [];
          renderCompras(compras);
          renderVentas(ventas);
        })
        .fail(()=> toastr.error('No fue posible cargar el kárdex.'))
        .always(()=> $('#LoadingImage').hide());
      }

      function renderCompras(rows){
        const $tb = $('#tablaComprasProducto tbody');
        if (!rows.length){
          $tb.html('<tr><td colspan="5" class="text-center text-muted">Sin compras para el filtro seleccionado.</td></tr>');
          return;
        }
        let html='';
        rows.forEach(r=>{
          const folio = r.folio || ('COMP-' + (r.id_compra || ''));
          html += `
            <tr>
              <td class="text-center"><b>${folio}</b></td>
              <td class="text-center">${fechaMx(r.fecha)}</td>
              <td class="text-end">${num(r.cantidad).toLocaleString('es-MX')}</td>
              <td class="text-end">${mxn(r.precio_proveedor)}</td>
              <td>${r.proveedor || '—'}</td>
            </tr>
          `;
        });
        $tb.html(html);
      }

      function renderVentas(rows){
        const $tb = $('#tablaVentasProducto tbody');
        if (!rows.length){
          $tb.html('<tr><td colspan="4" class="text-center text-muted">Sin ventas para el filtro seleccionado.</td></tr>');
          return;
        }
        let html='';
        rows.forEach(r=>{
          html += `
            <tr>
              <td class="text-center"><b>${r.folio || ('#' + (r.id_venta || ''))}</b></td>
              <td class="text-center">${fechaMx(r.fecha)}</td>
              <td class="text-end">${num(r.cantidad).toLocaleString('es-MX')}</td>
              <td class="text-end">${mxn(r.precio_unitario)}</td>
            </tr>
          `;
        });
        $tb.html(html);
      }

      function buscarProductos(term){
        if (term.length < 2){
          $('#ProductoSug').hide().empty();
          return;
        }
        $.getJSON(URL_PROD, { accion:'buscar-min', q: term, limite: 15 })
          .done(function(resp){
            const items = resp?.data || [];
            if (!items.length){
              $('#ProductoSug').html('<div class="p-2 text-muted">Sin resultados</div>').show();
              return;
            }
            let html = '<div class="list-group list-group-flush">';
            items.forEach(it=>{
              const label = `${it.codigo ? it.codigo + ' - ' : ''}${it.descripcion}`;
              html += `<a href="#" class="list-group-item list-group-item-action kardex-pick" data-id="${it.id_producto}" data-label="${label}">${label}</a>`;
            });
            html += '</div>';
            $('#ProductoSug').html(html).show();
          })
          .fail(()=> $('#ProductoSug').hide().empty());
      }

      let debounceTimer = null;
      $('#ProductoBuscar').on('input', function(){
        const term = ($(this).val() || '').trim();
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(()=> buscarProductos(term), 250);
      });

      $(document).on('click', '.kardex-pick', function(e){
        e.preventDefault();
        const id = Number($(this).data('id'));
        const label = String($(this).data('label') || '').trim();
        $('#ProductoId').val(id).trigger('change');
        $('#ProductoBuscar').val(label);
        $('#ProductoSeleccionado').text(label || '—');
        $('#ProductoSug').hide().empty();
        $('.input-group-append.clean-filter').show();
        cargarKardex();
      });

      $(document).on('click', function(e){
        if (!$(e.target).closest('#ProductoBuscar, #ProductoSug').length) {
          $('#ProductoSug').hide();
        }
      });

      // Filtros (patrón estándar)
      $(".filtrar")
        .change(function(){
          const $el = $(this);
          if(($el.is(':checkbox') && $el.is(':checked')) || ($el.val() && $el.val().length>0))
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
          $el.blur();
          setTimeout(()=> cargarKardex(), 200);
        })
        .keypress(function(e){ if (e.charCode == 13) cargarKardex(); })
        .keyup(function(){
          if ($(this).val().length > 0) $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
        });

      $(".clean-filter").click(function(){
        const $el = $(this).closest('.input-group, .form-group').find('.filtrar');
        if ($el.is(':checkbox')){ $el.prop('checked', false).trigger('change'); }
        else { $el.val('').trigger('change'); if ($el.hasClass('select2')) $el.select2('val', 0); }
        cargarKardex();
      });

      renderCompras([]);
      renderVentas([]);
    });
  </script>
</body>
</html>
