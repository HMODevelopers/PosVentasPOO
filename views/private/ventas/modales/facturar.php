<!-- Modal Facturar Venta -->
<div class="modal fade" id="modalFacturarVenta" tabindex="-1" role="dialog" aria-labelledby="modalFacturarVentaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <form id="formFacturarVenta" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title" id="modalFacturarVentaLabel">Facturar venta</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="fac-id-venta" name="id_venta">

          <div id="fac-loader" class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="mt-2 text-muted">Cargando información de facturación...</div>
          </div>

          <div id="fac-error" class="alert alert-danger d-none mb-3"></div>
          <div id="fac-warning" class="alert alert-warning d-none mb-3"></div>
          <div id="fac-success" class="alert alert-success d-none mb-3"></div>

          <div id="fac-contenido" class="d-none">
            <div class="row">
              <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                  <h6 class="mb-2">Venta</h6>
                  <div class="mb-1"><strong>Folio:</strong> <span id="fac-folio">—</span></div>
                  <div class="mb-1"><strong>Fecha:</strong> <span id="fac-fecha">—</span></div>
                  <div class="mb-1"><strong>Cliente:</strong> <span id="fac-cliente">—</span></div>
                  <div class="mb-1"><strong>Forma de pago:</strong> <span id="fac-forma-pago">—</span></div>
                  <div class="mb-1"><strong>Total:</strong> <span id="fac-total">$0.00</span></div>
                  <div class="mb-0"><strong>Estatus fiscal:</strong> <span id="fac-estatus-fiscal">—</span></div>
                </div>
              </div>

              <div class="col-md-6 mt-3 mt-md-0">
                <div class="border rounded p-3 h-100">
                  <h6 class="mb-2">Datos del receptor</h6>
                  <div class="mb-1"><strong>RFC:</strong> <span id="fac-rfc">—</span></div>
                  <div class="mb-1"><strong>Razón social:</strong> <span id="fac-razon-social">—</span></div>
                  <div class="mb-1"><strong>Uso CFDI:</strong> <span id="fac-uso-cfdi">—</span></div>
                  <div class="mb-1"><strong>Régimen fiscal:</strong> <span id="fac-regimen">—</span></div>
                  <div class="mb-0"><strong>C.P.:</strong> <span id="fac-cp">—</span></div>
                </div>
              </div>
            </div>

            <div class="mt-3">
              <h6 class="mb-2">Conceptos</h6>
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                  <thead class="thead-light">
                    <tr>
                      <th style="width:80px;" class="text-center">Cant.</th>
                      <th style="width:130px;">Clave</th>
                      <th>Descripción</th>
                      <th style="width:120px;" class="text-right">P.U.</th>
                      <th style="width:130px;" class="text-right">Importe</th>
                    </tr>
                  </thead>
                  <tbody id="fac-detalles-body">
                    <tr><td colspan="5" class="text-center text-muted">Sin conceptos</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="mt-3">
              <h6 class="mb-2">Validación previa</h6>
              <ul id="fac-validaciones" class="mb-0 pl-3 text-muted">
                <li>Sin validaciones disponibles.</li>
              </ul>
            </div>

            <div id="fac-archivos" class="mt-3 d-none">
              <h6 class="mb-2">Archivos CFDI</h6>
              <div class="btn-group btn-group-sm" role="group" aria-label="Archivos CFDI">
                <a id="fac-link-xml" href="#" target="_blank" class="btn btn-outline-secondary">
                  <i class="mdi mdi-code-tags"></i> XML
                </a>
                <a id="fac-link-pdf" href="#" target="_blank" class="btn btn-outline-secondary">
                  <i class="mdi mdi-file-pdf-box"></i> PDF
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
          <button type="submit" id="btnConfirmarFacturar" class="btn btn-primary" disabled>
            <i class="mdi mdi-file-document-check-outline mr-1"></i>Facturar venta
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
