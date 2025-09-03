<?php $pageTitle = 'Tokyo Sushi - Home'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
  <!-- JSON-LD placeholders (Restaurant, Menu) -->
  <!--
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Restaurant",
    "name": "Tokyo Sushi",
    "servesCuisine": ["Sushi","Japonesa"],
    "address": {"@type": "PostalAddress","addressLocality": "CDMX"}
  }
  </script>
  -->
</head>
<body data-page="home">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="hero">
      <div class="container">
        <h1 class="hero__title">Sushi fresco, rápido y delicioso</h1>
        <p class="muted max-w-md">Pide ahora o explora nuestro menú por categorías. Promos visibles y sucursales cerca de ti.</p>
        <div class="hero__actions mt-3">
          <a class="btn custom-btn" href="menu.php">Pedir ahora</a>
          <a class="btn custom-btn" href="menu.php">Ver menú</a>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-header"><h2>Promos de hoy</h2></div>
        <div id="home-promos" class="grid grid--auto grid--responsive"></div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-header"><h2>Sucursales cercanas</h2></div>
        <div id="home-sucursales" class="grid grid--auto grid--responsive"></div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-header"><h2>Top sellers</h2></div>
        <div id="home-top" class="grid grid--auto grid--responsive"></div>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
</body>
</html>

