<!-- =================== Modal Detalle =================== -->
        <div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog" aria-labelledby="detalleCompraLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">

              <div class="modal-header">
                <h5 class="modal-title" id="detalleCompraLabel">Detalle de Compra</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>

              <div class="modal-body">
                <div id="det-loader" class="text-center my-3" style="display:none;">
                  <div class="spinner-border" role="status"></div>
                  <div class="mt-2">Cargando…</div>
                </div>
                <div id="det-error" class="alert alert-danger" style="display:none;"></div>

                <div id="det-contenido" style="display:none;">
                  <div class="row">
                    <div class="col-md-3 mb-2">
                      <small class="text-primary font-weight-bold">Folio</small>
                      <div class="h5 mb-0" id="det-folio">—</div>
                    </div>
                    <div class="col-md-3 mb-2">
                      <small class="text-primary font-weight-bold">Fecha Factura</small>
                      <div class="h5 mb-0" id="det-fecha">—</div>
                    </div>
                    <div class="col-md-3 mb-2">
                      <small class="text-primary font-weight-bold">Estatus</small>
                      <div class="h5 mb-0" id="det-estatus">—</div>
                    </div>
                    <div class="col-md-3 mb-2">
                      <small class="text-primary font-weight-bold">Total</small>
                      <div class="h5 mb-0" id="det-total">—</div>
                    </div>

                    <div class="col-md-4 mb-2">
                      <small class="text-primary font-weight-bold">Proveedor</small>
                      <div class="h6 mb-0" id="det-proveedor">—</div>
                    </div>
                    <div class="col-md-4 mb-2">
                      <small class="text-primary font-weight-bold">Usuario</small>
                      <div class="h6 mb-0" id="det-usuario">—</div>
                    </div>
                    <div class="col-md-4 mb-2">
                      <small class="text-primary font-weight-bold">Sucursal</small>
                      <div class="h6 mb-0" id="det-sucursal">—</div>
                    </div>
                  </div>

                  <hr/>

                  <div class="table-responsive">
                    <table class="table table-hover table-striped table-sm table-bordered mb-0">
                      <thead>
                        <tr>
                          <th>Código</th>
                          <th>Producto</th>
                          <th class="text-center">Cantidad</th>
                          <th class="text-right">Costo</th>
                          <th class="text-right">Importe</th>
                        </tr>
                      </thead>
                      <tbody id="det-tbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
              </div>

            </div>
          </div>
        </div>
<!-- =================== /Modal Detalle =================== -->