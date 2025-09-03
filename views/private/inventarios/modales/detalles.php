<div class="modal fade" id="modalDetalleMovimiento" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="mdi mdi-eye mr-1"></i> Detalle de Movimiento</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">×</span>
              </button>
            </div>
            <div class="modal-body">
              <div id="det-loader" class="my-3 text-center">
                <div class="spinner-border" role="status"></div>
                <p class="mt-2">Cargando…</p>
              </div>
              <div id="det-error" class="alert alert-danger" style="display:none;"></div>

              <div id="det-contenido" style="display:none;">
                <div class="row">
                  <div class="col-md-4 mb-2">
                    <small class="text-primary font-weight-bold">Fecha</small>
                    <div id="det-fecha" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-4 mb-2">
                    <small class="text-primary font-weight-bold">Tipo</small>
                    <div id="det-tipo" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-4 mb-2">
                    <small class="text-primary font-weight-bold">Cantidad</small>
                    <div id="det-cantidad" class="h5 mb-0">—</div>
                  </div>

                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Código</small>
                    <div id="det-codigo" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Producto</small>
                    <div id="det-producto" class="h5 mb-0">—</div>
                  </div>

                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Sucursal</small>
                    <div id="det-sucursal" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Usuario</small>
                    <div id="det-usuario" class="h5 mb-0">—</div>
                  </div>

                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Referencia</small>
                    <div id="det-referencia" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Motivo</small>
                    <div id="det-motivo" class="h5 mb-0">—</div>
                  </div>

                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Estatus</small>
                    <div id="det-estatus" class="h5 mb-0">—</div>
                  </div>
                  <div class="col-md-6 mb-2">
                    <small class="text-primary font-weight-bold">Creado</small>
                    <div id="det-creado" class="h5 mb-0">—</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-light" data-dismiss="modal">Cerrar</button>
            </div>
          </div>
        </div>
      </div>