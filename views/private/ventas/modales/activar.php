<!-- Modal Activar Venta Guardada -->
<div class="modal fade" id="modalActivarVenta" tabindex="-1" role="dialog" aria-labelledby="lblActivarVenta" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title">
          Activar venta <span class="text-muted">Folio</span> <b id="ac-folio">—</b>
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="ac-id-venta" value="">

        <div class="form-group">
          <label for="ac-selFormaPago">Forma de pago</label>
          <select id="ac-selFormaPago" class="form-control">
            <option value="">Cargando…</option>
          </select>
          <small class="text-muted">Se requiere una forma de pago para contabilizarla en el corte.</small>
        </div>

        <!-- Cliente: oculto hasta que la FP sea Crédito -->
        <div id="ac-wrapCliente" class="form-group d-none">
          <label for="ac-selCliente">Cliente</label>
          <select id="ac-selCliente" class="form-control">
            <option value="">Cargando…</option>
          </select>
          <small id="ac-helpCliente" class="text-muted d-block mt-1"></small>
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
