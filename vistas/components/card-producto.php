<?php
// Card de producto (estructura de referencia); cuando se integre backend, rellenar dinámicamente.
// Variables sugeridas: $nombre, $precio, $slug, $tags, $estado
$nombre = $nombre ?? 'Sushi Roll';
$precio = $precio ?? 129.00;
$slug = $slug ?? 'sushi-roll';
$tags = $tags ?? ['spicy'];
$estado = $estado ?? 'disponible';
?>
<article class="card card--product state--<?= htmlspecialchars($estado) ?>">
  <a class="media" href="platillo.php?slug=<?= urlencode($slug) ?>" aria-label="Ver <?= htmlspecialchars($nombre) ?>">
    <img src="assets/img/placeholder.svg" alt="<?= htmlspecialchars($nombre) ?>">
  </a>
  <div class="card__body">
    <div class="flex justify-between items-center">
      <h3 class="title"><?= htmlspecialchars($nombre) ?></h3>
      <div class="price">$<?= number_format((float)$precio, 2) ?></div>
    </div>
    <div class="badges">
      <?php if($estado==='agotado'): ?><span class="badge badge--danger">Agotado</span><?php endif; ?>
      <?php if($estado==='fuera_horario'): ?><span class="badge badge--muted">Fuera de horario</span><?php endif; ?>
      <?php if($estado==='disponible'): ?><span class="badge badge--success">Disponible</span><?php endif; ?>
    </div>
    <div class="mt-2">
      <?php foreach($tags as $t): ?>
        <span class="chip"><span class="chip__dot"></span><?= htmlspecialchars($t) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card__footer flex justify-between items-center">
    <span class="text-muted">Producto</span>
    <button class="btn custom-btn btn--sm" data-toast="Agregado al carrito">Agregar</button>
  </div>
</article>

