<?php $titulo='Catalogos'; $modulo='Clientes SAT'; $subtitulo=''; require_once __DIR__.'/../../../includes/auth.php'; require_once __DIR__.'/../../../includes/config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<title>Clientes SAT | REFASOFT-V4</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet"/>
<link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet"/>
<link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet"/>
<link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
.clean-filter{display:none;}.clean-filter .input-group-text{cursor:pointer;}
.form-section{border:1px solid #e5e7eb;border-radius:8px;padding:12px 12px 2px;margin-bottom:12px;background:#fafafa;}
.form-section h6{font-size:.9rem;font-weight:700;margin-bottom:10px;color:#374151;}
.form-group label{font-weight:600;}
.select2-container{width:100%!important;}
.fallback-wrap{display:none;}
.location-alert{display:none;}
.location-tools{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:8px;}
</style>
</head>
<body>
<?php include_once __DIR__.'/../../../includes/header.php'; ?>
<div class="wrapper"><div class="wrapper-loader fade" id="LoadingImage" style="display: none;"><div class="loader"><div class="loader__figure"></div><p class="loader__label">Cargando...</p></div></div>
<div class="container-fluid"><?php include_once __DIR__.'/../../../includes/breadcrumb.php'; ?>
<div class="card-header" style="border-color:darkgray; border-style:dotted;"><h5>Filtros</h5><div class="row"><div class="col-lg-12"><div class="row"><div class="col-md-4"><div class="form-group"><label for="FiltroClave">RFC</label><div class="input-group"><input id="FiltroClave" class="form-control filtrar"><div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroClave')"></i></span></div></div></div></div><div class="col-md-8"><div class="form-group"><label for="FiltroDescripcion">Razón social</label><div class="input-group"><input id="FiltroDescripcion" class="form-control filtrar"><div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroDescripcion')"></i></span></div></div></div></div></div></div></div></div>
<div class="row mt-3"><div class="col-12"><div class="card-box"><div class="d-flex justify-content-between align-items-center mb-2"><h4 class="header-title">Catálogo Clientes SAT</h4><button id="btnNuevo" class="btn btn-primary"><i class="mdi mdi-plus"></i> Nuevo cliente SAT</button></div><div id="emptyState" class="alert alert-warning d-none mb-2">No hay registros que coincidan con los filtros.</div><div class="table-responsive"><table class="table table-bordered table-hover table-striped"><thead><tr><th>ID</th><th>RFC</th><th>Razón social</th><th>Régimen fiscal</th><th>Uso CFDI</th><th>Ubicación</th><th>CP</th><th style="width:110px">Acciones</th></tr></thead><tbody id="tbodyRegistros"></tbody></table></div><div class="row align-items-center justify-content-between mt-2"><div class="col-md-6"><div id="infoRegistros" class="dataTables_info"></div></div><div class="col-md-6 d-flex justify-content-end"><nav><ul id="pagination" class="pagination justify-content-end mb-0"></ul></nav></div></div></div></div></div>
</div></div>

<div class="modal fade" id="modalForm"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 id="tituloModal">Nuevo cliente SAT</h5><button class="close" data-dismiss="modal"><span>&times;</span></button></div>
<form id="formRegistro"><input type="hidden" id="row_key"><div class="modal-body">
<div class="form-section"><h6>A) Identificación fiscal</h6><div class="row">
<div class="col-12 col-md-6"><div class="form-group"><label>ID</label><input id="id" type="number" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>RFC</label><input id="rfc" class="form-control" maxlength="13"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Razón social</label><input id="razon_social" class="form-control" maxlength="118"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Nombre comercial</label><input id="nombre_comercial" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Régimen fiscal</label><select id="regimen_fiscal" class="form-control"></select></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Uso CFDI</label><select id="uso_cdfi" class="form-control"></select></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Domicilio fiscal CP</label><input id="dom_fiscal_cp" class="form-control"></div></div>
</div></div>

