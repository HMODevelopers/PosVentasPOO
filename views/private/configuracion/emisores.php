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
    .modal-xxl-custom{ max-width:98vw; width:98vw; }
    @media (min-width:1200px){ .modal-xxl-custom{ max-width:1200px; width:1200px; } }
    #modalEmisor .modal-content{ overflow:hidden; }
    #modalEmisor .modal-body{ overflow-y:auto !important; max-height: calc(100vh - 220px); }
    .section-card{ border:1px solid #e9ecef; border-radius:8px; padding:15px; margin-bottom:15px; }
    .section-title{ font-size:.85rem; text-transform:uppercase; color:#6c757d; margin-bottom:12px; font-weight:600; }
    .help-text{ font-size:.8rem; color:#6c757d; margin-top:4px; }
    .is-invalid + .help-text{ color:#dc3545; }
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

    <div class="card-header" style="border-color:darkgray; border-style:dotted;">
      <h5>Filtros</h5>
      <div class="row">
        <div class="col-md-3"><label for="fSucursal">Sucursal</label><div class="input-group"><select id="fSucursal" class="form-control filtrar"></select><div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('fSucursal')"></i></span></div></div></div>
        <div class="col-md-3"><label for="fRFC">RFC</label><div class="input-group"><input id="fRFC" class="form-control filtrar" placeholder="XAXX010101000"><div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('fRFC')"></i></span></div></div></div>
        <div class="col-md-3"><label for="fRazon">Razón social</label><div class="input-group"><input id="fRazon" class="form-control filtrar" placeholder="Razón social del emisor"><div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('fRazon')"></i></span></div></div></div>
        <div class="col-md-3"><label for="fActivo">Estatus</label><select id="fActivo" class="form-control filtrar"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select></div>
      </div>
    </div>

    <div class="card-box mt-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="header-title">Listado de Emisores CFDI</h4>
        <button id="btnNuevo" type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalEmisor"><i class="mdi mdi-plus"></i> Nuevo emisor</button>
      </div>

      <div class="table-responsive">
        <table id="tablaEmisores" class="table table-bordered table-hover table-striped">
          <thead>
            <tr>
              <th class="text-center" style="width:70px;">ID</th>
              <th style="width:160px;">Sucursal</th>
              <th class="text-center" style="width:130px;">RFC</th>
              <th>Razón social</th>
              <th style="width:220px;">Defaults CFDI</th>
              <th class="text-center" style="width:90px;">Default</th>
              <th class="text-center" style="width:90px;">Estatus</th>
              <th class="text-center" style="width:120px;">Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="row align-items-center justify-content-between mt-2">
        <div class="col-md-6"><div id="infoEmisores" class="dataTables_info"></div></div>
        <div class="col-md-6 d-flex justify-content-end"><nav><ul id="pagination" class="pagination justify-content-end mb-0"></ul></nav></div>
      </div>
    </div>
  </div>
</div>

<div id="modalEmisor" class="modal fade" tabindex="-1" role="dialog" data-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-xxl-custom" role="document">
    <div class="modal-content">
      <form id="formEmisor" autocomplete="off" novalidate>
        <input type="hidden" id="id_config" name="id_config" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEmisorLabel"><i class="mdi mdi-plus"></i> Nuevo emisor</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-info py-2 px-3 mb-3"><i class="mdi mdi-information-outline"></i> Campos con <span class="text-danger">*</span> obligatorios.</div>

          <div class="section-card">
            <div class="section-title">Bloque A · Datos fiscales del emisor</div>
            <div class="form-row">
              <div class="form-group col-md-4"><label>Sucursal <span class="text-danger">*</span></label><select class="form-control" name="id_sucursal" id="id_sucursal" required></select><div class="help-text">Sucursal operativa del emisor.</div></div>
              <div class="form-group col-md-4"><label>RFC emisor <span class="text-danger">*</span></label><input class="form-control mono" id="rfc_emisor" name="rfc_emisor" maxlength="13" required><div class="help-text">Se guarda en mayúsculas, sin espacios.</div></div>
              <div class="form-group col-md-4"><label>CP expedición <span class="text-danger">*</span></label><input class="form-control mono" id="cp_expedicion" name="cp_expedicion" maxlength="5" required><div class="help-text">Código postal de 5 dígitos.</div></div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-7"><label>Razón social <span class="text-danger">*</span></label><input class="form-control" id="razon_social_emisor" name="razon_social_emisor" required></div>
              <div class="form-group col-md-5"><label>Régimen fiscal <span class="text-danger">*</span></label><select class="form-control" id="regimen_fiscal_emisor" name="regimen_fiscal_emisor" required></select></div>
            </div>
          </div>

          <div class="section-card">
            <div class="section-title">Bloque B · Defaults de facturación</div>
            <div class="form-row">
              <div class="form-group col-md-4"><label>Tipo de comprobante</label><select class="form-control" id="tipo_comprobante" name="tipo_comprobante"></select></div>
              <div class="form-group col-md-4"><label>Exportación</label><select class="form-control" id="exportacion_default" name="exportacion_default"></select></div>
              <div class="form-group col-md-4"><label>Moneda</label><select class="form-control" id="moneda_default" name="moneda_default"></select></div>
            </div>
          </div>

          <div class="section-card">
            <div class="section-title">Bloque C · Foliado y control</div>
            <div class="form-row">
              <div class="form-group col-md-3"><label>Serie</label><input class="form-control mono" id="serie" name="serie" maxlength="10"><div class="help-text">Solo letras y números.</div></div>
              <div class="form-group col-md-3"><label>Folio actual</label><input class="form-control" id="folio_actual" name="folio_actual" type="number" min="0" value="0"></div>
              <div class="form-group col-md-3 d-flex align-items-center"><div class="custom-control custom-switch mt-4"><input type="checkbox" class="custom-control-input" id="activo" name="activo" checked><label class="custom-control-label" for="activo">Activo</label></div></div>
              <div class="form-group col-md-3 d-flex align-items-center"><div class="custom-control custom-switch mt-4"><input type="checkbox" class="custom-control-input" id="es_default" name="es_default"><label class="custom-control-label" for="es_default">Default sucursal</label></div></div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save"></i> Guardar emisor</button>
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
let catalogos = { sucursales: [], regimenes: [], monedas: [], tipos_comprobante: [], exportaciones: [] };

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
function showLoading(){ $('#LoadingImage').show().addClass('show'); }
function hideLoading(){ $('#LoadingImage').hide().removeClass('show'); }
function clearField(id){ $('#'+id).val('').trigger('change'); }

function buildOptions(items, selected = '', placeholder = '-- Selecciona --'){
  let html = `<option value="">${placeholder}</option>`;
  (items || []).forEach(i => {
    const key = i.id_sucursal ?? i.clave ?? '';
    const label = i.nombre ?? i.label ?? i.descripcion ?? key;
    html += `<option value="${esc(key)}">${esc(label)}</option>`;
  });
  return html;
}

function ensureLegacyOption($select, value, prefix){
  const val = String(value || '').trim();
  if (!val) return;
  if ($select.find(`option[value="${val.replace(/"/g, '\\"')}"]`).length) return;
  $select.append(`<option value="${esc(val)}">${esc(`${val} (legacy / no vigente en catálogo ${prefix})`)}</option>`);
}

function setSelectValueWithTolerance(selector, value, legacyLabel){
  const $select = $(selector);
  ensureLegacyOption($select, value, legacyLabel);
  $select.val(String(value ?? ''));
}

function validarFormulario(){
  let ok = true;
  const required = ['#id_sucursal','#rfc_emisor','#razon_social_emisor','#regimen_fiscal_emisor','#cp_expedicion'];
  required.forEach(sel => {
    const $el = $(sel);
    const val = String($el.val() || '').trim();
    $el.toggleClass('is-invalid', val === '');
    if (val === '') ok = false;
  });

  const rfc = String($('#rfc_emisor').val() || '').trim().toUpperCase();
  $('#rfc_emisor').val(rfc);
  if (rfc && !/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/.test(rfc)) {
    $('#rfc_emisor').addClass('is-invalid'); ok = false;
  }

  const cp = String($('#cp_expedicion').val() || '').replace(/\D+/g, '').slice(0, 5);
  $('#cp_expedicion').val(cp);
  if (cp.length !== 5) { $('#cp_expedicion').addClass('is-invalid'); ok = false; }

  const serie = String($('#serie').val() || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
  $('#serie').val(serie);

  const folio = parseInt($('#folio_actual').val(), 10);
  if (Number.isNaN(folio) || folio < 0) { $('#folio_actual').addClass('is-invalid'); ok = false; }
  else { $('#folio_actual').removeClass('is-invalid'); }

  return ok;
}

$(function(){
  toastr.options = { closeButton:true, progressBar:true, positionClass:'toast-bottom-right', timeOut:'3000' };

  $('#modalEmisor').on('shown.bs.modal', function(){ $(this).find('.modal-body').scrollTop(0); });
  $(document).on('input change', '#formEmisor input, #formEmisor select', function(){ $(this).removeClass('is-invalid'); });
  $(document).on('input', '#rfc_emisor', function(){ this.value = this.value.toUpperCase().replace(/\s+/g,''); });

  showLoading();
  $.getJSON(URL_AJAX + '/get.php', { catalogos: 1 }).done(function(resp){
    if (!resp.ok) { toastr.error(resp.message || 'No se pudieron cargar catálogos.'); return; }
    catalogos = resp.data || catalogos;
    $('#fSucursal').html(buildOptions(catalogos.sucursales, '', '-- Todas --'));
    $('#id_sucursal').html(buildOptions(catalogos.sucursales));
    $('#regimen_fiscal_emisor').html(buildOptions(catalogos.regimenes));
    $('#moneda_default').html(buildOptions(catalogos.monedas));
    $('#tipo_comprobante').html(buildOptions(catalogos.tipos_comprobante));
    $('#exportacion_default').html(buildOptions(catalogos.exportaciones));
  }).always(function(){ hideLoading(); loadList(1); });

  function loadList(page=1){
    paginaActual = page;
    showLoading();
    $.getJSON(URL_AJAX + '/list.php', {
      pagina: paginaActual, limite: limitePorPagina, id_sucursal: $('#fSucursal').val(),
      rfc_emisor: $('#fRFC').val(), razon_social_emisor: $('#fRazon').val(), activo: $('#fActivo').val()
    }).done(function(resp){
      if (!resp.ok) { toastr.error(resp.message || 'Error al cargar listado.'); return; }
      const data = resp.data || {};
      const rows = data.rows || [];
      const total = parseInt(data.total || 0, 10);
      let html = '';
      rows.forEach(r => {
        const id = r.id_config;
        const activo = parseInt(r.activo || 0, 10) === 1;
        const defaults = `<div><small><b>Tipo:</b> ${esc(r.tipo_comprobante_label || '—')}</small></div>
                          <div><small><b>Export:</b> ${esc(r.exportacion_default_label || '—')}</small></div>
                          <div><small><b>Moneda:</b> ${esc(r.moneda_default_label || '—')}</small></div>`;
        html += `<tr>
          <td class="text-center">${id}</td>
          <td>${esc(r.sucursal_nombre || '')}</td>
          <td class="text-center mono">${esc(r.rfc_emisor || '')}</td>
          <td><div>${esc(r.razon_social_emisor || '')}</div><small class="text-muted">${esc(r.regimen_fiscal_emisor_label || '—')}</small></td>
          <td>${defaults}</td>
          <td class="text-center"><span class="badge badge-${parseInt(r.es_default || 0, 10) === 1 ? 'success' : 'secondary'} badge-pill">${parseInt(r.es_default || 0, 10) === 1 ? 'Sí' : 'No'}</span></td>
          <td class="text-center"><span class="badge badge-${activo ? 'success' : 'danger'} badge-pill">${activo ? 'Activo' : 'Inactivo'}</span></td>
          <td class="text-center">
            <div class="btn-group dropdown">
              <a href="#" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown"><i class="mdi mdi-dots-horizontal"></i></a>
              <div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item btnEdit" href="#" data-id="${id}"><i class="mdi mdi-square-edit-outline mr-2 text-muted"></i>Editar</a>
                <a class="dropdown-item btnSetDefault ${parseInt(r.es_default || 0, 10) === 1 ? 'disabled' : ''}" href="#" data-id="${id}"><i class="mdi mdi-star mr-2 text-muted"></i>${parseInt(r.es_default || 0, 10) === 1 ? 'Default actual' : 'Marcar default'}</a>
                <a class="dropdown-item btnToggle" href="#" data-id="${id}" data-act="${activo ? 0 : 1}"><i class="mdi mdi-power mr-2 text-muted"></i>${activo ? 'Desactivar' : 'Activar'}</a>
              </div>
            </div>
          </td></tr>`;
      });
      if (!rows.length) html = '<tr><td colspan="8" class="text-center text-muted py-4">Sin resultados</td></tr>';
      $('#tablaEmisores tbody').html(html);
      renderPagination(data.page || paginaActual, total, data.perPage || limitePorPagina);
      const d = total ? ((paginaActual - 1) * limitePorPagina) + 1 : 0;
      const h = total ? Math.min(paginaActual * limitePorPagina, total) : 0;
      $('#infoEmisores').text(total ? `Mostrando ${d} a ${h} de ${total} registros` : 'Mostrando 0 a 0 de 0 registros');
    }).fail(function(xhr){ toastr.error(xhr?.responseJSON?.message || 'No se pudo obtener el listado.'); })
      .always(hideLoading);
  }

  function renderPagination(currentPage, totalItems, itemsPerPage){
    const totalPages = Math.max(1, Math.ceil((totalItems || 0) / (itemsPerPage || limitePorPagina)));
    const $ul = $('#pagination').empty();
    if (totalPages <= 1){ $ul.closest('nav').hide(); return; }
    $ul.closest('nav').show();
    for (let i = 1; i <= totalPages; i++) { $ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link page-btn" href="#" data-page="${i}">${i}</a></li>`); }
  }

  $('.filtrar').on('keyup change', function(e){
    $(this).closest('.input-group').find('.clean-filter').toggle(String($(this).val() || '').length > 0);
    if (e.type === 'change' || e.keyCode === 13) loadList(1);
  });

  $(document).on('click', '.page-btn', function(e){ e.preventDefault(); loadList(parseInt($(this).data('page'), 10) || 1); });

  $('#btnNuevo').on('click', function(){
    $('#formEmisor')[0].reset();
    $('#id_config').val('');
    $('#modalEmisorLabel').html('<i class="mdi mdi-plus"></i> Nuevo emisor');
    $('#activo').prop('checked', true);
    $('#es_default').prop('checked', false);
    setSelectValueWithTolerance('#tipo_comprobante', 'I', 'tipo');
    setSelectValueWithTolerance('#exportacion_default', '01', 'exportación');
    setSelectValueWithTolerance('#moneda_default', 'MXN', 'moneda');
  });

  $(document).on('click', '.btnEdit', function(e){
    e.preventDefault();
    const id = $(this).data('id');
    showLoading();
    $.getJSON(URL_AJAX + '/get.php', { id_config: id }, function(resp){
      if (!resp.ok) { toastr.error(resp.message || 'No se pudo cargar el registro.'); return; }
      const r = resp.data || {};
      $('#id_config').val(r.id_config || id);
      $('#rfc_emisor').val(String(r.rfc_emisor || '').toUpperCase());
      $('#razon_social_emisor').val(r.razon_social_emisor || '');
      $('#cp_expedicion').val(r.cp_expedicion || '');
      $('#serie').val(r.serie || '');
      $('#folio_actual').val(r.folio_actual ?? 0);
      $('#activo').prop('checked', parseInt(r.activo || 0, 10) === 1);
      $('#es_default').prop('checked', parseInt(r.es_default || 0, 10) === 1);
      setSelectValueWithTolerance('#id_sucursal', r.id_sucursal, 'sucursal');
      setSelectValueWithTolerance('#regimen_fiscal_emisor', r.regimen_fiscal_emisor, 'régimen');
      setSelectValueWithTolerance('#tipo_comprobante', r.tipo_comprobante || 'I', 'tipo');
      setSelectValueWithTolerance('#exportacion_default', r.exportacion_default || '01', 'exportación');
      setSelectValueWithTolerance('#moneda_default', r.moneda_default || 'MXN', 'moneda');
      $('#modalEmisorLabel').html('<i class="mdi mdi-pencil"></i> Editar emisor');
      $('#modalEmisor').modal('show');
    }).always(hideLoading);
  });

  $('#formEmisor').on('submit', function(e){
    e.preventDefault();
    if (!validarFormulario()) { toastr.warning('Corrige los campos marcados en rojo.'); return; }

    const payload = {};
    $(this).serializeArray().forEach(({name, value}) => payload[name] = value);
    payload.activo = $('#activo').is(':checked') ? 1 : 0;
    payload.es_default = $('#es_default').is(':checked') ? 1 : 0;
    if (payload.es_default) payload.activo = 1;

    const endpoint = payload.id_config ? '/update.php' : '/create.php';
    showLoading();
    $.ajax({ url: URL_AJAX + endpoint, method: 'POST', contentType: 'application/json', dataType: 'json', data: JSON.stringify(payload) })
      .done(function(resp){
        if (!resp.ok) { toastr.error(resp.message || 'No se pudo guardar.'); return; }
        toastr.success('Registro guardado correctamente.');
        $('#modalEmisor').modal('hide');
        loadList(1);
      })
      .fail(function(xhr){ toastr.error(xhr?.responseJSON?.message || 'Error al guardar el registro.'); })
      .always(hideLoading);
  });

  $(document).on('click', '.btnSetDefault', function(e){
    e.preventDefault(); if ($(this).hasClass('disabled')) return;
    showLoading();
    $.ajax({ url: URL_AJAX + '/set_default.php', method: 'POST', contentType: 'application/json', dataType: 'json', data: JSON.stringify({ id_config: $(this).data('id') }) })
      .done(function(resp){ if (!resp.ok) { toastr.error(resp.message || 'No se pudo establecer el emisor default.'); return; } toastr.success('Emisor default actualizado.'); loadList(1); })
      .fail(function(){ toastr.error('No se pudo actualizar el emisor default.'); })
      .always(hideLoading);
  });

  $(document).on('click', '.btnToggle', function(e){
    e.preventDefault();
    showLoading();
    $.ajax({ url: URL_AJAX + '/toggle.php', method: 'POST', contentType: 'application/json', dataType: 'json', data: JSON.stringify({ id_config: $(this).data('id'), activo: $(this).data('act') }) })
      .done(function(resp){ if (!resp.ok) { toastr.error(resp.message || 'No se pudo actualizar estatus.'); return; } toastr.success('Estatus actualizado.'); loadList(paginaActual); })
      .fail(function(){ toastr.error('No se pudo actualizar el estatus.'); })
      .always(hideLoading);
  });
});
</script>
</body>
</html>
