<?php $pageTitle = 'Tokyo Sushi - Sucursales'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
  <?php include __DIR__ . '/partials/head.php'; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
  <script defer src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
</head>

<body data-page="sucursales">
  <?php include __DIR__ . '/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container grid" style="grid-template-columns: 1fr 1.2fr; gap: 2rem;">
        <div>
          <div class="section-header">
            <h2>Sucursales</h2>
          </div>
          <div id="lista-sucursales" class="grid grid--responsive grid--responsive"></div>
        </div>

        <div class="card">
    <div class="card__body">
        <h2 class="mb-2">Mapa</h2>
        <div style="position: relative; height: 420px; width: 100%; display: block; z-index: 1;">
            <div id="mapa" style="height: 100%; width: 100%; position: absolute; top: 0; left: 0; z-index: 1;"></div>
        </div>
    </div>
</div>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>

  <script>
   document.addEventListener('DOMContentLoaded', function () {
  if (document.body.dataset.page !== 'sucursales') return;

  const contenedor = document.getElementById('mapa');
  if (!contenedor) return;

  const tokyoForestal = [24.041959935594893, -104.65779522073265];
  const tokyoDomingoA = [23.99704565241786, -104.66227861447034];
  const map = L.map(contenedor).setView(tokyoForestal, 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 16
  }).addTo(map);

  L.marker(tokyoForestal).addTo(map).bindPopup('Sucursal Forestal');
  L.marker(tokyoDomingoA).addTo(map).bindPopup('Sucursal Domingo Arrieta');

  window.addEventListener('load', () => {
    map.invalidateSize();
  });

  window.addEventListener('resize', () => {
    map.invalidateSize();
  });
});

  </script>
</body>

</html>