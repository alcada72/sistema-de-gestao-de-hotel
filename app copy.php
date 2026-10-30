<?php

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/models/quartosModels.php';

if (!isLoggedIn()) {
  header('Location: ' . BASE_URL . '/index.php');
  exit;
}

$user = getCurrentUser();

$quartosList = QuartosModels::findAll();

$totalQuartos = count($quartosList);
$totalQuartosDisponiveis = QuartosModels::countByEstado('disponivel');
$quartosOcupados = QuartosModels::countByEstado('ocupado');
$quartosManutencao = QuartosModels::countByEstado('manutencao');

// Layout
require __DIR__ . '/views/layouts/header.php';
require __DIR__ . '/views/layouts/sidebar.php';

?>

<div class="main-wrapper">

  <?php require __DIR__ . '/views/layouts/topbar.php'; ?>

  <main class="content">

    <?php require __DIR__ . '/views/pages/dashboard.php'; ?>
    <?php require __DIR__ . '/views/pages/quartos.php'; ?>
    <?php require __DIR__ . '/views/pages/reservas.php'; ?>
    <?php require __DIR__ . '/views/pages/restaurante.php'; ?>
    <?php require __DIR__ . '/views/pages/hospedes.php'; ?>
    <?php require __DIR__ . '/views/pages/relatorios.php'; ?>
    <?php require __DIR__ . '/views/pages/configuracoes.php'; ?>

    <?php require __DIR__ . '/views/layouts/footer.php'; ?>

  </main>
</div>

<?php

require __DIR__ . '/views/modals/reservation.php';
require __DIR__ . '/views/modals/room.php';
require __DIR__ . '/views/modals/product.php';
require __DIR__ . '/views/modals/room-details.php';

require __DIR__ . '/views/layouts/scripts.php';

?>