<!-- MODAL: Ticket -->
<div class="modal fade" id="modalTicket" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title mb-0">Ticket de venta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>×</span></button>
      </div>

      <div class="modal-body pt-2">
        <!-- SOLO ESTO SE IMPRIME -->
        <div id="ticketArea" class="print-area tk"
             style="margin:0 auto; font-family:monospace; font-size:12px; line-height:1.15;">

          <!-- ENCABEZADO -->
          <div class="text-center">
            <div style="font-size:14px; font-weight:700;">REFACCIONARIA RIVERA</div>
            <div>KARINA VALENTINA RIVERA LEON</div>
            <div>RFC: RILK830214NI9</div>
            <div>Régimen Fiscal: 612</div>
            <div>Blvd. Solidaridad 601, Col. Choyal</div>
            <div>C.P. 83130 Hermosillo, Sonora</div>
            <div>Tel: (662) 262-1129</div>
          </div>

          <div class="tk-line my-2"></div>

          <!-- FECHA Y FOLIO -->
          <div class="d-flex justify-content-between">
            <div id="tk-fecha">2025-08-11</div>
            <div><strong>FOLIO:</strong> <span id="tk-folio">—</span></div>
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
          <div class="d-flex justify-content-end">
            <div style="width:120px; text-align:right; font-weight:700;">TOTAL:</div>
            <div id="tk-total" style="width:80px; text-align:right; font-weight:700;">$ 0.00</div>
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

<!-- CSS -->
<style>
  /* layout general del ticket en pantalla */
  .tk { max-width: 340px; }
  .tk-line{ border-top:1px dashed #000; }

  /* cabecera de columnas + filas de items en "grid" fijo */
  .tk-head,
  .tk-item{
    display:grid;
    grid-template-columns: 50px 1fr 70px 80px; /* cant | artículo | precio | total */
    column-gap: 6px;
    align-items:start;
  }
  .tk-head{ font-weight:700; }
  .tk-item .c-art{ word-break:break-word; overflow-wrap:anywhere; }
  .tk-item .c-precio,
  .tk-item .c-total{ text-align:right; }

  /* separar filas un poco */
  .tk-item{ padding:2px 0; }
  .tk-item + .tk-item{ border-top:1px dotted #ccc; }

  /* impresión: solo 80mm y ocultar resto */
  @media print {
    body * { visibility: hidden !important; }
    .print-area, .print-area * { visibility: visible !important; }
    .modal, .modal-dialog, .modal-content { position: static !important; box-shadow:none !important; border:0 !important; }
    @page { size: 80mm auto; margin: 0; }
    .print-area { width: 80mm !important; margin: 0 !important; padding: 0 4mm; }
    .tk-item + .tk-item{ border-top:1px dotted #000; } /* más visible en impresión */
  }
</style>
