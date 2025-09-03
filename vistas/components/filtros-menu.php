<section class="section">
  <div class="container">
    <div class="grid" style="grid-template-columns: 1fr auto auto; gap: .75rem; align-items: center;">
      <div class="field">
        <label for="buscar" class="sr-only">Buscar</label>
        <input id="buscar" class="input" type="search" placeholder="Buscar platillos..." autocomplete="off">
      </div>
      <div>
        <label for="ordenar" class="sr-only">Ordenar</label>
        <select id="ordenar" class="select">
          <option value="relevancia">Ordenar por: Relevancia</option>
          <option value="precio-asc">Precio: menor a mayor</option>
          <option value="precio-desc">Precio: mayor a menor</option>
        </select>
      </div>
      <div id="chips-filtros" class="flex gap-2" aria-label="Filtros">
        <!-- Categorías reales desde DB: catalogo_categorias -->
        <button type="button" class="chip" data-cat-id="8">Rollo natural</button>
        <button type="button" class="chip" data-cat-id="9">Rollo empanizado</button>
        <button type="button" class="chip" data-cat-id="11">Rollo Premium</button>
        <button type="button" class="chip" data-cat-id="10">Entradas</button>
        <button type="button" class="chip" data-cat-id="3">Platillos</button>
        <button type="button" class="chip" data-cat-id="4">Sopas</button>
        <button type="button" class="chip" data-cat-id="5">Arroz</button>
      </div>
    </div>
  </div>
</section>
