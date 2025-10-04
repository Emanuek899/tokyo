<?php $pageTitle = 'Factura tu ticket'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php
  // Basic head partial: meta, title, CSS links
  $pageTitle = $pageTitle ?? 'Tokyo Sushi';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#dc281bff">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/utilities.css">
<!-- Estilos adicionales provistos en raíz para adaptar al sitio -->
<link rel="stylesheet" href="assets/css/factura.css">
  <style>
    .grid-2 { display:grid; grid-template-columns: 1.2fr 0.8fr; gap:1rem; }
    .muted { color: var(--color-muted); }
    .field { display:flex; flex-direction:column; gap:.35rem; }
    .field label { font-size:.9rem; color:var(--color-text); }
  </style>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const $ = (s,ctx=document)=>ctx.querySelector(s);
    const apiBase = (function(){
      const parts = window.location.pathname.split('/').filter(Boolean);
      const idx = parts.indexOf('tokyo');
      const base = idx>=0?('/'+parts.slice(0, idx+1).join('/')):('/'+(parts[0]||''));
      return base + '/api/public';
    })();

    const buscarForm = $('#form-buscar');
    const datosForm = $('#form-datos');
    const pagoForm = $('#form-pago');
    const prev = $('#preview');
    const res = $('#resultado');
    let foundTicket = null;
    let allUsos = [];
    const normUpper = (s)=> (s||'').trim().toUpperCase();
    const only5Digits = (s)=> /^\d{5}$/.test(String(s||'').trim());

    async function apiPost(path, body){
      const res = await fetch(apiBase + path, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify(body) });
      const ct = res.headers.get('content-type')||'';
      const isJson = ct.includes('application/json');
      if(!res.ok){
        let data = null;
        if(isJson){ try { data = await res.json(); } catch(e) { data = null; } }
        if(data && typeof data === 'object'){
          const err = new Error(data.message || 'Error');
          err.status = res.status; err.data = data; err.code = data.code; err.uuid = data.uuid;
          throw err;
        }
        const t = await res.text().catch(()=>'' );
        const err = new Error(t || ('HTTP '+res.status));
        err.status = res.status;
        throw err;
      }
      return isJson ? res.json() : res.text();
    }
    async function apiGet(path, params){
      const u = new URL(apiBase+path, window.location.origin);
      Object.entries(params||{}).forEach(([k,v])=>{ if(v!==''&&v!=null) u.searchParams.set(k,v); });
      const res = await fetch(u.toString(), { credentials:'same-origin' });
      if(!res.ok){ const t=await res.text().catch(()=>'' ); throw new Error('HTTP '+res.status+' '+t); }
      return res.json();
    }

    // Catalog loaders
    async function loadRegimenes(){
      const r = await apiGet('/facturacion/catalogos/regimenes.php');
      if(!r.ok) throw new Error('Error catálogos: regimenes');
      const sel = $('#regimen');
      sel.innerHTML = '<option value="">Selecciona…</option>' + r.data.map(x=>`<option value="${x.code}">${x.code} - ${x.descripcion}</option>`).join('');
    }
    async function loadUsos(){
      const r = await apiGet('/facturacion/catalogos/usos.php');
      if(!r.ok) throw new Error('Error catálogos: usos');
      allUsos = r.data || [];
      renderUsoOptions(allUsos.map(u=>u.code));
    }
    function renderUsoOptions(codes){
      const sel = $('#uso_cfdi');
      const allow = new Set(codes);
      sel.innerHTML = '<option value="">Selecciona…</option>' + allUsos.filter(u=>allow.has(u.code)).map(u=>`<option value="${u.code}">${u.code} - ${u.descripcion}</option>`).join('');
      const hint = $('#uso-hint');
      if (hint) hint.textContent = codes && codes.length ? `Usos permitidos para régimen seleccionado: ${codes.join(', ')}` : '';
    }
    async function onRegimenChange(){
      const reg = $('#regimen').value || '';
      if(!reg){ renderUsoOptions(allUsos.map(u=>u.code)); return; }
      try{
        const r = await apiGet('/facturacion/catalogos/usos_por_regimen.php', { regimen: reg });
        if(r.ok){ renderUsoOptions(r.data||[]); }
      }catch(e){ /* ignore */ }
    }

    function isGenericRFC(rfc){
      const x = (rfc||'').toUpperCase().trim();
      return x === 'XAXX010101000' || x === 'XEXX010101000';
    }

    function renderPreview(t){
      const rows = (t.partidas||[]).map(p=>`<tr><td>${p.descripcion||''}</td><td style="text-align:right">${p.cantidad}</td><td style="text-align:right">$${Number(p.precio_unitario).toFixed(2)}</td><td style="text-align:right">$${Number(p.importe).toFixed(2)}</td></tr>`).join('');
      prev.innerHTML = `
        <div  class="muted">Ticket #${t.folio||t.id} • ${t.fecha?.slice(0,10)||''}</div>
        <table class="table" style=" color:black; width:100%; font-size:.95rem; border-collapse:collapse;">
          <thead><tr><th style="text-align:left">Concepto</th><th>Cant.</th><th>Precio</th><th>Importe</th></tr></thead>
          <tbody>${rows}</tbody>
          <tfoot>
            <tr><td colspan="3" style="text-align:right">Base</td><td style="text-align:right">$${Number(t.base||0).toFixed(2)}</td></tr>
            <tr><td colspan="3" style="text-align:right">IVA</td><td style="text-align:right">$${Number(t.iva||0).toFixed(2)}</td></tr>
            <tr><td colspan="3" style="text-align:right"><strong>Total</strong></td><td style="text-align:right"><strong>$${Number(t.total||0).toFixed(2)}</strong></td></tr>
          </tfoot>
        </table>`;
      $('#payment_method').value = (t.sugerencias?.metodo_pago)||'PUE';
      $('#payment_form').value = (t.sugerencias?.forma_pago)||'03';
    }

buscarForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      res.textContent = '';
      const ticket_id = $('#ticket_id').value.trim();
      const folio = $('#folio').value.trim();
      const fecha = $('#fecha').value.trim();
      try{
        const r = await apiPost('/facturacion/buscar-ticket.php', { ticket_id: ticket_id?Number(ticket_id):undefined, folio: folio?Number(folio):undefined, fecha });
        if(!r.ok){ throw new Error(r.message||'Error'); }
        foundTicket = r.ticket;
        renderPreview(foundTicket);
        datosForm.removeAttribute('disabled');
        pagoForm.removeAttribute('disabled');
      }catch(err){
        // Si ya está facturado, mostrar botones de descarga
        const code = err && (err.code || (err.data && err.data.code));
        const uuid = err && (err.uuid || (err.data && err.data.uuid) || '');
        if(code === 'ALREADY_INVOICED' && uuid){
          const base = apiBase + '/facturacion/descargar.php';
          const xmlLink = base+'?uuid='+encodeURIComponent(uuid)+'&tipo=xml';
          const pdfLink = base+'?uuid='+encodeURIComponent(uuid)+'&tipo=pdf';
          res.innerHTML = `<div class="success">Este ticket ya tiene factura<br>UUID: <strong>${uuid}</strong><div class="mt-2"><a class="btn" href="${xmlLink}">Descargar XML</a> <a class="btn" href="${pdfLink}">Descargar PDF</a></div></div>`;
          // Bloquear captura para evitar duplicado
          foundTicket=null; prev.textContent=''; datosForm.setAttribute('disabled','disabled'); pagoForm.setAttribute('disabled','disabled');
          return;
        }
        foundTicket=null; prev.textContent=''; datosForm.setAttribute('disabled','disabled'); pagoForm.setAttribute('disabled','disabled');
        res.textContent = (err && err.message) ? err.message : 'Error al buscar ticket';
      }
    });

    $('#rfc').addEventListener('blur', async (e) => {
      const rfc = e.target.value.trim().toUpperCase();
      if(rfc.length<12) return;
      try{ const r = await apiGet('/clientes-fiscales.php', { rfc }); if(r && r.ok && r.cliente){
        $('#razon_social').value = r.cliente.razon_social||'';
        $('#regimen').value = r.cliente.regimen||'';
        $('#cp_cliente').value = r.cliente.cp||'';
        $('#uso_cfdi').value = r.cliente.uso_cfdi||'';
        $('#correo').value = r.cliente.correo||'';
        onRegimenChange();
      }}catch(e){}
      // RFC genérico: forzar S01 y mostrar aviso
      if(isGenericRFC(rfc)){
        const usoSel = $('#uso_cfdi');
        const hasS01 = Array.from(usoSel.options).some(o=>o.value==='S01');
        if(hasS01){ usoSel.value = 'S01'; }
        const note = $('#rfc-generic-note');
        if(note) note.textContent = 'RFC genérico: se recomienda Uso CFDI S01 y se usará el CP fiscal como Lugar de Expedición.';
      }
    });

    $('#btn-timbrar').addEventListener('click', async ()=>{
      if(!foundTicket){ res.textContent='Busca y selecciona un ticket válido'; return; }
      const cpCliente = $('#cp_cliente').value.trim();
      const expPlace = $('#exp_place').value;
      if(!only5Digits(cpCliente)) { alert('C.P. fiscal del cliente inválido'); return; }
      if(!only5Digits(expPlace))  { alert('Lugar de expedición inválido'); return; }
      const payload = {
        ticket_id: foundTicket.id,
        rfc: normUpper($('#rfc').value),
        razon_social: $('#razon_social').value.trim(),
        regimen: $('#regimen').value.trim(),
        Receiver: { TaxZipCode: cpCliente },
        ExpeditionPlace: expPlace,
        // compat keys
        cp_cliente: cpCliente,
        exp_place: expPlace,
        uso_cfdi: $('#uso_cfdi').value.trim(),
        correo: $('#correo').value.trim(),
        payment_method: $('#payment_method').value,
        payment_form: $('#payment_form').value,
        observaciones: $('#observaciones').value.trim()
      };
      try{
        const resHttp = await fetch(apiBase + '/facturacion/timbrar.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify(payload) });
        let r; try { r = await resHttp.json(); } catch(e){ throw new Error('Respuesta no JSON (revisa logs del servidor)'); }
        if(!resHttp.ok){ throw new Error(r.error || r.Message || 'Error al timbrar'); }
        const xmlLink = r.xml_url ? (apiBase+'/facturacion/descargar.php?uuid='+encodeURIComponent(r.uuid)+'&tipo=xml') : '#';
        const pdfLink = r.pdf_url ? (apiBase+'/facturacion/descargar.php?uuid='+encodeURIComponent(r.uuid)+'&tipo=pdf') : '#';
        res.innerHTML = `<div class="success">Factura generada<br>UUID: <strong>${r.uuid||''}</strong><div class="mt-2"><a class="btn" href="${xmlLink}">Descargar XML</a> <a class="btn" href="${pdfLink}">Descargar PDF</a></div></div>`;
      }catch(err){ res.textContent = err.message||'Error al timbrar'; }
    });

    // Init catalogs and bind
    (async function initCats(){
      async function cargarSucursales(){
        try {
          const r = await apiGet('/facturacion/branch-offices.php');
          if(!r.ok) return;
          const sel = $('#exp_place');
          sel.innerHTML = '';
          const lastZip = localStorage.getItem('exp_place_zip')||'';
          (r.branches||[]).forEach(b => {
            const zip = b?.Address?.ZipCode || '';
            const opt = document.createElement('option');
            opt.value = zip;
            opt.textContent = `${b.Name} (CP ${zip})`;
            opt.dataset.branchId = b.Id;
            if ((lastZip && zip===lastZip) || (!lastZip && b.IsDefault)) opt.selected = true;
            sel.appendChild(opt);
          });
          sel.addEventListener('change', ()=>{
            localStorage.setItem('exp_place_zip', sel.value||'');
          });
        } catch(e){ console.error(e); }
      }
      try { await Promise.all([loadRegimenes(), loadUsos(), cargarSucursales()]); } catch(e) { console.error(e); }
      $('#regimen').addEventListener('change', onRegimenChange);
    })();
  });
  </script>
  </head>
