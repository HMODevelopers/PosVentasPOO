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
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <style>.clean-filter{display:none;cursor:pointer}.mono{font-family:monospace}</style>
</head>
<body>
<?php include_once __DIR__ . '/../../../includes/header.php'; ?>
<div class="wrapper">
  <div class="wrapper-loader fade" id="LoadingImage" style="display: none;"><div class="loader"><div class="loader__figure"></div><p class="loader__label">Cargando...</p></div></div>
  <div class="container-fluid">
    <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

    <div class="card-header" style="border-color:darkgray; border-style:dotted;">
      <h5>Filtros</h5>
      <div class="row">
        <div class="col-md-3"><label>Sucursal</label><select id="fSucursal" class="form-control filtrar"></select></div>
        <div class="col-md-2"><label>RFC</label><div class="input-group"><input id="fRFC" class="form-control filtrar"/><div class="input-group-append clean-filter"><span class="input-group-text">X</span></div></div></div>
        <div class="col-md-3"><label>Razón social</label><div class="input-group"><input id="fRazon" class="form-control filtrar"/><div class="input-group-append clean-filter"><span class="input-group-text">X</span></div></div></div>
        <div class="col-md-2"><label>Ambiente</label><select id="fAmbiente" class="form-control filtrar"><option value="">Todos</option><option>DEMO</option><option>PROD</option></select></div>
        <div class="col-md-2"><label>Estatus</label><select id="fActivo" class="form-control filtrar"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select></div>
      </div>
    </div>

    <div class="card-box mt-3">
      <div class="d-flex justify-content-between align-items-center mb-2"><h4>Emisores CFDI</h4><button id="btnNuevo" class="btn btn-primary">Nuevo emisor</button></div>
      <div class="table-responsive">
        <table class="table table-striped table-bordered">
          <thead><tr><th>ID</th><th>Sucursal</th><th>Nombre emisor</th><th>RFC</th><th>Razón social</th><th>Ambiente</th><th>Estatus</th><th>Default</th><th>Acciones</th></tr></thead>
          <tbody id="tbody"></tbody>
        </table>
      </div>
      <div class="row"><div class="col-md-6" id="info"></div><div class="col-md-6"><ul id="paginacion" class="pagination justify-content-end"></ul></div></div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEmisor" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" id="formEmisor">
  <div class="modal-header"><h5 id="ttlModal">Nuevo emisor</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
  <div class="modal-body">
    <input type="hidden" name="id_config_fiscal_emisor" id="id_config_fiscal_emisor">
    <h6>A) EMISOR</h6><div class="row">
      <div class="col-md-4"><label>Sucursal</label><select class="form-control" name="id_sucursal" id="id_sucursal" required></select></div>
      <div class="col-md-4"><label>Nombre emisor</label><input class="form-control" name="nombre_emisor" required></div>
      <div class="col-md-4"><label>RFC</label><input class="form-control mono" name="rfc_emisor" required maxlength="13"></div>
      <div class="col-md-6"><label>Razón social</label><input class="form-control" name="razon_social_emisor" required></div>
      <div class="col-md-3"><label>Régimen fiscal</label><input class="form-control" name="regimen_fiscal_emisor"></div>
      <div class="col-md-3"><label>CP expedición</label><input class="form-control" name="cp_expedicion"></div>
      <div class="col-md-2"><label>Serie</label><input class="form-control" name="serie"></div>
      <div class="col-md-2"><label>Folio actual</label><input class="form-control" type="number" name="folio_actual" value="0"></div>
      <div class="col-md-3"><label>Tipo comp.</label><input class="form-control" name="tipo_comprobante" value="I"></div>
      <div class="col-md-3"><label>Exportación</label><input class="form-control" name="exportacion_default" value="01"></div>
      <div class="col-md-2"><label>Moneda</label><input class="form-control" name="moneda_default" value="MXN"></div>
      <div class="col-md-2"><label>Objeto imp.</label><input class="form-control" name="objeto_imp_default" value="02"></div>
    </div>
    <hr><h6>B) FOLIOS DIGITALES</h6><div class="row">
      <div class="col-md-3"><label>Ambiente</label><select class="form-control" name="fd_ambiente"><option>DEMO</option><option>PROD</option></select></div>
      <div class="col-md-4"><label>Usuario</label><input class="form-control" name="fd_usuario"></div>
      <div class="col-md-5"><label>Password</label><div class="input-group"><input class="form-control pwd" type="password" name="fd_password"><div class="input-group-append"><button class="btn btn-outline-secondary btnTogglePwd" type="button">Ver</button></div></div></div>
      <div class="col-md-6"><label>URL DEMO</label><input class="form-control" name="fd_url_demo"></div>
      <div class="col-md-6"><label>URL PROD</label><input class="form-control" name="fd_url_prod"></div>
    </div>
    <hr><h6>C) CERTIFICADOS</h6><div class="row">
      <div class="col-md-6"><label>CER Path</label><input class="form-control" name="csd_cer_path"></div>
      <div class="col-md-6"><label>KEY Path</label><input class="form-control" name="csd_key_path"></div>
      <div class="col-md-6"><label>KEY Password</label><div class="input-group"><input class="form-control pwd" type="password" name="csd_key_password"><div class="input-group-append"><button class="btn btn-outline-secondary btnTogglePwd" type="button">Ver</button></div></div></div>
      <div class="col-md-6"><label>PFX Path</label><input class="form-control" name="pfx_path"></div>
      <div class="col-md-6"><label>PFX Password</label><div class="input-group"><input class="form-control pwd" type="password" name="pfx_password"><div class="input-group-append"><button class="btn btn-outline-secondary btnTogglePwd" type="button">Ver</button></div></div></div>
    </div>
    <hr><h6>D) Logo</h6><textarea class="form-control" name="logo_base64" rows="2"></textarea>
    <div class="mt-2"><label><input type="checkbox" name="es_default" value="1"> Emisor default</label> &nbsp; <label><input type="checkbox" name="activo" value="1" checked> Activo</label></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
