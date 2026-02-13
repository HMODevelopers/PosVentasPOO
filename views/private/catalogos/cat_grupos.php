<?php
$titulo = "Catalogos";
$modulo = "Grupos";
$subtitulo = "";
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Grupos | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <style>.clean-filter{display:none;}.clean-filter .input-group-text{cursor:pointer;}</style>
</head>
<body>
<?php include_once __DIR__ . '/../../../includes/header.php'; ?>
<div class="wrapper"><div class="wrapper-loader fade" id="LoadingImage" style="display: none;">
  <div class="loader">
    <div class="loader__figure"></div>
    <p class="loader__label">Cargando...</p>
  </div>
</div><div class="container-fluid">
<?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>
<div class="card-header" style="border-color:darkgray; border-style:dotted;">
  <h5>Filtros</h5>
  <div class="row"><div class="col-lg-12"><div class="row">
    <div class="col-md-4"><div class="form-group"><label for="FiltroClave" class="control-label">Clave H</label><div class="input-group"><input type="text" id="FiltroClave" class="form-control filtrar" placeholder="Clave SAT"><div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroClave')"></i></span></div></div></div></div>
    <div class="col-md-8"><div class="form-group"><label for="FiltroDescripcion" class="control-label">Nombre grupo</label><div class="input-group"><input type="text" id="FiltroDescripcion" class="form-control filtrar" placeholder="Nombre del grupo"><div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroDescripcion')"></i></span></div></div></div></div>
  </div></div></div>
</div>
<div class="row mt-3"><div class="col-12"><div class="card-box">
  <div class="d-flex justify-content-between align-items-center mb-2"><h4 class="header-title">Catálogo de Grupos</h4><button id="btnNuevo" class="btn btn-primary"><i class="mdi mdi-plus"></i> Nuevo grupo</button></div>
  <div id="emptyState" class="alert alert-warning d-none mb-2">No hay registros que coincidan con los filtros.</div>
  <div class="table-responsive"><table class="table table-bordered table-hover table-striped"><thead><tr><th class="text-center" style="width:80px;">ID</th><th>Nombre</th><th>Clave H</th><th>Desc H</th><th>Observaciones</th><th class="text-center" style="width:110px;">Acciones</th></tr></thead><tbody id="tbodyRegistros"></tbody></table></div>
  <div class="row align-items-center justify-content-between mt-2"><div class="col-md-6"><div id="infoRegistros" class="dataTables_info"></div></div><div class="col-md-6 d-flex justify-content-end"><nav><ul id="pagination" class="pagination justify-content-end mb-0"></ul></nav></div></div>
