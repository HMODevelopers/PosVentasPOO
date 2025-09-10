<!-- Modal Abonar Venta (solo para ventas en Crédito) -->
<div class="modal fade" id="modalAbonarVenta" tabindex="-1" role="dialog" aria-labelledby="lblAbonarVenta" aria-hidden="true">
  <div class="modal-dialog modal-ms modal-center" role="document">
    <form id="formAbonoVenta" class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title">Abonar venta <span class="text-muted">Folio</span> <b id="ab-folio">—</b></h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="ab-id-venta" name="id_venta" value="">
        <div class="mb-2">
          <small class="text-muted">Saldo actual: <b id="ab-saldo">$0.00</b></small>
        </div>

        <div class="form-group">
          <label for="ab-monto">Monto a abonar</label>
          <input type="number" class="form-control" id="ab-monto" name="monto" min="0" step="0.01" required>
        </div>

        <div class="form-group">
          <label for="ab-forma">Forma de pago</label>
          <select id="ab-forma" name="id_forma_pago" class="form-control" required>
            <option value="">Cargando…</option>
          </select>
        </div>

        <div class="form-group">
          <label for="ab-fecha">Fecha del abono</label>
          <input type="date" id="ab-fecha" name="fecha_abono" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
          <label for="ab-ref">Referencia (opcional)</label>
          <input type="text" id="ab-ref" name="referencia_pago" class="form-control" maxlength="100" placeholder="Folio terminal / transferencia / nota">
        </div>

        <div id="ab-error" class="alert alert-danger d-none"></div>
      </div>

      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
        <button type="submit" class="btn btn-success" id="btnConfirmarAbono">
          <i class="mdi mdi-cash-plus"></i> Registrar abono
        </button>
      </div>
    </form>
  </div>
</div>