</form></div></div>

<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
<div class="rightbar-overlay"></div>
<script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
$(function(){
  const URL_AJAX = '<?= BASE_URL ?>/ajax/configuracion/emisores';
  let page=1, limit=10;
  cargarSucursales(); listar();

  function cargarSucursales(){
    $.getJSON('<?= BASE_URL ?>/controllers/SucursalesController.php', {accion:'listar-min'}, function(r){
      const arr = r.data||[]; let opt = '<option value="">Todas</option>';
      arr.forEach(x=>opt += `<option value="${x.id_sucursal}">${x.nombre}</option>`);
      $('#fSucursal').html(opt); $('#id_sucursal').html(opt.replace('Todas','Seleccione...'));
    });
  }

  function filtros(){ return {pagina:page, limite:limit, id_sucursal:$('#fSucursal').val(), rfc_emisor:$('#fRFC').val(), razon_social_emisor:$('#fRazon').val(), fd_ambiente:$('#fAmbiente').val(), activo:$('#fActivo').val()}; }
  function listar(){
    $.post(URL_AJAX + '/list.php', filtros(), function(resp){
      if(!resp.ok){ toastr.error(resp.message||'Error'); return; }
      const rows = resp.data.rows||[]; const total = parseInt(resp.data.total||0,10);
      let html='';
      rows.forEach(r=>{
        html += `<tr><td>${r.id_config_fiscal_emisor}</td><td>${r.sucursal_nombre||''}</td><td>${r.nombre_emisor||''}</td><td class="mono">${r.rfc_emisor||''}</td><td>${r.razon_social_emisor||''}</td><td>${r.fd_ambiente||''}</td><td>${parseInt(r.activo)===1?'Activo':'Inactivo'}</td><td>${parseInt(r.es_default)===1?'Sí':'No'}</td><td><button class="btn btn-sm btn-info btnEdit" data-id="${r.id_config_fiscal_emisor}">Editar</button> <button class="btn btn-sm ${parseInt(r.activo)===1?'btn-warning':'btn-success'} btnToggle" data-id="${r.id_config_fiscal_emisor}" data-act="${parseInt(r.activo)===1?0:1}">${parseInt(r.activo)===1?'Desactivar':'Activar'}</button> <button class="btn btn-sm btn-primary btnDefault" data-id="${r.id_config_fiscal_emisor}">Hacer default</button></td></tr>`;
      });
      if(!rows.length) html = '<tr><td colspan="9" class="text-center">Sin registros</td></tr>';
      $('#tbody').html(html); renderPag(total);
      const d=((page-1)*limit)+1,h=Math.min(page*limit,total); $('#info').text(total?`Mostrando ${d} a ${h} de ${total}`:'Sin datos');
    }, 'json');
  }
  function renderPag(total){ const pages=Math.max(1,Math.ceil(total/limit)); let html=''; for(let i=1;i<=pages;i++) html += `<li class="page-item ${i===page?'active':''}"><a class="page-link pg" data-p="${i}" href="#">${i}</a></li>`; $('#paginacion').html(pages>1?html:''); }

  $('.filtrar').on('change', ()=>{ page=1; listar(); }).on('keyup', function(){
    const has=$(this).val().trim().length>0; $(this).closest('.input-group').find('.clean-filter').toggle(has);
  }).on('keypress', function(e){ if(e.which===13){ page=1; listar(); }});
  $('.clean-filter').on('click', function(){ const inp=$(this).closest('.input-group').find('input'); inp.val('').trigger('change'); $(this).hide(); });

  $('#btnNuevo').click(function(){ $('#formEmisor')[0].reset(); $('#id_config_fiscal_emisor').val(''); $('#ttlModal').text('Nuevo emisor'); $('#modalEmisor').modal('show'); });
  $(document).on('click','.btnEdit', function(){
    const id=$(this).data('id');
    $.getJSON(URL_AJAX + '/get.php', {id_config_fiscal_emisor:id}, function(resp){
      if(!resp.ok){ toastr.error(resp.message); return; }
      const r=resp.data; $('#ttlModal').text('Editar emisor');
      Object.keys(r).forEach(k=>{ const $e=$(`[name="${k}"]`); if(!$e.length) return; if($e.attr('type')==='checkbox'){ $e.prop('checked', parseInt(r[k])===1); } else { $e.val(r[k]); }});
      $('#id_config_fiscal_emisor').val(r.id_config_fiscal_emisor); $('#modalEmisor').modal('show');
    });
  });

  $('#formEmisor').submit(function(e){
    e.preventDefault();
    const fd=$(this).serializeArray(); const payload={}; fd.forEach(x=>payload[x.name]=x.value);
    payload.es_default = $('[name="es_default"]').is(':checked') ? 1 : 0;
    payload.activo = $('[name="activo"]').is(':checked') ? 1 : 0;
    payload.rfc_emisor = (payload.rfc_emisor||'').toUpperCase().trim();
    const url = payload.id_config_fiscal_emisor ? '/update.php' : '/create.php';
    $.ajax({url:URL_AJAX+url,method:'POST',data:JSON.stringify(payload),contentType:'application/json',dataType:'json'})
      .done(resp=>{ if(resp.ok){ $('#modalEmisor').modal('hide'); toastr.success('Guardado correctamente'); listar(); } else toastr.error(resp.message||'Error'); })
      .fail(()=>toastr.error('Error al guardar'));
  });

  $(document).on('click','.btnToggle', function(){ const id=$(this).data('id'),activo=$(this).data('act'); $.ajax({url:URL_AJAX+'/toggle.php',method:'POST',contentType:'application/json',data:JSON.stringify({id_config_fiscal_emisor:id,activo}),dataType:'json'}).done(r=>{ if(r.ok){toastr.success('Estatus actualizado');listar();}else toastr.error(r.message); }); });
  $(document).on('click','.btnDefault', function(){ const id=$(this).data('id'); $.ajax({url:URL_AJAX+'/set_default.php',method:'POST',contentType:'application/json',data:JSON.stringify({id_config_fiscal_emisor:id}),dataType:'json'}).done(r=>{ if(r.ok){toastr.success('Default actualizado');listar();}else toastr.error(r.message); }); });
  $(document).on('click','.pg', function(e){ e.preventDefault(); page=parseInt($(this).data('p'),10); listar(); });
  $(document).on('click','.btnTogglePwd', function(){ const i=$(this).closest('.input-group').find('input'); i.attr('type', i.attr('type')==='password'?'text':'password'); });
});
</script>
</body>
</html>
