<?php
$titulo = "Facturación múltiple";
$modulo = "Facturar varios tickets";
$subtitulo = "";
session_start();
$SESSION_LIFETIME = 10 * 60 * 60;
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../../../includes/config.php';
if (!isset($_SESSION['usuario'])) {
  header('Location: ' . BASE_URL . '/views/public/index.php');
  exit();
}
$sessionStart = $_SESSION['SESSION_START'] ?? 0;
$sessionTTL = $_SESSION['SESSION_TTL'] ?? $SESSION_LIFETIME;
if ($sessionStart === 0 || (time() - $sessionStart) > $sessionTTL) {
  session_unset();
  session_destroy();
  header('Location: ' . BASE_URL . '/views/public/index.php?expired=1');
  exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Facturación múltiple | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/libs/select2/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>
  <style>
    #facturacionMultipleScreen .cfdi-section {
      background: #fff;
      border: 1px solid #dfe7f1;
      border-radius: 0.75rem;
      box-shadow: 0 0.25rem 1rem rgba(31, 45, 61, 0.05);
      margin-bottom: 1rem;
      overflow: hidden;
    }
    #facturacionMultipleScreen .cfdi-section__head {
      align-items: center;
      background: #f4f7fb;
      border-bottom: 1px solid #dfe7f1;
      display: flex;
      justify-content: space-between;
      padding: 0.9rem 1rem;
    }
    #facturacionMultipleScreen .cfdi-section__title {
      font-size: 0.98rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      margin: 0;
      text-transform: uppercase;
    }
    #facturacionMultipleScreen .cfdi-section__body { padding: 1rem; }
    #facturacionMultipleScreen .cfdi-kv { margin-bottom: 0.85rem; }
    #facturacionMultipleScreen .cfdi-kv small {
      color: #7a8797;
      display: block;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      margin-bottom: 0.2rem;
      text-transform: uppercase;
    }
    #facturacionMultipleScreen .cfdi-kv strong,
    #facturacionMultipleScreen .cfdi-kv span { color: #1f2d3d; display: block; word-break: break-word; }
    #facturacionMultipleScreen .cfdi-total-box { background: #f4f7fb; border: 1px solid #dfe7f1; border-radius: 0.75rem; padding: 1rem; }
    #facturacionMultipleScreen .cfdi-total-row { align-items: center; display: flex; justify-content: space-between; margin-bottom: 0.55rem; }
    #facturacionMultipleScreen .cfdi-total-row:last-child { margin-bottom: 0; }
    #facturacionMultipleScreen .cfdi-total-row--grand { border-top: 1px dashed #cfd8e3; margin-top: 0.8rem; padding-top: 0.8rem; }
    #facturacionMultipleScreen .cfdi-total-row--grand strong:last-child { font-size: 1.1rem; }
    #facturacionMultipleScreen .table td, #facturacionMultipleScreen .table th { vertical-align: middle; }
    #facturacionMultipleScreen .select2-container { width: 100% !important; }
    #facturacionMultipleScreen .cfdi-helper { color: #7a8797; font-size: 0.78rem; }
    #facturacionMultipleScreen .cfdi-select2-option { color: #1f2d3d; display: flex; flex-direction: column; gap: 0.2rem; line-height: 1.35; }
    #facturacionMultipleScreen .cfdi-select2-option__title { font-weight: 700; }
    #facturacionMultipleScreen .cfdi-select2-option__meta,
    #facturacionMultipleScreen .cfdi-select2-option__secondary { font-size: 0.8125rem; }
    #facturacionMultipleScreen .cfdi-block-status { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 1rem; }
    #facturacionMultipleScreen .cfdi-block-status__item { background: #f8fafc; border: 1px solid #dfe7f1; border-radius: 0.75rem; padding: 0.85rem 0.95rem; }
    #facturacionMultipleScreen .cfdi-block-status__item.is-complete { border-color: #b7ebc6; background: #f0fff4; }
    #facturacionMultipleScreen .cfdi-block-status__item.is-incomplete { border-color: #f5c2c7; background: #fff5f5; }
    #facturacionMultipleScreen .cfdi-block-status__title { align-items: center; display: flex; font-size: 0.84rem; font-weight: 700; justify-content: space-between; margin-bottom: 0.35rem; text-transform: uppercase; }
    #facturacionMultipleScreen .cfdi-block-status__desc { color: #526273; font-size: 0.8rem; margin: 0; }
    #facturacionMultipleScreen .cfdi-validation-list { display: grid; gap: 0.6rem; list-style: none; margin: 0; padding: 0; }
    #facturacionMultipleScreen .cfdi-validation-list__item { background: #fff8e6; border: 1px solid #ffe08a; border-radius: 0.75rem; color: #6b5300; font-size: 0.85rem; padding: 0.75rem 0.9rem; }
    #facturacionMultipleScreen .cfdi-validation-list__item strong { color: #5a4300; display: block; font-size: 0.78rem; letter-spacing: 0.04em; margin-bottom: 0.2rem; text-transform: uppercase; }
    #facturacionMultipleScreen .cfdi-validation-list__item.is-success { background: #f0fff4; border-color: #b7ebc6; color: #1f6f43; }
    #modalTicketsFacturacion .pagination .page-link { cursor: pointer; }
  </style>