<div class="form-section"><h6>B) Ubicación</h6><div class="row">
<div class="col-12 col-md-6"><div class="form-group"><label>Entidad</label><select id="estado" class="form-control"></select><div class="location-tools"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input manual-toggle" id="estado_manual_toggle" data-field="estado"><label class="custom-control-label" for="estado_manual_toggle">Editar manual</label></div></div><div class="alert alert-warning py-1 px-2 mt-2 mb-0 location-alert" id="estado_alert">No encontrado en catálogo. Se conservará como texto manual.</div><div class="fallback-wrap mt-2" id="estado_fallback_wrap"><input id="estado_fallback" class="form-control" placeholder="Valor actual de estado"></div></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Municipio</label><select id="municipio" class="form-control"></select><div class="location-tools"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input manual-toggle" id="municipio_manual_toggle" data-field="municipio"><label class="custom-control-label" for="municipio_manual_toggle">Editar manual</label></div></div><div class="alert alert-warning py-1 px-2 mt-2 mb-0 location-alert" id="municipio_alert">No encontrado en catálogo. Se conservará como texto manual.</div><div class="fallback-wrap mt-2" id="municipio_fallback_wrap"><input id="municipio_fallback" class="form-control" placeholder="Valor actual de municipio"></div></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Localidad</label><select id="localidad" class="form-control"></select><div class="location-tools"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input manual-toggle" id="localidad_manual_toggle" data-field="localidad"><label class="custom-control-label" for="localidad_manual_toggle">Editar manual</label></div></div><div class="alert alert-warning py-1 px-2 mt-2 mb-0 location-alert" id="localidad_alert">No encontrado en catálogo. Se conservará como texto manual.</div><div class="fallback-wrap mt-2" id="localidad_fallback_wrap"><input id="localidad_fallback" class="form-control" placeholder="Valor actual de localidad"></div></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Colonia</label><input id="colonia" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Calle</label><input id="calle" class="form-control"></div></div>
<div class="col-12 col-md-3"><div class="form-group"><label>Número exterior</label><input id="numero_exterior" class="form-control"></div></div>
<div class="col-12 col-md-3"><div class="form-group"><label>Número interior</label><input id="numero_interior" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Referencia</label><input id="referencia" class="form-control"></div></div>
</div></div>

<div class="form-section"><h6>C) Contacto / extras</h6><div class="row">
<div class="col-12 col-md-6"><div class="form-group"><label>Email</label><input id="email" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Email alterno</label><input id="email_alterno" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Teléfono</label><input id="telefono" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Celular</label><input id="celular" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>País</label><input id="pais" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Residencia fiscal</label><input id="residencia_fiscal" class="form-control"></div></div>
<div class="col-12 col-md-6"><div class="form-group"><label>Número registro tributario</label><input id="numero_registro_tributario" class="form-control"></div></div>
</div></div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div></form></div></div></div>

