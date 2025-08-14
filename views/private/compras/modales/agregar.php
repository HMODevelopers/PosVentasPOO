<!-- Modal: Agregar Compra -->
<div class="modal fade" id="modalAgregarCompra" tabindex="-1" role="dialog" aria-labelledby="agregarCompraLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="agregarCompraLabel">Nueva compra</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body pb-0">
        <!-- Encabezado -->
        <div class="row">
         <div class="col-md-3">
            <div class="form-group mb-2">
                <label class="control-label">Proveedor*</label>
                <select id="ac-proveedor" class="form-control" disabled>
                <option value="">-- Selecciona --</option>
                </select>
            </div>
        </div>
          <div class="col-md-3">
            <div class="form-group mb-2">
              <label class="control-label">Folio factura</label>
              <input type="text" id="ac-folio" class="form-control" placeholder="Opcional">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group mb-2">
              <label class="control-label">Fecha factura</label>
              <input type="date" id="ac-fecha" class="form-control">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group mb-2">
              <label class="control-label">Estatus</label>
              <select id="ac-estatus" class="form-control">
                <option value="Pendiente" selected>Pendiente</option>
                <option value="Pagada">Pagada</option>
                <option value="Parcial">Parcial</option>
              </select>
            </div>
          </div>
        </div>

        <hr class="mt-1 mb-2"/>

        <!-- Detalle -->
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="mb-1">Artículos</h6>
          <div>
            <button type="button" class="btn btn-light btn-sm" id="ac-btn-limpiar">
              <i class="mdi mdi-broom"></i> Limpiar renglones
            </button>
            <button type="button" class="btn btn-success btn-sm" id="ac-btn-agregar">
              <i class="mdi mdi-plus"></i> Agregar renglón
            </button>
          </div>
        </div>

         <!-- Detalle -->
        <div class="table-responsive mt-2" style="max-height: 250px; overflow-y: auto;">
        <table class="table table-bordered table-hover table-sm mb-2" id="ac-tabla">
           <thead class="thead-light">
                <tr>
                    <th style="min-width: 260px;">Producto*</th>
                    <th>Cantidad*</th>
                    <th>Costo Unitario*</th>
                    <th>Importe</th>
                    <th style="width:60px;">&nbsp;</th>
                </tr>
            </thead>
            <tbody id="ac-tbody">
            <!-- filas dinámicas -->
            </tbody>
            <tfoot>
            <tr>
                <th colspan="3" class="text-right">Total:</th>
                <th class="text-right" id="ac-total">$0.00</th>
                <th></th>
            </tr>
            </tfoot>
        </table>
        </div>

        <div id="ac-error" class="alert alert-danger mb-0" style="display:none;"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="ac-btn-guardar">
          <span class="txt">Guardar compra</span>
        </button>
      </div>

    </div>
  </div>
</div>
