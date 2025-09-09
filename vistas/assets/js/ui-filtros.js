// ui-filtros.js — UI de ejemplo para renderizar datos y filtros

(function(){
  const $ = (s, c=document) => c.querySelector(s);
  const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));

  function resolveImage(src){
    const baseUpload = 'upload/productos/';
    if(!src || typeof src !== 'string' || !src.trim()) return 'assets/img/placeholder.svg';
    if(/^https?:\/\//i.test(src)) return src; // URL absoluta
    if(src.startsWith('assets/')) return src;  // asset local
    if(src.startsWith('upload/productos/')) return src;
    if(src.startsWith('productos/')) return baseUpload + src.replace(/^productos\//,'');
    if(src.startsWith('upload/')) return baseUpload + src.replace(/^upload\//,'');
    if(src.startsWith('/')) return src; // ruta absoluta provista por el servidor
    return baseUpload + src; // nombre simple
  }

  function el(html){
    const t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  }

/**
 * Funcion para generar cards de producto
 * @param {} p 
 * @returns 
 */
  function productCard(p){
    const tags = (p.tags||[]).map(t => `<span class="chip"><span class="chip__dot" style="background:${tagColor(t)}"></span>${t}</span>`).join('');
    const stateBadge = stateToBadge(p.estado);
    const imgSrc = resolveImage(p.imagen);
    return `
    <article class="card card--product flex column" data-name="${(p.nombre||'').toLowerCase()}" data-category="${p.categoria||''}" data-tags="${(p.tags||[]).join(',')}">
      <a class="media" href="platillo.php?slug=${encodeURIComponent(p.slug||'')}" aria-label="Ver ${p.nombre||''}">
        <img src="${imgSrc}" alt="${p.nombre||''}">
      </a>
      <div class="card__body">
        <div class="flex justify-between items-center">
          <h3 class="title">${p.nombre||''}</h3>
          <div class="price">$${Number(p.precio_final||0).toFixed(2)}MX</div>
        </div>
        <div class="badges">${stateBadge}</div>
        <div class="mt-2">${tags}</div>
        <div class="description">${p.descripcion || "sin descripcion"}</div>
      </div>
      <div class="card__footer flex justify-between items-center bottom">
        <span class="text-muted">${p.categoria_nombre||'Producto'}</span>
        <button class="btn custom-btn btn--sm" data-toast="Agregado al carrito">Agregar</button>
      </div>
    </article>`;
  }

  /**
   * Funcion para generar etiquetas de estado de producto
   * Disponible|Agotado|Fuera_horario
   * 
   * @param {} st 
   * @return
   */
  function stateToBadge(st){
    if(st === 'agotado') return '<span class="badge badge--danger">Agotado</span>';
    if(st === 'fuera_horario') return '<span class="badge badge--muted">Fuera de horario</span>';
    return '<span class="badge badge--success">Disponible</span>';
  }

/**
 * Funcion para cambiar color de etiquetas
 * @param {*} tag 
 * @returns 
 */
  function tagColor(tag){
    switch(tag){
      case 'spicy': return '#e65100';
      case 'veg': return '#2e7d32';
      case 'gluten_free': return '#1565c0';
      default: return 'var(--color-accent)';
    }
  }

/**
 * Funcion para generar cards de productos en promocion
 * 
 * @param {*} pr 
 * @returns 
 */
  function promoCard(pr){
    return `
    <article class="card card--promo">
      <div class="card__body">
        <h3 class="title">${pr.nombre}</h3>
        <p class="rule text-muted">${pr.regla} · <strong>${pr.vigencia}</strong></p>
      </div>
      <div class="card__footer flex justify-between items-center">
        <span class="badge badge--muted">${pr.tipo}</span>
        <button class="btn custom-btn btn--sm" data-toast="Promo aplicada">Aplicar</button>
      </div>
    </article>`;
  }

/**
 * Funcion para generar
 * @param {} s 
 * @returns 
 */
  function branchCard(s){
    const title = s.nombre || s.colonia || 'Sede';
    const ciudad = s.ciudad ? ` · ${s.ciudad}` : '';
    const direccion = s.direccion ? `<p class="text-muted">${s.direccion}</p>` : '';
    const telefono = s.telefono ? `<p class="text-muted">Tel: ${s.telefono}</p>` : '';
    const horario = `<p class="text-muted">Horario: ${s.horario || '—'}</p>`;
    const servicios = (Array.isArray(s.servicios) && s.servicios.length)
      ? `<p class="text-muted">Servicios: ${s.servicios.join(', ')}</p>`
      : '';
    return `
    <article class="card">
      <div class="card__body">
        <h3 class="title">${title}${ciudad}</h3>
        ${direccion}
        ${telefono}
        ${horario}
        ${servicios}
        <div class="mt-2"><button class="btn custom-btn btn--sm" data-toast="Sede elegida">Elegir esta sede</button></div>
      </div>
    </article>`;
  }

  async function initHome(){
    async function loadPromos(){ try { const promos = await API.promos.listar(); $('#home-promos').innerHTML = promos.slice(0,3).map(promoCard).join(''); } catch(e) { console.error(e); } }
    async function loadSucursales(){ try { const sedeId = localStorage.getItem('selectedSedeId') || ''; const sucs = await API.sucursales.listar(sedeId? { sede_id: sedeId } : {}); $('#home-sucursales').innerHTML = sucs.slice(0,3).map(branchCard).join(''); } catch(e) { console.error(e); } }
    async function loadTop(){ try { const top = await API.menu.top(); $('#home-top').innerHTML = top.slice(0,4).map(productCard).join(''); } catch(e) { console.error(e); } }
    await Promise.all([loadPromos(), loadSucursales(), loadTop()]);
    window.addEventListener('sede:changed', async () => { await loadSucursales(); await loadTop(); });
  }

  let __TOKYO_MENU_BOUND = false;
  let __TOKYO_MENU_DEBOUNCE;
  async function initMenu(){
    if (__TOKYO_MENU_BOUND) return; __TOKYO_MENU_BOUND = true;
    const grid = $('#grid-productos');
    const q = $('#buscar');
    const chips = $$('#chips-filtros .chip');
    const ordenar = $('#ordenar');
    const render = (items) => { grid.innerHTML = (items||[]).map(productCard).join(''); };

    function normalizeOrden(by, term){
      let v = (by||'').trim();
      if (v === 'precio-asc') v = 'precio_asc';
      if (v === 'precio-desc') v = 'precio_desc';
      if (v === 'relevancia' && !term) v = 'nombre';
      if (!v) v = 'nombre';
      return v;
    }

    //Filtro de categorias
    function applyClientFilters(items, { term, catId, ordenar }){
      const t = (term||'').toLowerCase();
      let list = Array.isArray(items) ? items.slice() : [];
      if (t) {
        list = list.filter(it => {
          const n = String(it.nombre||'').toLowerCase();
          const d = String(it.descripcion||'').toLowerCase();
          const c = String(it.categoria||'').toLowerCase();
          return n.includes(t) || d.includes(t) || c.includes(t);
        });
      }
      if (catId) {
        const idn = parseInt(catId,10)||0;
        list = list.filter(it => {
          const cid = ('categoria_id' in it) ? parseInt(it.categoria_id,10)||0 : null;
          return cid ? (cid === idn) : true; // si no hay campo, no filtrar
        });
      }
      const by = ordenar;
      if (by === 'precio_asc' || by === 'precio_desc'){
        list.sort((a,b) => (Number(a.precio_final ?? a.precio ?? 0) - Number(b.precio_final ?? b.precio ?? 0)) * (by==='precio_asc'?1:-1));
      } else if (by === 'relevancia' && t){
        list.sort((a,b) => {
          const an = String(a.nombre||'').toLowerCase();
          const bn = String(b.nombre||'').toLowerCase();
          const ap = an.startsWith(t) ? 0 : 1;
          const bp = bn.startsWith(t) ? 0 : 1;
          if (ap !== bp) return ap - bp;
          return an.localeCompare(bn);
        });
      } else { // nombre por defecto
        list.sort((a,b) => String(a.nombre||'').localeCompare(String(b.nombre||'')));
      }
      return list;
    }
    async function load(){
      const term = (q?.value || '').trim();
      const catIds = chips.filter(c => c.getAttribute('data-active') === 'true').map(c => c.getAttribute('data-cat-id'));
      const byRaw = (ordenar?.value || 'nombre').trim();
      const byParam = normalizeOrden(byRaw, term);
      const params = { search: term, ordenar: byParam };
      if (catIds.length) params.categoria_id = catIds[0];
      try {
        const data = await (window.apiGet ? window.apiGet('/menu/listar.php', params) : API.menu.listar(params));
        const items = data?.items ?? data ?? [];
        const out = applyClientFilters(items, { term, catId: params.categoria_id, ordenar: byParam });
        render(out);
      } catch(e){ console.error(e); }
    }
    load();
    if (q) q.addEventListener('input', ()=>{ clearTimeout(__TOKYO_MENU_DEBOUNCE); __TOKYO_MENU_DEBOUNCE = setTimeout(()=> load(), 250); });
    chips.forEach(ch => ch.addEventListener('click', ()=>{ ch.setAttribute('data-active', ch.getAttribute('data-active') === 'true' ? 'false' : 'true'); load(); }));
    if (ordenar) ordenar.addEventListener('change', ()=> load());
  }

  async function initPromos(){
    try{ const promos = await API.promos.listar(); $('#lista-promos').innerHTML = promos.map(promoCard).join(''); }catch(e){ console.error(e); }
  }

  //!-------------------------------------------------
  function mostrarSucursalesEnMapa(sucursales) {
  map.eachLayer((layer) => {
    if (layer instanceof L.Marker) map.removeLayer(layer);
  });

  sucursales.forEach(sucursal => {
    if (sucursal.latitud && sucursal.longitud) {
      L.marker([sucursal.latitud, sucursal.longitud])
        .addTo(map)
        .bindPopup(sucursal.nombre);
    }
  });
  const group = L.featureGroup(
    sucursales.map(s => L.marker([s.latitud, s.longitud]))
  );
  if (sucursales.length > 0) map.fitBounds(group.getBounds().pad(0.2));
}
//!--------------------------------------------------------------------

  async function initSucursales(){
    try{
      const sedeId = localStorage.getItem('selectedSedeId') || '';
      const data = await API.sucursales.listar(sedeId? { sede_id: sedeId } : {});
      $('#lista-sucursales').innerHTML = data.map(branchCard).join('');
      mostrarSucursalesEnMapa(data);
      const name = localStorage.getItem('selectedSedeName') || '';
      $('#mapa').textContent = 'Mapa placeholder '+(name? `(Sede: ${name})` : '(todas las sedes)');
      window.addEventListener('sede:changed', async (ev) => {
        const id = ev.detail?.id || '';
        const list = await API.sucursales.listar(id? { sede_id: id } : {});
        $('#lista-sucursales').innerHTML = list.map(branchCard).join('');
        mostrarSucursalesEnMapa(list);
        const nm = ev.detail?.name || '';
        $('#mapa').textContent = 'Mapa placeholder '+(nm? `(Sede: ${nm})` : '(todas las sedes)');
      });
    }catch(e){ console.error(e); }
  }

  async function initPlatillo(){ /* sin lógica adicional */ }

  async function initCarrito(){
    const table = document.getElementById('tabla-carrito');
    if(!table) return;
    const resumen = document.getElementById('resumen');
    const $sumSubtotal = resumen ? resumen.querySelector('div:nth-of-type(1) strong, #sum-subtotal') : null;
    const $sumEnvio = resumen ? resumen.querySelector('div:nth-of-type(2) strong, #sum-envio') : null;
    const $sumTotal = resumen ? resumen.querySelector('div:nth-of-type(3) strong, #sum-total') : null;
    async function recalc(){
      const items = Array.from(table.querySelectorAll('tbody tr')).map(tr => ({ id: parseInt(tr.getAttribute('data-item-id'),10)||0, cantidad: parseInt(tr.querySelector('.qty')?.value||'0',10)||0 }));
      try {
        const resp = await API.carrito.calcular({ items });
        resp.items.forEach(it => {
          const tr = table.querySelector(`tbody tr[data-item-id="${it.id}"]`);
          if(!tr) return;
          const precio = tr.querySelector('td.precio');
          const subtotal = tr.querySelector('td.subtotal');
          if(precio) precio.textContent = `$${Number(it.precio||0).toFixed(2)}`;
          if(subtotal) subtotal.textContent = `$${Number(it.subtotal||0).toFixed(2)}`;
        });
        if($sumSubtotal) $sumSubtotal.textContent = `$${Number(resp.subtotal||0).toFixed(2)}`;
        if($sumEnvio) $sumEnvio.textContent = `$${Number(resp.envio||0).toFixed(2)}`;
        if($sumTotal) $sumTotal.textContent = `$${Number(resp.total||0).toFixed(2)}`;
      } catch(e){ console.error(e); }
    }
    recalc();
    table.addEventListener('input', (e)=>{ if(e.target.matches('.qty')) recalc(); });
    document.getElementById('btn-recalcular')?.addEventListener('click', recalc);
  }
  async function initCheckout(){ /* estático */ }

  async function initFacturacion(){
    const $r = (id) => document.getElementById(id);
    const btn = $r('btn-generar-fact');
    if(!btn) return;
    btn.addEventListener('click', async () => {
      const payload = {
        rfc: $r('fact-rfc')?.value?.trim(),
        razon_social: $r('fact-razon')?.value?.trim(),
        correo: $r('fact-correo')?.value?.trim(),
        telefono: $r('fact-telefono')?.value?.trim(),
        calle: $r('fact-calle')?.value?.trim(),
        numero_ext: $r('fact-numero-ext')?.value?.trim(),
        numero_int: $r('fact-numero-int')?.value?.trim(),
        colonia: $r('fact-colonia')?.value?.trim(),
        municipio: $r('fact-municipio')?.value?.trim(),
        estado: $r('fact-estado')?.value?.trim(),
        pais: $r('fact-pais')?.value?.trim(),
        cp: $r('fact-cp')?.value?.trim(),
        regimen: $r('fact-regimen')?.value?.trim(),
        uso_cfdi: $r('fact-uso')?.value?.trim()
      };
      const ticketId = parseInt($r('fact-ticket-id')?.value||'0',10)||0;
      const out = $r('fact-resumen');
      if(!payload.rfc || !payload.razon_social || !ticketId){ toast('Completa RFC, Razón social y Ticket ID'); return; }
      try {
        const c = await API.facturacion.registrarCliente(payload);
        const clienteId = c?.cliente?.id;
        if(!clienteId) throw new Error('Cliente no generado');
        const fact = await API.facturacion.generar({ ticket_id: ticketId, cliente_id: clienteId });
        const f = fact?.factura; const det = fact?.detalles||[];
        if(!f){ throw new Error('No se obtuvo la factura'); }
        out.innerHTML = `
          <div class="mt-2">
            <p><strong>Factura:</strong> ${f.folio || f.factura_id} · ${f.uuid || ''}</p>
            <p class="text-muted">Ticket #${f.ticket_folio || f.ticket_id} · Total: $${Number(f.total||0).toFixed(2)}</p>
          </div>
          <div class="mt-3">
            <h3>Conceptos</h3>
            <ul>
              ${det.map(d=>`<li>${d.cantidad} × ${d.descripcion} — $${Number(d.importe||0).toFixed(2)}</li>`).join('')}
            </ul>
          </div>`;
        toast('Factura generada');
      } catch(e){ console.error(e); toast('Error al generar factura'); }
    });
  }

  window.UI = {
    initPage: function(page){
      switch(page){
        case 'home': return initHome();
        case 'menu': return initMenu();
        case 'promos': return initPromos();
        case 'sucursales': return initSucursales();
        case 'platillo': return initPlatillo();
        case 'carrito': return initCarrito();
        case 'checkout': return initCheckout();
        case 'facturacion': return initFacturacion();
      }
    }
  };
})();
