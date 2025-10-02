<?php $pageTitle = 'Tokyo Sushi - Platillo'; $slug = $_GET['slug'] ?? 'sushi-roll'; $id = isset($_GET['id']) ? (int)$_GET['id'] : 0; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
  <!-- JSON-LD placeholder (Product/Offer) -->
  <!--
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "Nombre del platillo",
    "offers": {"@type":"Offer","price":"129","priceCurrency":"MXN","availability":"https://schema.org/InStock"}
  }
  </script>
  -->
</head>
<body data-page="platillo">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container">
        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 2rem;">
          <div>
            <div id="platillo-galeria" class="grid grid--auto" style="--cols: 3; gap: .5rem;" aria-live="polite"></div>
          </div>
          <div>
            <div class="section-header"><h2 id="platillo-titulo">Detalle de platillo</h2></div>
            <p id="platillo-desc" class="muted"></p>
            <p class="muted">Descripción breve del platillo seleccionado (slug: <?= htmlspecialchars($slug) ?>).</p>
            <div class="mt-3 flex items-center gap-3">
              <strong id="platillo-precio" style="font-size:1.25rem;"></strong>
              <strong style="font-size:1.25rem;">$129.00</strong>
              <?php include __DIR__.'/components/chips-etiquetas.php'; ?>
            </div>
            <div class="mt-3">
              <button class="btn custom-btn" data-toast="Agregado al carrito">Agregar al carrito</button>
            </div>
            <div class="mt-4">
              <h3>Información nutricional</h3>
              <p class="text-muted">Calorías: 450 · Alérgenos: soya, gluten.</p>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="section-header"><h2>Frecuentemente juntos</h2></div>
        <div class="grid grid--auto grid--responsive">
          <?php for($i=0;$i<4;$i++) include __DIR__.'/components/card-producto.php'; ?>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
</body>
</html>


