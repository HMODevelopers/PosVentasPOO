<!-- =================== Modal Registrar Abono =================== -->
<div id="modalAbonar" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modalAbonarLabel" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <form id="formAbono" autocomplete="off">
        <!-- Header -->
        <div class="modal-header">
          <h5 class="modal-title" id="modalAbonarLabel">
            <i class="mdi mdi-cash-multiple"></i> Registrar abono
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <!-- Body -->
        <div class="modal-body">
          <input type="hidden" name="id_prestamo" id="abono_id_prestamo">

          <!-- Línea 1: Fecha / Monto / Forma de pago -->
          <div class="form-row">
            <div class="form-group col-md-4">
              <label class="form-label">Fecha</label>
              <input type="date" class="form-control" name="fecha_abono" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group col-md-4">
              <label class="form-label">Monto</label>
              <input type="number" step="0.01" min="0.01" class="form-control" name="monto" required>
              <small class="text-muted d-block" id="abono_hint_saldo"></small>
            </div>

            <div class="form-group col-md-4">
              <label class="form-label" for="selFormaPagoAbono">Forma de pago</label>
              <!-- 👇 ID cambiado para que coincida con tu JS -->
              <select name="id_forma_pago" id="selFormaPagoAbono" class="form-control" required>
                <option value="">Cargando…</option>
              </select>
            </div>
          </div>

          <!-- Línea 2: Referencia -->
          <div class="form-row">
            <div class="form-group col-12">
              <label class="form-label">Referencia de pago</label>
              <input type="text" class="form-control" name="referencia_pago" placeholder="Opcional">
            </div>
          </div>
        </div><!-- /body -->

        <!-- Footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-success">
            <i class="mdi mdi-check-circle-outline"></i> Guardar abono
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- =================== /Modal Registrar Abono =================== -->
