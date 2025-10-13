<?php $pageTitle = 'Tokyo Sushi - Facturación'|| 'Factura tu ticket'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
  <style>
    /* Grid general */
    .grid-2 { display:grid; grid-template-columns: 1.2fr 0.8fr; gap:1rem; }
    .grid { display:grid; gap:.75rem; }
    .field { display:flex; flex-direction:column; gap:.35rem; }
    .field label { font-size:.9rem; color: var(--color-text); }

    /* Errores */
    .error-msg { color:#dc3545; font-size:0.875rem; margin-top:0.25rem; display:none; }
    .is-invalid { border-color:#dc3545 !important; background-color:#fff !important; }
    .is-invalid + .error-msg { display:block; }

    /* Texto muted */
    .muted { color: var(--color-muted); }

    /* Tabla responsive */
    .table-responsive { width:100%; overflow-x:auto; -webkit-overflow-scrolling: touch; }

    /* Media queries */
    @media (max-width: 768px) {
      .grid-2 { grid-template-columns: 1fr; }
      .container, .card__body { padding:1rem; }
    }
    @media (max-width: 640px) {
      form#form-buscar { grid-template-columns: 1fr !important; }
      fieldset .grid { grid-template-columns: 1fr !important; }
      .field { grid-column: span 1 !important; }
    }
  </style>
</head>
<body data-page="facturacion">
  <?php include __DIR__.'/partials/header.php'; ?>

  <main class="section">
    <div class="container">
      <div class="card">
        <div class="card__body">
          <h2>Factura tu ticket</h2>
          <p class="muted">Busca tu ticket, captura tus datos fiscales y genera CFDI 4.0.</p>

          <h4>Estado</h4>
          <div id="resultado" class="muted">Completa los pasos para timbrar.</div>

          <!-- Formulario búsqueda ticket -->
          <form id="form-buscar" class="grid" style="grid-template-columns: repeat(4, 1fr); align-items:end;">
            <div class="field">
              <label>Ticket ID</label>
              <input id="ticket_id" class="input" placeholder="Opcional si usas Folio">
              <span class="error-msg"></span>
            </div>
            <div class="field">
              <label>Folio</label>
              <input id="folio" class="input" placeholder="Del ticket">
              <span class="error-msg"></span>
            </div>
            <div class="field">
              <label>Fecha</label>
              <input id="fecha" class="input" placeholder="dd/mm/aaaa">
              <span class="error-msg"></span>
            </div>
            <div><button class="btn custom-btn" type="submit">Buscar ticket</button></div>
          </form>

          <!-- Preview ticket -->
          <div class="mt-2" id="preview"></div>

          <!-- Datos fiscales -->
          <fieldset id="form-datos" class="mt-3" disabled>
            <legend>Datos fiscales</legend>
            <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
              <div class="field" style="grid-column:span 2">
                <label>RFC</label>
                <input id="rfc" class="input" required>
                <span class="error-msg" id="rfc-error"></span>
                <small id="rfc-generic-note" class="muted"></small>
              </div>
              <div class="field" style="grid-column:span 2">
                <label>Razón Social</label>
                <input id="razon_social" class="input" required>
                <span class="error-msg" id="razonSocial-error"></span>
              </div>
              <div class="field">
                <label>Régimen fiscal</label>
                <select id="regimen" class="input" required></select>
                <span class="error-msg" id="regimen-error"></span>
              </div>
              <div class="field">
                <label>C.P. fiscal del cliente</label>
                <input id="cp_cliente" name="cp_cliente" type="text" maxlength="5" class="input">
                <span class="error-msg" id="cpCliente-error"></span>
              </div>
              <div class="field">
                <label>Lugar de expedición</label>
                <select id="exp_place" name="exp_place" class="input"></select>
                <span class="error-msg" id="expPlace-error"></span>
              </div>
              <div class="field">
                <label>Uso CFDI</label>
                <select id="uso_cfdi" class="input"></select>
                <small id="uso-hint" class="muted"></small>
                <span class="error-msg" id="usoCfdi-error"></span>
              </div>
              <div class="field" style="grid-column:span 2">
                <label>Correo (opcional)</label>
                <input id="correo" type="email" class="input">
              </div>
            </div>
          </fieldset>

          <!-- Pago SAT -->
          <fieldset id="form-pago" class="mt-3" disabled>
            <legend>Pago SAT</legend>
            <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap:.75rem;">
              <div class="field">
                <label>Método</label>
                <select id="payment_method" class="input">
                  <option value="PUE">PUE</option>
                  <option value="PPD">PPD</option>
                </select>
              </div>
              <div class="field">
                <label>Forma</label>
                <select id="payment_form" class="input">
                  <option value="01">01 - Efectivo</option>
                  <option value="02">02 - Cheque</option>
                  <option value="03">03 - Transferencia</option>
                  <option value="04">04 - Tarjeta Crédito</option>
                  <option value="28">28 - Tarjeta Débito</option>
                </select>
              </div>
              <div class="field" style="grid-column:span 3">
                <label>Observaciones</label>
                <input id="observaciones" class="input" placeholder="Opcional">
              </div>
            </div>
            <button id="btn-timbrar" class="btn custom-btn mt-2" type="button">Generar factura</button>
          </fieldset>

        </div>
      </div>
    </div>
  </main>

  <?php include __DIR__.'/partials/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const $ = (s, ctx=document)=>ctx.querySelector(s);
      const apiBase = (() => {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const idx = parts.indexOf('tokyo');
        return (idx>=0 ? '/' + parts.slice(0, idx+1).join('/') : '/' + (parts[0]||'')) + '/api/public';
      })();

      let foundTicket = null, allUsos = [];

      const normUpper = s => (s||'').trim().toUpperCase();
      const only5Digits = s => /^\d{5}$/.test(String(s||'').trim());

      async function apiPost(path, body){
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const res = await fetch(apiBase+path, {
          method:'POST',
          headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken||'' },
          credentials:'same-origin',
          body: JSON.stringify(body)
        });
        const isJson = (res.headers.get('content-type')||'').includes('application/json');
        if(!res.ok){
          const data = isJson ? await res.json().catch(()=>null) : null;
          const err = new Error((data && data.message) || ('HTTP '+res.status));
          err.status = res.status;
          throw err;
        }
        return isJson ? res.json() : res.text();
      }

      async function apiGet(path, params){
        const u = new URL(apiBase+path, window.location.origin);
        Object.entries(params||{}).forEach(([k,v])=>v!==''&&v!=null&&u.searchParams.set(k,v));
        const res = await fetch(u.toString(), { credentials:'same-origin' });
        if(!res.ok) throw new Error('HTTP '+res.status);
        return res.json();
      }

      // Load catalogs
      async function loadRegimenes(){
        const r = await apiGet('/facturacion/catalogos/regimenes.php');
        if(!r.ok) throw new Error('Error cargando régimenes');
        $('#regimen').innerHTML = '<option value="">Selecciona…</option>' + r.data.map(x=>`<option value="${x.code}">${x.code} - ${x.descripcion}</option>`).join('');
      }

      async function loadUsos(){
        const r = await apiGet('/facturacion/catalogos/usos.php');
        if(!r.ok) throw new Error('Error cargando usos');
        allUsos = r.data || [];
        renderUsoOptions(allUsos.map(u=>u.code));
      }

      function renderUsoOptions(codes){
        const sel = $('#uso_cfdi');
        sel.innerHTML = '<option value="">Selecciona…</option>' + allUsos.filter(u=>codes.includes(u.code)).map(u=>`<option value="${u.code}">${u.code} - ${u.descripcion}</option>`).join('');
        $('#uso-hint').textContent = codes.length ? `Usos permitidos: ${codes.join(', ')}` : '';
      }

      async function onRegimenChange(){
        const reg = $('#regimen').value || '';
        if(!reg){ renderUsoOptions(allUsos.map(u=>u.code)); return; }
        try{
          const r = await apiGet('/facturacion/catalogos/usos_por_regimen.php', { regimen: reg });
          if(r.ok) renderUsoOptions(r.data||[]);
        }catch(e){}
      }

      function isGenericRFC(rfc){
        return ['XAXX010101000','XEXX010101000'].includes((rfc||'').toUpperCase().trim());
      }

      function renderPreview(t){
        const rows = (t.partidas||[]).map(p=>`<tr><td>${p.descripcion||''}</td><td style="text-align:right">${p.cantidad}</td><td style="text-align:right">$${Number(p.precio_unitario).toFixed(2)}</td><td style="text-align:right">$${Number(p.importe).toFixed(2)}</td></tr>`).join('');
        $('#preview').innerHTML = `
          <div class="muted">Ticket #${t.folio||t.id} • ${t.fecha?.slice(0,10)||''}</div>
          <div class="table-responsive">
            <table class="table" style="color:black; width:100%; font-size:.95rem; border-collapse:collapse; min-width:500px;">
              <thead><tr><th>Concepto</th><th>Cant.</th><th>Precio</th><th>Importe</th></tr></thead>
              <tbody>${rows}</tbody>
              <tfoot>
                <tr><td colspan="3" style="text-align:right">Base</td><td style="text-align:right">$${Number(t.base||0).toFixed(2)}</td></tr>
                <tr><td colspan="3" style="text-align:right">IVA</td><td style="text-align:right">$${Number(t.iva||0).toFixed(2)}</td></tr>
                <tr><td colspan="3" style="text-align:right"><strong>Total</strong></td><td style="text-align:right"><strong>$${Number(t.total||0).toFixed(2)}</strong></td></tr>
              </tfoot>
            </table>
          </div>`;
        $('#payment_method').value = t.sugerencias?.metodo_pago || 'PUE';
        $('#payment_form').value = t.sugerencias?.forma_pago || '03';
      }

      // Buscar ticket
      $('#form-buscar').addEventListener('submit', async e => {
        e.preventDefault();
        $('#resultado').textContent = '';
        const ticket_id = $('#ticket_id').value.trim();
        const folio = $('#folio').value.trim();
        const fecha = $('#fecha').value.trim();
        try{
          const r = await apiPost('/facturacion/buscar-ticket.php', {
            ticket_id: ticket_id?Number(ticket_id):undefined,
            folio: folio?Number(folio):undefined,
            fecha
          });
          if(!r.ok) throw new Error(r.message||'Error');
          foundTicket = r.ticket;
          renderPreview(foundTicket);
          $('#form-datos').removeAttribute('disabled');
          $('#form-pago').removeAttribute('disabled');
        }catch(err){
          const code = err.code || err.data?.code;
          const uuid = err.uuid || err.data?.uuid || '';
          if(code==='ALREADY_INVOICED' && uuid){
            const base = apiBase + '/facturacion/descargar.php';
            $('#resultado').innerHTML = `<div class="success">Este ticket ya tiene factura<br>UUID: <strong>${uuid}</strong><div class="mt-2"><a class="btn" href="${base}?uuid=${encodeURIComponent(uuid)}&tipo=xml">Descargar XML</a> <a class="btn" href="${base}?uuid=${encodeURIComponent(uuid)}&tipo=pdf">Descargar PDF</a></div></div>`;
            foundTicket=null; $('#preview').textContent=''; 
            $('#form-datos').setAttribute('disabled','disabled');
            $('#form-pago').setAttribute('disabled','disabled');
            return;
          }
          foundTicket=null; $('#preview').textContent=''; 
          $('#form-datos').setAttribute('disabled','disabled');
          $('#form-pago').setAttribute('disabled','disabled');
          $('#resultado').textContent = err.message || 'Error al buscar ticket';
        }
      });

      // RFC blur
      $('#rfc').addEventListener('blur', async e => {
        const rfc = e.target.value.trim().toUpperCase();
        if(rfc.length<12) return;
        try{
          const r = await apiGet('/clientes-fiscales.php',{rfc});
          if(r.ok && r.cliente){
            $('#razon_social').value = r.cliente.razon_social||'';
            $('#regimen').value = r.cliente.regimen||'';
            $('#cp_cliente').value = r.cliente.cp||'';
            $('#uso_cfdi').value = r.cliente.uso_cfdi||'';
            onRegimenChange();
          }
        }catch(e){}
        if(isGenericRFC(rfc)){
          $('#uso_cfdi').value='S01';
          $('#rfc-generic-note').textContent = 'RFC genérico: se recomienda Uso CFDI S01 y se usará el CP fiscal como Lugar de Expedición.';
        }
      });

      function clearFieldErrors(){
        document.querySelectorAll('.error-msg').forEach(span=>span.textContent='');
        document.querySelectorAll('.is-invalid').forEach(field=>field.classList.remove('is-invalid'));
      }

      $('#btn-timbrar').addEventListener('click', async ()=>{
        if(!foundTicket){ $('#resultado').textContent='Busca y selecciona un ticket válido'; return; }
        clearFieldErrors();

        const rfc = normUpper($('#rfc').value);
        const razonSocial = $('#razon_social').value.trim();
        const regimen = $('#regimen').value.trim();
        const cpCliente = $('#cp_cliente').value.trim();
        const expPlace = $('#exp_place').value.trim();
        const usoCfdi = $('#uso_cfdi').value.trim();
        let valid = true;

        if(!rfc){ showFieldError('rfc','RFC es requerido'); valid=false; }
        else if(!/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/.test(rfc)){ showFieldError('rfc','Formato RFC inválido'); valid=false; }
        if(!razonSocial){ showFieldError('razon_social','Razón Social es requerida'); valid=false; }
        if(!regimen){ showFieldError('regimen','Régimen Fiscal es requerido'); valid=false; }
        if(!only5Digits(cpCliente)){ showFieldError('cp_cliente','C.P. fiscal inválido'); valid=false; }
        if(!only5Digits(expPlace)){ showFieldError('exp_place','Lugar de expedición inválido'); valid=false; }
        if(!usoCfdi){ showFieldError('uso_cfdi','Uso CFDI es requerido'); valid=false; }

        if(!valid) return;

        const payload = {
          ticket_id: foundTicket.id,
          rfc,
          razon_social: razonSocial,
          regimen,
          Receiver: { TaxZipCode: cpCliente },
          ExpeditionPlace: expPlace,
          cp_cliente: cpCliente,
          exp_place: expPlace,
          uso_cfdi: usoCfdi,
          correo: $('#correo')?.value.trim(),
          payment_method: $('#payment_method')?.value,
          payment_form: $('#payment_form')?.value,
          observaciones: $('#observaciones')?.value.trim()
        };

        try{
          const r = await apiPost('/facturacion/timbrar.php', payload);
          if(!r.ok) throw new Error(r.error||r.message||'Error al timbrar');
          const xmlLink = `${apiBase}/facturacion/descargar.php?uuid=${encodeURIComponent(r.uuid)}&tipo=xml`;
          const pdfLink = `${apiBase}/facturacion/descargar.php?uuid=${encodeURIComponent(r.uuid)}&tipo=pdf`;
          $('#resultado').innerHTML = `<div class="success">Factura generada<br>UUID: <strong>${r.uuid}</strong><div class="mt-2"><a class="btn" href="${xmlLink}">Descargar XML</a> <a class="btn" href="${pdfLink}">Descargar PDF</a></div></div>`;
        }catch(err){
          $('#resultado').textContent = err.message + (err.data?.detail ? ': ' + JSON.stringify(err.data.detail) : '');
        }
      });

      // Inicializar catálogos
      (async function init(){
        try{
          const r = await Promise.all([loadRegimenes(), loadUsos(), (async()=>{
            const res = await apiGet('/facturacion/branch-offices.php');
            if(!res.ok) return;
            const sel = $('#exp_place');
            sel.innerHTML='';
            const lastZip = localStorage.getItem('exp_place_zip')||'';
            (res.branches||[]).forEach(b=>{
              const zip = b?.Address?.ZipCode || '';
              const opt = document.createElement('option');
              opt.value = zip;
              opt.textContent = `${b.Name} (CP ${zip})`;
              if((lastZip && zip===lastZip) || (!lastZip && b.IsDefault)) opt.selected=true;
              sel.appendChild(opt);
            });
            sel.addEventListener('change', ()=>localStorage.setItem('exp_place_zip', sel.value||''));
          })()]);
        }catch(e){ console.error(e); }
        $('#regimen').addEventListener('change', onRegimenChange);
      })();

      function showFieldError(id,message){
        const field = $('#'+id);
        if(!field) return;
        field.classList.add('is-invalid');
        const span = field.parentElement.querySelector('.error-msg');
        if(span) span.textContent=message;
      }
    });
  </script>
</body>
</html>
