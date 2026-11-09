<?php

require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/utils.php";

requireLogin("admin");

$hotelId = filter_input(INPUT_GET, "hotel_id", FILTER_VALIDATE_INT);

if (!$hotelId || $hotelId <= 0) {
  http_response_code(400);
  exit("Hotel inválido.");
}

$db = getDB();

$stmt = $db->prepare("
    SELECT id, nome, slug, estado, endereco
    FROM hoteis
    WHERE id = :id
    LIMIT 1
");

$stmt->execute(["id" => $hotelId]);

$hotel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hotel) {
  http_response_code(404);
  exit("Hotel não encontrado.");
}
?>

<div class="hotel-details">
  <h3><?= e($hotel["nome"]) ?></h3>

  <p>Endereço: <?= e($hotel["endereco"]) ?></p>

  <p>Estado: <?= e($hotel["estado"]) ?></p>

  <a href="<?= BASE_URL ?>/hotel.php?slug=<?= urlencode($hotel["slug"]) ?>">
    Ver página do hotel
  </a>

  <div class="hotel-actions">
    <button type="button" onclick="openHotelRooms(<?= (int) $hotel["id"] ?>)">
      Gerir quartos
    </button>

    <button type="button" onclick="openHotelReservations(<?= (int) $hotel["id"] ?>)">
      Ver reservas
    </button>
  </div>
</div>