<?php
$titulo = "Sistemas";
$modulo = "Emisores CFDI";
$subtitulo = "";
require_once __DIR__ . '/../../../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Emisores CFDI | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <style>
    .clean-filter{ display:none; }
    .clean-filter .input-group-text{ cursor:pointer; }
    .badge-pill{ border-radius:50rem; }
    .mono{ font-family:monospace; }

    /* Evitar que estilos de tabla rompan scroll del modal */
    .table-responsive { overflow-x: auto; overflow-y: hidden; }
    .table-responsive .dropdown-menu { z-index: 2000; }

    /* Modal ancho tipo Clientes/Productos */
    .modal-xxl-custom{ max-width:98vw; width:98vw; }
    @media (min-width:1200px){ .modal-xxl-custom{ max-width:1400px; width:1400px; } }
    @media (min-width:1600px){ .modal-xxl-custom{ max-width:1600px; width:1600px; } }

    /* ===== FIX REAL: scroll SIEMPRE dentro de modal-body (sin pelear con Bootstrap) ===== */
    #modalEmisor .modal-content{ overflow:hidden; }
    #modalEmisor .modal-body{
      overflow-y:auto !important;
      max-height: calc(100vh - 220px);
    }
    @media (max-height: 800px){
      #modalEmisor .modal-body{ max-height: calc(100vh - 180px); }
    }
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

    <div class="card-header" style="border-color:darkgray; border-style:dotted;">
      <h5>Filtros</h5>
      <div class="row">
        <div class="col-lg-12">
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label for="fSucursal" class="control-label">Sucursal</label>
                <div class="input-group">
                  <select id="fSucursal" class="form-control filtrar"></select>
                  <div class="input-group-append clean-filter">
                    <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('fSucursal')"></i></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-3">
              <div class="form-group">
                <label for="fRFC" class="control-label">RFC</label>
                <div class="input-group">
                  <input id="fRFC" class="form-control filtrar" placeholder="XAXX010101000...">
                  <div class="input-group-append clean-filter">
                    <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('fRFC')"></i></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-3">
              <div class="form-group">
                <label for="fRazon" class="control-label">Razón social</label>
                <div class="input-group">
                  <input id="fRazon" class="form-control filtrar" placeholder="Razón social del emisor">
                  <div class="input-group-append clean-filter">
                    <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('fRazon')"></i></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-1">
              <div class="form-group">
                <label for="fAmbiente" class="control-label">Ambiente</label>
                <div class="input-group">
                  <select id="fAmbiente" class="form-control filtrar">
                    <option value="">Todos</option>
                    <option value="DEMO">DEMO</option>
                    <option value="PROD">PROD</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="col-md-2">
              <div class="form-group">
                <label for="fActivo" class="control-label">Estatus</label>
                <div class="input-group">
                  <select id="fActivo" class="form-control filtrar">
                    <option value="">Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                  </select>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <div class="card-box">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="header-title">Listado de Emisores CFDI</h4>
            <button id="btnNuevo" type="button" class="btn btn-primary btn-sm waves-effect waves-light" data-toggle="modal" data-target="#modalEmisor">
              <i class="mdi mdi-plus"></i> Nuevo emisor
            </button>
          </div>

          <div class="table-responsive">
            <table id="tablaEmisores" class="table table-bordered table-hover table-striped">
              <thead>
                <tr>
                  <th class="text-center" style="width:90px;">ID</th>
                  <th style="width:180px;">Sucursal</th>
                  <th class="text-center" style="width:140px;">RFC</th>
                  <th>Razón social</th>
                  <th class="text-center" style="width:90px;">Ambiente</th>
                  <th class="text-center" style="width:100px;">Estatus</th>
                  <th class="text-center" style="width:130px;">Acciones</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="row align-items-center justify-content-between mt-2">
            <div class="col-md-6">
              <div id="infoEmisores" class="dataTables_info" role="status" aria-live="polite"></div>
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

  </div>