</div></div></div>
</div></div>
<div class="modal fade" id="modalForm" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="tituloModal">Nuevo grupo</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><form id="formRegistro"><input type="hidden" id="id_grupo"><div class="modal-body"><div class="row"><div class="col-md-6"><label>Nombre grupo</label><input id="nombre_grupo" class="form-control" required></div><div class="col-md-3"><label>Clave H</label><input id="clave_h" class="form-control"></div><div class="col-md-3"><label>Fecha SAT</label><input id="fecha_sat" type="date" class="form-control"></div><div class="col-md-6 mt-2"><label>Desc H</label><input id="desc_h" class="form-control"></div><div class="col-md-6 mt-2"><label>Observaciones</label><input id="observaciones" class="form-control"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form></div></div></div>
<div class="modal fade" id="modalEliminar" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5>Eliminar grupo</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><p>¿Seguro que deseas eliminar <strong id="delNombre"></strong>?</p></div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-danger" id="btnConfirmEliminar">Eliminar</button></div></div></div></div>
<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script><script src="<?= BASE_URL ?>/assets/js/app.min.js"></script><script src="<?= BASE_URL ?>/assets/js/loader.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
$(function(){
  let paginaActual=1; const limitePorPagina=10; const URL_CTRL='<?= BASE_URL ?>/controllers/CatGruposController.php';
  const escapeHtml=(s)=>(s==null?'':String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;')); const htmlAttr=(s)=>escapeHtml(s).replaceAll('"','&quot;');
  cargarRegistros(1);
  $('.filtrar').change(function(){const $el=$(this);if(($el.is(':checkbox')&&$el.is(':checked'))||($el.val()&&$el.val().length>0))$el.closest('.input-group, .form-group').find('.clean-filter').css({display:'flex'});else $el.closest('.input-group, .form-group').find('.clean-filter').css({display:'none'});$el.blur();setTimeout(()=>cargarRegistros(1),200);}).keypress(function(e){if(e.charCode==13)cargarRegistros(1);}).keyup(function(){if($(this).val().length>0)$(this).closest('.input-group, .form-group').find('.clean-filter').css({display:'flex'});else $(this).closest('.input-group, .form-group').find('.clean-filter').css({display:'none'});});
  $('.clean-filter').click(function(){const $el=$(this).closest('.input-group, .form-group').find('.filtrar'); if($el.is(':checkbox')){$el.prop('checked',false).trigger('change');}else{$el.val('').trigger('change');} cargarRegistros(1);});
  $('#btnNuevo').on('click',function(){$('#formRegistro')[0].reset();$('#id_grupo').val('');$('#tituloModal').text('Nuevo grupo');$('#modalForm').modal('show');});
  $(document).on('click','a.accion-editar',function(e){e.preventDefault();const id=$(this).data('id');$.getJSON(URL_CTRL,{accion:'detalle',id_grupo:id},function(resp){const r=resp?.data||{};Object.keys(r).forEach(k=>$('#'+k).val(r[k]??''));$('#tituloModal').text('Editar grupo');$('#modalForm').modal('show');});});
  $('#formRegistro').on('submit',function(e){e.preventDefault();const id=$('#id_grupo').val();const payload={id_grupo:id||undefined,nombre_grupo:$('#nombre_grupo').val().trim(),clave_h:$('#clave_h').val().trim(),desc_h:$('#desc_h').val().trim(),observaciones:$('#observaciones').val().trim(),fecha_sat:$('#fecha_sat').val().trim()};const accion=id?'actualizar':'crear';$.ajax({url:URL_CTRL+'?accion='+accion,method:'POST',data:JSON.stringify(payload),contentType:'application/json; charset=UTF-8',dataType:'json'}).done(r=>{if(r?.ok||r?.id_grupo>0){$('#modalForm').modal('hide');toastr.success('Grupo guardado correctamente.');cargarRegistros(id?paginaActual:1);}else toastr.error(r?.msg||'No se pudo guardar.');}).fail(()=>toastr.error('Error al guardar.'));});
  $(document).on('click','a.accion-eliminar',function(e){e.preventDefault();$('#btnConfirmEliminar').data('id',$(this).data('id'));$('#delNombre').text($(this).data('nombre')||'');$('#modalEliminar').modal('show');});
  $('#btnConfirmEliminar').on('click',function(){$.post(URL_CTRL,{accion:'eliminar',id_grupo:$(this).data('id')},function(r){if(r?.ok){$('#modalEliminar').modal('hide');toastr.success('Grupo eliminado.');cargarRegistros(paginaActual);}else toastr.error(r?.msg||'No se pudo eliminar.');},'json');});
  function cargarRegistros(pagina){paginaActual=pagina;$.ajax({url:URL_CTRL,method:'POST',dataType:'json',data:{accion:'listar',pagina,limite:limitePorPagina,clave_h:$('#FiltroClave').val(),nombre_grupo:$('#FiltroDescripcion').val()}}).done(function(resp){const arr=resp?.data||[];const total=parseInt(resp?.total||0,10);renderizarTabla(arr);if(total===0){$('#infoRegistros').text('No hay registros para mostrar');}else{const desde=(pagina-1)*limitePorPagina+1;const hasta=Math.min(pagina*limitePorPagina,total);$('#infoRegistros').text(`Mostrando ${desde} a ${hasta} de ${total} registros`);}configurarPaginacion(pagina,total,limitePorPagina);}).fail(()=>toastr.error('Error al cargar datos.'));}
  function renderizarTabla(rows){let tbody='';if(!rows.length){$('#emptyState').removeClass('d-none');tbody='<tr><td colspan="6" class="text-center text-muted">— No hay registros —</td></tr>';}else{$('#emptyState').addClass('d-none');rows.forEach(v=>{tbody+=`<tr><td class="text-center"><b>${v.id_grupo??''}</b></td><td>${escapeHtml(v.nombre_grupo||'—')}</td><td>${escapeHtml(v.clave_h||'—')}</td><td>${escapeHtml(v.desc_h||'—')}</td><td>${escapeHtml(v.observaciones||'—')}</td><td class="text-center"><div class="btn-group dropdown"><a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown"><i class="mdi mdi-dots-horizontal"></i></a><div class="dropdown-menu dropdown-menu-right"><a class="dropdown-item accion-editar" href="#" data-id="${v.id_grupo}"><i class="mdi mdi-square-edit-outline mr-2 text-muted font-18 vertical-middle"></i>Editar</a><a class="dropdown-item accion-eliminar" href="#" data-id="${v.id_grupo}" data-nombre="${htmlAttr(v.nombre_grupo||'')}"><i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Eliminar</a></div></div></td></tr>`;});}$('#tbodyRegistros').html(tbody);}
  function configurarPaginacion(currentPage,totalItems,itemsPerPage=10){const totalPages=Math.max(1,Math.ceil(totalItems/itemsPerPage));const $ul=$('#pagination');const maxVisiblePages=5;$ul.empty();if(totalPages<=1){$ul.closest('nav').hide();return;}else{$ul.closest('nav').show();}let startPage=Math.max(1,currentPage-Math.floor(maxVisiblePages/2));let endPage=Math.min(totalPages,startPage+maxVisiblePages-1);if(endPage-startPage+1<maxVisiblePages)startPage=Math.max(1,endPage-maxVisiblePages+1);if(currentPage>1){$ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="1">Primera</a></li>`);$ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);}for(let i=startPage;i<=endPage;i++){$ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a></li>`);}if(currentPage<totalPages){$ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);$ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${totalPages}">Última</a></li>`);}$ul.off('click','a.page-link').on('click','a.page-link',function(e){e.preventDefault();const page=Number($(this).data('page'));if(Number.isFinite(page)){paginaActual=page;cargarRegistros(paginaActual);}});}
});
function clearField(id){const el=document.getElementById(id);if(!el)return; if(el.type==='checkbox'){el.checked=false;}else{el.value='';} el.dispatchEvent(new Event('change'));}
</script>

<script>
function syncClear(inputEl){
  if(!inputEl) return;
  const $input=$(inputEl);
  const hasValue=($input.val()||'').toString().trim().length>0;
  $input.closest('.input-group, .form-group').find('.clean-filter').css({display:hasValue?'flex':'none'});
}

function clearField(id){
  const el=document.getElementById(id);
  if(!el) return;
  if(el.type==='checkbox'){
    el.checked=false;
  }else{
    el.value='';
  }
  syncClear(el);
  el.dispatchEvent(new Event('input'));
  el.dispatchEvent(new Event('change'));
}

$(function(){
  $('.filtrar').each(function(){ syncClear(this); });
  $(document).on('input change blur', '.filtrar', function(){ syncClear(this); });
  $(document).on('click', '.clean-filter', function(){
    const input=this.closest('.input-group, .form-group')?.querySelector('.filtrar');
    if(input){ setTimeout(function(){ syncClear(input); }, 0); }
  });
});
</script>
</body></html>
