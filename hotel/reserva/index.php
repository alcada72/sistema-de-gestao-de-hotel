<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/utils.php';

requireLogin('cliente');

$pdo = getDB();
$usuario = getCurrentUser();
$usuarioId = (int) $usuario['id'];

// Validar ID da reserva
$reservaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reservaId || $reservaId < 1) {
  http_response_code(400);
  exit('Reserva inválida.');
}

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
  $idEnviado = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);

  if (
    !$token ||
    !hash_equals($csrfToken, $token) ||
    !$idEnviado ||
    $idEnviado !== $reservaId
  ) {
    $mensagem = 'Pedido inválido. Atualiza a página e tenta novamente.';
    $tipoMensagem = 'erro';

  } else {

    try {
      /*
       * Só o proprietário da reserva pode cancelá-la.
       * Apenas reservas pendentes podem ser canceladas.
       */
      $stmt = $pdo->prepare("
                UPDATE reservas
                SET estado = 'cancelada'
                WHERE id = ?
                  AND usuario_id = ?
                  AND estado = 'pendente'
            ");

      $stmt->execute([$reservaId, $usuarioId]);

      if ($stmt->rowCount() > 0) {
        header(
          'Location: ' . BASE_URL .
          '/reserva.php?id=' . $reservaId . '&cancelada=1'
        );
        exit;
      }

      $mensagem = 'Esta reserva já não está pendente ou não pode ser cancelada.';
      $tipoMensagem = 'erro';

    } catch (PDOException $e) {
      error_log('Erro ao cancelar reserva: ' . $e->getMessage());

      $mensagem = 'Não foi possível cancelar a reserva.';
      $tipoMensagem = 'erro';
    }
  }
}

if (isset($_GET['cancelada']) && $_GET['cancelada'] === '1') {
  $mensagem = 'A tua reserva foi cancelada com sucesso.';
  $tipoMensagem = 'sucesso';
}

if (isset($_GET['criada']) && $_GET['criada'] === '1') {
  $mensagem = 'O teu pedido de reserva foi registado com sucesso!';
  $tipoMensagem = 'sucesso';
}

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.codigo,
        r.hotel_id,
        r.quarto_id,
        r.data_entrada,
        r.data_saida,
        r.quantidade_pessoas,
        r.quantidade_noites,
        r.preco_diaria,
        r.total,
        r.estado,
        r.observacoes,
        r.criado_em,

        h.nome AS hotel_nome,
        h.slug AS hotel_slug,
        h.endereco AS hotel_endereco,
        h.telefone AS hotel_telefone,
        h.email AS hotel_email,

        p.nome AS provincia_nome,
        m.nome AS municipio_nome,

        q.numero AS quarto_numero,
        q.descricao AS quarto_descricao,

        c.nome AS categoria_nome,
        c.capacidade AS capacidade_quarto,

        (
            SELECT hi.url
            FROM hotel_imagens hi
            WHERE hi.hotel_id = h.id
            ORDER BY
                CASE WHEN hi.tipo = 'principal' THEN 0 ELSE 1 END,
                hi.ordem ASC,
                hi.id ASC
            LIMIT 1
        ) AS hotel_imagem

    FROM reservas r

    INNER JOIN hoteis h
        ON h.id = r.hotel_id

    INNER JOIN provincias p
        ON p.id = h.provincia_id

    INNER JOIN municipios m
        ON m.id = h.municipio_id

    INNER JOIN quartos q
        ON q.id = r.quarto_id
       AND q.hotel_id = r.hotel_id

    INNER JOIN categorias_quartos c
        ON c.id = q.categoria_id
       AND c.hotel_id = q.hotel_id

    WHERE r.id = ?
      AND r.usuario_id = ?

    LIMIT 1
");

$stmt->execute([$reservaId, $usuarioId]);

$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
  http_response_code(404);
  exit('Reserva não encontrada.');
}

// Estado e apresentação
$estados = [
  'pendente' => [
    'texto' => 'Aguardando confirmação',
    'classe' => 'pendente',
    'descricao' => 'O teu pedido foi recebido e está a aguardar confirmação pelo hotel.'
  ],
  'confirmada' => [
    'texto' => 'Confirmada',
    'classe' => 'confirmada',
    'descricao' => 'O hotel confirmou a tua reserva. Podes preparar a tua estadia.'
  ],
  'checkin' => [
    'texto' => 'Em hospedagem',
    'classe' => 'checkin',
    'descricao' => 'A tua hospedagem está em andamento.'
  ],
  'concluida' => [
    'texto' => 'Concluída',
    'classe' => 'concluida',
    'descricao' => 'Esta estadia foi concluída.'
  ],
  'cancelada' => [
    'texto' => 'Cancelada',
    'classe' => 'cancelada',
    'descricao' => 'Esta reserva foi cancelada.'
  ]
];

