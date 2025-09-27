<?php $pageTitle = 'Pago exitoso'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
  <style>
    .status-pill{display:inline-block;padding:.25rem .5rem;border-radius:999px;background:#eef6ee;color:#2e7d32;font-weight:600}
    .status-warn{background:#fff3e0;color:#e65100}
    .alert{display:none;margin:.5rem 0;padding:.5rem .75rem;border-radius:6px;background:#fdecea;color:#611a15}
    table.order{width:100%;border-collapse:collapse}
    table.order th, table.order td{padding:.5rem;border-bottom:1px solid #eee;text-align:left}
    table.order th:last-child, table.order td:last-child{text-align:right}
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const params = new URLSearchParams(location.search);
      const ref = params.get('ref');
      const elRef = document.getElementById('ref');
      const elStatus = document.getElementById('status');
      const elVenta = document.getElementById('venta');
      const alertBox = document.getElementById('alert');
      const tbody = document.getElementById('order-items');
      const sumSubtotal = document.getElementById('sum-subtotal');
      const sumEnvio = document.getElementById('sum-envio');
      const sumTotal = document.getElementById('sum-total');
      if (ref) elRef.textContent = ref;

      async function loadOrderSnapshot(){
        try {
          const [resCart, resStatus] = await Promise.all([
            fetch('../api/carrito/listar.php', { credentials:'same-origin' }),
            fetch('../api/checkout/status.php?ref='+encodeURIComponent(ref), { credentials:'same-origin' })
          ]);
          if(!resCart.ok) throw new Error('HTTP '+resCart.status);
          const data = await resCart.json();
          let surcharge = 0;
          if (resStatus.ok) {
            const st = await resStatus.json();
            if (typeof st.surcharge === 'number') surcharge = st.surcharge || 0;
          }
          tbody.innerHTML = '';
          (data.items||[]).forEach(it => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${it.nombre}</td><td>x${it.cantidad}</td><td>$${Number(it.precio).toFixed(2)}</td><td><strong>$${Number(it.subtotal).toFixed(2)}</strong></td>`;
            tbody.appendChild(tr);
          });
          if (surcharge > 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td><em>Cargo por plataforma</em></td><td>x1</td><td>$${surcharge.toFixed(2)}</td><td><strong>$${surcharge.toFixed(2)}</strong></td>`;
            tbody.appendChild(tr);
          }
          sumSubtotal.textContent = `$${Number(data.subtotal||0).toFixed(2)}`;
          sumEnvio.textContent = `$${Number(data.envio||0).toFixed(2)}`;
          const total = Number(data.total||0) + (surcharge||0);
          sumTotal.textContent = `$${total.toFixed(2)}`;
        } catch(e){ console.error('Load order snapshot failed', e); }
      }

      async function vaciarCarrito(){
        try { await fetch('../api/carrito/vaciar.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({}) }); } catch(e){}
      }

      async function confirmarPedido(){
        try {
          const sedeId = localStorage.getItem('selectedSedeId');
          // Obtener corte abierto para mandar el ID
          let corte_id = null;
          try {
            const rc = await fetch('../api/corte_caja/verificar_corte_abierto.php', { credentials:'same-origin' });
            if (rc.ok) { const jc = await rc.json(); corte_id = jc?.resultado?.corte_id || null; }
          } catch(e){}
          const res = await fetch('../api/checkout/confirmar.php', {
            method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ tipo:'rapido', ref, ...(sedeId ? { sede_id: Number(sedeId) } : {}), ...(corte_id ? { corte_id: Number(corte_id) } : {}) })
          });
          const data = await res.json().catch(()=>({}));
          if(!res.ok || !data.success){
            const details = data && (data.details || data.error) ? (data.details || data.error) : `HTTP ${res.status}`;
            alertBox.textContent = 'No se pudo registrar el pedido: '+details;
            alertBox.style.display = 'block';
            return null;
          }
          return data.venta_id || null;
        } catch(e){
          console.error('confirmarPedido failed', e);
          alertBox.textContent = 'No se pudo registrar el pedido. '+(e.message||e);
          alertBox.style.display = 'block';
          return null;
        }
      }

      async function poll(){
        try {
          const res = await fetch('../api/checkout/status.php?ref='+encodeURIComponent(ref), { credentials:'same-origin' });
          if(!res.ok) throw new Error('HTTP '+res.status);
          const data = await res.json();
          elStatus.textContent = data.status;
          // Dejar de consultar si el estatus ya no es 'pending'
          if (data.status !== 'pending') {
            if (data.status === 'paid') {
              if (data.venta_id) {
                elVenta.textContent = '#'+data.venta_id;
                document.getElementById('next-actions').style.display = 'block';
                await vaciarCarrito();
              } else {
                // Crear venta usando APIs (con corte_id) de forma idempotente
                const vid = await confirmarPedido();
                if (vid) {
                  elVenta.textContent = '#'+vid;
                  document.getElementById('next-actions').style.display = 'block';
                  await vaciarCarrito();
                }
              }
            }
            return; // status es distinto a pending, no seguir consultando
          }
          setTimeout(poll, 2000);
        } catch(e) {
          console.error(e);
          setTimeout(poll, 3000);
        }
      }
      if (ref) { loadOrderSnapshot(); poll(); }
    });
  </script>
  
</head>
<body>
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container">
        <div class="card">
          <div class="card__body">
            <h2>Pago recibido</h2>
            <p>Referencia: <strong id="ref">-</strong></p>
            <p>Estado: <span id="status" class="status-pill">procesando</span></p>
            <p>Pedido: <strong id="venta">(en preparación)</strong></p>
            <div id="alert" class="alert"></div>
            <h3 class="mt-3">Detalle de tu pedido</h3>
            <div class="mt-2">
              <table class="order" aria-label="Detalle de pedido">
                <thead>
                  <tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr>
                </thead>
                <tbody id="order-items"><tr><td colspan="4">Cargando…</td></tr></tbody>
                <tfoot>
                  <tr><td colspan="3" style="text-align:right">Subtotal</td><td id="sum-subtotal">$0.00</td></tr>
                  <tr><td colspan="3" style="text-align:right">Envío</td><td id="sum-envio">$0.00</td></tr>
                  <tr><td colspan="3" style="text-align:right"><strong>Total</strong></td><td id="sum-total"><strong>$0.00</strong></td></tr>
                </tfoot>
              </table>
            </div>
            <div id="next-actions" style="display:none" class="mt-3">
              <a class="btn custom-btn" href="index.php">Volver al inicio</a>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
  
</body>
</html>
