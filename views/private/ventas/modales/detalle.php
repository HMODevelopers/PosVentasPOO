<style>
  #modalDetalle .modal-dialog.modal-detalle-venta {
    max-width: 1200px;
    width: calc(100vw - 2rem);
  }
  #modalDetalle .modal-content {
    min-height: 85vh;
    max-height: 90vh;
    overflow: hidden;
    border: 0;
    border-radius: .75rem;
  }
  #modalDetalle .modal-header,
  #modalDetalle .modal-footer {
    background: #fff;
    border-color: #edf2f7;
  }
  #modalDetalle .modal-body {
    overflow-y: auto;
    background: #f8fafc;
    padding: 1rem 1.25rem 1.25rem;
  }
  #modalDetalle .dv-section {
    background: #fff;
    border: 1px solid #e9eef5;
    border-radius: .65rem;
    padding: 1rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
  }
  #modalDetalle .dv-section + .dv-section { margin-top: .95rem; }
  #modalDetalle .dv-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: .9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  #modalDetalle .dv-kpi small {
    display: block;
    color: #64748b;
    font-size: .76rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    margin-bottom: .2rem;
  }
  #modalDetalle .dv-kpi .value {
    color: #0f172a;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.3;
    word-break: break-word;
  }
  #modalDetalle .dv-total-box {
    background: #f1f5f9;
    border: 1px solid #dbe5f0;
    border-radius: .6rem;
    padding: .6rem .8rem;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
  }
  #modalDetalle .dv-total-box strong { font-size: 1.2rem; }
  #modalDetalle #det-cfdi-actions .btn { font-weight: 600; }
  #modalDetalle .badge-fiscal {
    font-size: .8rem;
    letter-spacing: .02em;
    padding: .4rem .65rem;
  }
  @media (max-width: 991.98px) {
    #modalDetalle .modal-dialog.modal-detalle-venta { width: calc(100vw - 1rem); }
    #modalDetalle .modal-content { min-height: 82vh; max-height: 88vh; }
    #modalDetalle #det-cfdi-actions .btn { margin-bottom: .35rem; }
  }
</style>

<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-detalle-venta" role="document">
    <div class="modal-content">

      <div class="modal-header py-2">
        <div>
          <h4 class="modal-title font-weight-bold mb-0" id="myLargeModalLabel">Detalle de venta</h4>
          <small class="text-muted">Folio: <span id="det-header-folio">—</span></small>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
      </div>

      <div class="modal-body">
        <div id="det-loader" class="text-center py-4" style="display:none;">
          <div class="spinner-border" role="status"></div>
          <div class="mt-2 small text-muted">Cargando detalle...</div>
        </div>

        <div id="det-contenido" style="display:none;">
          <div class="dv-section" id="wrap-det-resumen">
            <div class="dv-title">Resumen de venta</div>
            <div class="row">
              <div class="col-md-4 col-lg-3 mb-3 dv-kpi"><small>Folio</small><div class="value" id="det-folio">—</div></div>
              <div class="col-md-4 col-lg-3 mb-3 dv-kpi"><small>Fecha</small><div class="value" id="det-fecha">—</div></div>
              <div class="col-md-4 col-lg-3 mb-3 dv-kpi"><small>Estatus</small><div class="value" id="det-estatus">—</div></div>
              <div class="col-md-6 col-lg-3 mb-3 dv-kpi"><small>Cliente</small><div class="value" id="det-cliente">—</div></div>
              <div class="col-md-6 col-lg-3 mb-3 dv-kpi"><small>Cajero</small><div class="value" id="det-usuario">—</div></div>
              <div class="col-md-6 col-lg-3 mb-3 dv-kpi"><small>Caja</small><div class="value" id="det-caja">—</div></div>
              <div class="col-md-6 col-lg-3 mb-3 dv-kpi"><small>Forma de pago</small><div class="value" id="det-forma">—</div></div>
              <div class="col-md-6 col-lg-3 mb-3 dv-kpi"><small>Tipo de precio</small><div class="value" id="det-tipo">—</div></div>

              <div class="col-md-4 mb-3 d-none dv-kpi" id="wrap-det-estatus-credito"><small>Estatus crédito</small><div class="value" id="det-estatus-credito">N/A</div></div>
              <div class="col-md-4 mb-3 d-none dv-kpi" id="wrap-det-abonado"><small>Abonado</small><div class="value" id="det-abonado">$0.00</div></div>
              <div class="col-md-4 mb-3 d-none dv-kpi" id="wrap-det-saldo"><small>Saldo</small><div class="value" id="det-saldo">$0.00</div></div>
            </div>
          </div>

          <div class="dv-section" id="wrap-det-productos">
            <div class="dv-title">
              <span>Productos</span>
              <div class="dv-total-box"><span class="text-muted">Total</span><strong id="det-total">$0.00</strong></div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover table-bordered mb-0">
                <thead class="thead-light">
                  <tr>
                    <th class="text-center">Código</th>
                    <th>Producto</th>
                    <th class="text-center">Póliza</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-right">Precio</th>
                    <th class="text-right">Subtotal</th>
                  </tr>
                </thead>
                <tbody id="det-tbody"></tbody>
              </table>
            </div>
          </div>

          <div id="wrap-det-desglose" class="d-none dv-section">
            <div class="dv-title">Desglose de pagos</div>
            <div class="border rounded p-3">
              <div id="det-desglose-items" class="mb-2"></div>
              <div class="d-flex justify-content-between align-items-center font-weight-bold">
                <span>Total desglose</span>
                <span id="det-desglose-total">$0.00</span>
              </div>
              <div class="small text-muted" id="det-desglose-validacion"></div>
            </div>
          </div>

          <div id="wrap-det-cfdi" class="dv-section mt-3">
            <div class="dv-title">
              <span>CFDI emitido</span>
              <div id="det-cfdi-actions" class="text-right">
                <a id="det-cfdi-xml" href="#" class="btn btn-primary btn-sm d-none" target="_blank">
                  <i class="mdi mdi-xml mr-1"></i>Descargar XML
                </a>
                <a id="det-cfdi-pdf" href="#" class="btn btn-danger btn-sm d-none" target="_blank">
                  <i class="mdi mdi-file-pdf-box mr-1"></i>Imprimir PDF
                </a>
              </div>
            </div>
            <div id="det-cfdi-empty" class="alert alert-light border mb-0 d-none">
              <i class="mdi mdi-file-document-outline mr-1"></i>Esta venta no tiene CFDI emitido.
            </div>

            <div id="det-cfdi-card" class="d-none">
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered mb-0">
                  <thead class="thead-light">
                    <tr id="det-cfdi-head-row"></tr>
                  </thead>
                  <tbody>
                    <tr id="det-cfdi-body-row"></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div id="wrap-det-abonos" class="d-none dv-section">
            <div class="dv-title">Abonos</div>
            <div class="table-responsive">
              <table class="table table-sm table-striped table-bordered mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Forma de Pago</th>
                    <th class="text-right">Monto</th>
                    <th>Usuario</th>
                  </tr>
                </thead>
                <tbody id="det-abonos-body">
                  <tr><td colspan="4" class="text-center text-muted">Sin abonos</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <div id="det-error" class="alert alert-danger my-3" style="display:none;">No se pudo cargar el detalle.</div>
        </div>
      </div>

      <div class="modal-footer py-2">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
      </div>

    </div>
  </div>
</div>
