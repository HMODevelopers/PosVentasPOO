<style>
  #modalFacturarVenta .modal-xxl-custom {
    max-width: 98vw;
    width: 98vw;
  }
  @media (min-width: 1200px) {
    #modalFacturarVenta .modal-xxl-custom {
      max-width: 1400px;
      width: 1400px;
    }
  }
  @media (min-width: 1600px) {
    #modalFacturarVenta .modal-xxl-custom {
      max-width: 1600px;
      width: 1600px;
    }
  }
  #modalFacturarVenta .modal-dialog {
    margin: 1.25rem auto;
  }
  #modalFacturarVenta .modal-content {
    border: 0;
    display: flex;
    flex-direction: column;
    max-height: 92vh;
    min-height: 70vh;
  }
  #modalFacturarVenta .modal-body {
    background: #f8fafc;
    flex: 1 1 auto;
    max-height: calc(92vh - 140px);
    overflow-y: auto;
  }
  #modalFacturarVenta .cfdi-section {
    background: #fff;
    border: 1px solid #dfe7f1;
    border-radius: 0.75rem;
    box-shadow: 0 0.25rem 1rem rgba(31, 45, 61, 0.05);
    margin-bottom: 1rem;
    overflow: hidden;
  }
  #modalFacturarVenta .cfdi-section__head {
    align-items: center;
    background: #f4f7fb;
    border-bottom: 1px solid #dfe7f1;
    display: flex;
    justify-content: space-between;
    padding: 0.9rem 1rem;
  }
  #modalFacturarVenta .cfdi-section__title {
    font-size: 0.98rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    margin: 0;
    text-transform: uppercase;
  }
  #modalFacturarVenta .cfdi-section__body {
    padding: 1rem;
  }
  #modalFacturarVenta .cfdi-kv {
    margin-bottom: 0.85rem;
  }
  #modalFacturarVenta .cfdi-kv small {
    color: #7a8797;
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    margin-bottom: 0.2rem;
    text-transform: uppercase;
  }
  #modalFacturarVenta .cfdi-kv strong,
  #modalFacturarVenta .cfdi-kv span {
    color: #1f2d3d;
    display: block;
    word-break: break-word;
  }
  #modalFacturarVenta .cfdi-total-box {
    background: #f4f7fb;
    border: 1px solid #dfe7f1;
    border-radius: 0.75rem;
    padding: 1rem;
  }
  #modalFacturarVenta .cfdi-total-row {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.55rem;
  }
  #modalFacturarVenta .cfdi-total-row:last-child {
    margin-bottom: 0;
  }
  #modalFacturarVenta .cfdi-total-row--grand {
    border-top: 1px dashed #cfd8e3;
    margin-top: 0.8rem;
    padding-top: 0.8rem;
  }
  #modalFacturarVenta .cfdi-total-row--grand strong:last-child {
    font-size: 1.1rem;
  }
  #modalFacturarVenta .table td,
  #modalFacturarVenta .table th {
    vertical-align: middle;
  }
  #modalFacturarVenta .select2-container {
    width: 100% !important;
  }
  #modalFacturarVenta .select2-container--default .select2-selection--single {
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    height: calc(1.5em + 0.75rem + 2px);
    padding: 0.275rem 0.25rem;
  }
  #modalFacturarVenta .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 1.8;
    padding-left: 0.5rem;
    padding-right: 1.75rem;
  }
  #modalFacturarVenta .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: calc(1.5em + 0.75rem + 2px);
  }
  #modalFacturarVenta .cfdi-helper {
    color: #7a8797;
    font-size: 0.78rem;
  }
</style>

