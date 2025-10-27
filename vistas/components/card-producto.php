<section id="productos-container" class="grid gap-4"></section>

<script type="module">
  // Función para resolver imágenes
  function resolveImage(imagen) {
    if (!imagen) return 'assets/img/placeholder.svg';
    // Si la imagen ya tiene una ruta completa, usarla tal cual
    if (imagen.startsWith('http') || imagen.startsWith('assets/')) {
      return imagen;
    }
    // Si no, agregar el prefijo assets/img/
    return `assets/img/${imagen}`;
  }

  async function cargarProductos() {
    const contenedor = document.getElementById('productos-container');
    contenedor.innerHTML = '<p class="muted">Cargando productos...</p>';

    try {
      // Usar API.menu.listar() si existe, si no, usar fetch directo
      let data;
      if (window.API && API.menu && API.menu.listar) {
        const resp = await API.menu.listar();
        data = resp;
      } else {
        const resp = await fetch('../api/menu/listar.php');
        data = await resp.json();
      }

      if (!data.success || !Array.isArray(data.items)) {
        contenedor.innerHTML = '<p class="muted">Error al cargar productos.</p>';
        return;
      }

      contenedor.innerHTML = '';

      // Mezcla los productos y toma 4 aleatorios
      const productosAleatorios = data.items
        .sort(() => Math.random() - 0.5)
        .slice(0, 4);

      productosAleatorios.forEach(p => {
        const estadoBadge =
          p.estado === 'agotado' ? '<span class="badge badge--danger">Agotado</span>' :
          p.estado === 'fuera_horario' ? '<span class="badge badge--muted">Fuera de horario</span>' :
          '<span class="badge badge--success">Disponible</span>';

        // Usar resolveImage igual que en initPlatillo
        const imgSrc = resolveImage(p.imagen);
        const precio = Number(p.precio_final ?? p.precio ?? 0);

        const html = `
          <article id="producto-${p.id}" class="card card--product state--${p.estado}">
            <a class="media" href="platillo.php?id=${p.id}" aria-label="Ver ${p.nombre}">
              <img src="${imgSrc}" alt="${p.nombre || 'Producto'}">
            </a>
            <div class="card__body">
              <div class="flex justify-between items-center">
                <h3 class="title">${p.nombre || 'Producto'}</h3>
                <div class="price">$${precio.toFixed(2)}</div>
              </div>
              <div class="badges">${estadoBadge}</div>
              <div class="mt-2">
                ${(p.tags ?? []).map(t => `<span class="chip"><span class="chip__dot"></span>${t}</span>`).join('')}
              </div>
            </div>
            <div class="card__footer flex justify-between items-center">
              <span class="text-muted">Producto</span>
              <button class="btn custom-btn btn--sm btn-add-cart" data-id="${p.id}">Agregar</button>
            </div>
          </article>
        `;
        
        contenedor.insertAdjacentHTML('beforeend', html);
      });

      // Event listener para botones (similar a initPlatillo)
      contenedor.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('.btn-add-cart');
        if (!btn || btn.disabled) return;
        
        btn.disabled = true;
        const productoId = parseInt(btn.dataset.id, 10);
        
        try {
          if (window.API && API.carrito && API.carrito.agregar) {
            await API.carrito.agregar({ producto_id: productoId, cantidad: 1 });
            
          } else {
            console.log('Producto agregado (simulación):', productoId);
            toast('✅ Agregado al carrito');
          }
        } catch (err) {
          console.error(err);
          toast('❌ No se pudo agregar');
        } finally {
          setTimeout(() => { btn.disabled = false; }, 500);
        }
      }, { once: true });

    } catch (err) {
      console.error(err);
      contenedor.innerHTML = '<p class="muted">Error al conectar con el servidor.</p>';
    }
  }

  document.addEventListener('DOMContentLoaded', cargarProductos);
</script>

<style>
#productos-container {
  display: grid;
  grid-template-columns: repeat(4, 350px);
  gap: 1rem;
  width: 100%;
}

#productos-container .card {
  min-width: 0; /* Previene overflow */
}

@media (max-width: 1200px) {
  #productos-container {
    grid-template-columns: repeat(4, 340px);
  }
}

@media (max-width: 768px) {
  #productos-container {
    grid-template-columns: repeat(4, 200px);
  }
}

@media (max-width: 480px) {
  #productos-container {
    grid-template-columns: 1fr;
  }
}
</style>