</head>
<body>
<?php include_once __DIR__ . '/../../../includes/header.php'; ?>
<div class="wrapper">
  <div class="container-fluid">
    <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

    <div id="facturacionMultipleScreen">
      <form id="formFacturarVenta" autocomplete="off">
        <input type="hidden" id="fac-id-venta" name="id_venta">
        <input type="hidden" id="fac-id-cliente-sat" name="id_cliente_sat">

        <div id="fac-loader" class="text-center py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <div class="mt-2 text-muted">Cargando información de facturación...</div>
        </div>

        <div id="fac-error" class="alert alert-danger d-none mb-3"></div>
        <div id="fac-warning" class="alert alert-warning d-none mb-3"></div>
        <div id="fac-success" class="alert alert-success d-none mb-3"></div>

        <div id="fac-contenido" class="d-none">
          <div class="cfdi-section">
            <div class="cfdi-section__head">
              <h6 class="cfdi-section__title mb-0">1. Emisor</h6>
              <span id="fac-estatus-fiscal">—</span>
            </div>
            <div class="cfdi-section__body">
              <div class="row">
                <div class="col-md-3 col-sm-6"><div class="cfdi-kv"><small>RFC emisor</small><strong id="fac-emisor-rfc">—</strong></div></div>
                <div class="col-md-5 col-sm-6"><div class="cfdi-kv"><small>Razón social</small><strong id="fac-emisor-nombre">—</strong></div></div>
                <div class="col-md-4 col-sm-6"><div class="cfdi-kv"><small>Sucursal</small><span id="fac-emisor-sucursal">—</span></div></div>
                <div class="col-md-3 col-sm-6"><div class="cfdi-kv"><small>Régimen fiscal</small><span id="fac-emisor-regimen">—</span></div></div>
                <div class="col-md-3 col-sm-6"><div class="cfdi-kv"><small>Lugar de expedición</small><span id="fac-emisor-lugar">—</span></div></div>
                <div class="col-md-2 col-sm-6"><div class="cfdi-kv"><small>Serie</small><span id="fac-emisor-serie">—</span></div></div>
                <div class="col-md-2 col-sm-6"><div class="cfdi-kv"><small>Tipo comp.</small><span id="fac-emisor-tipo">—</span></div></div>
                <div class="col-md-2 col-sm-6"><div class="cfdi-kv"><small>Exportación</small><span id="fac-emisor-exportacion">—</span></div></div>
              </div>
            </div>
          </div>

          <div class="cfdi-section">
            <div class="cfdi-section__head"><h6 class="cfdi-section__title mb-0">2. Resumen de tickets</h6></div>
            <div class="cfdi-section__body">
              <div class="row">
                <div class="col-md-3 col-sm-6"><div class="cfdi-kv"><small>Venta base</small><span id="fac-folio">—</span></div></div>
                <div class="col-md-3 col-sm-6"><div class="cfdi-kv"><small>Fecha</small><span id="fac-fecha">—</span></div></div>
                <div class="col-md-6 col-sm-12"><div class="cfdi-kv"><small>Cliente</small><span id="fac-cliente">—</span></div><div class="cfdi-kv"><small>Tickets agregados</small><span id="fac-multi-tickets">0</span></div></div>
              </div>
            </div>
          </div>

          <div class="cfdi-section">
            <div class="cfdi-section__head"><h6 class="cfdi-section__title mb-0">3. Receptor</h6></div>
            <div class="cfdi-section__body">
              <div class="alert alert-info py-2 px-3 d-none" id="fac-publico-note"></div>
              <div class="row">
                <div class="col-12">
                  <label for="fac-select-cliente">Cliente existente</label>
                  <div class="cfdi-select-block"><select class="form-control" id="fac-select-cliente" data-placeholder="Buscar por nombre, razón social o RFC"></select></div>
                  <small class="form-text cfdi-helper mb-2">Busca por RFC, razón social o nombre comercial para cargar el receptor fiscal.</small>
                </div>
                <div class="col-md-4"><label for="fac-input-rfc">RFC</label><input type="text" class="form-control" id="fac-input-rfc" maxlength="13"></div>
                <div class="col-md-8 mt-2 mt-md-0"><label for="fac-input-razon-social">Nombre / razón social</label><input type="text" class="form-control" id="fac-input-razon-social" maxlength="255"></div>
                <div class="col-md-4 mt-2"><label for="fac-input-nombre-comercial">Nombre comercial</label><input type="text" class="form-control" id="fac-input-nombre-comercial" maxlength="255"></div>
                <div class="col-md-4 mt-2"><label for="fac-input-correo">Correo</label><input type="email" class="form-control" id="fac-input-correo" maxlength="255"></div>
                <div class="col-md-4 mt-2"><label for="fac-input-cp">C.P. fiscal</label><input type="text" class="form-control" id="fac-input-cp" maxlength="10"></div>
                <div class="col-md-4 mt-2"><label for="fac-select-regimen">Régimen fiscal</label><select class="form-control" id="fac-select-regimen"></select></div>
                <div class="col-md-4 mt-2"><label for="fac-select-uso-cfdi">Uso CFDI</label><select class="form-control" id="fac-select-uso-cfdi"></select></div>
                <div class="col-md-4 mt-2"><label for="fac-input-residencia-fiscal">Residencia fiscal</label><input type="text" class="form-control" id="fac-input-residencia-fiscal" maxlength="3"></div>
                <div class="col-md-4 mt-2"><label for="fac-input-num-reg-id-trib">Num. Reg. Id. Trib.</label><input type="text" class="form-control" id="fac-input-num-reg-id-trib" maxlength="40"></div>
              </div>
            </div>
          </div>

          <div class="cfdi-section">
            <div class="cfdi-section__head"><h6 class="cfdi-section__title mb-0">4. Forma de pago</h6></div>
            <div class="cfdi-section__body">
              <div class="row">
                <div class="col-md-4 col-sm-6"><label for="fac-select-moneda">Moneda</label><select class="form-control" id="fac-select-moneda"></select></div>
                <div class="col-md-4 col-sm-6"><label for="fac-select-metodo-pago">Método de pago</label><select class="form-control" id="fac-select-metodo-pago"></select></div>
                <div class="col-md-4 col-sm-6 mt-2 mt-md-0"><label for="fac-select-forma-pago">Forma de pago</label><select class="form-control" id="fac-select-forma-pago"></select></div>
                <div class="col-md-4 col-sm-6 mt-2"><label for="fac-input-tipo-cambio">Tipo de cambio</label><input type="number" class="form-control" id="fac-input-tipo-cambio" min="0" step="0.000001" value="1" readonly><small id="fac-tipo-cambio-help" class="form-text cfdi-helper mb-0">Se ajusta automáticamente según la moneda seleccionada.</small></div>
                <div class="col-md-4 col-sm-6 mt-2"><label for="fac-input-condiciones-pago">Condiciones de pago</label><input type="text" class="form-control" id="fac-input-condiciones-pago" maxlength="255" readonly></div>
                <div class="col-md-4 col-sm-6 mt-2"><label for="fac-select-tipo-comprobante">Tipo de comprobante</label><select class="form-control" id="fac-select-tipo-comprobante"></select></div>
                <div class="col-md-4 col-sm-6 mt-2"><label for="fac-select-exportacion">Exportación</label><select class="form-control" id="fac-select-exportacion"></select></div>
                <div class="col-12 mt-2"><div id="fac-forma-pago-alerta" class="alert alert-light border py-2 px-3 mb-0">Captura los datos del comprobante fiscal que se enviarán para construir el CFDI 4.0.</div></div>
              </div>
            </div>
          </div>

          <div class="cfdi-section">
            <div class="cfdi-section__head">
              <h6 class="cfdi-section__title mb-0">5. Conceptos</h6>
              <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarTickets"><i class="mdi mdi-ticket-confirmation-outline mr-1"></i>Agregar tickets</button>
            </div>
            <div class="cfdi-section__body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                  <thead class="thead-light"><tr><th class="text-center">Cant.</th><th>Prod/Serv</th><th>No. ident.</th><th>Clave unidad</th><th>Unidad</th><th>Descripción</th><th class="text-right">V. unitario</th><th class="text-right">Importe</th><th class="text-center">Obj. imp.</th><th class="text-right">Impuestos</th></tr></thead>
                  <tbody id="fac-detalles-body"><tr><td colspan="10" class="text-center text-muted">Sin conceptos</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="cfdi-section mb-0">
            <div class="cfdi-section__head"><h6 class="cfdi-section__title mb-0">6. Total / acciones finales</h6></div>
            <div class="cfdi-section__body">
              <div class="row">
                <div class="col-lg-7">
                  <h6 class="mb-2">Validación previa</h6>
                  <div id="fac-validacion-bloques" class="cfdi-block-status"><div class="cfdi-block-status__item is-incomplete"><div class="cfdi-block-status__title"><span>Sin estado</span><span class="badge badge-secondary">Pendiente</span></div><p class="cfdi-block-status__desc mb-0">Agrega tickets para calcular el draft de facturación.</p></div></div>
                  <ul id="fac-validaciones" class="cfdi-validation-list"><li class="cfdi-validation-list__item"><strong>Validación</strong>Sin validaciones disponibles.</li></ul>
                </div>
                <div class="col-lg-5 mt-3 mt-lg-0">
                  <div class="cfdi-total-box">
                    <div class="cfdi-total-row"><span>Subtotal</span><strong id="fac-total-subtotal">$0.00</strong></div>
                    <div class="cfdi-total-row"><span>Descuento</span><strong id="fac-total-descuento">$0.00</strong></div>
                    <div class="cfdi-total-row"><span>Impuestos</span><strong id="fac-total-impuestos">$0.00</strong></div>
                    <div class="cfdi-total-row cfdi-total-row--grand"><strong>Total</strong><strong id="fac-total">$0.00</strong></div>
                    <div class="cfdi-kv mb-0 mt-3"><small>Importe con letra</small><span id="fac-importe-letra">No disponible en el flujo actual.</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="text-right mt-3 mb-2">
            <button type="submit" id="btnConfirmarFacturar" class="btn btn-primary" disabled>
              <i class="mdi mdi-file-document-check-outline mr-1"></i>Facturar venta
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTicketsFacturacion" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Agregar tickets</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-8 mb-2"><input type="text" id="multi-ticket-search" class="form-control" placeholder="Buscar por folio, id o cliente"></div>
          <div class="col-md-4 mb-2 text-md-right"><small class="text-muted" id="multi-ticket-info">Mostrando 0 a 0 de 0 tickets</small></div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr><th class="text-center">Folio</th><th class="text-center">Usuario</th><th class="text-center">Forma de pago</th><th class="text-right">Total</th><th class="text-center">Cliente</th><th class="text-center">Fecha</th><th class="text-center" style="width:110px">Acción</th></tr>
            </thead>
            <tbody id="multi-ticket-body"><tr><td colspan="7" class="text-center text-muted">Sin tickets</td></tr></tbody>
          </table>
        </div>
        <div class="d-flex justify-content-end mt-3">
          <ul id="multi-ticket-pagination" class="pagination pagination-sm mb-0"></ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<br><br><br>
