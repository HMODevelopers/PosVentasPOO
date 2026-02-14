<?php
$titulo='Catalogos';
$modulo='Clientes SAT';
$subtitulo='';
require_once __DIR__.'/../../../includes/auth.php';
require_once __DIR__.'/../../../includes/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<title>Clientes SAT | REFASOFT-V4</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet"/>
<link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet"/>
<link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet"/>
<link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet"/>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<style>
.clean-filter{display:none;}
.clean-filter .input-group-text{cursor:pointer;}

.form-section{border:1px solid #e5e7eb;border-radius:8px;padding:12px 12px 2px;margin-bottom:12px;background:#fafafa;}
.form-section h6{font-size:.9rem;font-weight:700;margin-bottom:10px;color:#374151;}
.form-group label{font-weight:600;}

.select2-container{width:100%!important;}
.select2-container--open{z-index:1060;}

.location-alert{display:none;}
.pais-note{font-size:.8rem;color:#6b7280;}

.modal-xxl-custom{
  max-width: 98vw;
  width: 98vw;
}
@media (min-width: 1200px){
  .modal-xxl-custom{ max-width: 1400px; width: 1400px; }
}
@media (min-width: 1600px){
  .modal-xxl-custom{ max-width: 1600px; width: 1600px; }
}
</style>
</head>

<body>
<?php include_once __DIR__.'/../../../includes/header.php'; ?>

<div class="wrapper">
  <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
    <div class="loader">
      <div class="loader__figure"></div>
      <p class="loader__label">Cargando...</p>
    </div>
  </div>

  <div class="container-fluid">
    <?php include_once __DIR__.'/../../../includes/breadcrumb.php'; ?>

    <div class="card-header" style="border-color:darkgray;border-style:dotted;">
      <h5>Filtros</h5>
      <div class="row">
        <div class="col-lg-12">
          <div class="row">

            <div class="col-md-4">
              <div class="form-group">
                <label for="FiltroClave">RFC</label>
                <div class="input-group">
                  <input id="FiltroClave" class="form-control filtrar">
                  <div class="input-group-append clean-filter">
                    <span class="input-group-text" title="Limpiar">
                      <i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroClave')"></i>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-8">
              <div class="form-group">
                <label for="FiltroDescripcion">Razón social</label>
                <div class="input-group">
                  <input id="FiltroDescripcion" class="form-control filtrar">
                  <div class="input-group-append clean-filter">
                    <span class="input-group-text" title="Limpiar">
                      <i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroDescripcion')"></i>
                    </span>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <div class="row mt-3">
      <div class="col-12">
        <div class="card-box">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="header-title">Catálogo Clientes SAT</h4>
            <button id="btnNuevo" class="btn btn-primary"><i class="mdi mdi-plus"></i> Nuevo cliente SAT</button>
          </div>

          <div id="emptyState" class="alert alert-warning d-none mb-2">No hay registros que coincidan con los filtros.</div>

          <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>RFC</th>
                  <th>Razón social</th>
                  <th>Régimen fiscal</th>
                  <th>Uso CFDI</th>
                  <th>Ubicación</th>
                  <th>CP</th>
                  <th style="width:110px">Acciones</th>
                </tr>
              </thead>
              <tbody id="tbodyRegistros"></tbody>
            </table>
          </div>

          <div class="row align-items-center justify-content-between mt-2">
            <div class="col-md-6">
              <div id="infoRegistros" class="dataTables_info"></div>
            </div>
            <div class="col-md-6 d-flex justify-content-end">
              <nav><ul id="pagination" class="pagination justify-content-end mb-0"></ul></nav>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div>
</div>

<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-xxl-custom" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="tituloModal">Nuevo cliente SAT</h5>
        <button class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>

      <form id="formRegistro" autocomplete="off">
        <input type="hidden" id="row_key">
        <input type="hidden" id="id">

        <div class="modal-body">

          <div class="form-section">
            <h6>A) Fiscal</h6>
            <div class="row">
              <div class="col-12 col-md-6">
                <div class="form-group"><label>RFC</label><input id="rfc" class="form-control" maxlength="13"></div>
              </div>
              <div class="col-12 col-md-6">
                <div class="form-group"><label>Razón social</label><input id="razon_social" class="form-control" maxlength="118"></div>
              </div>
              <div class="col-12 col-md-6">
                <div class="form-group"><label>Nombre comercial</label><input id="nombre_comercial" class="form-control"></div>
              </div>
              <div class="col-12 col-md-6">
                <div class="form-group"><label>Régimen fiscal</label><select id="regimen_fiscal" class="form-control"></select></div>
              </div>
              <div class="col-12 col-md-6">
                <div class="form-group"><label>Uso CFDI</label><select id="uso_cdfi" class="form-control"></select></div>
              </div>
              <div class="col-12 col-md-6">
                <div class="form-group"><label>CP fiscal</label><input id="dom_fiscal_cp" class="form-control"></div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h6>B) Ubicación / Domicilio</h6>
            <div class="row">
              <div class="col-12 col-md-4">
                <div class="form-group">
                  <label>País</label>
                  <input id="pais_display" class="form-control" readonly>
                  <input type="hidden" id="pais">
                  <small id="pais_legacy_note" class="pais-note d-none"></small>
                </div>
              </div>

              <div class="col-12 col-md-4">
                <div class="form-group">
                  <label>Entidad</label>
                  <select id="estado" class="form-control"></select>
                  <small class="text-warning location-alert" id="estado_alert"></small>
                </div>
              </div>

              <div class="col-12 col-md-4">
                <div class="form-group">
                  <label>Municipio</label>
                  <select id="municipio" class="form-control"></select>
                  <small class="text-warning location-alert" id="municipio_alert"></small>
                </div>
              </div>

              <div class="col-12 col-md-4">
                <div class="form-group">
                  <label>Localidad</label>
                  <select id="localidad" class="form-control"></select>
                  <small class="text-warning location-alert" id="localidad_alert"></small>
                </div>
              </div>

              <div class="col-12 col-md-8"><div class="form-group"><label>Colonia</label><input id="colonia" class="form-control"></div></div>
              <div class="col-12 col-md-8"><div class="form-group"><label>Calle</label><input id="calle" class="form-control"></div></div>
              <div class="col-12 col-md-3"><div class="form-group"><label>No Ext</label><input id="numero_exterior" class="form-control"></div></div>
              <div class="col-12 col-md-3"><div class="form-group"><label>No Int</label><input id="numero_interior" class="form-control"></div></div>
              <div class="col-12 col-md-6"><div class="form-group"><label>Referencia</label><input id="referencia" class="form-control"></div></div>
            </div>
          </div>

          <div class="form-section">
            <h6>C) Contacto/extras</h6>
            <div class="row">
              <div class="col-12 col-md-6"><div class="form-group"><label>Email</label><input id="email" class="form-control"></div></div>
              <div class="col-12 col-md-6"><div class="form-group"><label>Email alterno</label><input id="email_alterno" class="form-control"></div></div>
              <div class="col-12 col-md-6"><div class="form-group"><label>Teléfono</label><input id="telefono" class="form-control"></div></div>
              <div class="col-12 col-md-6"><div class="form-group"><label>Celular</label><input id="celular" class="form-control"></div></div>
              <div class="col-12 col-md-6"><div class="form-group"><label>Residencia fiscal</label><input id="residencia_fiscal" class="form-control"></div></div>
              <div class="col-12 col-md-6"><div class="form-group"><label>Número registro tributario</label><input id="numero_registro_tributario" class="form-control"></div></div>
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>

    </div>
  </div>
