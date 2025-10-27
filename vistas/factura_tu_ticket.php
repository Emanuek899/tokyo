<?php $pageTitle = 'Tokyo Sushi - Facturación'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
  <style>
    /* Grid general */
    .grid-2 { 
      display: grid; 
      grid-template-columns: 1.2fr 0.8fr; 
      gap: 1rem; 
    }
    
    .grid { 
      display: grid; 
      gap: 0.75rem; 
    }
    
    .field { 
      display: flex; 
      flex-direction: column; 
      gap: 0.35rem; 
    }
    
    .field label { 
      font-size: 0.9rem; 
      color: var(--color-text); 
      font-weight: 500;
    }

    /* Errores */
    .error-msg { 
      color: #dc3545; 
      font-size: 0.875rem; 
      margin-top: 0.25rem; 
      display: none; 
    }
    
    .is-invalid { 
      border-color: #dc3545 !important; 
      background-color: #fff5f5 !important; 
    }
    
    .is-invalid + .error-msg { 
      display: block; 
    }

    /* Mensajes de éxito */
    .success {
      padding: 1rem;
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
      border-radius: 0.25rem;
      color: #155724;
      margin-top: 1rem;
    }

    /* Texto muted */
    .muted { 
      color: var(--color-muted); 
      font-size: 0.9rem;
    }

    /* Spacing utilities */
    .mt-2 { margin-top: 1rem; }
    .mt-3 { margin-top: 1.5rem; }

    /* Tabla responsive */
    .table-responsive { 
      width: 100%; 
      overflow-x: auto; 
      -webkit-overflow-scrolling: touch; 
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      min-width: 500px;
      color: black;
    }

    .table th,
    .table td {
      padding: 0.75rem;
      border-bottom: 1px solid #dee2e6;
      text-align: left;
    }

    .table thead th {
      background-color: #f8f9fa;
      font-weight: 600;
    }

    .table tfoot td {
      border-top: 2px solid #dee2e6;
      font-weight: 500;
    }

    /* Fieldset styling */
    fieldset {
      border: 1px solid #dee2e6;
      border-radius: 0.25rem;
      padding: 1rem;
      margin-bottom: 1rem;
    }

    fieldset legend {
      font-size: 1.1rem;
      font-weight: 600;
      padding: 0 0.5rem;
    }

    fieldset[disabled] {
      opacity: 0.6;
      pointer-events: none;
    }

    /* Media queries */
    @media (max-width: 768px) {
      .grid-2 { 
        grid-template-columns: 1fr; 
      }
      
      .container, 
      .card__body { 
        padding: 1rem; 
      }
    }

    @media (max-width: 640px) {
      form#form-buscar { 
        grid-template-columns: 1fr !important; 
      }
      
      fieldset .grid { 
        grid-template-columns: 1fr !important; 
      }
      
      .field { 
        grid-column: span 1 !important; 
      }
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
          <p class="muted">Busca tu ticket, captura tus datos fiscales y genera tu CFDI 4.0.</p>

          <h4>Estado</h4>
          <div id="resultado" class="muted">Completa los pasos para timbrar tu factura.</div>

          <!-- Formulario búsqueda ticket -->
          <form id="form-buscar" class="grid" style="grid-template-columns: repeat(4, 1fr); align-items: end;">
            <div class="field">
              <label for="ticket_id">Ticket ID</label>
              <input id="ticket_id" type="text" class="input" placeholder="Opcional si usas Folio">
              <span class="error-msg"></span>
            </div>
            
            <div class="field">
              <label for="folio">Folio</label>
              <input id="folio" type="text" class="input" placeholder="Del ticket">
              <span class="error-msg"></span>
            </div>
            
            <div class="field">
              <label for="fecha">Fecha</label>
              <input id="fecha" type="text" class="input" placeholder="dd/mm/aaaa">
              <span class="error-msg"></span>
            </div>
            
            <div>
              <button class="btn custom-btn" type="submit">Buscar ticket</button>
            </div>
          </form>

          <!-- Preview ticket -->
          <div class="mt-2" id="preview"></div>

          <!-- Datos fiscales -->
          <fieldset id="form-datos" class="mt-3" disabled>
            <legend>Datos fiscales del receptor</legend>
            <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
              
              <div class="field" style="grid-column: span 2;">
                <label for="rfc">RFC *</label>
                <input id="rfc" type="text" class="input" required maxlength="13" style="text-transform: uppercase;">
                <span class="error-msg"></span>
                <small id="rfc-generic-note" class="muted"></small>
              </div>
              
              <div class="field" style="grid-column: span 2;">
                <label for="razon_social">Razón Social *</label>
                <input id="razon_social" type="text" class="input" required>
                <span class="error-msg"></span>
              </div>
              
              <div class="field">
                <label for="regimen">Régimen fiscal *</label>
                <select id="regimen" class="input" required>
                  <option value="">Cargando...</option>
                </select>
                <span class="error-msg"></span>
              </div>
              
              <div class="field">
                <label for="cp_cliente">Código Postal *</label>
                <input id="cp_cliente" type="text" maxlength="5" class="input" required pattern="[0-9]{5}" placeholder="5 dígitos">
                <span class="error-msg"></span>
              </div>
              
              <div class="field">
                <label for="exp_place">Lugar de expedición *</label>
                <select id="exp_place" class="input" required>
                  <option value="">Cargando...</option>
                </select>
                <span class="error-msg"></span>
              </div>
              
              <div class="field">
                <label for="uso_cfdi">Uso CFDI *</label>
                <select id="uso_cfdi" class="input" required>
                  <option value="">Cargando...</option>
                </select>
                <small id="uso-hint" class="muted"></small>
                <span class="error-msg"></span>
              </div>
              
              <div class="field" style="grid-column: span 2;">
                <label for="correo">Correo electrónico (opcional)</label>
                <input id="correo" type="email" class="input" placeholder="ejemplo@correo.com">
              </div>
            </div>
          </fieldset>

          <!-- Pago SAT -->
          <fieldset id="form-pago" class="mt-3" disabled>
            <legend>Información de pago</legend>
            <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
              
              <div class="field">
                <label for="payment_method">Método de pago *</label>
                <select id="payment_method" class="input" required>
                  <option value="PUE">PUE - Pago en una sola exhibición</option>
                  <option value="PPD">PPD - Pago en parcialidades o diferido</option>
                </select>
              </div>
              
              <div class="field">
                <label for="payment_form">Forma de pago *</label>
                <select id="payment_form" class="input" required>
                  <option value="01">01 - Efectivo</option>
                  <option value="02">02 - Cheque nominativo</option>
                  <option value="03">03 - Transferencia electrónica</option>
                  <option value="04">04 - Tarjeta de crédito</option>
                  <option value="28">28 - Tarjeta de débito</option>
                  <option value="99">99 - Por definir</option>
                </select>
              </div>
              
              <div class="field" style="grid-column: span 2;">
                <label for="observaciones">Observaciones (opcional)</label>
                <textarea id="observaciones" class="input" rows="3" placeholder="Información adicional"></textarea>
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
      const $ = (s, ctx = document) => ctx.querySelector(s);
      const $$ = (s, ctx = document) => ctx.querySelectorAll(s);
      
      const apiBase = (() => {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const idx = parts.indexOf('tokyo');
        return (idx >= 0 ? '/' + parts.slice(0, idx + 1).join('/') : '/' + (parts[0] || '')) + '/api/public';
      })();

      let foundTicket = null;
      let allUsos = [];

      const normUpper = s => (s || '').trim().toUpperCase();
      const only5Digits = s => /^\d{5}$/.test(String(s || '').trim());
      const isGenericRFC = rfc => ['XAXX010101000', 'XEXX010101000'].includes(normUpper(rfc));

      async function apiPost(path, body) {
        const csrfToken = $('meta[name="csrf-token"]')?.getAttribute('content');
        const res = await fetch(apiBase + path, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken || ''
          },
          credentials: 'same-origin',
          body: JSON.stringify(body)
        });

        const isJson = (res.headers.get('content-type') || '').includes('application/json');
        
        if (!res.ok) {
          const data = isJson ? await res.json().catch(() => null) : null;
          const err = new Error((data && data.message) || `HTTP ${res.status}`);
          err.status = res.status;
          err.code = data?.code;
          err.data = data;
          err.uuid = data?.uuid;
          throw err;
        }
        
        return isJson ? res.json() : res.text();
      }

      async function apiGet(path, params = {}) {
        const url = new URL(apiBase + path, window.location.origin);
        Object.entries(params).forEach(([k, v]) => {
          if (v !== '' && v != null) url.searchParams.set(k, v);
        });

        const res = await fetch(url.toString(), { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      }

      async function loadRegimenes() {
        const r = await apiGet('/facturacion/catalogos/regimenes.php');
        if (!r.ok) throw new Error('Error cargando régimenes');
        $('#regimen').innerHTML = '<option value="">Selecciona régimen fiscal...</option>' + 
          r.data.map(x => `<option value="${x.code}">${x.code} - ${x.descripcion}</option>`).join('');
      }

      async function loadUsos() {
        const r = await apiGet('/facturacion/catalogos/usos.php');
        if (!r.ok) throw new Error('Error cargando usos');
        allUsos = r.data || [];
        renderUsoOptions(allUsos.map(u => u.code));
      }

      async function loadBranchOffices() {
        const r = await apiGet('/facturacion/branch-offices.php');
        if (!r.ok) return;
        
        const sel = $('#exp_place');
        sel.innerHTML = '';
        const lastZip = localStorage.getItem('exp_place_zip') || '';
        
        (r.branches || []).forEach(b => {
          const zip = b?.Address?.ZipCode || '';
          const opt = document.createElement('option');
          opt.value = zip;
          opt.textContent = `${b.Name} (CP ${zip})`;
          if ((lastZip && zip === lastZip) || (!lastZip && b.IsDefault)) opt.selected = true;
          sel.appendChild(opt);
        });
        
        sel.addEventListener('change', () => localStorage.setItem('exp_place_zip', sel.value || ''));
      }

      function renderUsoOptions(codes) {
        const sel = $('#uso_cfdi');
        sel.innerHTML = '<option value="">Selecciona uso CFDI...</option>' + 
          allUsos.filter(u => codes.includes(u.code))
            .map(u => `<option value="${u.code}">${u.code} - ${u.descripcion}</option>`).join('');
        $('#uso-hint').textContent = codes.length ? `Usos permitidos: ${codes.join(', ')}` : '';
      }

      async function onRegimenChange() {
        const reg = $('#regimen').value || '';
        if (!reg) {
          renderUsoOptions(allUsos.map(u => u.code));
          return;
        }
        try {
          const r = await apiGet('/facturacion/catalogos/usos_por_regimen.php', { regimen: reg });
          if (r.ok) renderUsoOptions(r.data || []);
        } catch (e) {
          console.error('Error cargando usos:', e);
        }
      }

      function renderPreview(t) {
        const rows = (t.partidas || []).map(p => `
          <tr>
            <td>${p.descripcion || ''}</td>
            <td style="text-align:right">${p.cantidad}</td>
            <td style="text-align:right">$${Number(p.precio_unitario).toFixed(2)}</td>
            <td style="text-align:right">$${Number(p.importe).toFixed(2)}</td>
          </tr>
        `).join('');
        
        $('#preview').innerHTML = `
          <div class="muted">Ticket #${t.folio || t.id} • ${t.fecha?.slice(0, 10) || ''}</div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Concepto</th>
                  <th style="text-align:right">Cant.</th>
                  <th style="text-align:right">Precio</th>
                  <th style="text-align:right">Importe</th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
              <tfoot>
                <tr>
                  <td colspan="3" style="text-align:right">Subtotal</td>
                  <td style="text-align:right">$${Number(t.base || 0).toFixed(2)}</td>
                </tr>
                <tr>
                  <td colspan="3" style="text-align:right">IVA (16%)</td>
                  <td style="text-align:right">$${Number(t.iva || 0).toFixed(2)}</td>
                </tr>
                <tr>
                  <td colspan="3" style="text-align:right"><strong>Total</strong></td>
                  <td style="text-align:right"><strong>$${Number(t.total || 0).toFixed(2)}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
        `;
        
        $('#payment_method').value = t.sugerencias?.metodo_pago || 'PUE';
        $('#payment_form').value = t.sugerencias?.forma_pago || '03';
      }

      function clearFieldErrors() {
        $$('.error-msg').forEach(s => s.textContent = '');
        $$('.is-invalid').forEach(f => f.classList.remove('is-invalid'));
      }

      function showFieldError(id, msg) {
        const field = $('#' + id);
        if (!field) return;
        field.classList.add('is-invalid');
        const span = field.parentElement.querySelector('.error-msg');
        if (span) span.textContent = msg;
      }

      $('#form-buscar').addEventListener('submit', async e => {
        e.preventDefault();
        $('#resultado').textContent = '';
        clearFieldErrors();

        const ticket_id = $('#ticket_id').value.trim();
        const folio = $('#folio').value.trim();
        const fecha = $('#fecha').value.trim();
        let valid = true;

        if (!fecha && !folio) {
          showFieldError('fecha', 'Ingresa fecha o folio');
          showFieldError('folio', 'Ingresa fecha o folio');
          valid = false;
        }
        if (fecha && !/^\d{2}\/\d{2}\/\d{4}$/.test(fecha)) {
          showFieldError('fecha', 'Formato: dd/mm/aaaa');
          valid = false;
        }
        if (folio && !/^\d+$/.test(folio)) {
          showFieldError('folio', 'Debe ser numérico');
          valid = false;
        }
        if (ticket_id && !/^\d+$/.test(ticket_id)) {
          showFieldError('ticket_id', 'Debe ser numérico');
          valid = false;
        }
        if (!valid) return;

        try {
          const r = await apiPost('/facturacion/buscar-ticket.php', {
            ticket_id: ticket_id ? Number(ticket_id) : undefined,
            folio: folio ? Number(folio) : undefined,
            fecha
          });
          
          if (!r.ok) throw new Error(r.message || 'Error');
          
          foundTicket = r.ticket;
          renderPreview(foundTicket);
          $('#form-datos').removeAttribute('disabled');
          $('#form-pago').removeAttribute('disabled');
          
        } catch (err) {
          const code = err.code || err.data?.code;
          const uuid = err.uuid || err.data?.uuid || '';
          
          if (code === 'ALREADY_INVOICED' && uuid) {
            const base = apiBase + '/facturacion/descargar.php';
            $('#resultado').innerHTML = `
              <div class="success">
                Este ticket ya tiene factura<br>
                UUID: <strong>${uuid}</strong>
                <div class="mt-2">
                  <a class="btn" href="${base}?uuid=${encodeURIComponent(uuid)}&tipo=xml">XML</a>
                  <a class="btn" href="${base}?uuid=${encodeURIComponent(uuid)}&tipo=pdf">PDF</a>
                </div>
              </div>
            `;
            foundTicket = null;
            $('#preview').textContent = '';
            $('#form-datos').setAttribute('disabled', 'disabled');
            $('#form-pago').setAttribute('disabled', 'disabled');
            return;
          }
          
          foundTicket = null;
          $('#preview').textContent = '';
          $('#form-datos').setAttribute('disabled', 'disabled');
          $('#form-pago').setAttribute('disabled', 'disabled');
          $('#resultado').textContent = err.message || 'Error al buscar ticket';
        }
      });

      $('#rfc').addEventListener('blur', async e => {
        const rfc = normUpper(e.target.value);
        if (rfc.length < 12) return;
        
        try {
          const r = await apiGet('/clientes-fiscales.php', { rfc });
          if (r.ok && r.cliente) {
            $('#razon_social').value = r.cliente.razon_social || '';
            $('#regimen').value = r.cliente.regimen || '';
            $('#cp_cliente').value = r.cliente.cp || '';
            $('#uso_cfdi').value = r.cliente.uso_cfdi || '';
            onRegimenChange();
          }
        } catch (e) {}
        
        if (isGenericRFC(rfc)) {
          $('#uso_cfdi').value = 'S01';
          $('#rfc-generic-note').textContent = 'RFC genérico: usar S01 (Sin efectos fiscales)';
        } else {
          $('#rfc-generic-note').textContent = '';
        }
      });

      $('#btn-timbrar').addEventListener('click', async () => {
        if (!foundTicket) {
          $('#resultado').textContent = 'Busca un ticket válido primero';
          return;
        }
        
        clearFieldErrors();

        const rfc = normUpper($('#rfc').value);
        const razon_social = $('#razon_social').value.trim();
        const regimen = $('#regimen').value.trim();
        const cp = $('#cp_cliente').value.trim();
        const uso_cfdi = $('#uso_cfdi').value.trim();
        let valid = true;

        if (!rfc) {
          showFieldError('rfc', 'RFC requerido');
          valid = false;
        } else if (!/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/.test(rfc)) {
          showFieldError('rfc', 'RFC inválido');
          valid = false;
        }
        if (!razon_social) {
          showFieldError('razon_social', 'Razón social requerida');
          valid = false;
        }
        if (!regimen) {
          showFieldError('regimen', 'Régimen requerido');
          valid = false;
        }
        if (!only5Digits(cp)) {
          showFieldError('cp_cliente', 'CP inválido (5 dígitos)');
          valid = false;
        }
        if (!uso_cfdi) {
          showFieldError('uso_cfdi', 'Uso CFDI requerido');
          valid = false;
        }

        if (!valid) return;

        const payload = {
          ticket_id: foundTicket.id,
          rfc,
          razon_social,
          regimen,
          cp,
          uso_cfdi,
          correo: $('#correo').value.trim() || undefined,
          payment_method: $('#payment_method').value,
          payment_form: $('#payment_form').value,
          observaciones: $('#observaciones').value.trim() || undefined
        };

        $('#resultado').textContent = 'Generando factura...';
        $('#btn-timbrar').disabled = true;

        try {
          const r = await apiPost('/facturacion/timbrar.php', payload);
          
          if (!r.ok) throw new Error(r.error || r.message || 'Error al timbrar');
          
          const xmlLink = `${apiBase}/facturacion/descargar.php?uuid=${encodeURIComponent(r.uuid)}&tipo=xml`;
          const pdfLink = `${apiBase}/facturacion/descargar.php?uuid=${encodeURIComponent(r.uuid)}&tipo=pdf`;
          
          $('#resultado').innerHTML = `
            <div class="success">
              ¡Factura generada!<br>
              UUID: <strong>${r.uuid}</strong><br>
              ${r.folio ? `Folio: <strong>${r.serie || ''}-${r.folio}</strong><br>` : ''}
              Total: <strong>$${Number(r.total || 0).toFixed(2)}</strong>
              <div class="mt-2">
                <a class="btn" href="${xmlLink}" download>Descargar XML</a>
                <a class="btn" href="${pdfLink}" download>Descargar PDF</a>
              </div>
            </div>
          `;
          
          foundTicket = null;
          $('#preview').innerHTML = '';
          $('#form-datos').setAttribute('disabled', 'disabled');
          $('#form-pago').setAttribute('disabled', 'disabled');
          $('#form-buscar').reset();
          
        } catch (err) {
          const detail = err.data?.detail ? '<br><small>' + JSON.stringify(err.data.detail) + '</small>' : '';
          $('#resultado').innerHTML = `<div style="color:#dc3545">${err.message}${detail}</div>`;
        } finally {
          $('#btn-timbrar').disabled = false;
        }
      });

      (async function init() {
        try {
          await Promise.all([loadRegimenes(), loadUsos(), loadBranchOffices()]);
          $('#regimen').addEventListener('change', onRegimenChange);
        } catch (e) {
          console.error(e);
          $('#resultado').textContent = 'Error al cargar catálogos. Recarga la página.';
        }
      })();
    });
  </script>
</body>
</html>