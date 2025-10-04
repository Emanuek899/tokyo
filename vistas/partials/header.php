<header class="site-header navbar navbar-light">
  <div class="container py-2 flex items-center">
    <a class="brand navbar-brand" href="index.php" aria-label="Ir a inicio">
      <span class="brand__logo" aria-hidden="true"></span>
      <span>Tokyo Sushi</span>
    </a>
    <nav class="header__spacer"></nav>
    <nav class="navbar-nav flex-row" aria-label="Principal">
      <a class="nav-link" href="index.php" <?= (basename($_SERVER['PHP_SELF']) === 'index.php' ? 'aria-current="page"' : '') ?>>Home</a>
      <a class="nav-link" href="menu.php" <?= (basename($_SERVER['PHP_SELF']) === 'menu.php' ? 'aria-current="page"' : '') ?>>Menú</a>
      <a class="nav-link" href="promociones.php" <?= (basename($_SERVER['PHP_SELF']) === 'promociones.php' ? 'aria-current="page"' : '') ?>>Promos</a>
      <a class="nav-link" href="sucursales.php" <?= (basename($_SERVER['PHP_SELF']) === 'sucursales.php' ? 'aria-current="page"' : '') ?>>Sucursales</a>
      <a class="nav-link" href="carrito.php" <?= (basename($_SERVER['PHP_SELF']) === 'carrito.php' ? 'aria-current="page"' : '') ?>>Carrito</a>
      <a class="nav-link" href="factura_tu_ticket.php" <?= (basename($_SERVER['PHP_SELF']) === 'factura_tu_ticket.php' ? 'aria-current="page"' : '') ?>>Facturacion</a>
    </nav>
    <div class="px-3 flex items-center gap-2">
      <label for="city-select" class="sr-only">Sede</label>
      <select id="city-select" class="select city-select" title="Sede">
        <option value="">Cargando sedes...</option>
      </select>
      <small id="sede-current" class="text-muted"></small>
    </div>

    <!-- mobile -->
    <nav>
      <input type="checkbox" id="menu-toogle">
      <label for="menu-toggle" class="menu-icon">
        <span></span>
      </label>
      <ul class="menu">
        <li>
          <a href="#">INICIO</a>
        </li>
        <li>
          <a href="#">CUENTAS</a>
        </li>
        <li>
          <a href="#">TARJETAS</a>
        </li>
        <li>
          <a href="#">PRÉSTAMOS</a>
        </li>
        <li>
          <a href="#">CONTACTO</a>
        </li>
      </ul>
    </nav>
    
  </div>
</header>