</div>

<?php include_once __DIR__.'/../../../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
$(function(){
  let paginaActual = 1;
  const limitePorPagina = 10;
  const URL_CTRL = '<?= BASE_URL ?>/controllers/ClientesSatController.php';

  const e = s => String(s ?? '')
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'",'&#039;');

  let catalogos = { entidades:[], regimenes:[], usos_cfdi:[] };
  let bloqueoEventos = false;

  // Loader global (AJAX)
  function showLoader(){ $('#LoadingImage').show().addClass('show'); }
  function hideLoader(){ $('#LoadingImage').hide().removeClass('show'); }
  $(document).ajaxStart(function(){ showLoader(); });
  $(document).ajaxStop(function(){ hideLoader(); });
  $(document).ajaxError(function(){ hideLoader(); });

  // X de limpiar filtros (solo cuando hay texto)
  function toggleCleanForInput(input){
    const $in = $(input);
    const hasVal = ($in.val() || '').toString().trim().length > 0;
    $in.closest('.input-group').find('.clean-filter')[hasVal ? 'show' : 'hide']();
  }
  function refreshAllCleanIcons(){
    $('#FiltroClave,#FiltroDescripcion').each(function(){ toggleCleanForInput(this); });
  }

  // Debounce filtros
  let filtroTimer = null;
  function aplicarFiltrosDebounced(){
    clearTimeout(filtroTimer);
    filtroTimer = setTimeout(function(){ cargarRegistros(1); }, 250);
  }

  window.clearField = function(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.value = '';
    $(el).trigger('input');
    $(el).trigger('change');
    cargarRegistros(1);
  };

  // Select2 estable dentro del modal
  function iniSelect2Modal(){
    if(!$.fn.select2) return;
    $('#modalForm select').each(function(){
      const $s = $(this);
      if($s.hasClass('select2-hidden-accessible')){
        $s.select2('destroy');
      }
      $s.select2({
        width: '100%',
        dropdownParent: $('#modalForm'),
        placeholder: 'Seleccione...',
        allowClear: true
      });
    });
  }
  $('#modalForm').on('shown.bs.modal', function(){ iniSelect2Modal(); });
  $('#modalForm').on('hidden.bs.modal', function(){
    $('#modalForm select').each(function(){
      const $s = $(this);
      if($s.hasClass('select2-hidden-accessible')){
        $s.select2('destroy');
      }
    });
  });

  function opt(v,t,sel=''){
    return `<option value="${e(v)}" ${String(sel)===String(v)?'selected':''}>${e(t)}</option>`;
  }

  // Placeholder REAL para evitar autoselección
  function fillSimple(sel, items, valKey, textKey, current=''){
    let html = '<option value=""></option>';
    (items||[]).forEach(it => html += opt(it[valKey], it[textKey], current));

    const $s = $(sel);
    $s.html(html);

    const val = (current === undefined || current === null || String(current).trim()==='') ? null : String(current);
    $s.val(val).trigger('change');
  }

  function showLegacyOption(field, legacyValue){
    const value = (legacyValue || '').toString().trim();
    if(!value) return;
    const $sel = $('#' + field);
    if(!$sel.find(`option[value="${value.replaceAll('"','\\"')}"]`).length){
      $sel.append(opt(value, `(No encontrado) ${value}`, value));
    }
    $sel.val(value).trigger('change');
    $(`#${field}_alert`).text(`No encontrado: ${value}`).show();
  }

  function clearLegacyAlerts(){
    ['estado','municipio','localidad'].forEach(field => $(`#${field}_alert`).hide().text(''));
  }

  function forzarPaisMEX(rawPais=''){
    const actual = (rawPais || '').toString().trim().toUpperCase();
    const mostrar = actual || 'MEX';
    $('#pais').val('MEX');
    $('#pais_display').val(mostrar);
    if(actual && actual !== 'MEX'){
      $('#pais_legacy_note').removeClass('d-none')
        .text(`Valor previo: ${mostrar}. Al guardar se forzará MEX.`);
    }else{
      $('#pais_legacy_note').addClass('d-none').text('');
    }
  }

  function cargarCatalogos(){
    return $.getJSON(URL_CTRL,{accion:'catalogos-form'}).then(resp=>{
      catalogos = resp || catalogos;
      fillSimple('#regimen_fiscal', catalogos.regimenes, 'ClaveRegimenFiscal', 'Descripcion');
      fillSimple('#uso_cdfi', catalogos.usos_cfdi, 'ClaveUsoCFDI', 'Descripcion');
      fillSimple('#estado', catalogos.entidades, 'cve_ent', 'nombre_ent');
      $('#municipio').html('<option value=""></option>').val(null).trigger('change');
      $('#localidad').html('<option value=""></option>').val(null).trigger('change');
    });
  }

  function cargarMunicipios(cveEnt, selected=''){
    if(!cveEnt){
      $('#municipio').html('<option value=""></option>').val(null).trigger('change');
      return $.Deferred().resolve().promise();
    }
    return $.getJSON(URL_CTRL,{accion:'municipios-por-entidad', cve_ent:cveEnt}).then(resp=>{
      fillSimple('#municipio', resp.data || [], 'cve_mun', 'nombre_mun', selected);
    });
  }

  function cargarLocalidades(cveEnt, cveMun, selected=''){
    if(!cveEnt || !cveMun){
      $('#localidad').html('<option value=""></option>').val(null).trigger('change');
      return $.Deferred().resolve().promise();
    }
    return $.getJSON(URL_CTRL,{accion:'localidades-por-municipio', cve_ent:cveEnt, cve_mun:cveMun}).then(resp=>{
      fillSimple('#localidad', resp.data || [], 'cve_loc', 'nombre_loc', selected);
    });
  }

  function resetForm(){
    $('#formRegistro')[0].reset();
    $('#row_key').val('');
    $('#id').val(''); // ✅ importante
    clearLegacyAlerts();
    forzarPaisMEX('MEX');

    fillSimple('#regimen_fiscal', catalogos.regimenes, 'ClaveRegimenFiscal', 'Descripcion');
    fillSimple('#uso_cdfi', catalogos.usos_cfdi, 'ClaveUsoCFDI', 'Descripcion');
    fillSimple('#estado', catalogos.entidades, 'cve_ent', 'nombre_ent');

    $('#municipio').html('<option value=""></option>').val(null).trigger('change');
    $('#localidad').html('<option value=""></option>').val(null).trigger('change');
  }

  async function precargarUbicacion(r){
    bloqueoEventos = true;
    clearLegacyAlerts();
    forzarPaisMEX(r.pais || '');

    const estadoSel = (r.estado_select || '').toString();
    const municipioSel = (r.municipio_select || '').toString();
    const localidadSel = (r.localidad_select || '').toString();

    $('#estado').val(estadoSel || null).trigger('change');
    await cargarMunicipios(estadoSel, municipioSel);
    await cargarLocalidades(estadoSel, municipioSel, localidadSel);

    if(!estadoSel) showLegacyOption('estado', r.estado_texto_fallback);
    if(!municipioSel) showLegacyOption('municipio', r.municipio_texto_fallback);
    if(!localidadSel) showLegacyOption('localidad', r.localidad_texto_fallback);

    bloqueoEventos = false;
  }

  function cargarRegistros(p){
    paginaActual = p;
    $.post(URL_CTRL,{
      accion:'listar',
      pagina:p,
      limite:limitePorPagina,
      rfc:$('#FiltroClave').val(),
      razon_social:$('#FiltroDescripcion').val()
    }, function(resp){
      const rows = resp?.data || [];
      const total = parseInt(resp?.total || 0, 10);
      let t = '';

      if(!rows.length){
        $('#emptyState').removeClass('d-none');
        t = '<tr><td colspan="8" class="text-center text-muted">— No hay registros —</td></tr>';
      }else{
        $('#emptyState').addClass('d-none');
        rows.forEach(v=>{
          const ubi = [v.estado,v.municipio,v.localidad].filter(Boolean).join(' / ') || '—';
          t += `<tr>
            <td>${v.id ?? '—'}</td>
            <td><b>${e(v.rfc || '')}</b></td>
            <td>${e(v.razon_social || '—')}</td>
            <td>${e(v.regimen_fiscal_descripcion || v.regimen_fiscal || '—')}</td>
            <td>${e(v.uso_cfdi_descripcion || v.uso_cfdi || '—')}</td>
            <td>${e(ubi)}</td>
            <td>${e(v.dom_fiscal_cp || '—')}</td>
            <td class="text-center">
              <a class="btn btn-light btn-sm accion-editar" href="#"
                 data-id="${v.id ?? ''}"
                 data-rfc="${e(v.rfc || '')}"
                 data-row="${e(v.row_key || '')}">
                 <i class="mdi mdi-square-edit-outline"></i>
              </a>
            </td>
          </tr>`;
        });
      }

      $('#tbodyRegistros').html(t);

      if(total === 0) $('#infoRegistros').text('No hay registros para mostrar');
      else $('#infoRegistros').text(`Mostrando ${(p-1)*limitePorPagina+1} a ${Math.min(p*limitePorPagina,total)} de ${total} registros`);

      configurarPaginacion(p,total,limitePorPagina);
    },'json');
  }

  function configurarPaginacion(currentPage,totalItems,itemsPerPage=10){
    const totalPages = Math.max(1, Math.ceil(totalItems/itemsPerPage));
    const $ul = $('#pagination');
    const maxVisiblePages = 5;

    $ul.empty();
    if(totalPages <= 1){ $ul.closest('nav').hide(); return; }
    $ul.closest('nav').show();

    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages/2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    if(endPage - startPage + 1 < maxVisiblePages) startPage = Math.max(1, endPage - maxVisiblePages + 1);

    if(currentPage > 1){
      $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="1">Primera</a></li>`);
      $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);
    }

    for(let i=startPage;i<=endPage;i++){
      $ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a></li>`);
    }

    if(currentPage < totalPages){
      $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);
      $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${totalPages}">Última</a></li>`);
    }

    $ul.off('click','a.page-link').on('click','a.page-link',function(ev){
      ev.preventDefault();
      const page = Number($(this).data('page'));
      if(Number.isFinite(page)) cargarRegistros(page);
    });
  }

  // INIT
  cargarCatalogos().then(function(){ cargarRegistros(1); });

  $(document).on('input','#rfc,#FiltroClave',function(){ this.value=this.value.toUpperCase(); });

  $(document).on('input change', '#FiltroClave,#FiltroDescripcion', function(){
    toggleCleanForInput(this);
    aplicarFiltrosDebounced();
  });

  refreshAllCleanIcons();

  $('#estado').on('change', async function(){
    if(bloqueoEventos) return;
    clearLegacyAlerts();
    const cveEnt = $(this).val();
    await cargarMunicipios(cveEnt);
    await cargarLocalidades('', '');
  });

  $('#municipio').on('change', async function(){
    if(bloqueoEventos) return;
    clearLegacyAlerts();
    await cargarLocalidades($('#estado').val(), $(this).val());
  });

  $('#btnNuevo').click(function(){
    resetForm();
    $('#tituloModal').text('Nuevo cliente SAT');
    $('#modalForm').modal('show');
  });

  $(document).on('click','a.accion-editar',function(ev){
    ev.preventDefault();
    $.getJSON(URL_CTRL,{
      accion:'detalle',
      id:$(this).data('id'),
      rfc:$(this).data('rfc'),
      row_key:$(this).data('row')
    }, async function(resp){
      const r = resp?.data || {};
      resetForm();

      // ✅ ahora sí: si viene id del backend, se setea (y se usará para actualizar)
      Object.keys(r).forEach(function(k){
        if($('#'+k).length && !['estado','municipio','localidad','pais','pais_display'].includes(k)){
          $('#'+k).val(r[k] ?? '');
        }
      });

      await precargarUbicacion(r);

      $('#tituloModal').text('Editar cliente SAT');
      $('#modalForm').modal('show');
    });
  });

  $('#formRegistro').submit(function(ev){
    ev.preventDefault();

    // ✅ Armar payload sin mandar id/row_key por defecto
    const payload = {};
    $(this).find('input,select').each(function(){
      if(!this.id) return;
      if(['id','row_key'].includes(this.id)) return; // ✅ NO enviar aquí
      payload[this.id] = $(this).val();
    });

    payload.pais = 'MEX';

    // ✅ Detectar crear/actualizar por id (ya es autoincrement)
    const idVal = ($('#id').val() || '').toString().trim();
    const accion = idVal ? 'actualizar' : 'crear';
    if(idVal) payload.id = idVal; // ✅ solo en actualizar

    $.ajax({
      url: URL_CTRL + '?accion=' + accion,
      method:'POST',
      data: JSON.stringify(payload),
      contentType:'application/json; charset=UTF-8',
      dataType:'json'
    })
    .done(function(r){
      if(r?.ok){
        $('#modalForm').modal('hide');
        toastr.success('Cliente SAT guardado correctamente.');
        cargarRegistros(accion === 'crear' ? 1 : paginaActual);
      }else{
        toastr.error(r?.msg || 'No se pudo guardar.');
      }
    })
    .fail(function(){
      toastr.error('Error al guardar.');
    });
  });

});
</script>

</body>
</html>
