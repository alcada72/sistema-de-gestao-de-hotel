<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/utils.php';

requireLogin('cliente');

$pdo = getDB();
$usuario = getCurrentUser();
$usuarioId = (int) $usuario['id'];

// Token CSRF
if (empty($_SESSION['csrf_reserva'])) {
  $_SESSION['csrf_reserva'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_reserva'];

$mensagem = '';
$tipoMensagem = '';

// Processar cancelamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_reserva'])) {

  $token = $_POST['csrf_token'] ?? '';
  $reservaId = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);

  if (!$token || !hash_equals($csrfToken, $token)) {
    $mensagem = 'Sessão inválida. Atualiza a página e tenta novamente.';
    $tipoMensagem = 'erro';

  } elseif (!$reservaId || $reservaId < 1) {
    $mensagem = 'Reserva inválida.';
    $tipoMensagem = 'erro';

  } else {
    try {
      // Só permite cancelar reservas pertencentes ao cliente
      // e que ainda estejam pendentes.
      $stmt = $pdo->prepare("
                UPDATE reservas
                SET estado = 'cancelada'
                WHERE id = ?
                  AND usuario_id = ?
                  AND estado = 'pendente'
            ");

      $stmt->execute([$reservaId, $usuarioId]);

      if ($stmt->rowCount() > 0) {
        $mensagem = 'Reserva cancelada com sucesso.';
        $tipoMensagem = 'sucesso';
      } else {
        $mensagem = 'Não foi possível cancelar. A reserva pode já ter sido processada.';
        $tipoMensagem = 'erro';
      }

    } catch (PDOException $e) {
      $mensagem = 'Ocorreu um erro ao cancelar a reserva.';
      $tipoMensagem = 'erro';
    }
  }

  // Evita repetir o cancelamento ao atualizar a página
  header('Location: ' . $_SERVER['PHP_SELF'] . '?mensagem=' . $tipoMensagem);
  exit;
}

// Mensagens após redirecionamento
if (isset($_GET['mensagem'])) {
  if ($_GET['mensagem'] === 'sucesso') {
    $mensagem = 'Reserva cancelada com sucesso.';
    $tipoMensagem = 'sucesso';
  } elseif ($_GET['mensagem'] === 'erro') {
    $mensagem = 'Não foi possível cancelar a reserva.';
    $tipoMensagem = 'erro';
  }
}

// Total de reservas
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservas
    WHERE usuario_id = ?
");
$stmt->execute([$usuarioId]);
$totalReservas = (int) $stmt->fetchColumn();

// Reservas pendentes
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservas
    WHERE usuario_id = ?
      AND estado = 'pendente'
");
$stmt->execute([$usuarioId]);
$reservasPendentes = (int) $stmt->fetchColumn();

// Reservas confirmadas
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservas
    WHERE usuario_id = ?
      AND estado = 'confirmada'
");
$stmt->execute([$usuarioId]);
$reservasConfirmadas = (int) $stmt->fetchColumn();

// Reservas concluídas
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservas
    WHERE usuario_id = ?
      AND estado = 'concluida'
");
$stmt->execute([$usuarioId]);
$reservasConcluidas = (int) $stmt->fetchColumn();

// Últimas reservas
$stmt = $pdo->prepare("
    SELECT
        r.*,
        h.nome AS hotel_nome,
        h.slug AS hotel_slug,
        q.numero AS quarto_numero
    FROM reservas r
    INNER JOIN hoteis h
        ON h.id = r.hotel_id
    LEFT JOIN quartos q
        ON q.id = r.quarto_id
        AND q.hotel_id = r.hotel_id
    WHERE r.usuario_id = ?
    ORDER BY r.criado_em DESC
    LIMIT 20
");

$stmt->execute([$usuarioId]);
$ultimasReservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-AO">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <?php require_once __DIR__ . '/../config/favicon.php' ?>

  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/btn.css">
</head>

<body>

  <div class="dashboard">

    <header class="dashboard-header">

      <div>
        <span class="welcome-label">ÁREA DO CLIENTE</span>

        <h1>
          Olá, <?= e($usuario['nome']) ?>!
        </h1>

        <p>Acompanha e gere as tuas reservas num só lugar.</p>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-logout">Sair</a>
      </div>

      <a href="<?= BASE_URL ?>/index.php" target="_top" class="btn-explore">
        <span>+</span>
        Explorar hotéis
      </a>

    </header>

    <?php if ($mensagem): ?>
      <div class="alert alert-<?= e($tipoMensagem) ?>">
        <?= e($mensagem) ?>
      </div>
    <?php endif; ?>

    <section class="stats-grid">

      <article class="stat-card">
        <div class="stat-icon icon-total">▤</div>

        <div>
          <span>Total de reservas</span>
          <strong><?= $totalReservas ?></strong>
          <small>Todas as tuas reservas</small>
        </div>
      </article>

      <article class="stat-card">
        <div class="stat-icon icon-pending">◷</div>

        <div>
          <span>Pendentes</span>
          <strong><?= $reservasPendentes ?></strong>
          <small>Aguardam confirmação</small>
        </div>
      </article>

      <article class="stat-card">
        <div class="stat-icon icon-confirmed">✓</div>

        <div>
          <span>Confirmadas</span>
          <strong><?= $reservasConfirmadas ?></strong>
          <small>Reservas confirmadas</small>
        </div>
      </article>

      <article class="stat-card">
        <div class="stat-icon icon-completed">★</div>

        <div>
          <span>Concluídas</span>
          <strong><?= $reservasConcluidas ?></strong>
          <small>Estadias concluídas</small>
        </div>
      </article>

    </section>

    <section class="reservations-section">

      <div class="section-header">

        <div>
          <span class="section-label">HISTÓRICO</span>
          <h2>As tuas reservas</h2>
          <p>Consulta o estado das tuas últimas reservas.</p>
        </div>

        <span class="reservation-count">
          <?= count($ultimasReservas) ?> de 20
        </span>

      </div>

      <?php if (!empty($ultimasReservas)): ?>

        <div class="reservations-list">

          <?php foreach ($ultimasReservas as $reserva): ?>

            <?php
            $estado = strtolower($reserva['estado']);

            $estadoClasse = match ($estado) {
              'pendente' => 'pending',
              'confirmada' => 'confirmed',
              'concluida' => 'completed',
              'cancelada' => 'cancelled',
              default => 'default'
            };

            $estadoTexto = ucfirst($estado);
            ?>

            <article class="reservation-card">

              <div class="hotel-symbol">
                <span>⌂</span>
              </div>

              <div class="reservation-info">

                <h3>
                  <?= e($reserva['hotel_nome']) ?>
                </h3>

                <p class="room-info">
                  Quarto:
                  <?= e($reserva['quarto_numero'] ?? 'Não definido') ?>
                </p>

                <span class="reservation-status <?= $estadoClasse ?>">
                  <span class="status-dot"></span>
                  <?= e($estadoTexto) ?>
                </span>

              </div>

              <div class="reservation-actions">

                <a href="<?= BASE_URL ?>/hotel/reserva?id=<?= (int) $reserva['id'] ?>" class="btn-details">
                  Ver detalhes
                </a>

                <?php if ($estado === 'pendente'): ?>

                  <form method="POST" action="" class="cancel-form"
                    onsubmit="return confirm('Tens a certeza de que desejas cancelar esta reserva?');">

                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <input type="hidden" name="reserva_id" value="<?= (int) $reserva['id'] ?>">

                    <button type="submit" name="cancelar_reserva" class="btn-cancel">
                      Cancelar reserva
                    </button>

                  </form>

                <?php endif; ?>

              </div>

            </article>

          <?php endforeach; ?>

        </div>

      <?php else: ?>

        <div class="empty-state">

          <div class="empty-icon">⌂</div>

          <h3>Ainda não tens reservas</h3>

          <p>
            Explora os nossos hotéis e encontra o lugar ideal
            para a tua próxima estadia.
          </p>

          <a href="<?= BASE_URL ?>/index.php" class="btn-explore">
            Explorar hotéis
          </a>

        </div>

      <?php endif; ?>

    </section>

  </div>

</body>

</html>