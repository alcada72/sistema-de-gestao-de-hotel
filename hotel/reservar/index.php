<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/utils.php';

requireLogin('cliente');

$pdo = getDB();
$usuario = getCurrentUser();
$usuarioId = (int) $usuario['id'];

$hotelId = filter_input(INPUT_GET, 'hotel_id', FILTER_VALIDATE_INT);

if (!$hotelId || $hotelId < 1) {
  http_response_code(400);
  exit('Hotel inválido.');
}

// Token CSRF
if (empty($_SESSION['csrf_reserva'])) {
  $_SESSION['csrf_reserva'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_reserva'];

$erro = '';
$sucesso = '';
$dados = [
  'quarto_id' => '',
  'data_entrada' => '',
  'data_saida' => '',
  'quantidade_pessoas' => '1',
  'observacoes' => ''
];

// Buscar hotel
$stmt = $pdo->prepare("
    SELECT
        h.id,
        h.nome,
        h.slug,
        h.descricao,
        h.endereco,
        h.telefone,
        p.nome AS provincia_nome,
        m.nome AS municipio_nome
    FROM hoteis h
    INNER JOIN provincias p ON p.id = h.provincia_id
    INNER JOIN municipios m ON m.id = h.municipio_id
    WHERE h.id = ?
      AND h.estado = 'aprovado'
    LIMIT 1
");

$stmt->execute([$hotelId]);
$hotel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hotel) {
  http_response_code(404);
  exit('Hotel não encontrado ou ainda não aprovado.');
}

// Buscar imagem principal ou primeira imagem disponível
$stmt = $pdo->prepare("
    SELECT url
    FROM hotel_imagens
    WHERE hotel_id = ?
    ORDER BY
        CASE WHEN tipo = 'principal' THEN 0 ELSE 1 END,
        ordem ASC,
        id ASC
    LIMIT 1
");

$stmt->execute([$hotelId]);
$imagemHotel = $stmt->fetchColumn();

// Buscar quartos ativos e respetivas categorias
$stmt = $pdo->prepare("
    SELECT
        q.id,
        q.numero,
        q.descricao AS quarto_descricao,
        c.nome AS categoria_nome,
        c.descricao AS categoria_descricao,
        c.capacidade,
        c.preco_base
    FROM quartos q
    INNER JOIN categorias_quartos c
        ON c.id = q.categoria_id
       AND c.hotel_id = q.hotel_id
    WHERE q.hotel_id = ?
      AND q.estado = 'ativo'
      AND c.ativo = 1
    ORDER BY c.preco_base ASC, q.numero ASC
");

$stmt->execute([$hotelId]);
$quartos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  foreach ($dados as $campo => $valor) {
    $dados[$campo] = trim((string) ($_POST[$campo] ?? $valor));
  }

  $token = $_POST['csrf_token'] ?? '';

  if (!$token || !hash_equals($csrfToken, $token)) {
    $erro = 'Pedido inválido. Atualiza a página e tenta novamente.';

  } else {

    $quartoId = filter_var($dados['quarto_id'], FILTER_VALIDATE_INT);
    $quantidadePessoas = filter_var(
      $dados['quantidade_pessoas'],
      FILTER_VALIDATE_INT
    );

    $dataEntrada = $dados['data_entrada'];
    $dataSaida = $dados['data_saida'];
    $observacoes = $dados['observacoes'];

    // Validar datas no formato YYYY-MM-DD
    $entrada = DateTime::createFromFormat('!Y-m-d', $dataEntrada);
    $saida = DateTime::createFromFormat('!Y-m-d', $dataSaida);

    $entradaValida = $entrada
      && $entrada->format('Y-m-d') === $dataEntrada;

    $saidaValida = $saida
      && $saida->format('Y-m-d') === $dataSaida;

    if (!$quartoId || $quartoId < 1) {
      $erro = 'Seleciona um quarto válido.';

    } elseif (
      !$quantidadePessoas ||
      $quantidadePessoas < 1
    ) {
      $erro = 'Indica uma quantidade válida de hóspedes.';

    } elseif (!$entradaValida || !$saidaValida) {
      $erro = 'Indica datas de entrada e saída válidas.';

    } elseif ($dataEntrada < date('Y-m-d')) {
      $erro = 'A data de entrada não pode ser anterior a hoje.';

    } elseif ($dataSaida <= $dataEntrada) {
      $erro = 'A data de saída deve ser posterior à entrada.';

    } elseif (strlen($observacoes) > 5000) {
      $erro = 'As observações não podem ultrapassar 5000 caracteres.';

    } else {

      $quantidadeNoites = (int) $entrada->diff($saida)->days;

      try {
        $pdo->beginTransaction();

        /*
         * Bloquear o registo do quarto.
         * Isto serializa as tentativas de reserva
         * para o mesmo quarto.
         */
        $stmt = $pdo->prepare("
                    SELECT
                        q.id,
                        q.numero,
                        q.hotel_id,
                        c.capacidade,
                        c.preco_base,
                        c.nome AS categoria_nome
                    FROM quartos q
                    INNER JOIN categorias_quartos c
                        ON c.id = q.categoria_id
                       AND c.hotel_id = q.hotel_id
                    INNER JOIN hoteis h
                        ON h.id = q.hotel_id
                    WHERE q.id = ?
                      AND q.hotel_id = ?
                      AND q.estado = 'ativo'
                      AND c.ativo = 1
                      AND h.estado = 'aprovado'
                    FOR UPDATE
                ");

        $stmt->execute([$quartoId, $hotelId]);
        $quarto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quarto) {
          throw new RuntimeException(
            'O quarto selecionado não está disponível.'
          );
        }

        if ($quantidadePessoas > (int) $quarto['capacidade']) {
          throw new RuntimeException(
            'Este quarto suporta no máximo ' .
            $quarto['capacidade'] . ' hóspedes.'
          );
        }

        /*
         * Verificar sobreposição de datas.
         *
         * Uma reserva existente ocupa o quarto quando:
         * entrada existente < saída pretendida
         * e saída existente > entrada pretendida.
         *
         * Reservas canceladas e concluídas não bloqueiam.
         */
        $stmt = $pdo->prepare("
                    SELECT id
                    FROM reservas
                    WHERE quarto_id = ?
                      AND hotel_id = ?
                      AND estado IN ('pendente', 'confirmada', 'checkin')
                      AND data_entrada < ?
                      AND data_saida > ?
                    LIMIT 1
                ");

        $stmt->execute([
          $quartoId,
          $hotelId,
          $dataSaida,
          $dataEntrada
        ]);

        if ($stmt->fetch()) {
          throw new RuntimeException(
            'Este quarto já possui uma reserva para o período selecionado. Escolhe outro quarto ou outras datas.'
          );
        }

        // Calcular o preço com os dados da base de dados
        $precoDiaria = (float) $quarto['preco_base'];
        $total = $precoDiaria * $quantidadeNoites;

        // Código único da reserva
        $codigo = 'RES-' . strtoupper(bin2hex(random_bytes(6)));

        // Criar reserva
        $stmt = $pdo->prepare("
                    INSERT INTO reservas (
                        codigo,
                        hotel_id,
                        quarto_id,
                        usuario_id,
                        data_entrada,
                        data_saida,
                        quantidade_pessoas,
                        quantidade_noites,
                        preco_diaria,
                        total,
                        estado,
                        observacoes
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?
                    )
                ");

        $stmt->execute([
          $codigo,
          $hotelId,
          $quartoId,
          $usuarioId,
          $dataEntrada,
          $dataSaida,
          $quantidadePessoas,
          $quantidadeNoites,
          $precoDiaria,
          $total,
          $observacoes !== '' ? $observacoes : null
        ]);

        $reservaId = (int) $pdo->lastInsertId();

        $pdo->commit();

        // Evitar reenvio do formulário
        header(
          'Location: ' . BASE_URL .
          '/hotel/reserva?id=' . $reservaId . '&criada=1'
        );
        exit;

      } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
          $erro = $e->getMessage();
        } else {
          error_log('Erro ao criar reserva: ' . $e->getMessage());
          $erro = 'Não foi possível concluir a reserva. Tenta novamente.';
        }
      }
    }
  }
}

?>

<!DOCTYPE html>
<html lang="pt-AO">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Reservar | <?= e($hotel['nome']) ?></title>

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reservar.css">
</head>

<body>

  <header class="topbar">
    <div class="topbar-content">
      <a href="<?= BASE_URL ?>/index.php" class="brand">
        <span>MultiploHotel</span>
      </a>

      <a href="<?= BASE_URL ?>/index.php" class="back-link">
        Voltar aos hotéis
      </a>
    </div>
  </header>

  <main class="booking-container">

    <div class="page-heading">
      <span class="eyebrow">RESERVA DE ALOJAMENTO</span>
      <h1>Faz a tua reserva</h1>
      <p>Escolhe o quarto e indica os dados da tua estadia.</p>
    </div>

    <?php if ($erro): ?>
      <div class="alert alert-error">
        <?= e($erro) ?>
      </div>
    <?php endif; ?>

    <div class="booking-layout">

      <section class="booking-main">

        <article class="hotel-card">

          <?php if ($imagemHotel): ?>
            <img src="<?= e($imagemHotel) ?>" alt="<?= e($hotel['nome']) ?>" class="hotel-image">
          <?php else: ?>
            <div class="hotel-image-placeholder">
              <span>⌂</span>
            </div>
          <?php endif; ?>

          <div class="hotel-details">

            <span class="hotel-label">HOTEL SELECIONADO</span>

            <h2><?= e($hotel['nome']) ?></h2>

            <p class="hotel-location">
              <span>⌖</span>
              <?= e($hotel['municipio_nome']) ?>,
              <?= e($hotel['provincia_nome']) ?>
            </p>

            <p class="hotel-address">
              <?= e($hotel['endereco']) ?>
            </p>

            <?php if (!empty($hotel['descricao'])): ?>
              <p class="hotel-description">
                <?= e($hotel['descricao']) ?>
              </p>
            <?php endif; ?>

          </div>

        </article>

        <?php if (empty($quartos)): ?>

          <div class="empty-rooms">
            <h3>Nenhum quarto disponível para reserva</h3>
            <p>Este hotel ainda não possui quartos ativos para reserva.</p>
          </div>

        <?php else: ?>

          <form method="POST" id="bookingForm" class="booking-form">

            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <section class="form-section">

              <div class="section-title">
                <span class="step-number">01</span>
                <div>
                  <h2>Escolhe o teu quarto</h2>
                  <p>Seleciona uma das opções disponíveis.</p>
                </div>
              </div>

              <div class="rooms-grid">

                <?php foreach ($quartos as $quarto): ?>

                  <label class="room-option">

                    <input type="radio" name="quarto_id" value="<?= (int) $quarto['id'] ?>"
                      data-price="<?= e($quarto['preco_base']) ?>" data-capacity="<?= (int) $quarto['capacidade'] ?>"
                      <?= (string) $dados['quarto_id'] === (string) $quarto['id'] ? 'checked' : '' ?> required>

                    <div class="room-content">

                      <div class="room-top">
                        <span class="room-category">
                          <?= e($quarto['categoria_nome']) ?>
                        </span>

                        <span class="room-check">✓</span>
                      </div>

                      <h3>Quarto <?= e($quarto['numero']) ?></h3>

                      <p class="room-description">
                        <?= e($quarto['quarto_descricao'] ?: $quarto['categoria_descricao'] ?: 'Quarto disponível para hospedagem.') ?>
                      </p>

                      <div class="room-capacity">
                        <span>♙</span>
                        Até <?= (int) $quarto['capacidade'] ?>
                        hóspedes
                      </div>

                      <div class="room-price">
                        <strong>
                          <?= number_format((float) $quarto['preco_base'], 2, ',', '.') ?>
                        </strong>
                        <span>Kz / noite</span>
                      </div>

                    </div>

                  </label>

                <?php endforeach; ?>

              </div>

            </section>

            <section class="form-section">

              <div class="section-title">
                <span class="step-number">02</span>
                <div>
                  <h2>Dados da estadia</h2>
                  <p>Indica quando pretendes ficar no hotel.</p>
                </div>
              </div>

              <div class="form-grid">

                <div class="form-group">
                  <label for="data_entrada">Data de entrada</label>

                  <input type="date" id="data_entrada" name="data_entrada" min="<?= date('Y-m-d') ?>"
                    value="<?= e($dados['data_entrada']) ?>" required>
                </div>

                <div class="form-group">
                  <label for="data_saida">Data de saída</label>

                  <input type="date" id="data_saida" name="data_saida" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                    value="<?= e($dados['data_saida']) ?>" required>
                </div>

                <div class="form-group">
                  <label for="quantidade_pessoas">
                    Número de hóspedes
                  </label>

                  <input type="number" id="quantidade_pessoas" name="quantidade_pessoas" min="1" max="20"
                    value="<?= e($dados['quantidade_pessoas']) ?>" required>

                  <small id="capacityHint">
                    Seleciona primeiro um quarto.
                  </small>
                </div>

              </div>

            </section>

            <section class="form-section">

              <div class="section-title">
                <span class="step-number">03</span>
                <div>
                  <h2>Informações adicionais</h2>
                  <p>Deixa uma observação para o hotel, se necessário.</p>
                </div>
              </div>

              <div class="form-group">
                <label for="observacoes">Observações (opcional)</label>

                <textarea name="observacoes" id="observacoes" rows="4" maxlength="5000"
                  placeholder="Ex.: preferência por quarto silencioso..."><?= e($dados['observacoes']) ?></textarea>
              </div>

            </section>

            <button type="submit" class="submit-booking">
              Confirmar pedido de reserva
              <span>→</span>
            </button>

            <p class="form-security">
              A tua reserva será registada como pendente
              até à confirmação pelo hotel.
            </p>

          </form>

        <?php endif; ?>

      </section>

      <aside class="booking-sidebar">

        <div class="summary-card">

          <h2>Resumo da reserva</h2>

          <div class="summary-hotel">
            <strong><?= e($hotel['nome']) ?></strong>
            <span><?= e($hotel['municipio_nome']) ?></span>
          </div>

          <div class="summary-line">
            <span>Quarto</span>
            <strong id="summaryRoom">Não selecionado</strong>
          </div>

          <div class="summary-line">
            <span>Entrada</span>
            <strong id="summaryEntry">--/--/----</strong>
          </div>

          <div class="summary-line">
            <span>Saída</span>
            <strong id="summaryExit">--/--/----</strong>
          </div>

          <div class="summary-line">
            <span>Hóspedes</span>
            <strong id="summaryGuests">1</strong>
          </div>

          <div class="summary-line">
            <span>Noites</span>
            <strong id="summaryNights">0</strong>
          </div>

          <div class="summary-line">
            <span>Preço por noite</span>
            <strong id="summaryPrice">0,00 Kz</strong>
          </div>

          <div class="summary-total">
            <span>Total estimado</span>
            <strong id="summaryTotal">0,00 Kz</strong>
          </div>

          <p class="summary-note">
            O valor final será calculado novamente pelo servidor
            com base no preço atual do quarto.
          </p>

        </div>

      </aside>

    </div>

  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {

      const form = document.getElementById('bookingForm');

      if (!form) return;

      const quartos = document.querySelectorAll('input[name="quarto_id"]');

      const entrada = document.getElementById('data_entrada');
      const saida = document.getElementById('data_saida');
      const pessoas = document.getElementById('quantidade_pessoas');

      const summaryRoom = document.getElementById('summaryRoom');
      const summaryEntry = document.getElementById('summaryEntry');
      const summaryExit = document.getElementById('summaryExit');
      const summaryGuests = document.getElementById('summaryGuests');
      const summaryNights = document.getElementById('summaryNights');
      const summaryPrice = document.getElementById('summaryPrice');
      const summaryTotal = document.getElementById('summaryTotal');
      const capacityHint = document.getElementById('capacityHint');

      let precoDiario = 0;
      let capacidade = 0;

      function formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-PT', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).format(valor) + ' Kz';
      }

      function formatarData(valor) {
        if (!valor) return '--/--/----';

        const partes = valor.split('-');

        if (partes.length !== 3) return '--/--/----';

        return `${partes[2]}/${partes[1]}/${partes[0]}`;
      }

      function calcular() {

        const data1 = entrada.value;
        const data2 = saida.value;

        let noites = 0;

        if (data1 && data2) {
          const inicio = new Date(data1 + 'T00:00:00');
          const fim = new Date(data2 + 'T00:00:00');

          noites = Math.round(
            (fim - inicio) / (1000 * 60 * 60 * 24)
          );

          if (noites < 0) noites = 0;
        }

        const total = noites * precoDiario;

        summaryEntry.textContent = formatarData(data1);
        summaryExit.textContent = formatarData(data2);
        summaryGuests.textContent = pessoas.value || '1';
        summaryNights.textContent = noites;
        summaryPrice.textContent = formatarMoeda(precoDiario);
        summaryTotal.textContent = formatarMoeda(total);

        const saidaMinima = new Date(entrada.value + 'T00:00:00');

        if (entrada.value) {
          saidaMinima.setDate(saidaMinima.getDate() + 1);

          const ano = saidaMinima.getFullYear();
          const mes = String(saidaMinima.getMonth() + 1).padStart(2, '0');
          const dia = String(saidaMinima.getDate()).padStart(2, '0');

          saida.min = `${ano}-${mes}-${dia}`;

          if (saida.value && saida.value <= entrada.value) {
            saida.value = '';
          }
        }

        return noites;
      }

      function atualizarQuarto() {

        const selecionado = document.querySelector(
          'input[name="quarto_id"]:checked'
        );

        if (!selecionado) {
          precoDiario = 0;
          capacidade = 0;
          summaryRoom.textContent = 'Não selecionado';
          capacityHint.textContent = 'Seleciona primeiro um quarto.';
          calcular();
          return;
        }

        precoDiario = Number(selecionado.dataset.price);
        capacidade = Number(selecionado.dataset.capacity);

        const titulo = selecionado
          .closest('.room-content')
          .querySelector('h3').textContent;

        summaryRoom.textContent = titulo;

        pessoas.max = capacidade;
        capacityHint.textContent = `Este quarto suporta até ${capacidade} hóspedes.`;

        if (Number(pessoas.value) > capacidade) {
          pessoas.value = capacidade;
        }

        calcular();
      }

      quartos.forEach(quarto => {
        quarto.addEventListener('change', atualizarQuarto);
      });

      entrada.addEventListener('change', calcular);
      saida.addEventListener('change', calcular);
      pessoas.addEventListener('input', calcular);

      form.addEventListener('submit', event => {

        if (Number(pessoas.value) > capacidade) {
          event.preventDefault();
          alert(`Este quarto suporta apenas ${capacidade} hóspedes.`);
          return;
        }

        if (calcular() < 1) {
          event.preventDefault();
          alert('Seleciona datas válidas para a tua estadia.');
        }
      });

      atualizarQuarto();
    });
  </script>

</body>

</html>