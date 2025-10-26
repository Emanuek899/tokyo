<?php $pageTitle = 'Tokyo Sushi - Checkout'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
  <?php require_once __DIR__.'/../config/conekta.php'; ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .muted{color:#666;font-size:.9rem}
    .error-msg{color:#dc2626;font-size:.85rem;display:block;min-height:1.2rem;margin-top:.25rem}
    
    /* Responsive Grid */
    .checkout-grid {
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 2rem;
    }
    
    @media (max-width: 968px) {
      .checkout-grid {
        grid-template-columns: 1fr;
      }
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .75rem;
    }
    
    @media (max-width: 640px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
      .form-grid .field[style*="span 2"] {
        grid-column: span 1 !important;
      }
    }
    
    .field-full {
      grid-column: span 2;
    }
    
    @media (max-width: 640px) {
      .field-full {
        grid-column: span 1;
      }
    }
    
    .input.error, .select.error {
      border-color: #dc2626;
    }
    
    .hidden {
      display: none;
    }
  </style>
  <script>
    window.PassThroughFees = <?php echo ConektaCfg::passThroughEnabled() ? 'true' : 'false'; ?>;
    window.FeesCfg = <?php $fc=ConektaCfg::feesCfg(); echo json_encode($fc['fees'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  </script>
</head>
<body data-page="checkout">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container checkout-grid">
        <form class="card" action="#" method="post" aria-labelledby="checkout-title" onsubmit="return false;" id="checkout-form">
          <div class="card__body">
            <div class="section-header"><h2 id="checkout-title">Checkout</h2></div>
            
            <fieldset class="mt-3">
              <legend><strong>Paso 1:</strong> Método</legend>
              <label class="flex items-center gap-2 mt-2">
                <input type="radio" name="metodo" value="pickup" checked> Pickup
              </label>
              <label class="flex items-center gap-2 mt-2">
                <input type="radio" name="metodo" value="delivery"> Delivery
              </label>
            </fieldset>
            
            <fieldset class="mt-3">
              <legend><strong>Paso 2:</strong> Datos de contacto/dirección</legend>
              <div class="form-grid">
                <div class="field">
                  <label>Nombre *</label>
                  <input class="input" type="text" name="nombre" id="inp-nombre" required>
                  <span class="error-msg" id="nombre-error"></span>
                </div>
                <div class="field">
                  <label>Teléfono *</label>
                  <input class="input" type="tel" name="telefono" id="inp-telefono" required>
                  <span class="error-msg" id="telefono-error"></span>
                </div>
                <div class="field field-full">
                  <label>Email</label>
                  <input class="input" type="email" name="email" id="inp-email" placeholder="opcional">
                  <span class="error-msg" id="email-error"></span>
                </div>
                <div class="field field-full" id="field-direccion">
                  <label>Dirección <span id="direccion-required"></span></label>
                  <input class="input" type="text" name="direccion" id="inp-direccion" placeholder="Calle, número, colonia">
                  <span class="error-msg" id="direccion-error"></span>
                </div>
                <div class="field" id="field-cp">
                  <label>Código Postal <span id="cp-required"></span></label>
                  <input class="input" type="text" name="cp" id="inp-cp" placeholder="34000" maxlength="5">
                  <span class="error-msg" id="cp-error"></span>
                </div>
                
              </div>
            </fieldset>
            
            <fieldset class="mt-3">
              <legend><strong>Paso 3:</strong> Pago</legend>
              <div class="field">
                <label>Método</label>
                <select class="select" id="inp-metodo">
                  <option value="card" selected>Tarjeta</option>
                  <option value="cash">Efectivo (OXXO)</option>
                  <option value="bank_transfer">Transferencia (SPEI)</option>
                </select>
              </div>
            </fieldset>
            
            <button class="btn custom-btn mt-4" id="btn-pagar">Ir a pagar con Conekta</button>
            <div id="alert" class="mt-2" style="display:none; background:#fdecea;color:#611a15;padding:.5rem .75rem;border-radius:6px"></div>
            <p class="mt-2 muted" id="msg"></p>
          </div>
        </form>
        
        <aside>
          <div class="card">
            <div class="card__body">
              <h2>Resumen</h2>
              <div class="flex justify-between mt-2"><span>Subtotal</span><strong id="sum-subtotal">$0.00</strong></div>
              <div class="flex justify-between mt-2"><span>Cargo por plataforma</span><strong id="sum-fee">$0.00</strong></div>
              <div class="flex justify-between mt-2"><span>Envío</span><strong id="sum-envio">$0.00</strong></div>
              <div class="flex justify-between mt-2"><span>Total</span><strong id="sum-total">$0.00</strong></div>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
  
  <script>
(function(){
  const btn = document.getElementById('btn-pagar');
  const msg = document.getElementById('msg');
  const alertBox = document.getElementById('alert');
  const sumSubtotal = document.getElementById('sum-subtotal');
  const sumFee = document.getElementById('sum-fee');
  const sumTotal = document.getElementById('sum-total');
  const sumEnvio = document.getElementById('sum-envio');
  const methodSel = document.getElementById('inp-metodo');
  
  // Inputs y errores
  const nombreInput = document.getElementById('inp-nombre');
  const telefonoInput = document.getElementById('inp-telefono');
  const emailInput = document.getElementById('inp-email');
  const direccionInput = document.getElementById('inp-direccion');
  const cpInput = document.getElementById('inp-cp');
  
  const nombreError = document.getElementById('nombre-error');
  const telefonoError = document.getElementById('telefono-error');
  const emailError = document.getElementById('email-error');
  const direccionError = document.getElementById('direccion-error');
  const cpError = document.getElementById('cp-error');
  
  const metodoRadios = document.querySelectorAll('input[name="metodo"]');
  const fieldDireccion = document.getElementById('field-direccion');
  const fieldCp = document.getElementById('field-cp');
  const direccionRequired = document.getElementById('direccion-required');
  const cpRequired = document.getElementById('cp-required');

  let isDelivery = false;

  // Función para mostrar/ocultar campos de delivery
  function toggleDeliveryFields() {
    const selectedMetodo = document.querySelector('input[name="metodo"]:checked')?.value;
    isDelivery = selectedMetodo === 'delivery';
    
    if (isDelivery) {
      direccionRequired.textContent = '*';
      cpRequired.textContent = '*';
      direccionInput.required = true;
      cpInput.required = true;
    } else {
      direccionRequired.textContent = '';
      cpRequired.textContent = '';
      direccionInput.required = false;
      cpInput.required = false;
      // Limpiar errores si cambia a pickup
      clearFieldError(direccionInput, direccionError);
      clearFieldError(cpInput, cpError);
    }
  }

  // Escuchar cambios en método de entrega
  metodoRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      toggleDeliveryFields();
      recalcSummary(); // Recalcular cuando cambia el método
    });
  });

  function setLoading(v){ 
    btn.disabled = v; 
    btn.textContent = v ? 'Redirigiendo…' : 'Ir a pagar con Conekta'; 
  }

  function selectCashTier(subtotal){
    const tiers = (window.FeesCfg?.cash?.tiers)||[];
    for (const t of tiers){ if (t.threshold == null || subtotal < Number(t.threshold)) return t; }
    return tiers.length ? tiers[tiers.length-1] : null;
  }

  function grossUp(p,r,f,iva,minFee){
    const denom = 1 - (1+iva)*r; if (Math.abs(denom) < 1e-9) return { total:p, surcharge:0 };
    let A = (p + (1+iva)*f) / denom;
    const C1 = ((A*r) + f) * (1+iva);
    const Cmin = (minFee != null) ? (minFee*(1+iva)) : null;
    if (Cmin != null && C1 < Cmin) { A = p + Cmin; }
    return { total:A, surcharge:A-p };
  }

  function computeSurcharge(subtotal, method){
    if (!window.PassThroughFees) return { total: subtotal, surcharge: 0 };
    if (method === 'card'){
      const f = window.FeesCfg.card || { rate:0, fixed:0, iva:0, min_fee:null };
      return grossUp(subtotal, Number(f.rate||0), Number(f.fixed||0), Number(f.iva||0), f.min_fee!=null?Number(f.min_fee):null);
    }
    if (method === 'bank_transfer' || method === 'spei'){
      const f = window.FeesCfg.spei || { fixed:0, iva:0 };
      const s = (1+Number(f.iva||0))*Number(f.fixed||0);
      return { total: subtotal + s, surcharge: s };
    }
    if (method === 'cash'){
      const cfg = window.FeesCfg.cash || { iva:0, tiers:[] };
      const t = selectCashTier(subtotal) || { rate:0, fixed:0, min_fee:null };
      return grossUp(subtotal, Number(t.rate||0), Number(t.fixed||0), Number(cfg.iva||0), t.min_fee!=null?Number(t.min_fee):null);
    }
    return { total: subtotal, surcharge: 0 };
  }

  function fmt(n){ return '$'+Number(n).toFixed(2); }

  async function recalcSummary(){
    try {
      const res = await fetch('../api/carrito/listar.php', { credentials:'same-origin' });
      if(!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      const subtotal = Number(data.subtotal||0);
      
      // Calcular envío: $30 si es delivery, $0 si es pickup
      const selectedMetodo = document.querySelector('input[name="metodo"]:checked')?.value;
      const envio = selectedMetodo === 'delivery' ? 30 : 0;
      
      const m = methodSel?.value || 'card';
      const calc = computeSurcharge(subtotal, m);
      sumSubtotal.textContent = fmt(subtotal);
      sumFee.textContent = fmt(calc.surcharge||0);
      if (sumEnvio) sumEnvio.textContent = fmt(envio);
      sumTotal.textContent = fmt((calc.total||subtotal)+envio);
    } catch(e){ console.error('recalcSummary', e); }
  }

  function normalizePhone(raw){
    const digits = String(raw||'').replace(/\D+/g,'');
    if (!digits) return '';
    if (digits.startsWith('52') && digits.length >= 12) return '+'+digits;
    if (digits.length === 10) return '+52'+digits;
    return '+'+digits;
  }

  // Funciones de validación
  function showFieldError(input, errorSpan, message) {
    input.classList.add('error');
    errorSpan.textContent = message;
  }

  function clearFieldError(input, errorSpan) {
    input.classList.remove('error');
    errorSpan.textContent = '';
  }

  function validarNombre(nombre) {
    clearFieldError(nombreInput, nombreError);
    if (!nombre.trim()) {
      showFieldError(nombreInput, nombreError, 'El nombre es obligatorio');
      return false;
    }
    if (nombre.trim().length < 3) {
      showFieldError(nombreInput, nombreError, 'El nombre debe tener al menos 3 caracteres');
      return false;
    }
    return true;
  }

  function validarTelefono(tel) {
    const soloNumeros = /^[0-9]+$/;
    clearFieldError(telefonoInput, telefonoError);
    
    if (!tel.trim()) {
      showFieldError(telefonoInput, telefonoError, 'El teléfono es obligatorio');
      return false;
    }
    if (!soloNumeros.test(tel)) {
      showFieldError(telefonoInput, telefonoError, 'El teléfono solo debe contener números');
      return false;
    }
    if (tel.length < 10) {
      showFieldError(telefonoInput, telefonoError, 'El teléfono debe tener al menos 10 dígitos');
      return false;
    }
    return true;
  }

  function validarEmail(email) {
    clearFieldError(emailInput, emailError);
    
    if (!email.trim()) {
      return true; // Email es opcional
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      showFieldError(emailInput, emailError, 'Formato de email no válido');
      return false;
    }
    return true;
  }

  function validarDireccion(direccion) {
    clearFieldError(direccionInput, direccionError);
    
    if (!isDelivery) {
      return true; // No es necesario en pickup
    }
    
    if (!direccion.trim()) {
      showFieldError(direccionInput, direccionError, 'La dirección es obligatoria para delivery');
      return false;
    }
    if (direccion.trim().length < 10) {
      showFieldError(direccionInput, direccionError, 'La dirección debe ser más detallada (min. 10 caracteres)');
      return false;
    }
    return true;
  }

  function validarCP(cp) {
    clearFieldError(cpInput, cpError);
    
    if (!isDelivery) {
      return true; // No es necesario en pickup
    }
    
    const cpLimpio = cp.trim();
    if (!cpLimpio) {
      showFieldError(cpInput, cpError, 'El código postal es obligatorio para delivery');
      return false;
    }
    
    if (!/^[0-9]{5}$/.test(cpLimpio)) {
      showFieldError(cpInput, cpError, 'El código postal debe tener 5 dígitos');
      return false;
    }
    
    return true;
  }

  // Validaciones automaticas
  nombreInput.addEventListener('blur', () => validarNombre(nombreInput.value));
  telefonoInput.addEventListener('blur', () => validarTelefono(telefonoInput.value));
  emailInput.addEventListener('blur', () => validarEmail(emailInput.value));
  direccionInput.addEventListener('blur', () => validarDireccion(direccionInput.value));
  cpInput.addEventListener('blur', () => validarCP(cpInput.value));

  // Limpiar error al escribir
  nombreInput.addEventListener('input', () => {
    if (nombreError.textContent) clearFieldError(nombreInput, nombreError);
  });
  telefonoInput.addEventListener('input', () => {
    if (telefonoError.textContent) clearFieldError(telefonoInput, telefonoError);
  });
  emailInput.addEventListener('input', () => {
    if (emailError.textContent) clearFieldError(emailInput, emailError);
  });
  direccionInput.addEventListener('input', () => {
    if (direccionError.textContent) clearFieldError(direccionInput, direccionError);
  });
  cpInput.addEventListener('input', () => {
    if (cpError.textContent) clearFieldError(cpInput, cpError);
  });

  async function startCheckout(){
    setLoading(true); 
    msg.textContent = '';
    alertBox.style.display = 'none'; 
    alertBox.textContent = '';

    // Validar todos los campos
    const nombreVal = nombreInput.value.trim();
    const telefonoVal = telefonoInput.value.trim();
    const emailVal = emailInput.value.trim();
    const direccionVal = direccionInput.value.trim();
    const cpVal = cpInput.value.trim();

    const nombreValido = validarNombre(nombreVal);
    const telefonoValido = validarTelefono(telefonoVal);
    const emailValido = validarEmail(emailVal);
    const direccionValida = validarDireccion(direccionVal);
    const cpValido = validarCP(cpVal);

    if (!nombreValido || !telefonoValido || !emailValido || !direccionValida || !cpValido) {
      setLoading(false);
      alertBox.textContent = 'Por favor, corrija los errores en el formulario';
      alertBox.style.display = 'block';
      // Scroll al primer error
      const firstError = document.querySelector('.input.error');
      if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      return;
    }

    try {
      const payload = {
        nombre: nombreVal,
        telefono: normalizePhone(telefonoVal),
        email: emailVal || undefined,
        metodos: [document.getElementById('inp-metodo').value],
        direccion: isDelivery ? direccionVal : undefined,
        cp: isDelivery ? cpVal : undefined
      };

      const res = await fetch('../api/checkout/conekta_init.php', {
        method: 'POST', 
        headers: {'Content-Type': 'application/json'}, 
        credentials: 'same-origin', 
        body: JSON.stringify(payload)
      });

      const data = await res.json().catch(() => ({}));
      
      if (!res.ok || !data.success) {
        const rawDetails = (data && (data.details || data.error)) || `HTTP ${res.status}`;
        console.error('Checkout init error', rawDetails);
        
        let friendly = '';
        if (typeof rawDetails === 'string') {
          if (rawDetails.includes('customer_info.phone') || rawDetails.includes('invalid_phone_number')) {
            friendly = 'Número de teléfono no válido';
          } else if (rawDetails.includes('customer_info.email') || rawDetails.includes('.email.invalid')) {
            friendly = 'Formato de correo no válido';
          } else if (rawDetails.includes('customer_info') && rawDetails.includes('invalid_datatype')) {
            friendly = 'Debe introducir datos válidos en el formulario';
          }
        }
        
        alertBox.textContent = friendly || ('No se pudo iniciar el pago: ' + rawDetails);
        alertBox.style.display = 'block';
        return;
      }
      
      window.location.href = data.checkout_url;
    } catch(e) { 
      console.error(e); 
      alertBox.textContent = 'No se pudo iniciar el pago. ' + (e.message || e);
      alertBox.style.display = 'block';
    } finally { 
      setLoading(false); 
    }
  }

  btn?.addEventListener('click', (e) => { 
    e.preventDefault(); 
    startCheckout(); 
  });
  
  methodSel?.addEventListener('change', recalcSummary);
  
  // Inicializar
  toggleDeliveryFields();
  recalcSummary();
})();
</script>
</body>
</html>