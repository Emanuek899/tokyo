// app.js — base initialization, toast, city selector, data helpers

(function(){
  const $ = (sel, ctx=document) => ctx.querySelector(sel);
  const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

  // API helper with AbortController and param normalization
  const API_BASE = '/tokyo/api';
  window.apiGet = async function(path, params = {}){
    const p = { ...params };
    const sede = localStorage.getItem('selectedSedeId');
    if (sede && (p.sede_id === undefined || p.sede_id === null || p.sede_id === '')) p.sede_id = sede;
    const url = new URL(API_BASE + path, window.location.origin);
    Object.entries(p).forEach(([k,v]) => { if(v !== undefined && v !== null && v !== '') url.searchParams.set(k, v); });
    if (window.__apiGetCtl) { try { window.__apiGetCtl.abort(); } catch(e){} }
    window.__apiGetCtl = new AbortController();
    const res = await fetch(url.toString(), { signal: window.__apiGetCtl.signal, credentials: 'same-origin' });
    if (!res.ok) {
      let txt = '';
      try { txt = await res.text(); } catch{}
      throw new Error(`HTTP ${res.status} ${txt}`);
    }
    return res.json();
  }

  // Style1 helpers: sticky navbar + back-to-top
  function ensureBackToTop(){
    let el = document.querySelector('.back-to-top');
    if(!el){
      el = document.createElement('a');
      el.href = '#';
      el.className = 'back-to-top';
      el.innerHTML = '<i>↑</i>';
      document.body.appendChild(el);
    }
    return el;
  }

  function initStyle1UI(){
    const navbar = document.querySelector('.navbar');
    const backTop = ensureBackToTop();
    function onScroll(){
      const y = window.scrollY || document.documentElement.scrollTop || 0;
      if(navbar){ navbar.classList.toggle('nav-sticky', y > 10); }
      if(backTop){ backTop.style.display = y > 100 ? 'block' : 'none'; }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    if(backTop){ backTop.addEventListener('click', (e)=>{ e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); }); }
  }

  function ensureToastContainer(){
    let el = $('.toast-container');
    if(!el){
      el = document.createElement('div');
      el.className = 'toast-container';
      document.body.appendChild(el);
    }
    return el;
  }

  window.toast = function(msg, opts={}){
    const c = ensureToastContainer();
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(()=>{
      t.style.transition = 'opacity .3s ease, transform .3s ease';
      t.style.opacity = '0';
      t.style.transform = 'translateY(6px)';
      setTimeout(()=>t.remove(), 350);
    }, opts.duration || 2200);
  }

  // City selection persistence
  async function initSedeSelector(){
    const sel = $('#city-select');
    const lbl = $('#sede-current');
    if(!sel) return;
    try {
      const sedes = await API.sucursales.listar({});
      sel.innerHTML = '';
      const optAll = document.createElement('option'); optAll.value=''; optAll.textContent='Todas las sedes'; sel.appendChild(optAll);
      sedes.forEach(s => { const o=document.createElement('option'); o.value=String(s.id); o.textContent=s.colonia || (`${s.colonia}`); sel.appendChild(o); });
      const savedId = localStorage.getItem('selectedSedeId') || '';
      if(savedId && Array.from(sel.options).some(o=>o.value===savedId)) sel.value = savedId; else sel.value='';
      updateSedeLabel();
      sel.addEventListener('change', () => {
        const id = sel.value || '';
        const name = id ? (sel.selectedOptions[0]?.textContent || '') : '';
        localStorage.setItem('selectedSedeId', id);
        localStorage.setItem('selectedSedeName', name);
        updateSedeLabel();
        window.dispatchEvent(new CustomEvent('sede:changed', { detail: { id, name } }));
        toast(name ? (`Sede: ${name}`) : 'Todas las sedes');
      });
    } catch(e) { console.error(e); }

    function updateSedeLabel(){
      const name = localStorage.getItem('selectedSedeName') || '';
      if(lbl) lbl.textContent = name ? ('Sede: '+name) : '';
    }
  }

  // Global click handlers for mock actions( se puede usar para la elecion de productos o promos)
  function initMockActions(){
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-toast]');// la mayor parte de las cards tiene en btn "data-toast" 
      if(btn){ toast(btn.getAttribute('data-toast')); }
    });
  }

  function initCardAction (){
    const sel = $('#city-select');
    const lBl = document.querySelector("#sede-current");
    document.addEventListener("click",(e) =>{
      const btn = e.target.closest("[data-sedelect]");
      if(btn){
      const card = btn.closest(".card");
      if (!card) return;

      const sedeId = card.getAttribute("data-sede-id");// en branchCard ui-filtros
      const sedeName = card.querySelector(".title")?.textContent.trim() || "";
      localStorage.setItem("selectedSedeId", sedeId);
      localStorage.setItem("selectedSedeName", sedeName);
      updateSedeLabel();
      const sedeSelected = document.getElementById('city-select');
      if(sedeSelected){
        sedeSelected.value =sedeId;
      }

      const listaSucursales = document.getElementById('lista-sucursales');
      if(listaSucursales){
        listaSucursales.querySelectorAll(".card").forEach(c=>{
          c.classList.toggle('block',c!==card);//o puede ser 'hidden' para que desaparezca al momento'
        });
      }
      sel.addEventListener('change', () => {
        const id = sel.value || '';
        const name = id ? (sel.selectedOptions[0]?.textContent || '') : '';
        localStorage.setItem('selectedSedeId', id);
        localStorage.setItem('selectedSedeName', name);
        updateSedeLabel();
        window.dispatchEvent(new CustomEvent('sede:changed', { detail: { id, name } }));
        toast(name ? (`Sede: ${name}`) : 'Todas las sedes');
      });


      window.dispatchEvent(new CustomEvent("sede:changed",{detail:{id: sedeId, name: sedeName}}));
      toast(`${btn.getAttribute("data-sedelect")}: ${sedeName}`); 
    }
  });

    function updateSedeLabel(){
      const n = localStorage.getItem("selectedSedeName") || "";
      if(lBl) lBl.textContent=n? `Sede: ${n}`: "";
    }
  }



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








  document.addEventListener('DOMContentLoaded', () => {
    if (window.__TOKYO_BOOTED) return;
    window.__TOKYO_BOOTED = true;
    // Adapt site UI to style1 behaviors
    initStyle1UI();
    initSedeSelector();
    initCardAction();
    initMockActions();
    

    // Page-specific initialization (see ui-filtros.js)
    const page = document.body.getAttribute('data-page');
    if(window.UI && typeof window.UI.initPage === 'function'){
      window.UI.initPage(page);
    }
  });
})();

