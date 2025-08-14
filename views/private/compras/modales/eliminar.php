<!-- =================== Modal Eliminar =================== -->
        <div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-labelledby="eliminarCompraLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width:600px">
            <div class="modal-content">

              <div class="modal-header">
                <h5 class="modal-title" id="eliminarCompraLabel">Cancelar compra</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>

              <div class="modal-body">
                <input type="hidden" id="el-id-compra">
                <p>¿Seguro que deseas <b>cancelar</b> la compra <b>#<span id="el-folio">—</span></b>? Esta acción ajustará el inventario.</p>
              </div>

              <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmarEliminarCompra"><i class="mdi mdi-delete-forever"></i>Cancelar compra</button>
              </div>

            </div>
          </div>
        </div>
<!-- =================== /Modal Eliminar =================== -->