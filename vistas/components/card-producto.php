<section id="productos-container" class="grid gap-4"></section>

<script type="module">
  async function cargarProductos() {
    const contenedor = document.getElementById('productos-container');
    contenedor.innerHTML = '<p class="muted">Cargando productos...</p>';

    try {
      const resp = await fetch('../api/menu/listar.php');
      const data = await resp.json();

      if (!data.success || !Array.isArray(data.items)) {
        contenedor.innerHTML = '<p class="muted">Error al cargar productos.</p>';
        return;
      }

      contenedor.innerHTML = '';

      // Mezcla los productos 
      const productosAleatorios = data.items
        .sort(() => Math.random() - 0.5)
        .slice(0, 4); // 


      productosAleatorios.forEach(p => {
        const estadoBadge =
          p.estado === 'agotado' ? '<span class="badge badge--danger">Agotado</span>' :
          p.estado === 'fuera_horario' ? '<span class="badge badge--muted">Fuera de horario</span>' :
          '<span class="badge badge--success">Disponible</span>';

        const html = `
        <article id="producto-${p.id}" class="card card--product state--${p.estado}">
          <a class="media" href="platillo.php?id=${p.id}" aria-label="Ver ${p.nombre}">
            <img src="assets/img/placeholder.svg" alt="${p.nombre}">
          </a>
          <div class="card__body">
            <div class="flex justify-between items-center">
              <h3 class="title">${p.nombre}</h3>
              <div class="price">$${parseFloat(p.precio_final ?? p.precio ?? 0).toFixed(2)}</div>
            </div>
            <div class="badges">${estadoBadge}</div>
            <div class="mt-2">
              ${(p.tags ?? []).map(t => `<span class="chip"><span class="chip__dot"></span>${t}</span>`).join('')}
            </div>
          </div>
          <div class="card__footer flex justify-between items-center">
            <span class="text-muted">Producto</span>
            <button class="btn custom-btn btn--sm" data-id="${p.id}" data-toast="Agregado al carrito">Agregar</button>
          </div>
        </article>
      `;
        contenedor.insertAdjacentHTML('beforeend', html);
      });

    } catch (err) {
      console.error(err);
      contenedor.innerHTML = '<p class="muted">Error al conectar con el servidor.</p>';
    }
  }

  document.addEventListener('DOMContentLoaded', cargarProductos);
</script>