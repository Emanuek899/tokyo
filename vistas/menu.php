<?php $pageTitle = 'Tokyo Sushi - Menú'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
</head>
<body data-page="menu">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <?php include __DIR__.'/components/filtros-menu.php'; ?>
    <section class="section">
      <div class="container">
        <div id="grid-productos" class="grid grid--auto grid--responsive grid-cols-2" aria-live="polite"></div>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
</body>
</html>

