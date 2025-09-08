<!-- =================== Modal Detalle Préstamo =================== -->
<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog" aria-labelledby="detallePrestamoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header">
        <h5 class="modal-title" id="detallePrestamoLabel">Detalle del Préstamo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body">
        <!-- Loader -->
        <div id="det-loader" class="text-center my-3" style="display:none;">
          <div class="spinner-border" role="status"></div>
          <div class="mt-2">Cargando…</div>
        </div>

        <!-- Error -->
        <div id="det-error" class="alert alert-danger" style="display:none;"></div>

        <!-- Contenido -->
        <div id="det-contenido" style="display:none;">
          <div class="row">
            <div class="col-md-3 mb-2">
              <small class="text-primary font-weight-bold">Folio</small>
              <div class="h5 mb-0" id="det-folio">—</div>
            </div>
            <div class="col-md-3 mb-2">
              <small class="text-primary font-weight-bold">Beneficiario</small>
              <div class="h6 mb-0" id="det-beneficiario">—</div>
            </div>
            <div class="col-md-3 mb-2">
              <small class="text-primary font-weight-bold">Tipo</small>
              <div class="h6 mb-0" id="det-tipo">—</div>
            </div>
            <div class="col-md-3 mb-2">
              <small class="text-primary font-weight-bold">Estatus</small>
              <div class="h6 mb-0" id="det-estatus">—</div>
            </div>

            <div class="col-md-4 mb-2">
              <small class="text-primary font-weight-bold">Monto</small>
              <div class="h5 mb-0" id="det-monto">—</div>
            </div>
            <div class="col-md-4 mb-2">
              <small class="text-primary font-weight-bold">Saldo</small>
              <div class="h5 mb-0" id="det-saldo">—</div>
            </div>
            <div class="col-md-4 mb-2">
              <small class="text-primary font-weight-bold">Fecha</small>
              <div class="h6 mb-0" id="det-fecha">—</div>
            </div>

            <div class="col-md-6 mb-2">
              <small class="text-primary font-weight-bold">Usuario</small>
              <div class="h6 mb-0" id="det-usuario">—</div>
            </div>
            <div class="col-md-6 mb-2">
              <small class="text-primary font-weight-bold">Sucursal</small>
              <div class="h6 mb-0" id="det-sucursal">—</div>
            </div>
          </div>

          <hr/>

          <!-- Tabla de Abonos -->
          <div class="table-responsive">
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Fecha</th>
                  <th class="text-right">Monto</th>
                  <th>Método</th>
                  <th>Referencia</th>
                  <th>Usuario</th>
                </tr>
              </thead>
              <tbody id="det-tbody">
                <tr>
                  <td colspan="6" class="text-muted text-center">Sin abonos</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <!-- /Contenido -->
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
      </div>

    </div>
  </div>
</div>
<!-- =================== /Modal Detalle Préstamo =================== -->
