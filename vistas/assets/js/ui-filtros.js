// ui-filtros.js — UI de ejemplo para renderizar datos y filtros

(function () {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

  function resolveImage(src) {
    const baseUpload = "upload/productos/";
    if (!src || typeof src !== "string" || !src.trim())
      return "assets/img/placeholder.svg";
    if (/^https?:\/\//i.test(src)) return src; // URL absoluta
    if (src.startsWith("assets/")) return src; // asset local
    if (src.startsWith("upload/productos/")) return src;
    if (src.startsWith("productos/"))
      return baseUpload + src.replace(/^productos\//, "");
    if (src.startsWith("upload/"))
      return baseUpload + src.replace(/^upload\//, "");
    if (src.startsWith("/")) return src; // ruta absoluta provista por el servidor
    return baseUpload + src; // nombre simple
  }

  function el(html) {
    const t = document.createElement("template");
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  
  }

  /**
   * Funcion para generar cards de producto
   * @param {} p
   * @returns
   */
  function productCard(p) {
    const tags = (p.tags || [])
      .map(
        (t) =>
          `<span class="chip"><span class="chip__dot" style="background:${tagColor(
            t
          )}"></span>${t}</span>`
      )
      .join("");
    const stateBadge = stateToBadge(p.estado);
    const imgSrc = resolveImage(p.imagen);
    return `
    <article class="card card--product flex column" data-name="${(
      p.nombre || ""
    ).toLowerCase()}" data-category="${p.categoria || ""}" data-tags="${(
      p.tags || []
    ).join(",")}">
      <a class="media" href="platillo.php?slug=${encodeURIComponent(
        p.slug || ""
      )}" aria-label="Ver ${p.nombre || ""}">
        <img src="${imgSrc}" alt="${p.nombre || ""}">
      </a>
      <div class="card__body">
        <div class="flex justify-between items-center">
          <h3 class="title">${p.nombre || ""}</h3>
          <div class="price">$${Number(p.precio_final || 0).toFixed(2)}MX</div>
        </div>
        <div class="badges">${stateBadge}</div>
        <div class="mt-2">${tags}</div>
        <div class="description text-center">${p.descripcion || "sin descripcion"}</div>
      </div>
      <div class="card__footer flex justify-between items-center bottom">
        <span class="text-muted">${p.categoria_nombre || "Producto"}</span>
        <button class="btn custom-btn btn--sm btn-add-cart" data-id="${p.id}">Agregar</button>
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
  function stateToBadge(st) {
    if (st === "agotado")
      return '<span class="badge badge--danger">Agotado</span>';
    if (st === "fuera_horario")
      return '<span class="badge badge--muted">Fuera de horario</span>';
    return '<span class="badge badge--success">Disponible</span>';
  }

  /**
   * Funcion para cambiar color de etiquetas
   * @param {*} tag
   * @returns
   */
  function tagColor(tag) {
    switch (tag) {
      case "spicy":
        return "#e65100";
      case "veg":
        return "#2e7d32";
      case "gluten_free":
        return "#1565c0";
      default:
        return "var(--color-accent)";
    }
  }

  /**
   * Funcion para generar cards de productos en promocion
   *
   * @param {*} pr
   * @returns
   */
  function promoCard(pr) {
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
  function branchCard(s) {
    const title = s.nombre || s.colonia || "Sede";
    const ciudad = s.ciudad ? ` · ${s.ciudad}` : "";
    const direccion = s.direccion
      ? `<p class="text-muted">${s.direccion}</p>`
      : "";
    const telefono = s.telefono
      ? `<p class="text-muted">Tel: ${s.telefono}</p>`
      : "";
    const horario = `<p class="text-muted">Horario: ${s.horario || "—"}</p>`;
    const servicios =
      Array.isArray(s.servicios) && s.servicios.length
        ? `<p class="text-muted">Servicios: ${s.servicios.join(", ")}</p>`
        : "";
    return `
    <article class="card" data-sede-id="${s.id}">
      <div class="card__body">
        <h3 class="title">${title}${ciudad}</h3>
        ${direccion}
        ${telefono}
        ${horario}
        ${servicios}
        <div class="mt-2"><button class="btn custom-btn btn--sm" data-sedelect="Sede elegida">Elegir esta sede</button></div>
      </div>
    </article>`;
  }

  async function initHome() {
    async function loadPromos() {
      try {
        const promos = await API.promos.listar();
        $("#home-promos").innerHTML = promos
          .slice(0, 3)
          .map(promoCard)
          .join("");
      } catch (e) {
        console.error(e);
      }
    }
    async function loadSucursales() {
      try {
        const sedeId = localStorage.getItem("selectedSedeId") || "";
        const sucs = await API.sucursales.listar(
          sedeId ? { sede_id: sedeId } : {}
        );
        $("#home-sucursales").innerHTML = sucs
          .slice(0, 3)
          .map(branchCard)
          .join("");
      } catch (e) {
        console.error(e);
      }
    }
    async function loadTop() {
      try {
        const top = await API.menu.top();
        $("#home-top").innerHTML = top.slice(0, 4).map(productCard).join("");
      } catch (e) {
        console.error(e);
      }
    }
    await Promise.all([loadPromos(), loadSucursales(), loadTop()]);
    window.addEventListener("sede:changed", async () => {
      await loadSucursales();
      await loadTop();
      await loadPromos();
    });
  }

  let __TOKYO_MENU_BOUND = false;
  let __TOKYO_MENU_DEBOUNCE;
  async function initMenu() {
    if (__TOKYO_MENU_BOUND) return;
    __TOKYO_MENU_BOUND = true;
    const grid = $("#grid-productos");
    const q = $("#buscar");
    const chips = $$("#chips-filtros .chip");
    const ordenar = $("#ordenar");
    const render = (items) => {
      grid.innerHTML = (items || []).map(productCard).join("");
    
    };
    

    function normalizeOrden(by, term) {
      let v = (by || "").trim();
      if (v === "precio-asc") v = "precio_asc";
      if (v === "precio-desc") v = "precio_desc";
      if (v === "relevancia" && !term) v = "nombre";
      if (!v) v = "nombre";
      return v;
    }

    //Filtro de categorias
    function applyClientFilters(items, { term, catId, ordenar }) {
      const t = (term || "").toLowerCase();
      let list = Array.isArray(items) ? items.slice() : [];
      if (t) {
        list = list.filter((it) => {
          const n = String(it.nombre || "").toLowerCase();
          const d = String(it.descripcion || "").toLowerCase();
          const c = String(it.categoria || "").toLowerCase();
          return n.includes(t) || d.includes(t) || c.includes(t);
        });
      }
      if (catId) {
        const idn = parseInt(catId, 10) || 0;
        list = list.filter((it) => {
          const cid =
            "categoria_id" in it ? parseInt(it.categoria_id, 10) || 0 : null;
          return cid ? cid === idn : true; // si no hay campo, no filtrar
        });
      }
      const by = ordenar;
      if (by === "precio_asc" || by === "precio_desc") {
        list.sort(
          (a, b) =>
            (Number(a.precio_final ?? a.precio ?? 0) -
              Number(b.precio_final ?? b.precio ?? 0)) *
            (by === "precio_asc" ? 1 : -1)
        );
      } else if (by === "relevancia" && t) {
        list.sort((a, b) => {
          const an = String(a.nombre || "").toLowerCase();
          const bn = String(b.nombre || "").toLowerCase();
          const ap = an.startsWith(t) ? 0 : 1;
          const bp = bn.startsWith(t) ? 0 : 1;
          if (ap !== bp) return ap - bp;
          return an.localeCompare(bn);
        });
      } else {
        // nombre por defecto
        list.sort((a, b) =>
          String(a.nombre || "").localeCompare(String(b.nombre || ""))
        );
      }
      return list;
    }
    async function load() {
      const term = (q?.value || "").trim();
      const catIds = chips
        .filter((c) => c.getAttribute("data-active") === "true")
        .map((c) => c.getAttribute("data-cat-id"));
      const byRaw = (ordenar?.value || "nombre").trim();
      const byParam = normalizeOrden(byRaw, term);
      const params = { search: term, ordenar: byParam };
      if (catIds.length) params.categoria_id = catIds[0];
      try {
        const data = await (window.apiGet
          ? window.apiGet("/menu/listar.php", params)
          : API.menu.listar(params));
        const items = data?.items ?? data ?? [];
        const out = applyClientFilters(items, {
          term,
          catId: params.categoria_id,
          ordenar: byParam,
        });
        render(out);
      } catch (e) {
        console.error(e);
      }
    }
    load();
    if (q)
      q.addEventListener("input", () => {
        clearTimeout(__TOKYO_MENU_DEBOUNCE);
        __TOKYO_MENU_DEBOUNCE = setTimeout(() => load(), 250);
      });
    chips.forEach((ch) =>
      ch.addEventListener("click", () => {
        ch.setAttribute(
          "data-active",
          ch.getAttribute("data-active") === "true" ? "false" : "true"
        );
        load();
      })
    );
    if (ordenar) ordenar.addEventListener("change", () => load());
  }

  async function initPromos() {
    try {
      const promos = await API.promos.listar();
      $("#lista-promos").innerHTML = promos.map(promoCard).join("");
    } catch (e) {
      console.error(e);
    }
  }

  async function initSucursales() {
    try {
      const sedeId = localStorage.getItem("selectedSedeId") || "";
      const data = await API.sucursales.listar({sedeId}); 
      console.log(data);
      $("#lista-sucursales").innerHTML = data.map(branchCard).join("");
      mostrarSucursalesEnMapa(data);
      const name = localStorage.getItem("selectedSedeName") || "";
      $("#mapa").textContent =
        "Mapa placeholder " + (name ? `(Sede: ${name})` : "(todas las sedes)");
      window.addEventListener("sede:changed", async (ev) => {
        const id = ev.detail?.id || "";
        const list = await API.sucursales.listar(id ? { sede_id: id } : {});
        $("#lista-sucursales").innerHTML = list.map(branchCard).join("");
        mostrarSucursalesEnMapa(list);
        const nm = ev.detail?.name || "";
        $("#mapa").textContent =
          "Mapa placeholder " + (nm ? `(Sede: ${nm})` : "(todas las sedes)");
      });
    } catch (e) {
      console.error(e);
    }
  }

async function initPlatillo() {
  const container = document.querySelector('main');
  const params = new URLSearchParams(window.location.search);
  const slug = decodeURIComponent(params.get('slug') || '');
  console.log('Nombre Slug(platillo):', slug);

  try {
    const { items } = await API.menu.listar();
    console.log('Productos obtenidos:', items);

    const normalizeSlug = (str) =>str.replace(/[^\w-]+/g, ''); 
    const platillo = items.find(p => normalizeSlug(p.nombre) === normalizeSlug(slug));
    console.log('Platillo encontrado:', platillo);
    if (!platillo) {
      container.innerHTML = '<p> <h2>Platillo no encontrado. </h2></p>';
      return;
    }
    document.querySelector('.section-header h2').textContent = platillo.nombre;
    document.querySelector('.muted ').textContent = platillo.descripcion || 'Sin descripción';
    document.querySelector('.price').textContent = `$${Number(platillo.precio_final).toFixed(2)} MX`;
    const imgs = document.querySelectorAll('.grid--auto img');
    if (imgs.length) {
      imgs.forEach((img, idx) => {
        img.src = idx === 0 && platillo.imagen ? `assets/img/${platillo.imagen}` : 'assets/img/placeholder.svg';
        img.alt = platillo.nombre;
      });
    }

  } catch (error) {
    console.error('Error al cargar el platillo:', error);
    container.innerHTML = '<p>Platillo no encontrado.</p>';
  }
}



  


    async function initCarrito(){
    const table = document.getElementById('tabla-carrito');
    if(!table) return;
    const resumen = document.getElementById('resumen');
    const $sumSubtotal = resumen ? (resumen.querySelector('#sum-subtotal') || resumen.querySelector('div:nth-of-type(1) strong')) : null;
    const $sumFee = resumen ? resumen.querySelector('#sum-fee') : null;
    const $sumEnvio = resumen ? (resumen.querySelector('#sum-envio') || resumen.querySelector('div:nth-of-type(3) strong')) : null;
    const $sumTotal = resumen ? (resumen.querySelector('#sum-total') || resumen.querySelector('div:nth-of-type(4) strong')) : null;
    function selectCashTier(subtotal){ const tiers=(window.FeesCfg?.cash?.tiers)||[]; for(const t of tiers){ if(t.threshold==null || subtotal < Number(t.threshold)) return t; } return tiers.length?tiers[tiers.length-1]:null; }
    function grossUp(p,r,f,iva,min){ const denom=1-(1+iva)*r; if(Math.abs(denom)<1e-9) return {total:p,surcharge:0}; let A=(p+(1+iva)*f)/denom; const C1=((A*r)+f)*(1+iva); const Cmin=(min!=null)?(min*(1+iva)):null; if(Cmin!=null && C1<Cmin){ A=p+Cmin; } return { total:A, surcharge:A-p } }
    function computeSurcharge(subtotal, method){ if(!window.PassThroughFees) return { total:subtotal, surcharge:0 }; if(method==='card'){ const f=window.FeesCfg?.card||{rate:0,fixed:0,iva:0,min_fee:null}; return grossUp(subtotal, Number(f.rate||0), Number(f.fixed||0), Number(f.iva||0), f.min_fee!=null?Number(f.min_fee):null); } if(method==='bank_transfer' || method==='spei'){ const f=window.FeesCfg?.spei||{fixed:0,iva:0}; const s=(1+Number(f.iva||0))*Number(f.fixed||0); return { total:subtotal+s, surcharge:s }; } if(method==='cash'){ const cfg=window.FeesCfg?.cash||{iva:0,tiers:[]}; const t=selectCashTier(subtotal)||{rate:0,fixed:0,min_fee:null}; return grossUp(subtotal, Number(t.rate||0), Number(t.fixed||0), Number(cfg.iva||0), t.min_fee!=null?Number(t.min_fee):null); } return { total:subtotal, surcharge:0 }; }
    
    // Intentar poblar desde sesión del backend si existe API de carrito
    async function loadFromSession(){
      try {
        if (!window.API || !API.carrito || !API.carrito.listar) return;
        const resp = await API.carrito.listar();
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        if ((resp.items||[]).length) {
          tbody.innerHTML = resp.items.map(it => `
            <tr data-item-id="${it.id}">
              <td>${it.nombre}</td>
              <td>
                <input class="input qty" type="number" min="0" value="${it.cantidad}">
                <button class="btn btn--sm btn-link btn-del" title="Eliminar">Eliminar</button>
              </td>
              <td class="precio">$${Number(it.precio||0).toFixed(2)}</td>
              <td class="subtotal">$${Number(it.subtotal||0).toFixed(2)}</td>
            </tr>`).join('');
          const subtotal = Number(resp.subtotal||0);
          const envio = Number(resp.envio||0);
          const calc = computeSurcharge(subtotal, 'card');
          if($sumSubtotal) $sumSubtotal.textContent = `$${subtotal.toFixed(2)}`;
          if($sumFee) $sumFee.textContent = `$${Number(calc.surcharge||0).toFixed(2)}`;
          if($sumEnvio) $sumEnvio.textContent = `$${envio.toFixed(2)}`;
          if($sumTotal) $sumTotal.textContent = `$${Number((calc.total||subtotal)+envio).toFixed(2)}`;
        }
      } catch(e){ console.warn('No se pudo cargar carrito de sesión', e); }
    }
    
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
        const subtotal = Number(resp.subtotal||0);
        const envio = Number(resp.envio||0);
        const calc = computeSurcharge(subtotal, 'card');
        if($sumSubtotal) $sumSubtotal.textContent = `$${subtotal.toFixed(2)}`;
        if($sumFee) $sumFee.textContent = `$${Number(calc.surcharge||0).toFixed(2)}`;
        if($sumEnvio) $sumEnvio.textContent = `$${envio.toFixed(2)}`;
        if($sumTotal) $sumTotal.textContent = `$${Number((calc.total||subtotal)+envio).toFixed(2)}`;
      } catch(e){ console.error(e); }
    }
    
    // Inicializar con backend si está disponible
    if (window.API && API.carrito && API.carrito.listar) { loadFromSession(); } else { recalc(); }
    table.addEventListener('input', async (e)=>{
      if(!e.target.matches('.qty')) return;
      const tr = e.target.closest('tr');
      const id = parseInt(tr.getAttribute('data-item-id'),10)||0;
      const cantidad = Math.max(0, parseInt(e.target.value||'0',10)||0);
      if (window.API && API.carrito && API.carrito.actualizar) {
        try { await API.carrito.actualizar({ producto_id: id, cantidad }); await loadFromSession(); } catch(err){ console.error(err); }
      } else {
        recalc();
      }
    });
    table.addEventListener('click', async (e)=>{
      if (!e.target.classList.contains('btn-del')) return;
      const tr = e.target.closest('tr');
      const id = parseInt(tr.getAttribute('data-item-id'),10)||0;
      if (window.API && API.carrito && API.carrito.eliminar) {
        try { await API.carrito.eliminar({ producto_id: id }); await loadFromSession(); } catch(err){ console.error(err); }
      } else {
        tr.remove(); recalc();
      }
    });
    document.getElementById('btn-recalcular')?.addEventListener('click', recalc);
  }
  async function initCheckout() {
    /* estático */
  }

  async function initFacturacion() {
    const $r = (id) => document.getElementById(id);
    const btn = $r("btn-generar-fact");
    if (!btn) return;
    btn.addEventListener("click", async () => {
      const payload = {
        rfc: $r("fact-rfc")?.value?.trim(),
        razon_social: $r("fact-razon")?.value?.trim(),
        correo: $r("fact-correo")?.value?.trim(),
        telefono: $r("fact-telefono")?.value?.trim(),
        calle: $r("fact-calle")?.value?.trim(),
        numero_ext: $r("fact-numero-ext")?.value?.trim(),
        numero_int: $r("fact-numero-int")?.value?.trim(),
        colonia: $r("fact-colonia")?.value?.trim(),
        municipio: $r("fact-municipio")?.value?.trim(),
        estado: $r("fact-estado")?.value?.trim(),
        pais: $r("fact-pais")?.value?.trim(),
        cp: $r("fact-cp")?.value?.trim(),
        regimen: $r("fact-regimen")?.value?.trim(),
        uso_cfdi: $r("fact-uso")?.value?.trim(),
      };
      const ticketId = parseInt($r("fact-ticket-id")?.value || "0", 10) || 0;
      const out = $r("fact-resumen");
      if (!payload.rfc || !payload.razon_social || !ticketId) {
        toast("Completa RFC, Razón social y Ticket ID");
        return;
      }
      try {
        const c = await API.facturacion.registrarCliente(payload);
        const clienteId = c?.cliente?.id;
        if (!clienteId) throw new Error("Cliente no generado");
        const fact = await API.facturacion.generar({
          ticket_id: ticketId,
          cliente_id: clienteId,
        });
        const f = fact?.factura;
        const det = fact?.detalles || [];
        if (!f) {
          throw new Error("No se obtuvo la factura");
        }
        out.innerHTML = `
          <div class="mt-2">
            <p><strong>Factura:</strong> ${f.folio || f.factura_id} · ${
          f.uuid || ""
        }</p>
            <p class="text-muted">Ticket #${
              f.ticket_folio || f.ticket_id
            } · Total: $${Number(f.total || 0).toFixed(2)}</p>
          </div>
          <div class="mt-3">
            <h3>Conceptos</h3>
            <ul>
              ${det
                .map(
                  (d) =>
                    `<li>${d.cantidad} × ${d.descripcion} — $${Number(
                      d.importe || 0
                    ).toFixed(2)}</li>`
                )
                .join("")}
            </ul>
          </div>`;
        toast("Factura generada");
      } catch (e) {
        console.error(e);
        toast("Error al generar factura");
      }
    });
  }

  window.UI = {
    initPage: function (page) {
      switch (page) {
        case "home":
          return initHome();
        case "menu":
          return initMenu();
        case "promos":
          return initPromos();
        case "sucursales":
          return initSucursales();
        case "platillo":
          return initPlatillo();
        case "carrito":
          return initCarrito();
        case "checkout":
          return initCheckout();
        case "facturacion":
          return initFacturacion();
      }
    },
  };
})();

// Integración de carrito (agregar desde menú) y checkout mínimo
(function(){
  // Agregar al carrito desde cards del menú
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-add-cart');
    if (!btn) return;
    const id = parseInt(btn.getAttribute('data-id'), 10) || 0;
    if (!id) return;
    try {
      let abierto = true;
      try {
        const res = await fetch('../api/corte_caja/verificar_corte_abierto.php', { credentials:'same-origin' });
        if (res.ok) { const j = await res.json(); abierto = !!(j && j.resultado && j.resultado.abierto); }
      } catch(e2){}
      if (!abierto) { toast('El establecimiento seleccionado se encuentra fuera del horario de operaciones'); return; }
      await API.carrito.agregar({ producto_id: id, cantidad: 1 });
      toast('Agregado al carrito');
    } catch(err){
      console.error(err);
      const msg = String((err && err.message) || '');
      if (msg.indexOf(' 409') !== -1) toast('El establecimiento seleccionado se encuentra fuera del horario de operaciones');
      else toast('No se pudo agregar');
    }
  });

  document.addEventListener('DOMContentLoaded', async () => {
    const page = document.body.getAttribute('data-page');
    // Checkout: selector de tipo de venta y confirmación (legacy)
    if (page === 'checkout') {
      if (document.getElementById('btn-pagar') || document.getElementById('sum-fee')) {
        return; // Nuevo flujo Conekta ya maneja el resumen y el cargo por plataforma
      }
      const resumen = document.querySelector('aside .card .card__body');
      const btnConfirm = document.querySelector('form.card .btn.custom-btn');
      if (resumen && btnConfirm) {
        try {
          const res = await fetch('../api/corte_caja/verificar_corte_abierto.php', { credentials:'same-origin' });
          if (res.ok) {
            const j = await res.json();
            if (!(j && j.resultado && j.resultado.abierto)) {
              btnConfirm.setAttribute('disabled','disabled');
              btnConfirm.addEventListener('click', (ev)=>{ ev.preventDefault(); toast('El establecimiento seleccionado se encuentra fuera del horario de operaciones'); });
            }
          }
        } catch(e){}
        const form = document.querySelector('form.card');
        const fs = document.createElement('fieldset');
        fs.className = 'mt-3';
        fs.innerHTML = `
          <legend><strong>Tipo de venta</strong></legend>
          <label class="flex items-center gap-2 mt-2"><input type="radio" name="tipo_venta" value="rapido" checked> Rápido</label>
          <label class="flex items-center gap-2 mt-2"><input type="radio" name="tipo_venta" value="mesa"> Mesa</label>
          <div class="field mt-2" id="campo-mesa" style="display:none"><label>No. mesa</label><input id="inp-mesa" class="input" type="number" min="1"></div>
          <label class="flex items-center gap-2 mt-2"><input type="radio" name="tipo_venta" value="domicilio"> Repartidor</label>
          <div class="field mt-2" id="campo-rep" style="display:none"><label>Repartidor (ID)</label><input id="inp-rep" class="input" type="number" min="1"></div>`;
        form.querySelector('.card__body')?.insertBefore(fs, form.querySelector('fieldset'));
        form.addEventListener('change', (ev)=>{
          if (ev.target.name !== 'tipo_venta') return;
          const v = ev.target.value;
          document.getElementById('campo-mesa').style.display = v==='mesa' ? '' : 'none';
          document.getElementById('campo-rep').style.display = v==='domicilio' ? '' : 'none';
        });
        try { const resp = await API.carrito.listar(); if (resumen){ resumen.innerHTML = `<h2>Resumen</h2>
          <div class="flex justify-between mt-2"><span>Subtotal</span><strong id="sum-subtotal">$${Number(resp.subtotal||0).toFixed(2)}</strong></div>
          <div class="flex justify-between mt-2"><span>Envío</span><strong id="sum-envio">$${Number(resp.envio||0).toFixed(2)}</strong></div>
          <div class="flex justify-between mt-2"><span>Total</span><strong id="sum-total">$${Number(resp.total||0).toFixed(2)}</strong></div>`; } } catch(e){ console.error(e); }
        btnConfirm.removeAttribute('disabled'); btnConfirm.title='';
        btnConfirm.addEventListener('click', async ()=>{
          try {
            const tipo = (document.querySelector('input[name="tipo_venta"]:checked')?.value)||'rapido';
            const mesa_id = tipo==='mesa' ? (parseInt(document.getElementById('inp-mesa').value,10)||null) : null;
            const repartidor_id = tipo==='domicilio' ? (parseInt(document.getElementById('inp-rep').value,10)||null) : null;
            await API.checkout.confirmar({ tipo, mesa_id, repartidor_id });
            toast('Pedido confirmado');
            window.location.href = 'carrito.php';
          } catch(err){ console.error(err); toast('No se pudo confirmar el pedido'); }
        });
      }
    }
  });
})();








 document.addEventListener('DOMContentLoaded', function(){
  if(document.body.dataset.page !== 'sucursales') return;

  const contenedor = document.getElementById('mapa');
  if(!contenedor) return;

  const map = L.map(contenedor).setView([24.04195, -104.65779], 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 16
  }).addTo(map);

  const markers = {
    "1": L.marker([24.04195, -104.65779]).addTo(map).bindPopup('Sucursal Forestal'),
    "2": L.marker([23.99704, -104.66227]).addTo(map).bindPopup('Sucursal Domingo Arrieta')
  };

  function centerMap(id){
    if(markers[id]){
      map.setView(markers[id].getLatLng(),14);
      markers[id].openPopup();
    } else {
      const group = L.featureGroup(Object.values(markers));
      map.fitBounds(group.getBounds().pad(0.2));
    }
  }

  window.addEventListener('load', () => map.invalidateSize());
  window.addEventListener('resize', () => map.invalidateSize());

  window.addEventListener("sede:changed", (e) => {
    centerMap(e.detail.id);
  });
  const savedSedeId = localStorage.getItem('selectedSedeId');
  if(savedSedeId) centerMap(savedSedeId);
});