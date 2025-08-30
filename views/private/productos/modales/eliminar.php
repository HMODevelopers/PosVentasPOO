<!-- =================== Modal Eliminar Producto =================== -->
<div class="modal fade" id="modalEliminarProducto" tabindex="-1" role="dialog" aria-labelledby="eliminarProductoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width:600px">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="eliminarProductoLabel">Desactivar producto</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="el-id-producto">
        <p>¿Seguro que deseas <b>desactivar</b> el producto <b>#<span id="el-codigo-prod">—</span></b>? Esta acción ajustará el inventario.</p>
        <!-- Si quieres mostrar la descripción también, descomenta:
        <p class="mb-0 text-muted">Descripción: <b><span id="el-descripcion-prod">—</span></b></p>
        -->
      </div>

      <div class="modal-footer py-2">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-danger btn-sm" id="btnConfirmarEliminarProducto">
          <i class="mdi mdi-delete-forever"></i> Desactivar producto
        </button>
      </div>

    </div>
  </div>
</div>
<!-- =================== /Modal Eliminar Producto =================== -->
