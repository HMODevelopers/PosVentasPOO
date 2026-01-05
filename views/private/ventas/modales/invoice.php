<!-- MODAL: Invoice (A4) -->
<div class="modal fade" id="modalInvoice" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title mb-0">Nota de Venta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>×</span></button>
      </div>

      <div class="modal-body pt-2">
        <!-- SOLO ESTO SE IMPRIME -->
        <div id="invArea" class="print-area inv">
          <!-- ENCABEZADO -->
          <div class="inv-header d-flex justify-content-between align-items-start">
            <div class="inv-emisor">
              <div class="h5 mb-1">REFACCIONARIA RIVERA</div>
              <div>KARINA VALENTINA RIVERA LEON</div>
              <div>RFC: RILK830214NI9</div>
              <div>Régimen Fiscal: 612</div>
              <div>Blvd. Solidaridad 601, Col. Choyal</div>
              <div>C.P. 83130 Hermosillo, Sonora</div>
              <div>Tel: (662) 262-1129</div>
            </div>
            <div class="text-right inv-meta">
              <div class="h4 mb-0">NOTA VENTA</div>
              <div><b>Folio:</b> <span id="inv-folio">—</span></div>
              <div><b>Fecha:</b> <span id="inv-fecha">—</span></div>
              <div><b>Forma de pago:</b> <span id="inv-forma">—</span></div>
              <div><b>Tipo de precio:</b> <span id="inv-tipo">—</span></div>
              <div><b>Estatus:</b> <span id="inv-estatus" class="ml-1"></span></div>
            </div>
          </div>

          <hr class="my-2"/>

          <!-- CLIENTE -->
          <div class="inv-seccion">
            <div class="h6 mb-1">Datos del cliente</div>
            <div><b>Nombre / Razón social:</b> <span id="inv-cliente">Público en general</span></div>
            <div><b>RFC:</b> <span id="inv-rfc">N/A</span></div>
            <div><b>Domicilio:</b> <span id="inv-dom">N/A</span></div>
            <div><b>Teléfono:</b> <span id="inv-tel">N/A</span></div>
          </div>

          <!-- PÓLIZAS (solo acumuladores) -->
          <div class="inv-seccion" id="inv-polizas-wrap" style="display:none;">
            <div><span id="inv-polizas"></span></div>
          </div>

          <!-- DETALLE -->
          <div class="inv-seccion">
            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-2 inv-table">
                <thead class="thead-light">
                  <tr>
                    <th style="width: 70px;" class="text-center">CANT</th>
                    <th style="width: 120px;">CLAVE</th>
                    <th>DESCRIPCIÓN</th>
                    <th style="width: 110px;" class="text-right">P.U.</th>
                    <th style="width: 130px;" class="text-right">IMPORTE</th>
                  </tr>
                </thead>
                <tbody id="inv-tbody">
                  <tr><td colspan="5" class="text-center text-muted">Sin productos</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- TOTALES -->
          <div class="d-flex justify-content-end">
            <table class="table table-sm w-auto mb-0">
              <tbody>
                <tr>
                  <th class="text-right pr-3">Subtotal:</th>
                  <td class="text-right" id="inv-subtotal">$0.00</td>
                </tr>
                <tr id="inv-row-descuento" style="display:none;">
                  <th class="text-right pr-3">Descuento:</th>
                  <td class="text-right" id="inv-descuento">$0.00</td>
                </tr>
                <tr id="inv-row-iva" style="display:none;">
                  <th class="text-right pr-3">IVA:</th>
                  <td class="text-right" id="inv-iva">$0.00</td>
                </tr>
                <tr class="table-active">
                  <th class="text-right pr-3 h5 mb-0">TOTAL:</th>
                  <td class="text-right h5 mb-0" id="inv-total">$0.00</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- NOTAS -->
          <div class="inv-notas mt-3">
            <div class="small text-muted">
              * Documento de previsualización para impresión en hoja tamaño carta/A4. <br/>
              * En partes eléctricas no hay garantía.
            </div>
          </div>
        </div>
        <!-- /print-area -->
      </div>

      <div class="modal-footer py-2 d-flex justify-content-between no-print">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cerrar</button>
        <div>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDescargarInvoicePDF" style="display:none;">
            <i class="mdi mdi-file-pdf-box"></i> PDF
          </button>
          <button type="button" class="btn btn-primary btn-sm" id="btnImprimirInvoice">
            <i class="mdi mdi-printer"></i> Imprimir
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
