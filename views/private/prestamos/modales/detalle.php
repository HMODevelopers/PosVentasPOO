<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
              <div class="modal-header py-2">
                <h5 class="modal-title">Detalle del registro</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
              </div>
              <div class="modal-body">
                <div id="det-loader" class="py-3 text-center"><div class="spinner-border"></div><div class="small text-muted mt-2">Cargando…</div></div>
                <div id="det-error" class="alert alert-danger d-none"></div>
                <div id="det-contenido" style="display:none;">
                  <div class="row">
                    <div class="col-md-6">
                      <p class="mb-1"><b>Folio:</b> <span id="det-folio">—</span></p>
                      <p class="mb-1"><b>Tipo:</b> <span id="det-tipo">—</span></p>
                      <p class="mb-1"><b>Estatus:</b> <span id="det-estatus">—</span></p>
                      <p class="mb-1"><b>Beneficiario:</b> <span id="det-benef">—</span></p>
                    </div>
                    <div class="col-md-6">
                      <p class="mb-1"><b>Monto:</b> <span id="det-monto">—</span></p>
                      <p class="mb-1"><b>Saldo:</b> <span id="det-saldo">—</span></p>
                      <p class="mb-1"><b>Fecha:</b> <span id="det-fecha">—</span></p>
                    </div>
                  </div>
                  <hr>
                  <h6>Abonos</h6>
                  <div class="table-responsive">
                    <table class="table table-sm">
                      <thead><tr><th>#</th><th>Fecha</th><th>Monto</th><th>Referencia</th></tr></thead>
                      <tbody id="det-tbody"><tr><td colspan="4" class="text-muted text-center">Sin abonos</td></tr></tbody>
                    </table>
                  </div>
                </div>
              </div>
              <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
              </div>
            </div>
          </div>
        </div>