</div>

<div id="modalEmisor" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modalEmisorLabel" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-xxl-custom" role="document">
    <div class="modal-content">
      <form id="formEmisor" autocomplete="off">
        <input type="hidden" id="id_config" name="id_config" value="">

        <div class="modal-header">
          <h5 class="modal-title" id="modalEmisorLabel"><i class="mdi mdi-plus"></i> Nuevo emisor</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-info py-2 px-3 mb-3">
            <i class="mdi mdi-information-outline"></i>
            Los campos marcados con <span class="text-danger">*</span> son obligatorios.
          </div>

          <fieldset class="border rounded p-3">
            <legend class="w-auto px-2 mb-0 small text-muted text-uppercase">Datos del emisor</legend>
            <div class="form-row mt-2">
              <div class="form-group col-md-4">
                <label for="id_sucursal">Sucursal <span class="text-danger">*</span></label>
                <select class="form-control" name="id_sucursal" id="id_sucursal" required></select>
              </div>
              <div class="form-group col-md-8">
                <label for="rfc_emisor">RFC <span class="text-danger">*</span></label>
                <input class="form-control mono" id="rfc_emisor" name="rfc_emisor" maxlength="13" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="razon_social_emisor">Razón social <span class="text-danger">*</span></label>
                <input class="form-control" id="razon_social_emisor" name="razon_social_emisor" required>
              </div>
              <div class="form-group col-md-3">
                <label for="regimen_fiscal_emisor">Régimen fiscal</label>
                <input class="form-control" id="regimen_fiscal_emisor" name="regimen_fiscal_emisor" maxlength="3">
              </div>
              <div class="form-group col-md-3">
                <label for="cp_expedicion">CP expedición</label>
                <input class="form-control" id="cp_expedicion" name="cp_expedicion" maxlength="5">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-2"><label for="serie">Serie</label><input class="form-control" id="serie" name="serie" maxlength="10"></div>
              <div class="form-group col-md-2"><label for="folio_actual">Folio actual</label><input class="form-control" id="folio_actual" name="folio_actual" type="number" min="0" value="0"></div>
              <div class="form-group col-md-2"><label for="tipo_comprobante">Tipo comp.</label><input class="form-control" id="tipo_comprobante" name="tipo_comprobante" value="I" maxlength="1"></div>
              <div class="form-group col-md-2"><label for="exportacion_default">Exportación</label><input class="form-control" id="exportacion_default" name="exportacion_default" value="01" maxlength="2"></div>
              <div class="form-group col-md-2"><label for="moneda_default">Moneda</label><input class="form-control" id="moneda_default" name="moneda_default" value="MXN" maxlength="3"></div>
              <div class="form-group col-md-2"><label for="objeto_imp_default">Obj. Imp.</label><input class="form-control" id="objeto_imp_default" name="objeto_imp_default" value="02" maxlength="2"></div>
            </div>
          </fieldset>

          <fieldset class="border rounded p-3 mt-3">
            <legend class="w-auto px-2 mb-0 small text-muted text-uppercase">Facturación Digital (FD)</legend>
            <div class="form-row mt-2">
              <div class="form-group col-md-3">
                <label for="fd_ambiente">Ambiente</label>
                <select class="form-control" name="fd_ambiente" id="fd_ambiente">
                  <option value="DEMO">DEMO</option>
                  <option value="PROD">PROD</option>
                </select>
              </div>
              <div class="form-group col-md-3"><label for="fd_usuario">Usuario</label><input class="form-control" id="fd_usuario" name="fd_usuario"></div>
              <div class="form-group col-md-6">
                <label for="fd_password">Password</label>
                <div class="input-group">
                  <input class="form-control" id="fd_password" type="password" name="fd_password">
                  <div class="input-group-append">
                    <button class="btn btn-outline-secondary btnTogglePwd" type="button"><i class="mdi mdi-eye"></i></button>
                  </div>
                </div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6"><label for="fd_url_demo">URL Demo</label><input class="form-control" id="fd_url_demo" name="fd_url_demo"></div>
              <div class="form-group col-md-6"><label for="fd_url_prod">URL Prod</label><input class="form-control" id="fd_url_prod" name="fd_url_prod"></div>
            </div>
          </fieldset>

          <fieldset class="border rounded p-3 mt-3">
            <legend class="w-auto px-2 mb-0 small text-muted text-uppercase">Certificados y logo</legend>
            <div class="form-row mt-2">
              <div class="form-group col-md-6"><label for="csd_cer_path">CSD CER path</label><input class="form-control" id="csd_cer_path" name="csd_cer_path"></div>
              <div class="form-group col-md-6"><label for="csd_key_path">CSD KEY path</label><input class="form-control" id="csd_key_path" name="csd_key_path"></div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="csd_key_password">CSD KEY password</label>
                <div class="input-group">
                  <input class="form-control" id="csd_key_password" type="password" name="csd_key_password">
                  <div class="input-group-append">
                    <button class="btn btn-outline-secondary btnTogglePwd" type="button"><i class="mdi mdi-eye"></i></button>
                  </div>
                </div>
              </div>
              <div class="form-group col-md-6"><label for="pfx_path">PFX path</label><input class="form-control" id="pfx_path" name="pfx_path"></div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="pfx_password">PFX password</label>
                <div class="input-group">
                  <input class="form-control" id="pfx_password" type="password" name="pfx_password">
                  <div class="input-group-append">
                    <button class="btn btn-outline-secondary btnTogglePwd" type="button"><i class="mdi mdi-eye"></i></button>
                  </div>
                </div>
              </div>
              <div class="form-group col-md-6"><label for="logo_base64">Logo base64</label><textarea class="form-control" id="logo_base64" name="logo_base64" rows="3"></textarea></div>
            </div>
          </fieldset>

          <fieldset class="border rounded p-3 mt-3">
            <legend class="w-auto px-2 mb-0 small text-muted text-uppercase">Estado</legend>
            <div class="form-row mt-2">
              <div class="form-group col-md-3 d-flex align-items-center">
                <div class="custom-control custom-switch mt-2">
                  <input type="checkbox" class="custom-control-input" id="activo" name="activo" checked>
                  <label class="custom-control-label" for="activo">Activo</label>
                </div>
              </div>
            </div>
          </fieldset>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save"></i> Guardar</button>
        </div>
      </form>
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
const URL_AJAX = '<?= BASE_URL ?>/ajax/configuracion/emisores';
let paginaActual = 1;
const limitePorPagina = 10;

