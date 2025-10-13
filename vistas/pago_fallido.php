<?php $pageTitle = 'Pago no completado'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
</head>
<body>
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
        <div class="container">
        <div class="card">
          <div class="card__body">
            <h2>Pago no completado</h2>
            <p><span class="status-fail">Transacción fallida</span></p>
            <p>Tu pago fue cancelado o falló. Puedes intentar nuevamente.</p>
            <p>Referencia: <strong><?php echo htmlspecialchars($_GET['ref'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <div class="mt-3">
              <a class="btn custom-btn" href="/tokyo/vistas/carrito.php">Volver al carrito</a>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
</body>
</html>
