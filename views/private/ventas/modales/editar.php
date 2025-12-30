<div class="modal fade" id="modalEditarVenta" tabindex="-1" role="dialog" aria-labelledby="lblEditarVenta" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
              <div class="modal-header py-2">
                <h5 class="modal-title">
                  Editar venta <span class="text-muted">Folio</span> <b id="ed-folio">—</b>
                  <small id="ed-estatus" class="ml-2"></small>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
              </div>

              <div class="modal-body">
                <!-- Encabezado editable (todo editable) -->
                <div class="row g-2 mb-3">
                  <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Cliente</label>
                    <select id="ed-selCliente" class="form-control">
                      <option value="">Cargando clientes…</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Tipo de precio</label>
                    <!-- usa los mismos slugs que caja -->
                    <select id="ed-tpPrecio" class="form-control">
                      <option value="publico">Mostrador (Público)</option>
                      <option value="taller">Taller</option>
                      <option value="proveedor">Proveedor</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Fecha venta</label>
                    <input type="date" id="ed-fechaVenta" class="form-control" value="<?= date('Y-m-d') ?>">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Forma de pago</label>
                    <select id="ed-selFormaPago" class="form-control">
                      <option value="">Cargando…</option>
                    </select>
                  </div>
                </div>

                <div id="ed-wrapMixto" class="row g-2 mb-2 d-none">
                  <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Efectivo</label>
                    <input type="number" min="0" step="0.01" class="form-control" id="ed-mixto-efectivo" value="0">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Tarjeta</label>
                    <input type="number" min="0" step="0.01" class="form-control" id="ed-mixto-tarjeta" value="0">
                  </div>
                  <div class="col-12 col-md-6 d-flex align-items-end">
                    <div class="text-muted small" id="ed-helpMixto">La suma debe coincidir con el total de la venta.</div>
                  </div>
                </div>

                <!-- Info fija (solo informativa) -->
                <div class="row g-2 mb-2">
                  <div class="col-sm-4">
                    <label class="form-label mb-0"><small>Creada</small></label>
                    <div id="ed-fecha" class="form-control-plaintext py-0">—</div>
                  </div>
                  <div class="col-sm-4">
                    <label class="form-label mb-0"><small>Cajero / Caja</small></label>
                    <div id="ed-usr-caja" class="form-control-plaintext py-0">—</div>
                  </div>
                  <div class="col-sm-4">
                    <label class="form-label mb-0"><small>&nbsp;</small></label>
                    <div class="form-control-plaintext py-0">&nbsp;</div>
                  </div>
                </div>

                <!-- Buscar y agregar productos (igual que caja) -->
                <div class="border rounded p-2 mb-2 ed-search-wrap">
                  <label class="form-label">Agregar producto</label>
                  <input id="ed-buscar" type="text" class="form-control" placeholder="Nombre o código… (↑/↓ navega, Enter agrega)" autocomplete="off">
                  <div id="ed-sug" class="list-group ed-sug-panel"></div>
                  <small class="text-muted">Escribe para buscar; Enter agrega el seleccionado o intenta por código exacto (escáner).</small>
                </div>

                <!-- Carrito editable (mismo layout de caja) -->
                <div id="ed-wrapCarritoVacio" class="text-muted text-center py-4">
                  No hay productos en la orden.
                </div>
                <div id="ed-wrapCarritoTabla" class="d-none">
                  <div class="carrito-scroll" id="ed-tabla">
                    <table class="table align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th>Producto</th>
                          <th class="text-center" style="width:210px;">Cant.</th>
                          <th class="text-end" style="width:140px;">Costo unitario</th>
                          <th class="text-end" style="width:160px;">Subtotal</th>
                          <th class="text-end" style="width:54px;"></th>
                        </tr>
                      </thead>
                      <tbody id="ed-tbody"></tbody>
                    </table>
                  </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end mt-2">
                  <h4 class="mb-0">Total: <span id="ed-total">$0.00</span></h4>
                </div>

                <div id="ed-error" class="alert alert-danger mt-2 d-none"></div>
              </div>

              <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarEdicion">
                  <i class="mdi mdi-content-save"></i> Guardar cambios
                </button>
              </div>
            </div>
          </div>
        </div>