<!-- MODAL: Etiquetas de producto (revisado) -->
<div class="modal fade" id="modalEtiquetas" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">

      <div class="modal-header py-2">
        <h5 class="modal-title mb-0">
          <i class="mdi mdi-tag-multiple-outline"></i> Etiquetas de producto
          <small class="text-muted" id="etq-nombre-prod"></small>
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>×</span></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info py-2 px-3 mb-3">
          <i class="mdi mdi-information-outline"></i>
          El <strong>preview</strong> muestra <strong>una sola etiqueta</strong> a tamaño real. El campo <strong>Copias</strong> se aplica <u>solo al imprimir</u>.
        </div>

        <form id="formEtiquetas" autocomplete="off">
          <input type="hidden" id="etq-idprod">

          <!-- ===================== Configuración de etiqueta ===================== -->
          <fieldset class="border rounded p-3">
            <legend class="w-auto px-2 mb-0 small text-muted text-uppercase">Configuración</legend>

            <!-- Línea 1: Tamaño / Modo / Copias / Tienda -->
            <div class="form-row mt-2">
              <div class="form-group col-md-2">
                <label for="etq-w">Ancho (mm)</label>
                <input type="number" min="10" max="100" step="0.5" class="form-control" id="etq-w" value="50">
              </div>
              <div class="form-group col-md-2">
                <label for="etq-h">Alto (mm)</label>
                <input type="number" min="10" max="100" step="0.5" class="form-control" id="etq-h" value="30">
              </div>
              <div class="form-group col-md-3">
                <label for="etq-modo">Modo</label>
                <select id="etq-modo" class="form-control">
                  <option value="hoja">Hoja (A4, grilla)</option>
                  <option value="rollo">Rollo (1 por página)</option>
                </select>
              </div>
              <div class="form-group col-md-2">
                <label for="etq-copias">Copias</label>
                <input type="number" min="1" max="500" class="form-control" id="etq-copias" value="1">
              </div>
              <div class="form-group col-md-3">
                <label for="etq-tienda">Tienda</label>
                <input type="text" id="etq-tienda" class="form-control" value="REFASOFT" maxlength="40">
              </div>
            </div>

            <!-- Línea 2: Precio / Mostrar -->
            <div class="form-row">
              <div class="form-group col-md-3">
                <label for="etq-precio">Precio a mostrar</label>
                <select id="etq-precio" class="form-control">
                  <option value="precio_publico">Público</option>
                  <option value="precio_taller">Taller</option>
                  <option value="precio_proveedor">Proveedor</option>
                </select>
              </div>
              <div class="form-group col-md-9 d-flex align-items-end">
                <div class="custom-control custom-checkbox mr-3">
                  <input class="custom-control-input" id="etq-show-price" type="checkbox" checked>
                  <label class="custom-control-label" for="etq-show-price">Mostrar precio</label>
                </div>
                <div class="custom-control custom-checkbox">
                  <input class="custom-control-input" id="etq-show-desc" type="checkbox" checked>
                  <label class="custom-control-label" for="etq-show-desc">Mostrar descripción</label>
                </div>
              </div>
            </div>

            <!-- Línea 3: Código de barras -->
            <div class="form-row">
              <div class="form-group col-md-3">
                <label for="etq-barcode-fmt">Formato de código</label>
                <select id="etq-barcode-fmt" class="form-control">
                  <option value="CODE128">CODE128</option>
                  <option value="EAN13">EAN-13</option>
                  <option value="EAN8">EAN-8</option>
                  <option value="UPC">UPC</option>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label for="etq-barcode-field">Campo para el código</label>
                <select id="etq-barcode-field" class="form-control">
                  <option value="codigo">Código</option>
                  <option value="id_producto">ID Producto</option>
                </select>
              </div>

              <div class="form-group col-md-6 d-flex align-items-end justify-content-end">
                <button type="button" id="btnAplicarEtiquetas" class="btn btn-secondary mr-2">
                  <i class="mdi mdi-eye"></i> Aplicar a preview
                </button>
                <button type="button" class="btn btn-primary" id="btnImprimirEtiquetas">
                  <i class="mdi mdi-printer"></i> Imprimir
                </button>
              </div>
            </div>
          </fieldset>
        </form>

        <!-- ===================== Vista previa (1 etiqueta) ===================== -->
        <fieldset class="border rounded p-3 mt-3">
          <legend class="w-auto px-2 mb-0 small text-muted text-uppercase">Vista previa</legend>
          <!-- SOLO ESTO SE IMPRIME (en la ventana de impresión) -->
          <div id="etqArea" class="print-area">
            <div id="etqRoot" class="etq-root">
              <div class="etq-sheet">
                <div id="etqGrid" class="etq-grid"></div>
              </div>
            </div>
          </div>
          <div class="small text-muted mt-2">
            Nota: el preview siempre muestra <strong>una</strong> etiqueta. Para ver múltiples, usa <em>Imprimir</em> con “Copias”.
          </div>
        </fieldset>
      </div>

      <div class="modal-footer py-2">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<style>
  /* ========= Estilos de preview/impresión de etiquetas ========= */
  .etq-root{
    --lab-w: 50mm;   /* ajustado por JS */
    --lab-h: 30mm;   /* ajustado por JS */
    --gap: 2mm;
    font-family: Arial, Helvetica, sans-serif;
  }
  .etq-sheet{ padding: 10mm; background: #fff; }
  .etq-grid{
    display: grid;
    grid-template-columns: minmax(var(--lab-w), var(--lab-w)); /* una sola columna en preview */
    gap: var(--gap);
    align-items: start;
    justify-content: start;
  }
  .etq-label{
    width: var(--lab-w);
    height: var(--lab-h);
    border: 1px dashed #aaa;
    padding: 2mm 2.2mm 1.5mm;
    display: flex; flex-direction: column; justify-content: space-between;
    background: #fff;
  }
  .etq-brand{ font-weight: 700; font-size: 10pt; line-height: 1; letter-spacing: .2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .etq-desc{ margin-top: .5mm; font-size: 8pt; line-height: 1.1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .etq-price{ margin-top: .5mm; font-weight: 800; font-size: 14pt; line-height: 1; }
  .etq-bottom{ display: flex; align-items: center; justify-content: space-between; gap: 2mm; }
  .etq-barwrap{ width: 100%; margin-top: .5mm; }
  .etq-code{ font-size: 7pt; line-height: 1; margin-top: .5mm; text-align: right; opacity: .85; }

  /* Rollo: 1 por página (se activa solo al imprimir) */
  .etq-rollo .etq-label{ border: none; page-break-after: always; }
  .etq-rollo .etq-label:last-child{ page-break-after: auto; }
</style>