// API minimal: JS solo transaccional (consultas), sin cálculos
(function(){
  // Detect base prefix (e.g., "/tokyo") to build absolute API URLs
  function detectBasePrefix(){
    const parts = window.location.pathname.split('/').filter(Boolean);
    if (!parts.length) return '';
    const idx = parts.indexOf('tokyo');
    if (idx >= 0) return '/' + parts.slice(0, idx + 1).join('/');
    return '/' + parts[0];
  }
  const BASE = detectBasePrefix() + '/api';
  function withDefaults(params={}){
    const p = { ...params };
    const sedeId = localStorage.getItem('selectedSedeId');
    if (sedeId) p.sede_id = sedeId;
    return p;
  }
  async function get(path, params={}){
    const qp = new URLSearchParams(withDefaults(params)).toString();
    const url = `${BASE}${path}${qp?`?${qp}`:''}`;
    const res = await fetch(url, { credentials: 'same-origin' });
    if(!res.ok) throw new Error(`GET ${url} ${res.status}`);
    return res.json();
  }
  async function post(path, body={}){
    const url = `${BASE}${path}`;
    const payload = { ...body, ...withDefaults({}) };
    const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload), credentials: 'same-origin' });
    if(!res.ok) throw new Error(`POST ${url} ${res.status}`);
    return res.json();
  }
  window.API = {
    menu: {
      listar: (params) => get('/menu/listar.php', params),
      categorias: () => get('/menu/categorias.php'),
      top: () => get('/menu/top_vendidos.php')
    },
    promos: { listar: () => get('/promos/listar.php') },
    sucursales: { listar: (params) => get('/sucursales/listar.php', params) },
    carrito: { calcular: (payload) => post('/carrito/calcular.php', payload) },
    facturacion: {
      registrarCliente: (payload) => post('/facturacion/registrar_cliente.php', payload),
      generar: (payload) => post('/facturacion/generar.php', payload),
      obtener: (params) => get('/facturacion/obtener.php', params)
    }
  };
})();

