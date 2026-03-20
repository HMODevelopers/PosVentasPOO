<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h4 class="modal-title fw-bold" id="myLargeModalLabel">Detalle de venta</h4>
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
      </div>

      <div class="modal-body">
        <!-- Loader -->
        <div id="det-loader" class="text-center py-3" style="display:none;">
          <div class="spinner-border" role="status"></div>
          <div class="mt-2 small text-muted">Cargando detalle...</div>
        </div>

        <!-- Contenido -->
        <div id="det-contenido" style="display:none;">
          <div class="row">
            <div class="col-md-4 mb-3">
              <small class="text-primary font-weight-bold">Folio</small>
              <div class="h5 mb-0" id="det-folio">—</div>
            </div>
            <div class="col-md-4 mb-3">
              <small class="text-primary font-weight-bold">Fecha</small>
              <div class="h5 mb-0" id="det-fecha">—</div>
            </div>
            <div class="col-md-4 mb-3">
              <small class="text-primary font-weight-bold">Estatus</small>
              <div class="h5 mb-0" id="det-estatus">—</div>
            </div>

            <div class="col-md-4 mb-3">
              <small class="text-primary font-weight-bold">Cliente</small>
              <div class="h5 mb-0" id="det-cliente">—</div>
            </div>
            <div class="col-md-4 mb-3">
              <small class="text-primary font-weight-bold">Cajero</small>
              <div class="h5 mb-0" id="det-usuario">—</div>
            </div>
            <div class="col-md-4 mb-3">
              <small class="text-primary font-weight-bold">Caja</small>
              <div class="h5 mb-0" id="det-caja">—</div>
            </div>

            <div class="col-md-4 mb-3">
              <small class="text-primary font-weight-bold">Forma de pago</small>
              <div class="h5 mb-0" id="det-forma">—</div>
            </div>
            <div class="col-md-4 mb-3">
              <small class="text-primary font-weight-bold">Tipo de precio</small>
              <div class="h5 mb-0" id="det-tipo">—</div>
            </div>

            <!-- Bloques exclusivos de crédito (ocultos por defecto) -->
            <div class="col-md-4 mb-3 d-none" id="wrap-det-estatus-credito">
              <small class="text-primary font-weight-bold">Estatus crédito</small>
              <div class="h5 mb-0" id="det-estatus-credito">N/A</div>
            </div>
            <div class="col-md-6 mb-3 d-none" id="wrap-det-abonado">
              <small class="text-primary font-weight-bold">Abonado</small>
              <div class="h5 mb-0" id="det-abonado">$0.00</div>
            </div>
            <div class="col-md-6 mb-3 d-none" id="wrap-det-saldo">
              <small class="text-primary font-weight-bold">Saldo</small>
              <div class="h5 mb-0" id="det-saldo">$0.00</div>
            </div>
          </div>

          <hr>

          <!-- Detalle (items) -->
          <h5 class="mb-2">Productos</h5>
          <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered mb-0">
              <thead>
                <tr>
                  <th class="text-center">Codigo</th>
                  <th>Producto</th>
                  <th class="text-center">Póliza</th>
                  <th class="text-center">Cant.</th>
                  <th class="text-right">Precio</th>
                  <th class="text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody id="det-tbody"></tbody>
              <tfoot>
                <tr>
                  <th colspan="5" class="text-right">Total</th>
                  <th class="text-right h5 mb-0" id="det-total">$0.00</th>
                </tr>
              </tfoot>
            </table>
          </div>

          <div id="wrap-det-desglose" class="d-none mt-3">
            <h5 class="mb-2">Desglose de pagos</h5>
            <div class="border rounded p-3">
              <div id="det-desglose-items" class="mb-2"></div>
              <div class="d-flex justify-content-between align-items-center font-weight-bold">
                <span>Total desglose</span>
                <span id="det-desglose-total">$0.00</span>
              </div>
              <div class="small text-muted" id="det-desglose-validacion"></div>
            </div>
          </div>

          <hr>

          <div id="wrap-det-cfdi" class="mt-3">
            <h5 class="mb-2">Facturación</h5>
            <div class="row">
              <div class="col-md-3 mb-3">
                <small class="text-primary font-weight-bold">Estatus fiscal</small>
                <div class="h5 mb-0" id="det-cfdi-estatus">SIN FACTURAR</div>
              </div>
              <div class="col-md-3 mb-3">
                <small class="text-primary font-weight-bold">UUID</small>
                <div class="h5 mb-0" id="det-cfdi-uuid">—</div>
              </div>
              <div class="col-md-3 mb-3">
                <small class="text-primary font-weight-bold">Referencia</small>
                <div class="h5 mb-0" id="det-cfdi-ref">—</div>
              </div>
              <div class="col-md-3 mb-3">
                <small class="text-primary font-weight-bold">Fecha timbrado</small>
                <div class="h5 mb-0" id="det-cfdi-fecha">—</div>
              </div>
              <div class="col-12 mb-2">
                <div id="det-cfdi-msg" class="alert alert-light border mb-2">Sin CFDI generado.</div>
              </div>
              <div class="col-12 d-flex flex-wrap gap-2">
                <a id="det-cfdi-xml" href="#" class="btn btn-outline-secondary btn-sm mr-2 d-none" target="_blank">
                  <i class="mdi mdi-xml mr-1"></i>XML
                </a>
                <a id="det-cfdi-pdf" href="#" class="btn btn-outline-secondary btn-sm d-none" target="_blank">
                  <i class="mdi mdi-file-pdf-box mr-1"></i>PDF
                </a>
              </div>
            </div>
          </div>

          <!-- Abonos (oculto por defecto) -->
          <div id="wrap-det-abonos" class="d-none">
            <h5 class="mb-2">Abonos</h5>
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

          <!-- Error -->
          <div id="det-error" class="alert alert-danger my-3" style="display:none;">
            No se pudo cargar el detalle.
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
      </div>

    </div>
  </div>
</div>
