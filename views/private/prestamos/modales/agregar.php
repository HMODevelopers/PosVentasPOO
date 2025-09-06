<!-- Modal: Nuevo préstamo / disposición -->
<div id="modalNuevo" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modalNuevoLabel" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <form id="formNuevo" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title" id="modalNuevoLabel">
            <i class="mdi mdi-plus"></i> Nuevo préstamo / disposición
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">

          <div class="alert alert-info py-2 px-3 mb-3">
            <i class="mdi mdi-information-outline"></i>
            Si eliges <b>Préstamo</b> después podrás registrar <b>abonos</b>. Una <b>Disposición</b> queda <b>SinRetorno</b>.
          </div>

          <!-- Línea 1: Tipo de operación / Beneficiario -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label class="form-label">Tipo de operación</label>
              <select name="tipo_operacion" id="tipo_operacion" class="form-control" required>
                <option value="Prestamo">Préstamo</option>
                <option value="Disposicion">Disposición</option>
              </select>
            </div>
            <div class="form-group col-md-6">
              <label class="form-label">Beneficiario</label>
              <select name="tipo" id="tipo" class="form-control">
                <option value="Cliente">Cliente</option>
                <option value="Empleado">Empleado</option>
                <option value="Otro">Otro</option>
              </select>
            </div>
          </div>

          <!-- Línea 2: Cliente / Empleado / Otro (se muestra uno según selección) -->
          <div class="form-row d-none" id="wrapCliente">
            <div class="form-group col-12">
              <label class="form-label">Cliente (opcional)</label>
              <select name="id_cliente" id="selCliente" class="form-control">
                <option value="">-- Seleccionar Opción --</option>
              </select>
            </div>
          </div>

          <div class="form-row d-none" id="wrapEmpleado">
            <div class="form-group col-12">
              <label class="form-label">Empleado (opcional)</label>
              <select name="id_empleado" id="selEmpleado" class="form-control">
                <option value="">-- Seleccionar Opción --</option>
              </select>
            </div>
          </div>

          <div class="form-row d-none" id="wrapOtro">
            <div class="form-group col-12">
              <label class="form-label">Nombre del beneficiario (Otro)</label>
              <input type="text" class="form-control" id="txtOtro" placeholder="Nombre de la persona" maxlength="120">
              <small class="text-muted">Se añadirá a <b>Concepto</b> como <b>[Beneficiario: Nombre]</b>.</small>
            </div>
          </div>

          <!-- Línea 3: Fecha / Monto -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label class="form-label">Fecha</label>
              <input type="date" class="form-control" name="fecha_prestamo" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group col-md-6">
              <label class="form-label">Monto</label>
              <input type="number" step="0.01" min="0.01" class="form-control" name="monto_total" required>
            </div>
          </div>

          <!-- Línea 4: Concepto -->
          <div class="form-row">
            <div class="form-group col-12">
              <label class="form-label">Concepto</label>
              <input type="text" class="form-control" name="concepto" id="inpConcepto" placeholder="Motivo / descripción">
            </div>
          </div>

        </div><!-- /modal-body -->

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">
            <i class="mdi mdi-content-save"></i> Guardar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
