<?php $pageTitle = 'Tokyo Sushi - Checkout'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
</head>
<body data-page="checkout">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container grid" style="grid-template-columns: 1.5fr 1fr; gap: 2rem;">
        <form class="card" action="#" method="post" aria-labelledby="checkout-title" onsubmit="return false;">
          <div class="card__body">
            <div class="section-header"><h2 id="checkout-title">Checkout</h2></div>
            <fieldset class="mt-3">
              <legend><strong>Paso 1:</strong> Método</legend>
              <label class="flex items-center gap-2 mt-2"><input type="radio" name="metodo" checked> Pickup</label>
              <label class="flex items-center gap-2 mt-2"><input type="radio" name="metodo"> Delivery</label>
            </fieldset>
            <fieldset class="mt-3">
              <legend><strong>Paso 2:</strong> Datos de contacto/dirección</legend>
              <div class="grid" style="grid-template-columns: 1fr 1fr; gap: .75rem;">
                <div class="field"><label>Nombre</label><input class="input" type="text" required></div>
                <div class="field"><label>Teléfono</label><input class="input" type="tel" required></div>
                <div class="field" style="grid-column: span 2"><label>Dirección</label><input class="input" type="text"></div>
              </div>
            </fieldset>
            <fieldset class="mt-3">
              <legend><strong>Paso 3:</strong> Pago</legend>
              <div class="field"><label>Método</label>
                <select class="select" id="inp-metodo">
                  <option>Tarjeta</option>
                  <option>Efectivo</option>
                  <option>Contraentrega</option>
                </select>
              </div>
            </fieldset>
            <button class="btn custom-btn mt-4" disabled title="Demo" id="btn-pagar">Confirmar pedido</button>
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
</body>
</html>