<?php include_once __DIR__.'/../../../includes/footer.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script><script src="<?= BASE_URL ?>/assets/js/app.min.js"></script><script src="<?= BASE_URL ?>/assets/js/loader.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
$(function(){
  let paginaActual=1;const limitePorPagina=10;const URL_CTRL='<?= BASE_URL ?>/controllers/ClientesSatController.php';
  const e=s=>String(s??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  let catalogos={entidades:[],regimenes:[],usos_cfdi:[]};
  let bloqueoEventos=false;

  function iniSelect2(){ if($.fn.select2){ $('#modalForm select').select2({width:'100%',dropdownParent:$('#modalForm'),placeholder:'Seleccione'}); } }
  function opt(v,t,sel=''){ return `<option value="${e(v)}" ${sel===v?'selected':''}>${e(t)}</option>`; }
  function fillSimple(sel,items,valKey,textKey,current=''){ let html='<option value="">Seleccione...</option>'; items.forEach(it=>html+=opt(it[valKey],`${it[valKey]} - ${it[textKey]}`,current)); $(sel).html(html).val(current).trigger('change.select2'); }
  function codEntidad(v){return (v||'').toString().slice(0,2);} function codMunicipio(v){return (v||'').toString().slice(2,5);}

  function setManualState(field,{manual=false,text='',warning=false}={}){
    const wrap=$(`#${field}_fallback_wrap`); const inp=$(`#${field}_fallback`); const alert=$(`#${field}_alert`); const chk=$(`#${field}_manual_toggle`);
    chk.prop('checked',manual);
    if(text!==undefined) inp.val(text||'');
    inp.prop('disabled',!manual);
    wrap.toggle(!!manual);
    alert.toggle(!!warning);
  }

  function getFinalGeoValue(field){
    const selectVal=$(`#${field}`).val();
    const manualOn=$(`#${field}_manual_toggle`).is(':checked');
    const manualVal=$(`#${field}_fallback`).val();
    if(!manualOn && (selectVal||'').toString().trim()!=='') return selectVal;
    if((manualVal||'').toString().trim()!=='') return manualVal;
    return selectVal||'';
  }

  function cargarCatalogos(){
    return $.getJSON(URL_CTRL,{accion:'catalogos_form'}).then(resp=>{
      catalogos=resp||catalogos;
      fillSimple('#regimen_fiscal',catalogos.regimenes||[],'ClaveRegimenFiscal','Descripcion');
      fillSimple('#uso_cdfi',catalogos.usos_cfdi||[],'ClaveUsoCFDI','Descripcion');
      fillSimple('#estado',catalogos.entidades||[],'cvegeo','nombre_ent');
      $('#municipio').html('<option value="">Seleccione entidad...</option>').trigger('change.select2');
      $('#localidad').html('<option value="">Seleccione municipio...</option>').trigger('change.select2');
    });
  }

  function cargarMunicipios(cveEnt,selected=''){
    if(!cveEnt){$('#municipio').html('<option value="">Seleccione entidad...</option>').trigger('change.select2');return $.Deferred().resolve().promise();}
    return $.getJSON(URL_CTRL,{accion:'municipios_por_entidad',cve_ent:cveEnt}).then(resp=>{fillSimple('#municipio',resp.data||[],'cvegeo','nombre_mun',selected);});
  }

  function cargarLocalidades(cveEnt,cveMun,selected=''){
    if(!cveEnt||!cveMun){$('#localidad').html('<option value="">Seleccione municipio...</option>').trigger('change.select2');return $.Deferred().resolve().promise();}
    return $.getJSON(URL_CTRL,{accion:'localidades_por_municipio',cve_ent:cveEnt,cve_mun:cveMun}).then(resp=>{fillSimple('#localidad',resp.data||[],'cvegeo','nombre_loc',selected);});
  }

  function resetForm(){
    $('#formRegistro')[0].reset(); $('#row_key').val('');
    fillSimple('#regimen_fiscal',catalogos.regimenes||[],'ClaveRegimenFiscal','Descripcion');
    fillSimple('#uso_cdfi',catalogos.usos_cfdi||[],'ClaveUsoCFDI','Descripcion');
    fillSimple('#estado',catalogos.entidades||[],'cvegeo','nombre_ent');
    $('#municipio').html('<option value="">Seleccione entidad...</option>').trigger('change.select2');
    $('#localidad').html('<option value="">Seleccione municipio...</option>').trigger('change.select2');
    ['estado','municipio','localidad'].forEach(k=>setManualState(k,{manual:false,text:'',warning:false}));
  }

  async function precargarUbicacion(r){
    bloqueoEventos=true;
    const estadoSel=(r.estado_select||'').toString();
    const municipioSel=(r.municipio_select||'').toString();
    const localidadSel=(r.localidad_select||'').toString();
    $('#estado').val(estadoSel).trigger('change.select2');
    await cargarMunicipios(codEntidad(estadoSel),municipioSel);
    await cargarLocalidades(codEntidad(municipioSel||estadoSel),codMunicipio(municipioSel),localidadSel);

    const estadoTxt=(r.estado_texto_fallback||'').toString();
    const municipioTxt=(r.municipio_texto_fallback||'').toString();
    const localidadTxt=(r.localidad_texto_fallback||'').toString();
    setManualState('estado',{manual:estadoTxt.trim()!=='' ,text:estadoTxt,warning:estadoTxt.trim()!==''});
    setManualState('municipio',{manual:municipioTxt.trim()!=='' ,text:municipioTxt,warning:municipioTxt.trim()!==''});
    setManualState('localidad',{manual:localidadTxt.trim()!=='' ,text:localidadTxt,warning:localidadTxt.trim()!==''});
    bloqueoEventos=false;
  }

  function cargarRegistros(p){paginaActual=p;$.post(URL_CTRL,{accion:'listar',pagina:p,limite:limitePorPagina,rfc:$('#FiltroClave').val(),razon_social:$('#FiltroDescripcion').val()},function(resp){const rows=resp?.data||[];const total=parseInt(resp?.total||0,10);let t='';if(!rows.length){$('#emptyState').removeClass('d-none');t='<tr><td colspan="8" class="text-center text-muted">— No hay registros —</td></tr>';}else{$('#emptyState').addClass('d-none');rows.forEach(v=>{const ubi=[v.estado,v.municipio,v.localidad].filter(Boolean).join(' / ')||'—';t+=`<tr><td>${v.id??'—'}</td><td><b>${e(v.rfc||'')}</b></td><td>${e(v.razon_social||'—')}</td><td>${e(v.regimen_fiscal_descripcion||v.regimen_fiscal||'—')}</td><td>${e(v.uso_cfdi_descripcion||v.uso_cdfi||'—')}</td><td>${e(ubi)}</td><td>${e(v.dom_fiscal_cp||'—')}</td><td class="text-center"><a class="btn btn-light btn-sm accion-editar" href="#" data-id="${v.id??''}" data-rfc="${e(v.rfc||'')}" data-row="${e(v.row_key||'')}"><i class="mdi mdi-square-edit-outline"></i></a></td></tr>`;});}$('#tbodyRegistros').html(t);if(total===0)$('#infoRegistros').text('No hay registros para mostrar');else $('#infoRegistros').text(`Mostrando ${(p-1)*limitePorPagina+1} a ${Math.min(p*limitePorPagina,total)} de ${total} registros`);configurarPaginacion(p,total,limitePorPagina);},'json');}

  function configurarPaginacion(currentPage,totalItems,itemsPerPage=10){const totalPages=Math.max(1,Math.ceil(totalItems/itemsPerPage));const $ul=$('#pagination');const maxVisiblePages=5;$ul.empty();if(totalPages<=1){$ul.closest('nav').hide();return;}else{$ul.closest('nav').show();}let startPage=Math.max(1,currentPage-Math.floor(maxVisiblePages/2));let endPage=Math.min(totalPages,startPage+maxVisiblePages-1);if(endPage-startPage+1<maxVisiblePages)startPage=Math.max(1,endPage-maxVisiblePages+1);if(currentPage>1){$ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="1">Primera</a></li>`);$ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);}for(let i=startPage;i<=endPage;i++){$ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a></li>`);}if(currentPage<totalPages){$ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);$ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${totalPages}">Última</a></li>`);} $ul.off('click','a.page-link').on('click','a.page-link',function(ev){ev.preventDefault();const page=Number($(this).data('page'));if(Number.isFinite(page)){paginaActual=page;cargarRegistros(paginaActual);}});}

  window.clearField=function(id){const el=document.getElementById(id);if(!el)return;el.value='';$(el).trigger('change');};

  iniSelect2();
  cargarCatalogos().then(()=>cargarRegistros(1));
  $(document).on('input','#rfc,#FiltroClave',function(){this.value=this.value.toUpperCase();});
  $('.filtrar').on('change keyup',()=>setTimeout(()=>cargarRegistros(1),200));

  $('#estado').on('change',async function(){
    if(bloqueoEventos) return;
    const cveEnt=$(this).val();
    await cargarMunicipios(cveEnt);
    await cargarLocalidades('','');
    setManualState('municipio',{manual:false,text:'',warning:false});
    setManualState('localidad',{manual:false,text:'',warning:false});
  });

  $('#municipio').on('change',async function(){
    if(bloqueoEventos) return;
    const mun=$(this).val(); const ent=codEntidad(mun)||$('#estado').val();
    await cargarLocalidades(ent,codMunicipio(mun));
    setManualState('localidad',{manual:false,text:'',warning:false});
  });

  $('.manual-toggle').on('change',function(){
    const field=$(this).data('field');
    const active=$(this).is(':checked');
    const currentText=$(`#${field}_fallback`).val()||$(`#${field}`).find('option:selected').text();
    setManualState(field,{manual:active,text:active?currentText:'',warning:false});
  });

  $('#btnNuevo').click(()=>{resetForm();$('#tituloModal').text('Nuevo cliente SAT');$('#modalForm').modal('show');});

  $(document).on('click','a.accion-editar',function(ev){ev.preventDefault();
    $.getJSON(URL_CTRL,{accion:'detalle',id:$(this).data('id'),rfc:$(this).data('rfc'),row_key:$(this).data('row')},async resp=>{
      const r=resp?.data||{}; resetForm(); Object.keys(r).forEach(k=>{ if($('#'+k).length && !['estado','municipio','localidad'].includes(k)) $('#'+k).val(r[k]??'');});
      await precargarUbicacion(r);
      $('#tituloModal').text('Editar cliente SAT');$('#modalForm').modal('show');
    });
  });

  $('#formRegistro').submit(function(ev){ev.preventDefault();const payload={};
    $(this).find('input,select').each(function(){if(this.id && !this.id.endsWith('_fallback') && !this.id.endsWith('_manual_toggle'))payload[this.id]=$(this).val();});
    ['estado','municipio','localidad'].forEach(k=>payload[k]=getFinalGeoValue(k));
    const accion=payload.row_key?'actualizar':'crear';
    $.ajax({url:URL_CTRL+'?accion='+accion,method:'POST',data:JSON.stringify(payload),contentType:'application/json; charset=UTF-8',dataType:'json'})
      .done(r=>{if(r?.ok){$('#modalForm').modal('hide');toastr.success('Cliente SAT guardado correctamente.');cargarRegistros(accion==='crear'?1:paginaActual);}else toastr.error(r?.msg||'No se pudo guardar.');})
      .fail(()=>toastr.error('Error al guardar.'));
  });

});
</script>
</body>
</html>