$estado = $estados[$reserva['estado']] ?? [
  'texto' => ucfirst($reserva['estado']),
  'classe' => 'default',
  'descricao' => 'Consulta o hotel para mais informações.'
];

$dataEntrada = new DateTime($reserva['data_entrada']);
$dataSaida = new DateTime($reserva['data_saida']);
$dataCriacao = new DateTime($reserva['criado_em']);

function formatarDataReserva(DateTime $data): string
{
  $meses = [
    1 => 'janeiro',
    2 => 'fevereiro',
    3 => 'março',
    4 => 'abril',
    5 => 'maio',
    6 => 'junho',
    7 => 'julho',
    8 => 'agosto',
    9 => 'setembro',
    10 => 'outubro',
    11 => 'novembro',
    12 => 'dezembro'
  ];

  return $data->format('d') . ' de ' .
    $meses[(int) $data->format('n')] . ' de ' .
    $data->format('Y');
}

$precoDiaria = (float) $reserva['preco_diaria'];
$total = (float) $reserva['total'];

?>

<!DOCTYPE html>
<html lang="pt-AO">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <?php require_once __DIR__ . '/../../config/favicon.php' ?>

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reserva.css">
</head>

<body>

  <header class="topbar">

    <div class="topbar-content">

      <a href="<?= BASE_URL ?>/index.php" class="brand">
        <span class="brand-icon">⌂</span>
        <span>MultiploHotel</span>
      </a>

      <nav>
        <a href="<?= BASE_URL ?>/cliente">Meu painel</a>
        <a href="<?= BASE_URL ?>/index.php">Explorar hotéis</a>
      </nav>

    </div>

  </header>

  <main class="page-container">

    <div class="page-heading">

      <div>
        <span class="eyebrow">ÁREA DO CLIENTE</span>
        <h1>Detalhes da reserva</h1>
        <p>Consulta todas as informações da tua estadia.</p>
      </div>

      <a href="<?= BASE_URL ?>/cliente" class="back-button">
        ← Voltar ao painel
      </a>

    </div>

    <?php if ($mensagem): ?>
      <div class="alert alert-<?= e($tipoMensagem) ?>">
        <?= e($mensagem) ?>
      </div>
    <?php endif; ?>

    <section class="reservation-banner">

      <div class="banner-left">

        <div class="reservation-icon">▤</div>

        <div>
          <span class="banner-label">CÓDIGO DA RESERVA</span>

          <h2><?= e($reserva['codigo']) ?></h2>

          <p>
            Pedido criado em
            <?= formatarDataReserva($dataCriacao) ?>
          </p>
        </div>

      </div>

      <div class="status-badge <?= e($estado['classe']) ?>">
        <span class="status-dot"></span>
        <?= e($estado['texto']) ?>
      </div>

    </section>

    <div class="status-description <?= e($estado['classe']) ?>">
      <span class="status-symbol">ⓘ</span>
      <p><?= e($estado['descricao']) ?></p>
    </div>

    <div class="details-layout">

      <div class="details-main">

        <section class="detail-card">

          <div class="card-heading">
            <div>
              <span class="section-label">ALOJAMENTO</span>
              <h2>Informações do hotel</h2>
            </div>
          </div>

          <div class="hotel-information">

            <?php if (!empty($reserva['hotel_imagem'])): ?>

              <img src="<?= e($reserva['hotel_imagem']) ?>" alt="<?= e($reserva['hotel_nome']) ?>" class="hotel-image">

            <?php else: ?>

              <div class="hotel-placeholder">⌂</div>

            <?php endif; ?>

            <div class="hotel-info">

              <h3><?= e($reserva['hotel_nome']) ?></h3>

              <p class="hotel-location">
                ⌖
                <?= e($reserva['municipio_nome']) ?>,
                <?= e($reserva['provincia_nome']) ?>
              </p>

              <p><?= e($reserva['hotel_endereco']) ?></p>

              <?php if (!empty($reserva['hotel_telefone'])): ?>
                <p>
                  Telefone:
                  <?= e($reserva['hotel_telefone']) ?>
                </p>
              <?php endif; ?>

              <a href="<?= BASE_URL ?>/hotel?slug=<?= urlencode($reserva['hotel_slug']) ?>" class="hotel-link">
                Ver página do hotel →
              </a>

            </div>

          </div>

        </section>

        <section class="detail-card">

          <div class="card-heading">
            <div>
              <span class="section-label">HOSPEDAGEM</span>
              <h2>Detalhes da estadia</h2>
            </div>
          </div>

          <div class="stay-grid">

            <div class="stay-item">
              <span class="stay-icon">↗</span>
              <div>
                <small>Entrada</small>
                <strong>
                  <?= formatarDataReserva($dataEntrada) ?>
                </strong>
                <span>Check-in</span>
              </div>
            </div>

            <div class="stay-item">
              <span class="stay-icon">↙</span>
              <div>
                <small>Saída</small>
                <strong>
                  <?= formatarDataReserva($dataSaida) ?>
                </strong>
                <span>Check-out</span>
              </div>
            </div>

            <div class="stay-item">
              <span class="stay-icon">♙</span>
              <div>
                <small>Hóspedes</small>
                <strong>
                  <?= (int) $reserva['quantidade_pessoas'] ?>
                </strong>
                <span>
                  <?= (int) $reserva['quantidade_pessoas'] === 1
                    ? 'Pessoa'
                    : 'Pessoas' ?>
                </span>
              </div>
            </div>

            <div class="stay-item">
              <span class="stay-icon">◷</span>
              <div>
                <small>Duração</small>
                <strong>
                  <?= (int) $reserva['quantidade_noites'] ?>
                </strong>
                <span>
                  <?= (int) $reserva['quantidade_noites'] === 1
                    ? 'Noite'
                    : 'Noites' ?>
                </span>
              </div>
            </div>

          </div>

          <div class="room-information">

            <div class="room-icon">▣</div>

            <div>
              <small>Quarto selecionado</small>
              <h3>Quarto <?= e($reserva['quarto_numero']) ?></h3>
              <p><?= e($reserva['categoria_nome']) ?></p>
            </div>

            <div class="room-capacity">
              Capacidade:
              <?= (int) $reserva['capacidade_quarto'] ?>
              hóspedes
            </div>

          </div>

        </section>

        <section class="detail-card">

          <div class="card-heading">
            <div>
              <span class="section-label">OBSERVAÇÕES</span>
              <h2>Informações adicionais</h2>
            </div>
          </div>

          <?php if (!empty($reserva['observacoes'])): ?>

            <p class="observations">
              <?= nl2br(e($reserva['observacoes'])) ?>
            </p>

          <?php else: ?>

            <p class="no-observations">
              Nenhuma observação foi adicionada a esta reserva.
            </p>

          <?php endif; ?>

        </section>

      </div>

      <aside class="details-sidebar">

        <section class="detail-card price-card">

          <span class="section-label">RESUMO FINANCEIRO</span>

          <h2>Valor da reserva</h2>

          <div class="price-line">
            <span>Preço por noite</span>
            <strong>
              <?= number_format($precoDiaria, 2, ',', '.') ?> Kz
            </strong>
          </div>

          <div class="price-line">
            <span>Quantidade de noites</span>
            <strong>
              <?= (int) $reserva['quantidade_noites'] ?>
            </strong>
          </div>

          <div class="price-line">
            <span>Hóspedes</span>
            <strong>
              <?= (int) $reserva['quantidade_pessoas'] ?>
            </strong>
          </div>

          <div class="price-total">
            <span>Total</span>
            <strong>
              <?= number_format($total, 2, ',', '.') ?> Kz
            </strong>
          </div>

          <p class="price-note">
            Este é o valor registado no momento da criação da reserva.
          </p>

        </section>

        <?php if ($reserva['estado'] === 'pendente'): ?>

          <section class="detail-card cancellation-card">

            <h3>Precisas cancelar?</h3>

            <p>
              Enquanto o pedido estiver pendente, podes cancelar
              esta reserva.
            </p>

            <form method="POST" action="<?= BASE_URL ?>/reserva.php?id=<?= (int) $reserva['id'] ?>"
              onsubmit="return confirm('Tens a certeza de que desejas cancelar esta reserva? Esta ação não poderá ser desfeita.');">

              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

              <input type="hidden" name="reserva_id" value="<?= (int) $reserva['id'] ?>">

              <button type="submit" name="cancelar_reserva" class="cancel-button">
                Cancelar reserva
              </button>

            </form>

          </section>

        <?php endif; ?>

        <a href="<?= BASE_URL ?>/cliente" class="panel-button">
          Voltar ao meu painel
        </a>

      </aside>

    </div>

  </main>

</body>

</html>