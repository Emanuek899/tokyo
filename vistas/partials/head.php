<?php
  // Basic head partial: meta, title, CSS links
  $pageTitle = $pageTitle ?? 'Tokyo Sushi';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#DA9F5B">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/utilities.css">
<!-- Estilos adicionales provistos en raíz para adaptar al sitio -->
<link rel="stylesheet" href="assets/css/style1.css">

