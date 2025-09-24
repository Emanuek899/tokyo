<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Carrusel Aislado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Solo para darle altura al carrusel y que sea visible */
        .box { height: 400px; }
    </style>
</head>
<body>

<div class="container mt-5">
    <h1>Prueba de Carrusel</h1>
    <p>Si este carrusel se mueve correctamente, el problema está en uno de tus archivos (CSS o JS).</p>

    <div id="miCarrusel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active" data-bs-interval="2000">
                <div class="box bg-primary d-flex align-items-center justify-content-center">
                    <h2 class="text-white">SLIDE 1</h2>
                </div>
            </div>
            <div class="carousel-item" data-bs-interval="2000">
                <div class="box bg-success d-flex align-items-center justify-content-center">
                    <h2 class="text-white">SLIDE 2</h2>
                </div>
            </div>
            <div class="carousel-item" data-bs-interval="2000">
                <div class="box bg-danger d-flex align-items-center justify-content-center">
                    <h2 class="text-white">SLIDE 3</h2>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
