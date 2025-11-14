<!-- MODAL: Ticket -->
<div class="modal fade" id="modalTicket" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title mb-0">Ticket de venta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>×</span></button>
      </div>

      <div class="modal-body pt-2">
        <input type="hidden" id="tk-idventa" />
        <!-- SOLO ESTO SE IMPRIME -->
        <div id="ticketArea" class="print-area tk">
          <!-- ENCABEZADO -->
          <div class="text-center">
            <div class="tk-razon">REFACCIONARIA RIVERA</div>
            <div>KARINA VALENTINA RIVERA LEON</div>
            <div>RFC: RILK830214NI9</div>
            <div>Régimen Fiscal: 612</div>
            <div>Blvd. Solidaridad 601, Col. Choyal</div>
            <div>C.P. 83130 Hermosillo, Sonora</div>
            <div>Tel: (662) 262-1129</div>
          </div>

          <div class="tk-line my-2"></div>

          <!-- FECHA / FOLIO -->
          <div class="tk-meta">
            <div class="left"><strong>Fecha:</strong> <span id="tk-fecha">—</span></div>
            <div class="right"><strong>Folio:</strong> <span id="tk-folio">—</span></div>
          </div>

          <!-- ESTATUS -->
          <div class="tk-meta-line">
            <strong>Estatus:</strong> <span id="tk-estatus">—</span>
          </div>

          <!-- CLIENTE (solo crédito; se muestra por JS) -->
          <div class="tk-meta-line d-none" id="wrap-tk-cliente">
            <strong>Cliente:</strong> <span id="tk-cliente">—</span>
          </div>

          <div class="tk-line my-2"></div>

          <!-- CABECERA DE DETALLE -->
          <div class="tk-head">
            <div class="c-cant">CANT</div>
            <div class="c-art">ARTICULO</div>
            <div class="c-precio">PRECIO</div>
            <div class="c-total">TOTAL</div>
          </div>

          <!-- ITEMS -->
          <div id="tk-items"></div>

          <div class="tk-line my-2"></div>

          <!-- TOTALES -->
          <div class="tk-totals">
            <div class="lbl">TOTAL:</div>
            <div id="tk-total" class="val">$ 0.00</div>
          </div>

          <div class="text-center mt-2">
            <div>GRACIAS POR TU COMPRA</div>
            <div>EN PARTES ELECTRICAS NO HAY GARANTIA</div>
          </div>
        </div>
        <!-- /print-area -->
      </div>

      <div class="modal-footer py-2 d-flex justify-content-between">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnImprimirTicket">
          <i class="mdi mdi-printer"></i> Imprimir
        </button>
      </div>
    </div>
  </div>
</div>
