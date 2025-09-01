<!-- Modal Agregar/Editar Producto -->
<div id="modalProducto" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modalProductoLabel" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <form id="formProducto" autocomplete="off">
        <input type="hidden" id="p_id_producto" name="id_producto" value="">

        <div class="modal-header">
          <h5 class="modal-title" id="modalProductoLabel">
            <i class="mdi mdi-plus"></i> Agregar producto
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">

          <div class="alert alert-info py-2 px-3 mb-3">
            <i class="mdi mdi-information-outline"></i>
            Los campos marcados con <span class="text-danger">*</span> son obligatorios.
          </div>

          <!-- ===================== Datos del producto ===================== -->
          <fieldset class="border rounded p-3">
            <legend class="w-auto px-2 mb-0 small text-muted text-uppercase">Datos del producto</legend>

            <!-- Línea 1: Proveedor / Unidad SAT / Clave ProdServ SAT -->
            <div class="form-row mt-2">
              <div class="form-group col-md-5">
                <label for="p_id_proveedor">Proveedor <span class="text-danger">*</span></label>
                <select id="p_id_proveedor" name="id_proveedor" class="form-control" required>
                  <option value="">— Selecciona —</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label for="p_id_unidad_sat">Unidad SAT <span class="text-danger">*</span></label>
                <select id="p_id_unidad_sat" name="id_unidad_sat" class="form-control" required>
                  <option value="">— Selecciona —</option>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label for="p_clave_prod_serv_sat">Clave Prod/Serv SAT</label>
                <input type="text" maxlength="8" class="form-control" id="p_clave_prod_serv_sat" name="clave_prod_serv_sat" placeholder="Ej. 01010101">
              </div>
            </div>

            <!-- Línea 1.1: Grupo (cat_grupos) -->
            <div class="form-row">
              <div class="form-group col-md-12">
                <label for="p_id_grupo">Grupo <span class="text-danger">*</span></label>
                <select id="p_id_grupo" name="id_grupo" class="form-control" required>
                  <option value="">— Selecciona —</option>
                </select>
              </div>
            </div>

            <!-- Línea 2: Código / Descripción -->
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="p_codigo">Código <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="p_codigo" name="codigo" required>
              </div>
              <div class="form-group col-md-8">
                <label for="p_descripcion">Descripción <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="p_descripcion" name="descripcion" required>
              </div>
            </div>

            <!-- Línea 3: Costos/Precios -->
            <div class="form-row">
              <div class="form-group col-md-3">
                <label for="p_precio_proveedor">Precio Proveedor <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" class="form-control" id="p_precio_proveedor" name="precio_proveedor" value="0" required>
              </div>
              <div class="form-group col-md-3">
                <label for="p_costo_neto">Costo Neto <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" class="form-control" id="p_costo_neto" name="costo_neto" value="0" required>
              </div>
              <div class="form-group col-md-3">
                <label for="p_precio_taller">Precio Taller <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" class="form-control" id="p_precio_taller" name="precio_taller" value="0" required>
              </div>
              <div class="form-group col-md-3">
                <label for="p_precio_publico">Precio Público <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" class="form-control" id="p_precio_publico" name="precio_publico" value="0" required>
              </div>
            </div>

            <!-- Línea 4: Stocks y Activo -->
            <div class="form-row">
              <div class="form-group col-md-3">
                <label for="p_stock_actual">Stock actual <span class="text-danger">*</span></label>
                <input type="number" step="1" min="0" class="form-control" id="p_stock_actual" name="stock_actual" value="0" required>
              </div>
              <div class="form-group col-md-3">
                <label for="p_stock_maximo">Stock máximo <span class="text-danger">*</span></label>
                <input type="number" step="1" min="0" class="form-control" id="p_stock_maximo" name="stock_maximo" value="0" required>
              </div>
              <div class="form-group col-md-3">
                <label for="p_stock_minimo">Stock mínimo <span class="text-danger">*</span></label>
                <input type="number" step="1" min="0" class="form-control" id="p_stock_minimo" name="stock_minimo" value="0" required>
              </div>
              <div class="form-group col-md-3 d-flex align-items-center">
                <div class="custom-control custom-switch mt-3">
                  <input type="checkbox" class="custom-control-input" id="p_activo" name="activo" checked>
                  <label class="custom-control-label" for="p_activo">Activo</label>
                </div>
              </div>
            </div>
          </fieldset>

          <!-- ===================== Ubicación física ===================== -->
          <fieldset class="border rounded p-3 mt-3">
            <legend class="w-auto px-2 mb-0 small text-muted text-uppercase">Ubicación física</legend>
            <div class="form-row mt-2">
              <div class="form-group col-md-3">
                <label for="p_piso">Piso</label>
                <input type="number" step="1" min="0" class="form-control" id="p_piso" name="piso" value="0">
              </div>
              <div class="form-group col-md-3">
                <label for="p_pasillo">Pasillo</label>
                <input type="number" step="1" min="0" class="form-control" id="p_pasillo" name="pasillo" value="0">
              </div>
              <div class="form-group col-md-3">
                <label for="p_estante">Estante</label>
                <input type="number" step="1" min="0" class="form-control" id="p_estante" name="estante" value="0">
              </div>
              <div class="form-group col-md-3">
                <label for="p_peldano">Peldaño</label>
                <!-- Si tu columna en BD usa "peldaño" con ñ, mapea en backend peldano -> peldaño -->
                <input type="number" step="1" min="0" class="form-control" id="p_peldano" name="peldano" value="0">
              </div>
            </div>
          </fieldset>

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
