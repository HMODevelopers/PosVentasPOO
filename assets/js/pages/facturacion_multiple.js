(function(){
  const VENTAS_URL = `${BASE_URL}/controllers/VentasController.php`;
  const selectedTickets = new Map();
  let draft = null;

  const mxn = n => new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(Number(n||0));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const fechaMx = f => f ? new Date(String(f).replace(' ','T')).toLocaleString('es-MX') : '—';

  function resetView(){
    $('#fac-loader').hide(); $('#fac-contenido').removeClass('d-none');
    $('#fac-error, #fac-warning, #fac-success').addClass('d-none').empty();
    $('#fac-multi-tickets').text(selectedTickets.size);
    $('#fac-folio').text('—');
    $('#fac-fecha').text('—');
    $('#fac-cliente').text('—');
    $('#fac-detalles-body').html('<tr><td colspan="10" class="text-center text-muted">Sin conceptos</td></tr>');
    $('#btnConfirmarFacturar').prop('disabled', true);
  }

  function initClienteSelect(){
    const $s = $('#fac-select-cliente');
    $s.select2({
      width:'100%',
      dropdownParent: $('#facturacionMultipleScreen'),
      placeholder: 'Buscar cliente SAT', allowClear:true, minimumInputLength:1,
      ajax:{
        url: VENTAS_URL, dataType:'json', delay:250,
        data: p => ({accion:'facturacion-buscar-clientes', q:p.term||'', limite:20}),
        processResults: r => ({results: Array.isArray(r?.results) ? r.results : []})
      }
    });
  }

  function loadTickets(q=''){
    $.get(VENTAS_URL,{accion:'facturacion-multiple-tickets',q,limite:100},resp=>{
      const rows = Array.isArray(resp?.tickets) ? resp.tickets : [];
      if(!rows.length){ $('#multi-ticket-body').html('<tr><td colspan="5" class="text-center text-muted">Sin tickets disponibles</td></tr>'); return; }
      const html = rows.map(r=>{
        const id=Number(r.id_venta||0); const added=selectedTickets.has(id);
        return `<tr>
          <td>${esc(r.folio || ('#'+id))}</td>
          <td>${esc(fechaMx(r.fecha))}</td>
          <td>${esc(r.cliente_nombre || 'Público en general')}</td>
          <td class="text-right">${mxn(r.total||0)}</td>
          <td class="text-center"><button type="button" class="btn btn-sm ${added?'btn-secondary':'btn-primary'} btn-ticket-add" data-id="${id}" data-folio="${esc(r.folio||'')}">${added?'Agregado':'Agregar'}</button></td>
        </tr>`;
      }).join('');
      $('#multi-ticket-body').html(html);
    },'json');
  }

  function refreshPreview(){
    const ids = Array.from(selectedTickets.keys());
    if(!ids.length){ resetView(); return; }
    $('#fac-loader').show();
    $.post(VENTAS_URL,{accion:'facturacion-multiple-preview',ids_ventas:ids},resp=>{
      if(!resp?.ok){ $('#fac-error').removeClass('d-none').text(resp?.msg||'Error de preview'); return; }
      const ctx = resp.contexto || {};
      draft = ctx.factura_draft || {};
      const venta = ctx.venta || {}; const em = ctx.emisor || {}; const conceptos = draft?.venta?.conceptos || [];
      $('#fac-loader').hide(); $('#fac-contenido').removeClass('d-none');
      $('#fac-id-venta').val(venta.id_venta || ids[0]);
      $('#fac-folio').text((draft?.venta?.tickets_folios || []).join(', ') || venta.folio || '—');
      $('#fac-fecha').text(fechaMx(venta.fecha));
      $('#fac-cliente').text(venta.cliente_nombre || venta.cliente || 'Venta múltiple');
      $('#fac-multi-tickets').text(ids.length);
      $('#fac-emisor-rfc').text(em.rfc || '—');
      $('#fac-emisor-nombre').text(em.nombre || '—');
      $('#fac-emisor-sucursal').text(em.sucursal || '—');
      $('#fac-emisor-regimen').text(em.regimen_fiscal || '—');
      $('#fac-emisor-lugar').text(em.lugar_expedicion || '—');
      $('#fac-emisor-serie').text(em.serie || '—');
      $('#fac-emisor-tipo').text((draft?.comprobante?.tipo_comprobante || 'I'));
      $('#fac-emisor-exportacion').text((draft?.comprobante?.exportacion || '01'));

      $('#fac-total-subtotal').text(mxn(ctx?.totales?.subtotal||0));
      $('#fac-total-descuento').text(mxn(ctx?.totales?.descuento||0));
      $('#fac-total-impuestos').text(mxn(ctx?.totales?.impuestos||0));
      $('#fac-total').text(mxn(ctx?.totales?.total||0));

      const rows = conceptos.map(c=>{
        const tr = Array.isArray(c?.Traslados) ? c.Traslados[0] : null;
        const iva = tr ? `IVA ${Number(tr.TasaOCuota||0)*100}%: ${mxn(tr.Importe||0)}` : '—';
        return `<tr>
          <td class="text-center">${esc(c.Cantidad||0)}</td>
          <td>${esc(c.ClaveProdServ||'—')}</td>
          <td>${esc(c.NoIdentificacion||'—')}</td>
          <td>${esc(c.ClaveUnidad||'—')}</td>
          <td>${esc(c.Unidad||'—')}</td>
          <td>${esc(c.Descripcion||'—')}</td>
          <td class="text-right">${mxn(c.ValorUnitario||0)}</td>
          <td class="text-right">${mxn(c.Importe||0)}</td>
          <td class="text-center">${esc(c.ObjetoImp||'—')}</td>
          <td class="text-right">${esc(iva)}</td>
        </tr>`;
      }).join('');
      $('#fac-detalles-body').html(rows || '<tr><td colspan="10" class="text-center text-muted">Sin conceptos</td></tr>');

      $('#fac-select-regimen').html((ctx.catalogos?.regimenes_fiscales||[]).map(x=>`<option value="${esc(x.ClaveRegimenFiscal)}">${esc(x.ClaveRegimenFiscal)} - ${esc(x.Descripcion||'')}</option>`));
      $('#fac-select-uso-cfdi').html((ctx.catalogos?.usos_cfdi||[]).map(x=>`<option value="${esc(x.ClaveUsoCFDI)}">${esc(x.ClaveUsoCFDI)} - ${esc(x.Descripcion||'')}</option>`));
      $('#fac-select-moneda').html((ctx.catalogos?.monedas||[]).map(x=>`<option value="${esc(x.ClaveMoneda)}">${esc(x.ClaveMoneda)} - ${esc(x.Descripcion||'')}</option>`));
      $('#fac-select-metodo-pago').html((ctx.catalogos?.metodos_pago||[]).map(x=>`<option value="${esc(x.clave)}">${esc(x.clave)} - ${esc(x.descripcion||'')}</option>`));
      $('#fac-select-forma-pago').html((ctx.catalogos?.formas_pago||[]).map(x=>`<option value="${esc(x.clave_sat)}">${esc(x.clave_sat)} - ${esc(x.descripcion||'')}</option>`));
      $('#fac-select-tipo-comprobante').html((ctx.catalogos?.tipos_comprobante||[]).map(x=>`<option value="${esc(x.clave)}">${esc(x.clave)} - ${esc(x.descripcion||'')}</option>`));
      $('#fac-select-exportacion').html((ctx.catalogos?.exportaciones||[]).map(x=>`<option value="${esc(x.clave)}">${esc(x.clave)} - ${esc(x.descripcion||'')}</option>`));

      const rec = draft.receptor || {};
      $('#fac-input-rfc').val(rec.rfc||''); $('#fac-input-razon-social').val(rec.nombre||'');
      $('#fac-input-nombre-comercial').val(rec.nombre_comercial||''); $('#fac-input-correo').val(rec.correo||'');
      $('#fac-input-cp').val(rec.codigo_postal||''); $('#fac-select-regimen').val(rec.regimen_fiscal||'');
      $('#fac-select-uso-cfdi').val(rec.uso_cfdi||''); $('#fac-id-cliente-sat').val(rec.id_cliente_fiscal||'');

      const comp = draft.comprobante || {};
      $('#fac-select-moneda').val(comp.moneda||'MXN'); $('#fac-select-metodo-pago').val(comp.metodo_pago||'');
      $('#fac-select-forma-pago').val(comp.forma_pago||''); $('#fac-input-tipo-cambio').val(comp.tipo_cambio||'1');
      $('#fac-input-condiciones-pago').val(comp.condiciones_pago||''); $('#fac-select-tipo-comprobante').val(comp.tipo_comprobante||'I');
      $('#fac-select-exportacion').val(comp.exportacion||'01');

      $('#btnConfirmarFacturar').prop('disabled', !resp.facturable);
    },'json').fail(xhr=>{
      $('#fac-loader').hide();
      $('#fac-error').removeClass('d-none').text(xhr?.responseJSON?.msg || 'Error al cargar preview.');
    });
  }

  function buildPayload(){
    const ids = Array.from(selectedTickets.keys());
    const conceptos = Array.isArray(draft?.venta?.conceptos) ? draft.venta.conceptos : [];
    return {
      accion:'facturar',
      id_venta:Number($('#fac-id-venta').val()||ids[0]||0),
      ids_ventas: ids,
      id_cliente_sat:Number($('#fac-id-cliente-sat').val()||0),
      emisor: draft?.emisor || {},
      receptor: {
        id_cliente_sat:Number($('#fac-id-cliente-sat').val()||0),
        rfc:$('#fac-input-rfc').val(), nombre:$('#fac-input-razon-social').val(), nombre_comercial:$('#fac-input-nombre-comercial').val(),
        correo:$('#fac-input-correo').val(), domicilio_fiscal_receptor:$('#fac-input-cp').val(), regimen_fiscal:$('#fac-select-regimen').val(),
        uso_cfdi:$('#fac-select-uso-cfdi').val(), residencia_fiscal:$('#fac-input-residencia-fiscal').val(), numero_registro_tributario:$('#fac-input-num-reg-id-trib').val()
      },
      comprobante: {
        moneda:$('#fac-select-moneda').val(), metodo_pago:$('#fac-select-metodo-pago').val(), forma_pago:$('#fac-select-forma-pago').val(),
        tipo_cambio:$('#fac-input-tipo-cambio').val(), condiciones_pago:$('#fac-input-condiciones-pago').val(),
        tipo_comprobante:$('#fac-select-tipo-comprobante').val(), exportacion:$('#fac-select-exportacion').val()
      },
      conceptos,
      totales: { subtotal:Number((draft?.venta?.subtotal)||0), descuento:Number((draft?.venta?.descuento)||0), impuestos:Number((draft?.venta?.impuestos)||0), total:Number((draft?.venta?.total)||0) },
      draft_snapshot: { emisor:draft?.emisor||{}, receptor:draft?.receptor||{}, comprobante:draft?.comprobante||{} }
    };
  }

  $(document).on('click','#btnAgregarTickets',()=>{ $('#modalTicketsFacturacion').modal('show'); loadTickets(''); });
  $(document).on('input','#multi-ticket-search',function(){ loadTickets($(this).val()||''); });
  $(document).on('click','.btn-ticket-add',function(){
    const id = Number($(this).data('id')||0); const folio = $(this).data('folio')||'';
    if(!id) return;
    if(selectedTickets.has(id)){ selectedTickets.delete(id); } else { selectedTickets.set(id,{id,folio}); }
    loadTickets($('#multi-ticket-search').val()||'');
    refreshPreview();
  });

  $(document).on('select2:select','#fac-select-cliente',function(e){
    const c = e?.params?.data || {}; $('#fac-id-cliente-sat').val(c.id||c.id_cliente_sat||'');
    $('#fac-input-rfc').val(c.rfc||''); $('#fac-input-razon-social').val(c.nombre||'');
    $('#fac-input-nombre-comercial').val(c.nombre_comercial||''); $('#fac-input-correo').val(c.correo||'');
    $('#fac-input-cp').val(c.domicilio_fiscal_receptor||''); $('#fac-select-regimen').val(c.regimen_fiscal_receptor||''); $('#fac-select-uso-cfdi').val(c.uso_cfdi||'');
  });

  $(document).on('submit','#formFacturarVenta',function(e){
    e.preventDefault();
    const payload = buildPayload();
    if(!payload.ids_ventas.length){ toastr.error('Debes agregar al menos un ticket.'); return; }
    if(!payload.id_cliente_sat){ toastr.error('Debes seleccionar un receptor existente.'); return; }
    const $btn = $('#btnConfirmarFacturar'); const old = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Facturando...');
    $.post(VENTAS_URL,payload,resp=>{
      if(resp?.ok){ toastr.success(resp.msg || 'CFDI timbrado correctamente.'); $('#fac-success').removeClass('d-none').text(resp.msg||'CFDI timbrado correctamente.'); refreshPreview(); }
      else { toastr.error(resp?.msg || 'No fue posible facturar.'); $('#fac-error').removeClass('d-none').text(resp?.msg || 'No fue posible facturar.'); }
    },'json').fail(xhr=>{
      const msg = xhr?.responseJSON?.msg || 'Error al facturar.'; toastr.error(msg); $('#fac-error').removeClass('d-none').text(msg);
    }).always(()=> $btn.prop('disabled', false).html(old));
  });

  $(function(){ initClienteSelect(); resetView(); });
})();
