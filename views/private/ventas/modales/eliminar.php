<!-- MODAL: Eliminar venta -->
<div class="modal fade bs-example-modal-lg" id="modalEliminar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" style="max-width:600px">
    <div class="modal-content border-0">
      <div class="modal-header py-2">
        <h5 class="modal-title mb-0">
          <i class="mdi mdi-alert-octagon-outline mr-1"></i> Eliminar venta
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">×</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="d-flex align-items-start">
          <div class="mr-2 text-danger">
            <i class="mdi mdi-trash-can-outline" style="font-size:28px;"></i>
          </div>
          <div>
            <p class="mb-2">
            <b>¿Seguro que deseas eliminar la venta con folio <span id="el-folio">—</span>?</b>
            <br>
            Esta acción no se puede deshacer.
            </p>
          </div>
        </div>

        <!-- Donde guardamos datos para la futura lógica -->
        <input type="hidden" id="el-id-venta">
      </div>

      <div class="modal-footer py-2">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger btn-sm" id="btnConfirmarEliminar">
          <i class="mdi mdi-delete-forever"></i> Eliminar
        </button>
      </div>
    </div>
  </div>
</div>
