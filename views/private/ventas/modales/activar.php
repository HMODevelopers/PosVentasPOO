<!-- Modal Activar Venta Guardada -->
<div class="modal fade" id="modalActivarVenta" tabindex="-1" role="dialog" aria-labelledby="lblActivarVenta" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="lblActivarVenta">
          Activar venta <span class="text-muted">Folio</span> <b id="ac-folio">—</b>
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <!-- ID de la venta -->
        <input type="hidden" id="ac-id-venta" value="">
        <!-- Total de la venta (para validar mixto) -->
        <input type="hidden" id="ac-total-venta" value="0">

        <!-- Forma de pago principal (simple / mixto / crédito) -->
        <div class="form-group">
          <label for="ac-selFormaPago">Forma de pago</label>
          <select id="ac-selFormaPago" class="form-control">
            <option value="">Cargando…</option>
          </select>
          <small class="text-muted d-block">
            Se requiere una forma de pago para contabilizarla en el corte.
            Si eliges <b>Mixto</b>, podrás capturar varias formas de pago.
          </small>
        </div>

        <!-- Cliente: oculto hasta que la FP sea Crédito -->
        <div id="ac-wrapCliente" class="form-group d-none">
          <label for="ac-selCliente">Cliente</label>
          <select id="ac-selCliente" class="form-control">
            <option value="">Cargando…</option>
          </select>
          <small id="ac-helpCliente" class="text-muted d-block mt-1"></small>
        </div>

        <!-- BLOQUE PARA PAGO MIXTO -->
        <div id="ac-wrapMixto" class="border rounded p-2 d-none mt-2">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <strong>Pagos mixtos</strong>
              <small class="d-block text-muted">
                Captura las formas de pago y montos. La suma debe coincidir con el total de la venta.
              </small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="ac-btnAddPago">
              <i class="mdi mdi-plus"></i> Agregar pago
            </button>
          </div>

          <table class="table table-sm mb-1">
            <thead>
              <tr>
                <th style="width:55%">Forma de pago</th>
                <th style="width:35%">Monto</th>
                <th style="width:10%"></th>
              </tr>
            </thead>
            <tbody id="ac-tbMixto">
              <!-- filas dinámicas -->
            </tbody>
          </table>

          <div class="text-right mt-1">
            <small class="text-muted d-block">
              Total venta: <b id="ac-totalVentaTexto">$0.00</b>
            </small>
            <small class="text-muted d-block">
              Suma de pagos: <b id="ac-sumaPagosTexto">$0.00</b>
            </small>
            <small id="ac-helpSumaPagos" class="text-danger d-none">
              La suma de los pagos debe ser igual al total de la venta.
            </small>
          </div>
        </div>

        <div class="form-group form-check mt-2">
          <input class="form-check-input" type="checkbox" id="ac-fechaAhora" checked>
          <label class="form-check-label" for="ac-fechaAhora">Usar fecha y hora actual al activar</label>
        </div>

        <div id="ac-error" class="alert alert-danger d-none mt-2"></div>
      </div>

      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-success" id="btnConfirmarActivar">
          <i class="mdi mdi-check-circle-outline"></i> Activar
        </button>
      </div>
    </div>
  </div>
</div>
<!-- /Modal Activar -->