<!-- Modal Facturar Venta -->
<div class="modal fade" id="modalFacturarVenta" tabindex="-1" role="dialog" aria-labelledby="modalFacturarVentaLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-xxl-custom" role="document">
    <div class="modal-content">
      <form id="formFacturarVenta" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title" id="modalFacturarVentaLabel">Facturar venta</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="fac-id-venta" name="id_venta">

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
                  <div class="col-md-3 col-sm-6">
                    <div class="cfdi-kv"><small>RFC emisor</small><strong id="fac-emisor-rfc">—</strong></div>
                  </div>
                  <div class="col-md-5 col-sm-6">
                    <div class="cfdi-kv"><small>Razón social</small><strong id="fac-emisor-nombre">—</strong></div>
                  </div>
                  <div class="col-md-4 col-sm-6">
                    <div class="cfdi-kv"><small>Sucursal</small><span id="fac-emisor-sucursal">—</span></div>
                  </div>
                  <div class="col-md-3 col-sm-6">
                    <div class="cfdi-kv"><small>Régimen fiscal</small><span id="fac-emisor-regimen">—</span></div>
                  </div>
                  <div class="col-md-3 col-sm-6">
                    <div class="cfdi-kv"><small>Lugar de expedición</small><span id="fac-emisor-lugar">—</span></div>
                  </div>
                  <div class="col-md-2 col-sm-6">
                    <div class="cfdi-kv"><small>Serie</small><span id="fac-emisor-serie">—</span></div>
                  </div>
                  <div class="col-md-2 col-sm-6">
                    <div class="cfdi-kv"><small>Tipo comp.</small><span id="fac-emisor-tipo">—</span></div>
                  </div>
                  <div class="col-md-2 col-sm-6">
                    <div class="cfdi-kv"><small>Exportación</small><span id="fac-emisor-exportacion">—</span></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="cfdi-section">
              <div class="cfdi-section__head">
                <h6 class="cfdi-section__title mb-0">2. Resumen de la venta</h6>
              </div>
              <div class="cfdi-section__body">
                <div class="row">
                  <div class="col-md-3 col-sm-6">
                    <div class="cfdi-kv"><small>Venta / folio</small><span id="fac-folio">—</span></div>
                  </div>
                  <div class="col-md-3 col-sm-6">
                    <div class="cfdi-kv"><small>Fecha venta</small><span id="fac-fecha">—</span></div>
                  </div>
                  <div class="col-md-6 col-sm-12">
                    <div class="cfdi-kv"><small>Cliente</small><span id="fac-cliente">—</span></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="cfdi-section">
              <div class="cfdi-section__head">
                <h6 class="cfdi-section__title mb-0">3. Receptor</h6>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnGuardarDatosFiscales">
                  <i class="mdi mdi-content-save-outline mr-1"></i>Guardar datos fiscales
                </button>
              </div>
              <div class="cfdi-section__body">
                <div class="alert alert-info py-2 px-3 d-none" id="fac-publico-note"></div>
                <div class="row">
                  <div class="col-12">
                    <label for="fac-select-cliente">Cliente existente</label>
                    <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center">
                      <select class="form-control" id="fac-select-cliente" data-placeholder="Buscar por nombre, razón social o RFC"></select>
                      <button type="button" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0 ml-md-2" id="btnFacLimpiarCliente">
                        <i class="mdi mdi-refresh mr-1"></i>Captura manual / público en general
                      </button>
                    </div>
                    <small class="form-text cfdi-helper mb-2">Busca clientes existentes por nombre, razón social o RFC para cargar sus datos fiscales al instante.</small>
                  </div>
                  <div class="col-md-4">
                    <label for="fac-input-rfc">RFC</label>
                    <input type="text" class="form-control" id="fac-input-rfc" maxlength="13" autocomplete="off">
                  </div>
                  <div class="col-md-8 mt-2 mt-md-0">
                    <label for="fac-input-razon-social">Nombre / razón social</label>
                    <input type="text" class="form-control" id="fac-input-razon-social" maxlength="255" autocomplete="off">
                  </div>
                  <div class="col-md-4 mt-2">
                    <label for="fac-input-nombre-comercial">Nombre comercial</label>
                    <input type="text" class="form-control" id="fac-input-nombre-comercial" maxlength="255" autocomplete="off" readonly>
                  </div>
                  <div class="col-md-4 mt-2">
                    <label for="fac-input-correo">Correo</label>
                    <input type="email" class="form-control" id="fac-input-correo" maxlength="255" autocomplete="off">
                  </div>
                  <div class="col-md-4 mt-2">
                    <label for="fac-input-cp">C.P. fiscal</label>
                    <input type="text" class="form-control" id="fac-input-cp" maxlength="10" autocomplete="off">
                  </div>
                  <div class="col-md-4 mt-2">
                    <label for="fac-select-regimen">Régimen fiscal</label>
                    <select class="form-control" id="fac-select-regimen"></select>
                  </div>
                  <div class="col-md-4 mt-2">
                    <label for="fac-select-uso-cfdi">Uso CFDI</label>
                    <select class="form-control" id="fac-select-uso-cfdi"></select>
                  </div>
                  <div class="col-md-4 mt-2">
                    <label for="fac-input-residencia-fiscal">Residencia fiscal</label>
                    <input type="text" class="form-control" id="fac-input-residencia-fiscal" maxlength="3" autocomplete="off">
                  </div>
                  <div class="col-md-4 mt-2">
                    <label for="fac-input-num-reg-id-trib">Num. Reg. Id. Trib.</label>
                    <input type="text" class="form-control" id="fac-input-num-reg-id-trib" maxlength="40" autocomplete="off">
                  </div>
                </div>
              </div>
            </div>

            <div class="cfdi-section">
              <div class="cfdi-section__head">
                <h6 class="cfdi-section__title mb-0">4. Información global</h6>
              </div>
              <div class="cfdi-section__body">
                <div id="fac-info-global" class="alert alert-light border mb-0">No aplica información global para esta venta.</div>
              </div>
            </div>

            <div class="cfdi-section">
              <div class="cfdi-section__head">
                <h6 class="cfdi-section__title mb-0">5. Forma de pago</h6>
              </div>
              <div class="cfdi-section__body">
                <div class="row">
                  <div class="col-md-3 col-sm-6"><div class="cfdi-kv"><small>Moneda</small><span id="fac-moneda">—</span></div></div>
                  <div class="col-md-3 col-sm-6"><div class="cfdi-kv"><small>Método de pago</small><span id="fac-metodo-pago">—</span></div></div>
                  <div class="col-md-3 col-sm-6"><div class="cfdi-kv"><small>Forma de pago</small><span id="fac-forma-pago">—</span></div></div>
                  <div class="col-md-3 col-sm-6"><div class="cfdi-kv"><small>Tipo de cambio</small><span id="fac-tipo-cambio">—</span></div></div>
                  <div class="col-md-6 col-sm-12"><div class="cfdi-kv mb-0"><small>Condiciones de pago</small><span id="fac-condiciones-pago">—</span></div></div>
                  <div class="col-md-3 col-sm-6"><div class="cfdi-kv mb-0"><small>Tipo comprobante</small><span id="fac-tipo-comprobante">—</span></div></div>
                  <div class="col-md-3 col-sm-6"><div class="cfdi-kv mb-0"><small>Exportación</small><span id="fac-exportacion">—</span></div></div>
                </div>
              </div>
            </div>

            <div class="cfdi-section">
              <div class="cfdi-section__head">
                <h6 class="cfdi-section__title mb-0">6. Conceptos</h6>
              </div>
              <div class="cfdi-section__body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-0">
                    <thead class="thead-light">
                      <tr>
                        <th style="width:75px;" class="text-center">Cant.</th>
                        <th style="width:120px;">Prod/Serv</th>
                        <th style="width:120px;">No. ident.</th>
                        <th style="width:110px;">Clave unidad</th>
                        <th style="width:110px;">Unidad</th>
                        <th>Descripción</th>
                        <th style="width:120px;" class="text-right">V. unitario</th>
                        <th style="width:120px;" class="text-right">Importe</th>
                        <th style="width:105px;" class="text-center">Obj. imp.</th>
                        <th style="width:145px;" class="text-right">Impuestos</th>
                      </tr>
                    </thead>
                    <tbody id="fac-detalles-body">
                      <tr><td colspan="10" class="text-center text-muted">Sin conceptos</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="cfdi-section mb-0">
              <div class="cfdi-section__head">
                <h6 class="cfdi-section__title mb-0">7. Total / acciones finales</h6>
              </div>
              <div class="cfdi-section__body">
                <div class="row">
                  <div class="col-lg-7">
                    <h6 class="mb-2">Validación previa</h6>
                    <ul id="fac-validaciones" class="mb-3 pl-3 text-muted">
                      <li>Sin validaciones disponibles.</li>
                    </ul>

                    <div id="fac-archivos" class="d-none">
                      <h6 class="mb-2">Archivos CFDI</h6>
                      <div class="btn-group btn-group-sm" role="group" aria-label="Archivos CFDI">
                        <a id="fac-link-xml" href="#" target="_blank" class="btn btn-outline-secondary">
                          <i class="mdi mdi-code-tags"></i> XML
                        </a>
                        <a id="fac-link-pdf" href="#" target="_blank" class="btn btn-outline-secondary">
                          <i class="mdi mdi-file-pdf-box"></i> PDF
                        </a>
                      </div>
                    </div>
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
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
          <button type="submit" id="btnConfirmarFacturar" class="btn btn-primary" disabled>
            <i class="mdi mdi-file-document-check-outline mr-1"></i>Facturar venta
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