<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
<div class="rightbar-overlay"></div>
<script>const BASE_URL='<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
<script src="<?= BASE_URL ?>/assets/libs/select2/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
(function(){
  const VENTAS_URL = `${BASE_URL}/controllers/VentasController.php`;
  const selectedTickets = new Map();
  let draft = null;
  let ticketPage = 1;
  const ticketLimit = 10;

  const mxn = n => new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(Number(n||0));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const fechaMx = f => f ? new Date(String(f).replace(' ','T')).toLocaleString('es-MX') : '—';

  function getBadgeFiscal(estatus){
    const e = String(estatus || '').toUpperCase();
    if (e === 'TIMBRADO') return '<span class="badge badge-success">Timbrado</span>';
    if (e === 'CANCELADO') return '<span class="badge badge-danger">Cancelado</span>';
    return '<span class="badge badge-secondary">Sin CFDI</span>';
  }

  function fillFacturacionSelect($select, items, valueKey, textKey, currentValue, placeholder = 'Seleccione…'){
    let html = `<option value="">${placeholder}</option>`;
    (items || []).forEach(item => {
      const val = item?.[valueKey] ?? '';
      const text = item?.label ?? item?.[textKey] ?? val;
      html += `<option value="${esc(String(val))}">${esc(String(text))}</option>`;
    });
    $select.html(html).val(currentValue !== undefined && currentValue !== null ? String(currentValue) : '');
  }

  function fillFacturacionSelectFromPairs($select, items, currentValue, placeholder = 'Seleccione…'){
    let html = `<option value="">${placeholder}</option>`;
    (items || []).forEach(item => {
      const val = item?.clave ?? '';
      const descripcion = item?.descripcion ?? val;
      html += `<option value="${esc(String(val))}">${esc(String(val))}${descripcion ? ' · ' + esc(String(descripcion)) : ''}</option>`;
    });
    $select.html(html).val(currentValue !== undefined && currentValue !== null ? String(currentValue) : '');
  }

  function renderValidationReport(report){
    const blocks = report?.bloques || {};
    const lista = Array.isArray(report?.listaErrores) ? report.listaErrores : [];
    const keys = [
      ['emisor','Emisor'],['receptor','Receptor'],['comprobante','Comprobante'],['conceptos','Conceptos'],['totales','Totales']
    ];
    let bHtml = '';
    keys.forEach(([k, label]) => {
      const ok = !!blocks[k];
      bHtml += `<div class="cfdi-block-status__item ${ok ? 'is-complete':'is-incomplete'}"><div class="cfdi-block-status__title"><span>${label}</span><span class="badge ${ok ? 'badge-success':'badge-secondary'}">${ok ? 'OK':'Pendiente'}</span></div><p class="cfdi-block-status__desc mb-0">${ok ? 'Bloque completo para timbrar.' : 'Faltan datos requeridos.'}</p></div>`;
    });
    $('#fac-validacion-bloques').html(bHtml || '<div class="cfdi-block-status__item is-incomplete"><div class="cfdi-block-status__title"><span>Sin estado</span></div></div>');

    if (!lista.length) {
      $('#fac-validaciones').html('<li class="cfdi-validation-list__item is-success"><strong>Validación</strong>La información está completa. Ya puedes facturar la venta.</li>');
      return;
    }
    $('#fac-validaciones').html(lista.map(msg => `<li class="cfdi-validation-list__item"><strong>Validación</strong>${esc(msg)}</li>`).join(''));
  }

  function resetView(){
    draft = null;
    $('#fac-loader').hide();
    $('#fac-contenido').removeClass('d-none');
    $('#fac-error, #fac-warning, #fac-success').addClass('d-none').empty();
    $('#fac-multi-tickets').text(selectedTickets.size);
    $('#fac-folio,#fac-fecha,#fac-cliente,#fac-emisor-rfc,#fac-emisor-nombre,#fac-emisor-sucursal,#fac-emisor-regimen,#fac-emisor-lugar,#fac-emisor-serie,#fac-emisor-tipo,#fac-emisor-exportacion').text('—');
    $('#fac-estatus-fiscal').html(getBadgeFiscal(''));
    $('#fac-detalles-body').html('<tr><td colspan="10" class="text-center text-muted">Sin conceptos</td></tr>');
    $('#fac-total-subtotal,#fac-total-descuento,#fac-total-impuestos,#fac-total').text(mxn(0));
    $('#fac-importe-letra').text('No disponible en el flujo actual.');
    fillFacturacionSelect($('#fac-select-regimen'), [], 'ClaveRegimenFiscal', 'Descripcion', '');
    fillFacturacionSelect($('#fac-select-uso-cfdi'), [], 'ClaveUsoCFDI', 'Descripcion', '');
    fillFacturacionSelect($('#fac-select-moneda'), [], 'ClaveMoneda', 'Descripcion', '');
    fillFacturacionSelect($('#fac-select-forma-pago'), [], 'clave_sat', 'descripcion', '');
    fillFacturacionSelectFromPairs($('#fac-select-metodo-pago'), [], '');
    fillFacturacionSelectFromPairs($('#fac-select-tipo-comprobante'), [], '');
    fillFacturacionSelectFromPairs($('#fac-select-exportacion'), [], '');
    $('#fac-input-rfc,#fac-input-razon-social,#fac-input-nombre-comercial,#fac-input-correo,#fac-input-cp,#fac-input-residencia-fiscal,#fac-input-num-reg-id-trib,#fac-id-cliente-sat').val('');
    renderValidationReport(null);
    $('#btnConfirmarFacturar').prop('disabled', true);
  }

  function initClienteSelect(){
    const $s = $('#fac-select-cliente');
    if ($s.hasClass('select2-hidden-accessible')) $s.select2('destroy');
    $s.select2({
      width:'100%',
      dropdownParent: $('#facturacionMultipleScreen'),
      placeholder: 'Buscar cliente SAT', allowClear:true, minimumInputLength:1,
      ajax:{
        url: VENTAS_URL, dataType:'json', delay:250,
        data: p => ({accion:'facturacion-buscar-clientes', q:p.term||'', limite:20}),
        processResults: r => ({results: Array.isArray(r?.results) ? r.results : []}),
        cache:true
      },
      templateResult: function(item){
        if (item.loading) return item.text;
        const razon = item.nombre || item.razon_social || item.text || 'Sin nombre fiscal';
        const comercial = item.nombre_comercial || '';
        const rfc = item.rfc || 'Sin RFC';
        const correo = item.correo || '';
        const razonSocial = item.razon_social && item.razon_social !== razon ? item.razon_social : '';
        return $(`<div class="cfdi-select2-option"><span class="cfdi-select2-option__title">${esc(razon)}</span><span class="cfdi-select2-option__meta">${esc(rfc)}${correo ? ' · ' + esc(correo) : ''}</span>${comercial || razonSocial ? `<span class="cfdi-select2-option__secondary">${esc(comercial || razonSocial)}</span>` : ''}</div>`);
      },
      templateSelection: i => i.text || i.nombre || i.razon_social || 'Buscar cliente SAT'
    });
  }

  function buildTicketPagination(currentPage,totalItems,itemsPerPage){
    const totalPages = Math.max(1, Math.ceil(totalItems/itemsPerPage));
    const $ul = $('#multi-ticket-pagination').empty();
    if (totalPages <= 1) return;
    const maxVisible = 5;
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);
    if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
    if (currentPage > 1) {
      $ul.append(`<li class="page-item"><a class="page-link" data-page="1">Primera</a></li>`);
      $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);
    }
    for (let i = start; i <= end; i += 1) $ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" data-page="${i}">${i}</a></li>`);
    if (currentPage < totalPages) {
      $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);
      $ul.append(`<li class="page-item"><a class="page-link" data-page="${totalPages}">Última</a></li>`);
    }
  }

  function loadTickets(page = 1){
    const q = $('#multi-ticket-search').val() || '';
    ticketPage = page;
    $.get(VENTAS_URL,{accion:'facturacion-multiple-tickets',q,pagina:page,limite:ticketLimit},resp=>{
      if (!resp?.ok) {
        const msg = resp?.msg || 'Error al cargar tickets.';
        const detail = resp?.error ? ` (${resp.error})` : '';
        $('#multi-ticket-body').html(`<tr><td colspan="7" class="text-center text-danger">${esc(msg + detail)}</td></tr>`);
        $('#multi-ticket-info').text('No fue posible cargar los tickets.');
        $('#multi-ticket-pagination').empty();
        return;
      }

      const rows = Array.isArray(resp?.items) ? resp.items : (Array.isArray(resp?.tickets) ? resp.tickets : []);
      const pag = resp?.paginacion || {};
      const total = Number(pag?.total ?? resp?.total ?? rows.length ?? 0);
      const currentPage = Number(pag?.pagina ?? page);
      const currentLimit = Number(pag?.limite ?? ticketLimit);
      const desde = Number(pag?.desde ?? (total === 0 ? 0 : ((currentPage - 1) * currentLimit) + 1));
      const hasta = Number(pag?.hasta ?? Math.min(currentPage * currentLimit, total));
      $('#multi-ticket-info').text(`Mostrando ${desde} a ${hasta} de ${total} tickets`);

      if(!rows.length){
        $('#multi-ticket-body').html('<tr><td colspan="7" class="text-center text-muted">Sin tickets disponibles</td></tr>');
        buildTicketPagination(currentPage, total, currentLimit);
        return;
      }
      const html = rows.map(r=>{
        const id=Number(r.id_venta||0); const added=selectedTickets.has(id);
        return `<tr>
          <td class="text-center"><b>${esc(r.folio || ('#'+id))}</b></td>
          <td class="text-center">${esc(r.usuario || '—')}</td>
          <td class="text-center">${esc(r.forma_pago || '—')}</td>
          <td class="text-right"><b>${mxn(r.total||0)}</b></td>
          <td class="text-center">${esc(r.cliente || 'Público general')}</td>
          <td class="text-center">${esc(fechaMx(r.fecha))}</td>
          <td class="text-center"><button type="button" class="btn btn-sm ${added?'btn-secondary':'btn-primary'} btn-ticket-add" data-id="${id}" data-folio="${esc(r.folio||'')}">${added?'Agregado':'Agregar'}</button></td>
        </tr>`;
      }).join('');
      $('#multi-ticket-body').html(html);
      buildTicketPagination(currentPage, total, currentLimit);
    },'json').fail(xhr=>{
      const msg = xhr?.responseJSON?.msg || 'Error al cargar tickets.';
      const detail = xhr?.responseJSON?.error ? ` (${xhr.responseJSON.error})` : '';
      $('#multi-ticket-body').html(`<tr><td colspan="7" class="text-center text-danger">${esc(msg + detail)}</td></tr>`);
      $('#multi-ticket-info').text('No fue posible cargar los tickets.');
      $('#multi-ticket-pagination').empty();
    });
  }

  function refreshPreview(){
    const ids = Array.from(selectedTickets.keys());
    if(!ids.length){ resetView(); return; }
    $('#fac-loader').show();
    $('#fac-contenido').addClass('d-none');
    $('#fac-error, #fac-success').addClass('d-none').empty();

    $.post(VENTAS_URL,{accion:'facturacion-multiple-preview',ids_ventas:ids},resp=>{
      $('#fac-loader').hide();
      if(!resp?.ok){ $('#fac-error').removeClass('d-none').text(resp?.msg||'Error de preview'); return; }

      const ctx = resp.contexto || {};
      draft = ctx.factura_draft || {};
      const venta = ctx.venta || {};
      const em = ctx.emisor || {};
      const conceptos = Array.isArray(draft?.venta?.conceptos) ? draft.venta.conceptos : [];
      const report = resp.validation_report || draft.validaciones || null;

      $('#fac-id-venta').val(venta.id_venta || ids[0]);
      $('#fac-folio').text((draft?.venta?.tickets_folios || []).join(', ') || venta.folio || '—');
      $('#fac-fecha').text(fechaMx(venta.fecha));
      $('#fac-cliente').text(venta.cliente_nombre || venta.cliente || 'Venta múltiple');
      $('#fac-multi-tickets').text(ids.length);
      $('#fac-emisor-rfc').text(em.rfc || '—');
      $('#fac-emisor-nombre').text(em.nombre || '—');
      $('#fac-emisor-sucursal').text(em.sucursal || '—');
      $('#fac-emisor-regimen').text(em.regimen_fiscal_label || em.regimen_fiscal || '—');
      $('#fac-emisor-lugar').text(em.lugar_expedicion || '—');
      $('#fac-emisor-serie').text(em.serie || '—');
      $('#fac-emisor-tipo').text(em.tipo_comprobante_label || draft?.comprobante?.tipo_comprobante || 'I');
      $('#fac-emisor-exportacion').text(em.exportacion_label || draft?.comprobante?.exportacion || '01');
      $('#fac-estatus-fiscal').html(getBadgeFiscal(ctx?.cfdi_actual?.estatus || venta?.estatus_fiscal || ''));

      $('#fac-total-subtotal').text(mxn(draft?.venta?.subtotal || ctx?.totales?.subtotal || 0));
      $('#fac-total-descuento').text(mxn(draft?.venta?.descuento || ctx?.totales?.descuento || 0));
      $('#fac-total-impuestos').text(mxn(draft?.venta?.impuestos || ctx?.totales?.impuestos || 0));
      $('#fac-total').text(mxn(draft?.venta?.total || ctx?.totales?.total || 0));
      $('#fac-importe-letra').text(ctx?.totales?.importe_letra || 'No disponible en el flujo actual.');

      fillFacturacionSelect($('#fac-select-regimen'), ctx.catalogos?.regimenes_fiscales || [], 'ClaveRegimenFiscal', 'Descripcion', draft?.receptor?.regimen_fiscal || '');
      fillFacturacionSelect($('#fac-select-uso-cfdi'), ctx.catalogos?.usos_cfdi || [], 'ClaveUsoCFDI', 'Descripcion', draft?.receptor?.uso_cfdi || '');
      fillFacturacionSelect($('#fac-select-moneda'), ctx.catalogos?.monedas || [], 'ClaveMoneda', 'Descripcion', draft?.comprobante?.moneda || 'MXN');
      fillFacturacionSelect($('#fac-select-forma-pago'), ctx.catalogos?.formas_pago || [], 'clave_sat', 'descripcion', draft?.comprobante?.forma_pago || '');
      fillFacturacionSelectFromPairs($('#fac-select-metodo-pago'), ctx.catalogos?.metodos_pago || [], draft?.comprobante?.metodo_pago || '');
      fillFacturacionSelectFromPairs($('#fac-select-tipo-comprobante'), ctx.catalogos?.tipos_comprobante || [], draft?.comprobante?.tipo_comprobante || 'I');
      fillFacturacionSelectFromPairs($('#fac-select-exportacion'), ctx.catalogos?.exportaciones || [], draft?.comprobante?.exportacion || '01');

      const rec = draft.receptor || {};
      $('#fac-id-cliente-sat').val(rec.id_cliente_fiscal || '');
      $('#fac-input-rfc').val(rec.rfc || '');
      $('#fac-input-razon-social').val(rec.nombre || '');
      $('#fac-input-nombre-comercial').val(rec.nombre_comercial || '');
      $('#fac-input-correo').val(rec.correo || '');
      $('#fac-input-cp').val(rec.codigo_postal || '');
      $('#fac-input-residencia-fiscal').val(rec.residencia_fiscal || '');
      $('#fac-input-num-reg-id-trib').val(rec.numero_registro_tributario || '');
      $('#fac-input-tipo-cambio').val(draft?.comprobante?.tipo_cambio || '1');
      $('#fac-input-condiciones-pago').val(draft?.comprobante?.condiciones_pago || '');

      const rows = conceptos.map(c=>{
        const tr = Array.isArray(c?.Traslados) ? c.Traslados[0] : null;
        const iva = tr ? `IVA ${Number(tr.TasaOCuota||0)*100}%: ${mxn(tr.Importe||0)}` : '—';
        return `<tr><td class="text-center">${esc(c.Cantidad||0)}</td><td>${esc(c.ClaveProdServ||'—')}</td><td>${esc(c.NoIdentificacion||'—')}</td><td>${esc(c.ClaveUnidad||'—')}</td><td>${esc(c.Unidad||'—')}</td><td>${esc(c.Descripcion||'—')}</td><td class="text-right">${mxn(c.ValorUnitario||0)}</td><td class="text-right">${mxn(c.Importe||0)}</td><td class="text-center">${esc(c.ObjetoImp||'—')}</td><td class="text-right">${esc(iva)}</td></tr>`;
      }).join('');
      $('#fac-detalles-body').html(rows || '<tr><td colspan="10" class="text-center text-muted">Sin conceptos</td></tr>');

      renderValidationReport(report);
      $('#fac-warning').toggleClass('d-none', !(Array.isArray(resp.advertencias) && resp.advertencias.length)).html((resp.advertencias||[]).join('<br>'));
      $('#btnConfirmarFacturar').prop('disabled', !resp.facturable);
      $('#fac-contenido').removeClass('d-none');
    },'json').fail(xhr=>{
      $('#fac-loader').hide();
      $('#fac-contenido').removeClass('d-none');
      $('#fac-error').removeClass('d-none').text(xhr?.responseJSON?.msg || 'Error al cargar preview.');
    });
  }

  function buildPayload(){
    const ids = Array.from(selectedTickets.keys());
    const conceptos = Array.isArray(draft?.venta?.conceptos) ? draft.venta.conceptos : [];
    return {
      accion:'facturar',
      id_venta:Number($('#fac-id-venta').val()||ids[0]||0),
      ids_ventas: ids,
      id_cliente_sat:Number($('#fac-id-cliente-sat').val()||0),
      emisor: draft?.emisor || {},
      receptor: {
        id_cliente_sat:Number($('#fac-id-cliente-sat').val()||0),
        rfc:$('#fac-input-rfc').val(), nombre:$('#fac-input-razon-social').val(), nombre_comercial:$('#fac-input-nombre-comercial').val(),
        correo:$('#fac-input-correo').val(), domicilio_fiscal_receptor:$('#fac-input-cp').val(), regimen_fiscal:$('#fac-select-regimen').val(),
        uso_cfdi:$('#fac-select-uso-cfdi').val(), residencia_fiscal:$('#fac-input-residencia-fiscal').val(), numero_registro_tributario:$('#fac-input-num-reg-id-trib').val()
      },
      comprobante: {
        moneda:$('#fac-select-moneda').val(), metodo_pago:$('#fac-select-metodo-pago').val(), forma_pago:$('#fac-select-forma-pago').val(),
        tipo_cambio:$('#fac-input-tipo-cambio').val(), condiciones_pago:$('#fac-input-condiciones-pago').val(),
        tipo_comprobante:$('#fac-select-tipo-comprobante').val(), exportacion:$('#fac-select-exportacion').val()
      },
      conceptos,
      totales: { subtotal:Number((draft?.venta?.subtotal)||0), descuento:Number((draft?.venta?.descuento)||0), impuestos:Number((draft?.venta?.impuestos)||0), total:Number((draft?.venta?.total)||0) },
      draft_snapshot: { emisor:draft?.emisor||{}, receptor:draft?.receptor||{}, comprobante:draft?.comprobante||{} }
    };
  }

  $(document).on('click','#btnAgregarTickets',()=>{ $('#modalTicketsFacturacion').modal('show'); loadTickets(1); });
  $(document).on('input','#multi-ticket-search',function(){ loadTickets(1); });
  $(document).on('click','#multi-ticket-pagination .page-link',function(e){ e.preventDefault(); const p = Number($(this).data('page')||0); if (p > 0) loadTickets(p); });
  $(document).on('click','.btn-ticket-add',function(){
    const id = Number($(this).data('id')||0); const folio = $(this).data('folio')||'';
    if(!id) return;
    if(selectedTickets.has(id)) selectedTickets.delete(id); else selectedTickets.set(id,{id,folio});
    loadTickets(ticketPage);
    refreshPreview();
  });

  $(document).on('select2:select','#fac-select-cliente',function(e){
    const c = e?.params?.data || {};
    $('#fac-id-cliente-sat').val(c.id||c.id_cliente_sat||'');
    $('#fac-input-rfc').val(c.rfc||'');
    $('#fac-input-razon-social').val(c.nombre||c.razon_social||'');
    $('#fac-input-nombre-comercial').val(c.nombre_comercial||'');
    $('#fac-input-correo').val(c.correo||'');
    $('#fac-input-cp').val(c.domicilio_fiscal_receptor||'');
    $('#fac-select-regimen').val(c.regimen_fiscal_receptor||'');
    $('#fac-select-uso-cfdi').val(c.uso_cfdi||'');
    $('#fac-input-residencia-fiscal').val(c.residencia_fiscal || '');
    $('#fac-input-num-reg-id-trib').val(c.num_reg_id_trib || '');
  });

  $(document).on('submit','#formFacturarVenta',function(e){
    e.preventDefault();
    const payload = buildPayload();
    if(!payload.ids_ventas.length){ toastr.error('Debes agregar al menos un ticket.'); return; }
    if(!payload.id_cliente_sat){ toastr.error('Debe seleccionar un receptor existente.'); return; }
    const $btn = $('#btnConfirmarFacturar'); const old = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Facturando...');
    $.post(VENTAS_URL,payload,resp=>{
      if(resp?.ok){
        toastr.success(resp.msg || 'CFDI timbrado correctamente.');
        $('#fac-success').removeClass('d-none').text(resp.msg||'CFDI timbrado correctamente.');
        selectedTickets.clear();
        resetView();
      } else {
        toastr.error(resp?.msg || 'No fue posible facturar.');
        $('#fac-error').removeClass('d-none').text(resp?.msg || 'No fue posible facturar.');
      }
    },'json').fail(xhr=>{
      const msg = xhr?.responseJSON?.msg || 'Error al facturar.';
      toastr.error(msg);
      $('#fac-error').removeClass('d-none').text(msg);
    }).always(()=> $btn.prop('disabled', false).html(old));
  });

  $(function(){ initClienteSelect(); resetView(); });
})();
</script>
</body>
</html>
