<?php $pageTitle = 'Tokyo Sushi - Carrito'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
</head>
<body data-page="carrito">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container grid carrito">
        <div>
          <div class="section-header"><h2>Tu carrito</h2></div>
          <div class="card">
            <div class="card__body">
              <div class="mb-3 flex items-center gap-3">
                <input type="text" class="input" placeholder="Código de promoción">
                <button class="btn custom-btn" data-toast="Promo aplicada">Aplicar</button>
              </div>
              <div class="w-full">
                <table aria-describedby="resumen" id="tabla-carrito">
                  <thead>
                    <tr><th>Producto</th><th style="width:140px">Cantidad</th><th>Precio</th><th>Subtotal</th></tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
                <div class="mt-3"><button id="btn-recalcular" class="btn custom-btn btn--sm">Recalcular</button></div>
              </div>
            </div>
          </div>
        </div>
        <aside>
          <div class="card">
            <div class="card__body" id="resumen">
              <h2>Resumen</h2>
              <div class="flex justify-between mt-2"><span>Subtotal</span><strong id="sum-subtotal">$0.00</strong></div>
              <div class="flex justify-between mt-2"><span>Envío</span><strong id="sum-envio">$0.00</strong></div>
              <div class="flex justify-between mt-2"><span>Total</span><strong id="sum-total">$0.00</strong></div>
              <a class="btn custom-btn mt-3 w-full" href="checkout.php" id="btn-checkout">Proceder al pago</a>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
  <script>
    document.addEventListener('DOMContentLoaded', async function () {
  const tabla = document.getElementById('tabla-carrito')?.querySelector('tbody');
  const btnCheckout = document.getElementById('btn-checkout');
  const resumen = document.getElementById('resumen');
  const btnRecalcular = document.getElementById('btn-recalcular');

  // Verificar que los elementos existen
  if (!tabla) {
    console.error('No se encontró la tabla del carrito');
    return;
  }

  if (!btnCheckout) {
    console.error('No se encontró el botón de checkout. Asegúrate de que tenga id="btn-checkout"');
  }

  await cargarCarrito();

  async function cargarCarrito() {
    try {
      // Cargar desde el backend
      const resp = await API.carrito.listar();
      console.log('Respuesta del carrito:', resp);
      
      tabla.innerHTML = '';

      if (!resp.items || resp.items.length === 0) {
        console.log('El carrito está vacío');
        verificarCarritoVacio();
        return;
      }

      // Crear filas
      resp.items.forEach((item) => {
        const precio = parseFloat(item.precio || 0);
        const cantidad = parseInt(item.cantidad || 0);
        const itemId = item.id || item.producto_id;
        
        const fila = document.createElement('tr');
        fila.setAttribute('data-item-id', itemId);
        fila.innerHTML = `
          <td>${item.nombre}</td>
          <td>
            <input type="number" min="1" value="${cantidad}" 
                   class="input qty" style="width: 80px;">
          </td>
          <td class="precio">$${precio.toFixed(2)}</td>
          <td class="subtotal">$${(precio * cantidad).toFixed(2)}</td>
          <td>
            <button class="btn btn--danger btn--sm btn-eliminar">
              Eliminar
            </button>
          </td>
        `;
        tabla.appendChild(fila);
      });

      actualizarTotales(resp);
      verificarCarritoVacio();
      agregarEventos();

    } catch(e) {
      console.error('Error al cargar carrito:', e);
      verificarCarritoVacio();
    }
  }

  function agregarEventos() {
    // Eliminar producto
    document.querySelectorAll('.btn-eliminar').forEach(btn => {
      btn.addEventListener('click', async function() {
        const tr = this.closest('tr');
        const itemId = parseInt(tr.getAttribute('data-item-id'), 10);
        
        try {
          await API.carrito.eliminar({ producto_id: itemId });
          await cargarCarrito();
        } catch(e) {
          console.error('Error al eliminar:', e);
        }
      });
    });

    // Cambiar cantidad
    document.querySelectorAll('.qty').forEach(input => {
      input.addEventListener('change', async function() {
        const tr = this.closest('tr');
        const itemId = parseInt(tr.getAttribute('data-item-id'), 10);
        const nuevaCantidad = parseInt(this.value);
        
        if (nuevaCantidad < 1) {
          this.value = 1;
          return;
        }
        
        try {
          await API.carrito.actualizar({ producto_id: itemId, cantidad: nuevaCantidad });
          await cargarCarrito();
        } catch(e) {
          console.error('Error al actualizar:', e);
        }
      });
    });
  }

  function actualizarTotales(resp) {
    const subtotal = parseFloat(resp.subtotal || 0);
    const envio = parseFloat(resp.envio || 0);
    const total = parseFloat(resp.total || subtotal + envio);

    const sumSubtotal = document.getElementById('sum-subtotal');
    const sumEnvio = document.getElementById('sum-envio');
    const sumTotal = document.getElementById('sum-total');

    if (sumSubtotal) sumSubtotal.textContent = `$${subtotal.toFixed(2)}`;
    if (sumEnvio) sumEnvio.textContent = `$${envio.toFixed(2)}`;
    if (sumTotal) sumTotal.textContent = `$${total.toFixed(2)}`;
  }
  
  function verificarCarritoVacio() {
    const filas = tabla.querySelectorAll('tr');
    const carritoVacio = filas.length === 0;

    console.log('Verificando carrito - Filas:', filas.length, 'Vacío:', carritoVacio);

    // Solo modificar el botón si existe
    if (btnCheckout) {
      if (carritoVacio) {
        btnCheckout.classList.add('btn-disabled');
        btnCheckout.removeAttribute('href');
        btnCheckout.style.cssText = 'pointer-events: none !important; opacity: 0.6 !important; cursor: not-allowed !important;';
      } else {
        btnCheckout.classList.remove('btn-disabled');
        btnCheckout.setAttribute('href', 'checkout.php');
        btnCheckout.style.cssText = 'pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important;';
      }
    }

    // Botón recalcular
    if (btnRecalcular) {
      if (carritoVacio) {
        btnRecalcular.setAttribute('disabled', 'true');
      } else {
        btnRecalcular.removeAttribute('disabled');
      }
    }

    // Mensaje de carrito vacío
    if (resumen) {
      const msgExistente = document.getElementById('msgCarritoVacio');
      
      if (carritoVacio) {
        if (!msgExistente) {
          const msg = document.createElement('div');
          msg.id = 'msgCarritoVacio';
          msg.textContent = 'Tu carrito está vacío. Agrega productos antes de proceder al pago.';
          msg.className = 'alert alert-warning mt-3';
          resumen.appendChild(msg);
        }
      } else {
        if (msgExistente) {
          msgExistente.remove();
        }
      }
    }
  }

  if (btnRecalcular) {
    btnRecalcular.addEventListener('click', async function(e) {
      e.preventDefault();
      await cargarCarrito();
    });
  }
});
  </script>
</body>
</html>

