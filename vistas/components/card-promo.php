<?php
// Card de promoción
$nombre = $nombre ?? '2x1 Sushi Rolls';
$tipo = $tipo ?? '2x1';
$regla = $regla ?? 'Martes 5–8 pm';
$vigencia = $vigencia ?? 'Hasta 30/09';
?>
<article class="card card--promo">
  <div class="card__body">
    <h3 class="title"><?= htmlspecialchars($nombre) ?></h3>
    <p class="rule text-muted"><?= htmlspecialchars($regla) ?> · <strong><?= htmlspecialchars($vigencia) ?></strong></p>
  </div>
  <div class="card__footer flex justify-between items-center">
    <span class="badge badge--muted"><?= htmlspecialchars($tipo) ?></span>
    <button class="btn custom-btn btn--sm" data-toast="Promo aplicada">Aplicar</button>
  </div>
</article>

