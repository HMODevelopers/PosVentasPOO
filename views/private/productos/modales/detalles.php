 <div id="modalDetalleProducto" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Detalle del producto</h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
              </div>
              <div class="modal-body">

                <div id="det-loader" class="text-center py-5" style="display:none;">
                  <div class="spinner-border" role="status"><span class="sr-only">Cargando...</span></div>
                </div>

                <div id="det-error" class="alert alert-danger" style="display:none;"></div>

                <div id="det-contenido" style="display:none;">
                  <div class="row">
                    <div class="col-md-4 mb-3">
                      <small class="text-primary font-weight-bold">Código</small>
                      <div class="h5 mb-0" id="det-codigo">—</div>
                    </div>
                    <div class="col-md-8 mb-3">
                      <small class="text-primary font-weight-bold">Descripción</small>
                      <div class="h5 mb-0" id="det-descripcion">—</div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Proveedor</small>
                      <div id="det-proveedor">—</div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Unidad SAT</small>
                      <div id="det-unidad">—</div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Clave Prod/Serv SAT</small>
                      <div id="det-clave">—</div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Creado</small>
                      <div id="det-creado">—</div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Stock</small>
                      <div id="det-stock">—</div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Stock Mín.</small>
                      <div id="det-stock-min">—</div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Stock Máx.</small>
                      <div id="det-stock-max">—</div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Precio Público</small>
                      <div id="det-precio-publico">—</div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Precio Proveedor</small>
                      <div id="det-precio-proveedor">—</div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Costo Neto</small>
                      <div id="det-costo-neto">—</div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Precio Taller</small>
                      <div id="det-precio-taller">—</div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <small class="text-primary font-weight-bold">Ubicación (P/P/E/Pe)</small>
                      <div id="det-ubicacion">—</div>
                    </div>
                  </div>
                </div>

              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
              </div>
            </div>
          </div>
        </div>