# Tokyo Sushi â€” Frontend (MPA)

Este proyecto contiene las vistas y estilos base para un sitio de sushi listo para integrar con un backend PHP + JS Vanilla.

## Mapa de archivos

```
vistas/
  index.php                 # Home (Hero, promos, sucursales, top sellers)
  menu.php                  # Catálogo con búsqueda, chips, ordenar
  platillo.php              # Detalle de platillo
  promociones.php           # Listado de promociones
  sucursales.php            # Lista + mapa placeholder
  carrito.php               # Carrito simple
  checkout.php              # Paso a paso de checkout
  /partials/
    head.php                # <head> (meta + CSS)
    header.php              # Header / nav / selector ciudad
    footer.php              # Footer + scripts
  /components/
    card-producto.php       # Estructura de card de producto
    card-promo.php          # Estructura de card de promo
    filtros-menu.php        # Barra superior de filtros
    chips-etiquetas.php     # Chips de ejemplo
  /assets/
    /css/
      tokens.css            # Design tokens (variables CSS)
      base.css              # Reset + base + grid + componentes
      utilities.css         # Utilidades auxiliares
      /references/          # Extractos de estilos locales usados como referencia
        manifest.txt
        coffee-root-vars.css
        spicyo-snippets.css
    /js/
      app.js                # Inicialización, toasts, helpers, ciudad
      ui-filtros.js         # Render y filtros (mocks)
    /img/
      placeholder.svg       # Imagen de placeholder
  /data/
    menu.sample.json
    sucursales.sample.json
    promos.sample.json
```

## Cambiar paleta / tipografí­as (tokens.css)

- Edita `assets/css/tokens.css` para ajustar variables:
  - Colores: `--color-primary`, `--color-secondary`, `--color-bg`, `--color-text`, `--color-muted`, `--success`, `--warning`, `--danger`.
  - Tipografí­as: `--font-sans`.
  - Redondeos y sombras: `--radius`, `--shadow`.
  - Espaciado: `--spacing-1..6`.

Ejemplo:
```css
:root {
  --color-primary: #E53935; /* rojo */
  --font-sans: "Poppins", system-ui, sans-serif;
}
```

## Agregar nuevas categorí­as/platillos

- Datos de ejemplo en `data/menu.sample.json`:
  - Campos: `id`, `nombre`, `slug`, `precio`, `imagen`, `tags`, `estado` (`disponible|agotado|fuera_horario`), `categoria`.
- Para que aparezcan en el catálogo, el JS los renderiza desde el JSON en `menu.php`.
- Los filtros por chips usan `tags` y `categoria`.

## Integrar APIs reales después

1. Reemplaza las lecturas de `fetch('data/*.json')` en `assets/js/ui-filtros.js` por tus endpoints reales.
2. Mantén la forma de los objetos (keys) para reutilizar el render.
3. Sustituye los includes PHP de componentes con loops que impriman datos reales si prefieres render del lado del servidor.

## Accesibilidad & SEO

- Marcado semántico con `<header> <main> <footer>`.
- Imágenes con `alt` descriptivo.
- Placeholders de JSON-LD comentados en `index.php` (Restaurant) y `platillo.php` (Product/Offer).

## Breakpoints / Grid responsive

- Grid utilitario `.grid.grid--responsive`:
  - â‰¤480px: 1 columna
  - 481â€“768px: 2 columnas
  - â‰¥769px: 4 columnas (ajustable con utilidades `grid-cols-*`)

## Notas de estilos / Homologación

- En `partials/head.php` se cargan `assets/css/tokens.css`, `assets/css/base.css`, `assets/css/utilities.css` y `style2.css` (CSS del sistema). `style2.css` se aplica al final para homogeneizar visuales con el resto del sistema.
- Se consolidaron colores/tipografías desde:
  - `coffee-shop-html-template/css/style.css` (:root con `--primary: #DA9F5B`, `--secondary: #33211D`, `--light: #FFFBF2`, `--dark: #111111`)
  - `spicyo/css/style.css` (acento teal `#38C8A8`, patrones de focus)
  - `bakery/css/style.css` (familias Poppins/Roboto y acentos cálidos)
- Extractos disponibles en `assets/css/references/`.

## Desarrollo local

- No requiere dependencias externas. Usa PHP sólo para `include` de parciales.
- Abre `index.php` en tu entorno (XAMPP) y navega entre vistas.


