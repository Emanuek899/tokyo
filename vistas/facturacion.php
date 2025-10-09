<?php /* Aparentemente este archivo ha sido remplazado por "factura_tu_ticket.php"   */?>


 <?php /* $pageTitle = 'Tokyo Sushi - Facturación';*/ ?>

<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__.'/partials/head.php'; ?>
</head>
<body data-page="facturacion">
  <?php include __DIR__.'/partials/header.php'; ?>
  <main>
    <section class="section">
      <div class="container grid" style="grid-template-columns: 1.4fr 1fr; gap: 2rem;">
        <form id="form-fact" class="card" onsubmit="return false;">
          <div class="card__body">
            <div class="section-header"><h2>Facturación</h2></div>
            <p class="muted">Registra tus datos fiscales y genera tu factura usando el folio de tu ticket.</p>

            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: .75rem;">
              <div class="field" style="grid-column: span 2"><label>RFC</label><input id="fact-rfc" class="input" required></div>
              <div class="field" style="grid-column: span 2"><label>Razón social</label><input id="fact-razon" class="input" required></div>
              <div class="field"><label>Correo</label><input id="fact-correo" type="email" class="input"></div>
              <div class="field"><label>Teléfono</label><input id="fact-telefono" class="input"></div>
              <div class="field" style="grid-column: span 2"><label>Calle</label><input id="fact-calle" class="input"></div>
              <div class="field"><label>Número ext.</label><input id="fact-numero-ext" class="input"></div>
              <div class="field"><label>Número int.</label><input id="fact-numero-int" class="input"></div>
              <div class="field"><label>Colonia</label><input id="fact-colonia" class="input"></div>
              <div class="field"><label>Municipio</label><input id="fact-municipio" class="input"></div>
              <div class="field"><label>Estado</label><input id="fact-estado" class="input"></div>
              <div class="field"><label>País</label><input id="fact-pais" class="input" value="México"></div>
              <div class="field"><label>C.P.</label><input id="fact-cp" class="input"></div>
              <div class="field"><label>Régimen</label><input id="fact-regimen" class="input"></div>
              <div class="field"><label>Uso CFDI</label><input id="fact-uso" class="input" placeholder="G01, G03, etc."></div>
            </div>

            <div class="grid mt-3" style="grid-template-columns: 1fr auto; gap: .75rem; align-items: end;">
              <div class="field"><label>Ticket ID</label><input id="fact-ticket-id" class="input" placeholder="ID de ticket" required></div>
              <button id="btn-generar-fact" class="btn custom-btn">Generar factura</button>
            </div>
          </div>
        </form>

        <aside>
          <div class="card">
            <div class="card__body">
              <h2>Resultado</h2>
              <div id="fact-resumen" class="mt-2 text-muted">Completa el formulario y genera tu factura.</div>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </main>
  <?php include __DIR__.'/partials/footer.php'; ?>
</body>
</html>

