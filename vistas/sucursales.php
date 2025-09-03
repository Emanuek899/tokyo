<?php $pageTitle = 'Tokyo Sushi - Sucursales'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
</head>
<body data-page="sucursales">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container grid" style="grid-template-columns: 1fr 1.2fr; gap: 2rem;">
        <div>
          <div class="section-header"><h2>Sucursales</h2></div>
          <div id="lista-sucursales" class="grid grid--auto grid--responsive"></div>
        </div>
        <div class="card">
          <div class="card__body">
            <h2 class="mb-2">Mapa</h2>
            <div id="mapa" class="bg-muted rounded" style="height: 420px; display:flex; align-items:center; justify-content:center;">Mapa placeholder</div>
          </div>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
</body>
</html>