<body>
  <?php include __DIR__.'/partials/header.php'; ?>
  <main class="section">
    <div class="container ">
      <div stu class="card">
        <div class="card__body">
          <h2>Factura tu ticket</h2>
          <p class="muted">Busca tu ticket, captura tus datos fiscales y genera CFDI 4.0.</p>
          <h4>Estado</h4><div id="resultado" class="muted">Completa los pasos para timbrar.</div>
          <form id="form-buscar" class="grid" style="grid-template-columns: repeat(4, 1fr); gap:.75rem; align-items:end;">
            <div class="field"><label>Ticket ID</label><input id="ticket_id" class="input" placeholder="Opcional si usas Folio"></div>
            <div class="field"><label>Folio</label><input id="folio" class="input" placeholder="Del ticket"></div>
            <div class="field"><label>Fecha</label><input id="fecha" class="input" placeholder="dd/mm/aaaa"></div>
            <div><button class="btn custom-btn" type="submit">Buscar ticket</button></div>
          </form>

          <div class="mt-2" id="preview"></div>

          <fieldset id="form-datos" class="mt-3" disabled>
            <legend>Datos fiscales</legend>
            <div class="grid" style="grid-template-columns: repeat(2, 1fr); gap:.75rem;">
              <div class="field" style="grid-column:span 2"><label>RFC</label><input id="rfc" class="input" required></div>
              <div class="field" style="grid-column:span 2"><label>Razón Social</label><input id="razon_social" class="input" required></div>
              <div class="field"><label>Régimen fiscal</label>
                <select id="regimen" class="input"></select>
              </div>
              <div class="field"><label for="cp_cliente">C.P. fiscal del cliente</label><input id="cp_cliente" name="cp_cliente" type="text" maxlength="5" pattern="\d{5}" required placeholder="Ej. 34127" class="input"></div>
              <div class="field"><label for="exp_place">Lugar de expedición</label>
                <select id="exp_place" name="exp_place" required class="input"></select>
                <small>Domicilio fiscal del cliente (CSF - Lugar de expedición).</small>
              </div>
              <div class="field"><label>Uso CFDI</label>
                <select id="uso_cfdi" class="input"></select>
                <small id="uso-hint" class="muted"></small>
              </div>
              <div class="field" style="grid-column:span 2"><small id="rfc-generic-note" class="muted"></small></div>
              <div class="field" style="grid-column:span 2"><label>Correo (opcional)</label><input id="correo" type="email" class="input"></div>
            </div>
          </fieldset>

          <fieldset id="form-pago" class="mt-3" disabled>
            <legend>Pago SAT</legend>
            <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap:.75rem;">
              <div class="field"><label>Método</label>
                <select id="payment_method" class="input">
                  <option value="PUE">PUE</option>
                  <option value="PPD">PPD</option>
                </select>
              </div>
              <div class="field"><label>Forma</label>
                <select id="payment_form" class="input">
                  <option value="01">01 - Efectivo</option>
                  <option value="02">02 - Cheque</option>
                  <option value="03">03 - Transferencia</option>
                  <option value="04">04 - T. Crédito</option>
                  <option value="28">28 - T. Débito</option>
                </select>
              </div>
              <div class="field" style="grid-column:span 3"><label>Observaciones</label><input id="observaciones" class="input" placeholder="Opcional"></div>
            </div>
            <button id="btn-timbrar" class="btn custom-btn mt-2" type="button">Generar factura</button>
          </fieldset>
        </div>
      </div>
    </div>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
  </body>
  </html>
