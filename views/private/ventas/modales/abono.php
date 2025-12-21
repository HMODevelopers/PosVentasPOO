<!-- Modal Abonar Venta -->
<div class="modal fade" id="modalAbonarVenta" tabindex="-1" role="dialog" aria-labelledby="modalAbonarVentaLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      
      <form id="formAbonoVenta" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title" id="modalAbonarVentaLabel">Abonar a venta</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">

          <!-- Hidden: id venta -->
          <input type="hidden" id="ab-id-venta" name="id_venta">

          <!-- Folio y saldo en el mismo renglón -->
          <div class="form-row">
            <div class="form-group col-md-6 mb-2">
              <label>Folio de venta:</label>
              <div><strong id="ab-folio">—</strong></div>
            </div>

            <div class="form-group col-md-6 mb-2">
              <label>Saldo pendiente:</label>
              <div><strong id="ab-saldo">$0.00</strong></div>
            </div>
          </div>

          <!-- Fecha de abono -->
          <div class="form-group">
            <label for="ab-fecha">Fecha del abono</label>
            <input type="date" class="form-control" id="ab-fecha" name="fecha_abono">
          </div>

          <!-- Forma de pago (incluye opción Mixto) -->
          <div class="form-group">
            <label for="ab-forma">Forma de pago</label>
            <select id="ab-forma" class="form-control">
              <!-- Se llenará por JS (cargarFormasPagoAbono) -->
            </select>
          </div>

          <!-- Bloque para pago mixto -->
          <div id="wrapAbonoMixto" class="border rounded p-2 mb-3 d-none">
            <div class="mb-2">
              <strong>Detalle pago mixto</strong>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="ab-monto-efe">Efectivo</label>
                <input 
                  type="number" 
                  step="0.01" 
                  min="0" 
                  class="form-control" 
                  id="ab-monto-efe"
                  placeholder="0.00">
              </div>
              <div class="form-group col-md-6">
                <label for="ab-monto-tar">Tarjeta</label>
                <input 
                  type="number" 
                  step="0.01" 
                  min="0" 
                  class="form-control" 
                  id="ab-monto-tar"
                  placeholder="0.00">
              </div>
            </div>

            <div class="text-right">
              <small>Total mixto:</small>
              <div><strong id="ab-total-mixto">$0.00</strong></div>
            </div>
          </div>

          <!-- Monto total del abono -->
          <div class="form-group">
            <label for="ab-monto">Monto del abono</label>
            <input 
              type="number" 
              step="0.01" 
              min="0" 
              class="form-control" 
              id="ab-monto" 
              name="monto"
              placeholder="0.00">
          </div>

          <!-- Referencia del pago -->
          <div class="form-group">
            <label for="ab-ref">Referencia / Nota</label>
            <input 
              type="text" 
              class="form-control" 
              id="ab-ref" 
              name="referencia_pago"
              placeholder="Ej. ABONO 123, folio, etc.">
          </div>

          <!-- Mensajes de error -->
          <div id="ab-error" class="alert alert-danger d-none"></div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
          <button type="submit" id="btnConfirmarAbono" class="btn btn-primary">
            Guardar abono
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
