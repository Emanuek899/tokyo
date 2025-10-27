<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 2);
$CFG = is_file($BASE . '/config/db.php') ? ($BASE . '/config/db.php') : ($BASE . '/backend/config/db.php');
$UTL = is_file($BASE . '/utils/response.php') ? ($BASE . '/utils/response.php') : ($BASE . '/backend/utils/response.php');
require_once $CFG;
require_once $UTL;
require_once $BASE . '/components/promosRepo.php';

function table_exists(PDO $pdo, string $table): bool {
  $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $q->execute([$table]);
  return (bool)$q->fetchColumn();
}

try {
  header('Content-Type: application/json; charset=utf-8');
  $pdo = DB::get();
  $repo = new promosRepo($pdo);

  $items = [];
  if (table_exists($pdo, 'promociones')) {
    $st = $repo->select(
      'SELECT id, nombre, regla, vigencia, tipo FROM promociones ORDER BY id DESC'
    );
    while ($r = $st) {
      $items[] = [
        'id' => (int)$r['id'],
        'nombre' => (string)($r['nombre'] ?? ''),
        'regla' => (string)($r['regla'] ?? ''),
        'vigencia' => (string)($r['vigencia'] ?? ''),
        'tipo' => (string)($r['tipo'] ?? 'promo'),
      ];
    }
  } else {
    $items = [
      ['id'=>1,'nombre'=>'2x1 Roll clásico','regla'=>'Lunes a jueves','vigencia'=>'Septiembre','tipo'=>'2x1'],
      ['id'=>2,'nombre'=>'-20% en combos','regla'=>'En pedidos online','vigencia'=>'Hasta 30/09','tipo'=>'descuento'],
    ];
  }

  echo json_encode($items, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  json_error(['Error al listar promociones'], 500, $e->getMessage());
}
