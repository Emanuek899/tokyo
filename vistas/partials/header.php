<header class="site-header navbar navbar-light">
  <div class="container py-2 flex items-center">
    <a class="brand navbar-brand" href="index.php" aria-label="Ir a inicio">
      <span class="brand__logo" aria-hidden="true"></span>
      <span>Tokyo Sushi</span>
    </a>
    <nav class="header__spacer"></nav>
    <nav class="navbar-desk navbar-nav flex-row" aria-label="Principal">
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
<div class="nav-mobile mobile-menu md:hidden">
  <button id="menu-toggle" class="p-2 rounded-md border border-gray-300 focus:outline-none" aria-label="Abrir menú">
    <!-- Icono hamburguesa -->
    <svg id="icon-open" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"
      viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" 
        d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
    <!-- Icono cerrar -->
    <svg id="icon-close" class="w-6 h-6 hidden" fill="none" stroke="currentColor" stroke-width="2"
      viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" 
        d="M6 18L18 6M6 6l12 12"/>
    </svg>
  </button>

  <div id="menu" class="nav-mobile  absolute right-2 top-14 bg-white shadow-lg rounded-lg hidden flex-col w-48 p-3">
    <a class="block py-2 px-3 hover:bg-gray-100 nav-link" href="index.php">Home</a>
    <a class="block py-2 px-3 hover:bg-gray-100 nav-link" href="menu.php">Menú</a>
    <a class="block py-2 px-3 hover:bg-gray-100 nav-link" href="promociones.php">Promos</a>
    <a class="block py-2 px-3 hover:bg-gray-100 nav-link" href="sucursales.php">Sucursales</a>
    <a class="block py-2 px-3 hover:bg-gray-100 nav-link" href="carrito.php">Carrito</a>
    <a class="block py-2 px-3 hover:bg-gray-100 nav-link" href="factura_tu_ticket.php">Facturación</a>
  </div>
</div>

<script>
  const toggle = document.getElementById("menu-toggle");
  const menu = document.getElementById("menu");
  const openIcon = document.getElementById("icon-open");
  const closeIcon = document.getElementById("icon-close");

  toggle.addEventListener("click", () => {
    menu.classList.toggle("hidden");
    openIcon.classList.toggle("hidden");
    closeIcon.classList.toggle("hidden");
  });
</script>

  </div>
</header>