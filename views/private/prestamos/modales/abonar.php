<div class="modal fade" id="modalAbonar" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
              <form id="formAbono">
                <div class="modal-header py-2">
                  <h5 class="modal-title">Registrar abono</h5>
                  <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="id_prestamo" id="abono_id_prestamo">
                  <div class="form-group">
                    <label class="form-label">Fecha</label>
                    <input type="date" class="form-control" name="fecha_abono" value="<?= date('Y-m-d') ?>">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Monto</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="monto" required>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Referencia de pago</label>
                    <input type="text" class="form-control" name="referencia_pago" placeholder="Opcional">
                  </div>
                </div>
                <div class="modal-footer py-2">
                  <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                  <button type="submit" class="btn btn-success"><i class="mdi mdi-check-circle-outline"></i> Guardar abono</button>
                </div>
              </form>
            </div>
          </div>
        </div>