<?php $pageTitle = 'Tokyo Sushi - Checkout'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
  <?php require_once __DIR__.'/../config/conekta.php'; ?>
  <meta charset="utf-8">
  <style>.muted{color:#666;font-size:.9rem}</style>
  <script>
    // Exponer configuración de recargos al frontend
    window.PassThroughFees = <?php echo ConektaCfg::passThroughEnabled() ? 'true' : 'false'; ?>;
    window.FeesCfg = <?php $fc=ConektaCfg::feesCfg(); echo json_encode($fc['fees'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  </script>
</head>
<body data-page="checkout">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container grid" style="grid-template-columns: 1.5fr 1fr; gap: 2rem;">
        <form class="card" action="#" method="post" aria-labelledby="checkout-title" onsubmit="return false;" id="checkout-form">
          <div class="card__body">
            <div class="section-header"><h2 id="checkout-title">Checkout</h2></div>
            <fieldset class="mt-3">
              <legend><strong>Paso 1:</strong> Método</legend>
              <label class="flex items-center gap-2 mt-2"><input type="radio" name="metodo" value="pickup" checked> Pickup</label>
              <label class="flex items-center gap-2 mt-2"><input type="radio" name="metodo" value="delivery"> Delivery</label>
            </fieldset>
            <fieldset class="mt-3">
              <legend><strong>Paso 2:</strong> Datos de contacto/dirección</legend>
              <div class="grid" style="grid-template-columns: 1fr 1fr; gap: .75rem;">
                <div class="field"><label>Nombre</label><input class="input" type="text" name="nombre" id="inp-nombre" required></div>
                <div class="field"><label>Teléfono</label><input class="input" type="tel" name="telefono" id="inp-telefono" required></div>
                <div class="field" style="grid-column: span 2"><label>Email</label><input class="input" type="email" name="email" id="inp-email" placeholder="opcional"></div>
                <div class="field" style="grid-column: span 2"><label>Dirección</label><input class="input" type="text" name="direccion" id="inp-direccion" placeholder="si aplica"></div>
              </div>
            </fieldset>
            <fieldset class="mt-3">
              <legend><strong>Paso 3:</strong> Pago</legend>
              <div class="field"><label>Método</label>
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
      function setLoading(v){ btn.disabled = v; btn.textContent = v ? 'Redirigiendo…' : 'Ir a pagar con Conekta'; }
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
          const envio = Number(data.envio||0);
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
        if (digits.length === 10) return '+52'+digits; // MX default
        return '+'+digits;
      }
      async function startCheckout(){
        setLoading(true); msg.textContent='';
        alertBox.style.display='none'; alertBox.textContent='';
        try {
          const payload = {
            nombre: document.getElementById('inp-nombre').value.trim(),
            telefono: normalizePhone(document.getElementById('inp-telefono').value),
            email: document.getElementById('inp-email').value.trim(),
            metodos: [ document.getElementById('inp-metodo').value ],
          };
          const res = await fetch('../api/checkout/conekta_init.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify(payload)
          });
          const data = await res.json().catch(()=>({}));
          if(!res.ok || !data.success){
            const rawDetails = (data && (data.details || data.error)) || `HTTP ${res.status}`;
            // Log completo en consola
            console.error('Checkout init error', rawDetails);
            // Front: mensajes amigables para errores comunes (teléfono/email/datatypes)
            let friendly = '';
            const setPhoneMsg = () => friendly = 'numero de telefono no valido';
            const setEmailMsg = () => friendly = 'formato de correo no valido';
            const setGenericFormMsg = () => friendly = 'debe introducir datos validos en el formulario';
            if (typeof rawDetails === 'string'){
              try {
                const idx = rawDetails.indexOf('{');
                if (idx >= 0){
                  const body = JSON.parse(rawDetails.slice(idx));
                  const errs = Array.isArray(body.details) ? body.details : [];
                  if (errs.some(e => String(e.param||'').includes('customer_info') && String(e.code||'').includes('invalid_datatype'))){
                    setGenericFormMsg();
                  } else if (errs.some(e => String(e.param||'').includes('customer_info.email') || String(e.code||'').includes('.email.invalid'))){
                    setEmailMsg();
                  } else if (errs.some(e => String(e.param||'').includes('customer_info.phone') || String(e.code||'').includes('invalid_phone_number'))){
                    setPhoneMsg();
                  }
                }
              } catch(_){}
              if (!friendly && (rawDetails.includes('customer_info') && rawDetails.includes('invalid_datatype'))){
                setGenericFormMsg();
              }
              if (!friendly && (rawDetails.includes('customer_info.email') || rawDetails.includes('.email.invalid'))){
                setEmailMsg();
              }
              if (!friendly && (rawDetails.includes('customer_info.phone') || rawDetails.includes('invalid_phone_number'))){
                setPhoneMsg();
              }
            }
            alertBox.textContent = friendly || ('No se pudo iniciar el pago: '+rawDetails);
            alertBox.style.display = 'block';
            return;
          }
          window.location.href = data.checkout_url;
        } catch(e){ console.error(e); msg.textContent = 'No se pudo iniciar el pago. '+(e.message||e); }
        finally { setLoading(false); }
      }
      btn?.addEventListener('click', (e)=>{ e.preventDefault(); startCheckout(); });
      methodSel?.addEventListener('change', recalcSummary);
      recalcSummary();
    })();
  </script>
</body>
</html>

