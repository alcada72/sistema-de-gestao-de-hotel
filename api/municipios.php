<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin('cliente');

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();

$hotel = requireHotelAccess(
  isset($_GET['hotel_id']) ? (int) $_GET['hotel_id'] : null
);

$provinciaId = filter_input(INPUT_GET, 'provincia_id', FILTER_VALIDATE_INT);

if (!$provinciaId) {
  http_response_code(400);
  echo json_encode(['erro' => 'Província inválida.']);
  exit;
}

$stmt = $pdo->prepare("
    SELECT id, nome
    FROM municipios
    WHERE provincia_id = ?
    ORDER BY nome
");

$stmt->execute([$provinciaId]);

echo json_encode(
  $stmt->fetchAll(PDO::FETCH_ASSOC),
  JSON_UNESCAPED_UNICODE
);