function showLoading(){ $('#LoadingImage').show().addClass('show'); }
function hideLoading(){ $('#LoadingImage').hide().removeClass('show'); }
function clearField(id){ $('#'+id).val('').trigger('change'); }

$(function(){
  toastr.options = { closeButton:true, progressBar:true, positionClass:'toast-bottom-right', timeOut:'3000' };

  // reset scroll al abrir
  $('#modalEmisor').on('shown.bs.modal', function(){
    $(this).find('.modal-body').scrollTop(0);
  });

  loadSucursales();
  loadList(1);

  function loadSucursales(){
    showLoading();
    $.getJSON(URL_AJAX + '/get.php', {sucursales: 1}, function(resp){
      if (!resp.ok) return;
      let opts = '<option value="">-- Todas --</option>';
      (resp.data || []).forEach(s => { opts += `<option value="${s.id_sucursal}">${s.nombre}</option>`; });
      $('#fSucursal').html(opts);
      $('#id_sucursal').html(opts.replace('-- Todas --','-- Selecciona --'));
    }).always(hideLoading);
  }

  function loadList(page=1){
    paginaActual = page;
    showLoading();
    $.getJSON(URL_AJAX + '/list.php', {
      pagina: paginaActual,
      limite: limitePorPagina,
      id_sucursal: $('#fSucursal').val(),
      rfc_emisor: $('#fRFC').val(),
      razon_social_emisor: $('#fRazon').val(),
      fd_ambiente: $('#fAmbiente').val(),
      activo: $('#fActivo').val()
    })
    .done(function(resp){
      if (!resp.ok) { toastr.error(resp.message || 'Error al cargar listado.'); return; }
      const data = resp.data || {};
      const rows = data.rows || [];
      const total = parseInt(data.total || 0, 10);

      let html = '';
      rows.forEach(r => {
        const id = r.id_config;
        const activo = parseInt(r.activo || 0, 10) === 1;
        html += `<tr>
          <td class="text-center">${id}</td>
          <td>${r.sucursal_nombre || ''}</td>
          <td class="text-center mono">${r.rfc_emisor || ''}</td>
          <td>${r.razon_social_emisor || ''}</td>
          <td class="text-center"><span class="badge badge-${(r.fd_ambiente||'DEMO') === 'PROD' ? 'success' : 'secondary'} badge-pill">${r.fd_ambiente || 'DEMO'}</span></td>
          <td class="text-center"><span class="badge badge-${activo ? 'success' : 'danger'} badge-pill">${activo ? 'Activo' : 'Inactivo'}</span></td>
          <td class="text-center">
            <div class="btn-group dropdown">
              <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                <i class="mdi mdi-dots-horizontal"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item btnEdit" href="#" data-id="${id}">
                  <i class="mdi mdi-square-edit-outline mr-2 text-muted font-18 vertical-middle"></i>Editar
                </a>
                <a class="dropdown-item btnToggle" href="#" data-id="${id}" data-act="${activo ? 0 : 1}">
                  <i class="mdi mdi-power mr-2 text-muted font-18 vertical-middle"></i>${activo ? 'Desactivar' : 'Activar'}
                </a>
              </div>
            </div>
          </td>
        </tr>`;
      });

      if (!rows.length) {
        html = '<tr><td colspan="7" class="text-center text-muted py-4">Sin resultados</td></tr>';
      }
      $('#tablaEmisores tbody').html(html);
      renderPagination(data.page || paginaActual, total, data.perPage || limitePorPagina);

      const d = total ? ((paginaActual - 1) * limitePorPagina) + 1 : 0;
      const h = total ? Math.min(paginaActual * limitePorPagina, total) : 0;
      $('#infoEmisores').text(total ? `Mostrando ${d} a ${h} de ${total} registros` : 'Mostrando 0 a 0 de 0 registros');
    })
    .fail(function(xhr){
      toastr.error(xhr?.responseJSON?.message || 'No se pudo obtener el listado.');
    })
    .always(hideLoading);
  }

  function renderPagination(currentPage, totalItems, itemsPerPage){
    const totalPages = Math.max(1, Math.ceil((totalItems || 0) / (itemsPerPage || limitePorPagina)));
    const maxVisiblePages = 5;
    const $ul = $('#pagination');
    $ul.empty();
    if (totalPages <= 1){ $ul.closest('nav').hide(); return; }
    $ul.closest('nav').show();

    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    if (endPage - startPage + 1 < maxVisiblePages) startPage = Math.max(1, endPage - maxVisiblePages + 1);

    if (currentPage > 1){
      $ul.append(`<li class="page-item"><a class="page-link page-btn" href="#" data-page="1">Primera</a></li>`);
      $ul.append(`<li class="page-item"><a class="page-link page-btn" href="#" data-page="${currentPage - 1}">&laquo; Anterior</a></li>`);
    }
    for (let i = startPage; i <= endPage; i++){
      $ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link page-btn" href="#" data-page="${i}">${i}</a></li>`);
    }
    if (currentPage < totalPages){
      $ul.append(`<li class="page-item"><a class="page-link page-btn" href="#" data-page="${currentPage + 1}">Siguiente &raquo;</a></li>`);
      $ul.append(`<li class="page-item"><a class="page-link page-btn" href="#" data-page="${totalPages}">Última</a></li>`);
    }
  }

  $('.filtrar').on('keyup change', function(e){
    const v = $(this).val();
    $(this).closest('.input-group').find('.clean-filter').toggle(String(v || '').length > 0);
    if (e.type === 'change' || e.keyCode === 13) loadList(1);
  });

  $(document).on('click', '.page-btn', function(e){
    e.preventDefault();
    const p = parseInt($(this).data('page'), 10);
    if (Number.isNaN(p) || p < 1) return;
    loadList(p);
  });

  $('#btnNuevo').on('click', function(){
    $('#formEmisor')[0].reset();
    $('#id_config').val('');
    $('#modalEmisorLabel').html('<i class="mdi mdi-plus"></i> Nuevo emisor');
    $('#activo').prop('checked', true);
    $('#modalEmisor').modal('show');
  });

  $(document).on('click', '.btnEdit', function(e){
    e.preventDefault();
    const id = $(this).data('id');
    showLoading();
    $.getJSON(URL_AJAX + '/get.php', { id_config: id }, function(resp){
      if (!resp.ok) { toastr.error(resp.message || 'No se pudo cargar el registro.'); return; }
      const r = resp.data || {};
      Object.keys(r).forEach(k => {
        const $el = $('[name="'+k+'"]');
        if (!$el.length) return;
        if ($el.attr('type') === 'checkbox') {
          $el.prop('checked', parseInt(r[k] || 0, 10) === 1);
        } else {
          $el.val(r[k] ?? '');
        }
      });
      $('#id_config').val(r.id_config || id);
      $('#modalEmisorLabel').html('<i class="mdi mdi-pencil"></i> Editar emisor');
      $('#modalEmisor').modal('show');
    }).always(hideLoading);
  });

  $('#formEmisor').on('submit', function(e){
    e.preventDefault();
    const payload = {};
    $(this).serializeArray().forEach(({name, value}) => payload[name] = value);
    payload.activo = $('#activo').is(':checked') ? 1 : 0;
    payload.rfc_emisor = String(payload.rfc_emisor || '').toUpperCase().trim();

    const endpoint = payload.id_config ? '/update.php' : '/create.php';
    showLoading();
    $.ajax({
      url: URL_AJAX + endpoint,
      method: 'POST',
      contentType: 'application/json',
      dataType: 'json',
      data: JSON.stringify(payload)
    })
    .done(function(resp){
      if (!resp.ok) { toastr.error(resp.message || 'No se pudo guardar.'); return; }
      toastr.success('Registro guardado correctamente.');
      $('#modalEmisor').modal('hide');
      loadList(1);
    })
    .fail(function(){ toastr.error('Error al guardar el registro.'); })
    .always(hideLoading);
  });

  $(document).on('click', '.btnToggle', function(e){
    e.preventDefault();
    const id = $(this).data('id');
    const activo = $(this).data('act');
    showLoading();
    $.ajax({
      url: URL_AJAX + '/toggle.php',
      method: 'POST',
      contentType: 'application/json',
      dataType: 'json',
      data: JSON.stringify({ id_config: id, activo })
    })
    .done(function(resp){
      if (!resp.ok) { toastr.error(resp.message || 'No se pudo actualizar estatus.'); return; }
      toastr.success('Estatus actualizado.');
      loadList(paginaActual);
    })
    .fail(function(){ toastr.error('No se pudo actualizar el estatus.'); })
    .always(hideLoading);
  });

  $(document).on('click', '.btnTogglePwd', function(){
    const $input = $(this).closest('.input-group').find('input');
    const currentType = $input.attr('type');
    $input.attr('type', currentType === 'password' ? 'text' : 'password');
  });
});
</script>
</body>
</html>
