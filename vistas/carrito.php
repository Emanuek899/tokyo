<?php $pageTitle = 'Tokyo Sushi - Carrito'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
</head>
<body data-page="carrito">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container grid" style="grid-template-columns: 1.5fr 1fr; gap: 2rem;">
        <div>
          <div class="section-header"><h2>Tu carrito</h2></div>
          <div class="card">
            <div class="card__body">
              <div class="mb-3 flex items-center gap-3">
                <input type="text" class="input" placeholder="Código de promoción">
                <button class="btn custom-btn" data-toast="Promo aplicada">Aplicar</button>
              </div>
              <div class="w-full">
                <table aria-describedby="resumen" id="tabla-carrito">
                  <thead>
                    <tr><th>Producto</th><th style="width:140px">Cantidad</th><th>Precio</th><th>Subtotal</th></tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
                <div class="mt-3"><button id="btn-recalcular" class="btn custom-btn btn--sm">Recalcular</button></div>
              </div>
            </div>
          </div>
        </div>
        <aside>
          <div class="card">
            <div class="card__body" id="resumen">
              <h2>Resumen</h2>
              <div class="flex justify-between mt-2"><span>Subtotal</span><strong id="sum-subtotal">$0.00</strong></div>
              <div class="flex justify-between mt-2"><span>Envío</span><strong id="sum-envio">$0.00</strong></div>
              <div class="flex justify-between mt-2"><span>Total</span><strong id="sum-total">$0.00</strong></div>
              <a class="btn custom-btn mt-3 w-full" href="checkout.php">Proceder al pago</a>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
</body>
</html>

