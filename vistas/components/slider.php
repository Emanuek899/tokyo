<?php
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $url = $_SERVER['REQUEST_URI'];
    define('BASE_URL', "$protocol://$host/tokyo/vistas");
    
?>

<div id="carouselExampleInterval" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-inner">
    <div class="carousel-item active" data-bs-interval="10000">
        <img src="<?= BASE_URL ?>/assets/img/sushi1.webp" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item" data-bs-interval="2000">
        <img src="<?= BASE_URL ?>/assets/img/sushi2.webp" class="d-block w-100" alt="...">
    </div>
    <div class="carousel-item" data-bs-interval="2000">
        <img src="<?= BASE_URL ?>/assets/img/sushi3.webp" class="d-block w-100" alt="...">
    </div>
  </div>
</div>
