<?php
declare(strict_types=1);

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

$erro = '';
$dados = [
  'quarto_id' => '',
  'data_entrada' => '',
  'data_saida' => '',
  'quantidade_pessoas' => '1',
  'observacoes' => '',
];

if (empty($_SESSION['csrf_reserva'])) {
  $_SESSION['csrf_reserva'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_reserva'];

// Hotel
$stmt = $pdo->prepare("
    SELECT h.id, h.nome, h.slug, h.descricao, h.endereco, h.telefone,
           p.nome AS provincia_nome, m.nome AS municipio_nome
    FROM hoteis h
    INNER JOIN provincias p ON p.id = h.provincia_id
    INNER JOIN municipios m ON m.id = h.municipio_id
    WHERE h.id = ? AND h.estado = 'aprovado'
    LIMIT 1
");
$stmt->execute([$hotelId]);
$hotel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hotel) {
  http_response_code(404);
  exit('Hotel não encontrado ou ainda não aprovado.');
}

// Imagem do hotel
$stmt = $pdo->prepare("
    SELECT url
    FROM hotel_imagens
    WHERE hotel_id = ?
    ORDER BY CASE WHEN tipo = 'principal' THEN 0 ELSE 1 END, ordem ASC, id ASC
    LIMIT 1
");
$stmt->execute([$hotelId]);
$imagemHotel = $stmt->fetchColumn();

// Andares
$stmt = $pdo->prepare("
    SELECT id, numero_andar, descricao
    FROM andares
    WHERE hotel_id = ?
    ORDER BY numero_andar ASC
");
$stmt->execute([$hotelId]);
$andares = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Datas da pesquisa (GET) ou da tentativa de reserva (POST)
$metodoPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$dataEntradaBusca = trim((string) (
  $metodoPost ? ($_POST['data_entrada'] ?? '') : ($_GET['data_entrada'] ?? '')
));
$dataSaidaBusca = trim((string) (
  $metodoPost ? ($_POST['data_saida'] ?? '') : ($_GET['data_saida'] ?? '')
));

$entradaBusca = DateTime::createFromFormat('!Y-m-d', $dataEntradaBusca);
$saidaBusca = DateTime::createFromFormat('!Y-m-d', $dataSaidaBusca);

$entradaBuscaValida = $entradaBusca instanceof DateTime
  && $entradaBusca->format('Y-m-d') === $dataEntradaBusca;
$saidaBuscaValida = $saidaBusca instanceof DateTime
  && $saidaBusca->format('Y-m-d') === $dataSaidaBusca;

$datasSelecionadas = $entradaBuscaValida
  && $saidaBuscaValida
  && $dataEntradaBusca >= date('Y-m-d')
  && $dataSaidaBusca > $dataEntradaBusca;

if (!$metodoPost && ($dataEntradaBusca !== '' || $dataSaidaBusca !== '') && !$datasSelecionadas) {
  $erro = 'Seleciona datas válidas. A saída deve ser posterior à entrada.';
}

// Todos os quartos ativos, com o andar associado
$stmt = $pdo->prepare("
    SELECT
        q.id, q.numero, q.andar_id,
        q.descricao AS quarto_descricao,
        c.nome AS categoria_nome,
        c.descricao AS categoria_descricao,
        c.capacidade, c.preco_base,
        a.numero_andar, a.descricao AS andar_descricao
    FROM quartos q
    INNER JOIN categorias_quartos c
        ON c.id = q.categoria_id AND c.hotel_id = q.hotel_id
    LEFT JOIN andares a
        ON a.id = q.andar_id AND a.hotel_id = q.hotel_id
    WHERE q.hotel_id = ? AND q.estado = 'ativo' AND c.ativo = 1
    ORDER BY a.numero_andar ASC, q.numero ASC
");
$stmt->execute([$hotelId]);
$quartos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marcar quartos ocupados para o intervalo pesquisado
$quartosOcupados = [];
if ($datasSelecionadas) {
  $stmt = $pdo->prepare("
        SELECT DISTINCT quarto_id
        FROM reservas
        WHERE hotel_id = ?
          AND estado IN ('pendente', 'confirmada', 'checkin')
          AND data_entrada < ?
          AND data_saida > ?
    ");
  $stmt->execute([$hotelId, $dataSaidaBusca, $dataEntradaBusca]);
  $quartosOcupados = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

foreach ($quartos as &$quarto) {
  $quarto['ocupado'] = in_array((int) $quarto['id'], $quartosOcupados, true);
}
unset($quarto);

// Criar reserva
if ($metodoPost) {
  foreach ($dados as $campo => $valor) {
    $dados[$campo] = trim((string) ($_POST[$campo] ?? $valor));
  }

  $token = (string) ($_POST['csrf_token'] ?? '');

  if ($token === '' || !hash_equals($csrfToken, $token)) {
    $erro = 'Pedido inválido. Atualiza a página e tenta novamente.';
  } else {
    $quartoId = filter_var($dados['quarto_id'], FILTER_VALIDATE_INT);
    $quantidadePessoas = filter_var($dados['quantidade_pessoas'], FILTER_VALIDATE_INT);
    $dataEntrada = $dados['data_entrada'];
    $dataSaida = $dados['data_saida'];
    $observacoes = $dados['observacoes'];

    $entrada = DateTime::createFromFormat('!Y-m-d', $dataEntrada);
    $saida = DateTime::createFromFormat('!Y-m-d', $dataSaida);
    $entradaValida = $entrada instanceof DateTime && $entrada->format('Y-m-d') === $dataEntrada;
    $saidaValida = $saida instanceof DateTime && $saida->format('Y-m-d') === $dataSaida;

    if (!$quartoId || $quartoId < 1) {
      $erro = 'Seleciona um quarto disponível.';
    } elseif (!$quantidadePessoas || $quantidadePessoas < 1) {
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

        // Bloqueia o registo do quarto enquanto valida a reserva.
        $stmt = $pdo->prepare("
                    SELECT q.id, q.numero, q.hotel_id, c.capacidade, c.preco_base,
                           c.nome AS categoria_nome
                    FROM quartos q
                    INNER JOIN categorias_quartos c
                        ON c.id = q.categoria_id AND c.hotel_id = q.hotel_id
                    INNER JOIN hoteis h ON h.id = q.hotel_id
                    WHERE q.id = ?
                      AND q.hotel_id = ?
                      AND q.estado = 'ativo'
                      AND c.ativo = 1
                      AND h.estado = 'aprovado'
                    FOR UPDATE
                ");
        $stmt->execute([$quartoId, $hotelId]);
        $quartoSelecionado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quartoSelecionado) {
          throw new RuntimeException('O quarto selecionado não está disponível.');
        }

        if ($quantidadePessoas > (int) $quartoSelecionado['capacidade']) {
          throw new RuntimeException(
            'Este quarto suporta no máximo '
            . (int) $quartoSelecionado['capacidade'] . ' hóspedes.'
          );
        }

        // Verificação definitiva de conflito, dentro da transação.
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
        $stmt->execute([$quartoId, $hotelId, $dataSaida, $dataEntrada]);

        if ($stmt->fetch()) {
          throw new RuntimeException(
            'Este quarto já possui uma reserva para o período selecionado. '
            . 'Escolhe outro quarto ou outras datas.'
          );
        }

        $precoDiaria = (float) $quartoSelecionado['preco_base'];
        $total = $precoDiaria * $quantidadeNoites;
        $codigo = 'RES-' . strtoupper(bin2hex(random_bytes(6)));

        $stmt = $pdo->prepare("
                    INSERT INTO reservas (
                        codigo, hotel_id, quarto_id, usuario_id,
                        data_entrada, data_saida, quantidade_pessoas,
                        quantidade_noites, preco_diaria, total, estado, observacoes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?)
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
          $observacoes !== '' ? $observacoes : null,
        ]);

        $reservaId = (int) $pdo->lastInsertId();
        $pdo->commit();

        header('Location: ' . BASE_URL . '/hotel/reserva?id=' . $reservaId . '&criada=1');
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

$amanha = (new DateTime('tomorrow'))->format('Y-m-d');
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
      <a href="<?= BASE_URL ?>/index.php" class="brand"><span>MultiploHotel</span></a>
      <a href="<?= BASE_URL ?>/index.php" class="back-link">Voltar aos hotéis</a>
    </div>
  </header>

  <main class="booking-container">
    <div class="page-heading">
      <span class="eyebrow">RESERVA DE ALOJAMENTO</span>
      <h1>Faz a tua reserva</h1>
      <p>Escolhe as datas, consulta os quartos e confirma o teu pedido.</p>
    </div>

    <?php if ($erro !== ''): ?>

            <div class="alert alert-error" role="alert"><?= e($erro) ?></div>
    <?php endif; ?>

    <div class="booking-layout">
      <section class="booking-main">

        <article class="hotel-card">
          <?php if ($imagemHotel): ?>
              <img src="<?= e($imagemHotel) ?>" alt="<?= e($hotel['nome']) ?>" class="hotel-image">
          <?php else: ?>
              <div class="hotel-image-placeholder"><span>⌂</span></div>
          <?php endif; ?>

          <div class="hotel-details">
            <span class="hotel-label">HOTEL SELECIONADO</span>
            <h2><?= e($hotel['nome']) ?></h2>
            <p class="hotel-location">
              <span>⌖</span>
              <?= e($hotel['municipio_nome']) ?>, <?= e($hotel['provincia_nome']) ?>
            </p>
            <p class="hotel-address"><?= e($hotel['endereco']) ?></p>
            <?php if (!empty($hotel['descricao'])): ?>
                <p class="hotel-description"><?= e($hotel['descricao']) ?></p>
            <?php endif; ?>
          </div>
        </article>

        <?php if (empty($quartos)): ?>
            <div class="empty-rooms">
              <h3>Nenhum quarto registado</h3>
              <p>Este hotel ainda não possui quartos ativos para reserva.</p>
            </div>
        <?php else: ?>

            <!-- ETAPA 1: DATAS -->
            <section class="availability-search">
              <div class="section-title">
                <span class="step-number">01</span>
                <div>
                  <h2>Quando pretendes ficar?</h2>
                  <p>Escolhe as datas para consultar os quartos disponíveis.</p>
                </div>
              </div>

              <form method="GET" class="availability-form">
                <input type="hidden" name="hotel_id" value="<?= (int) $hotelId ?>">

                <div class="form-group">
                  <label for="search_data_entrada">Data de entrada</label>
                  <input type="date" id="search_data_entrada" name="data_entrada" min="<?= date('Y-m-d') ?>"
                    value="<?= e($dataEntradaBusca) ?>" required>
                </div>

                <div class="form-group">
                  <label for="search_data_saida">Data de saída</label>
                  <input type="date" id="search_data_saida" name="data_saida" min="<?= $amanha ?>"
                    value="<?= e($dataSaidaBusca) ?>" required>
                </div>

                <button type="submit" class="submit-booking">
                  Verificar disponibilidade <span>→</span>
                </button>
              </form>
            </section>

            <?php if (!$datasSelecionadas): ?>
                <div class="availability-empty">
                  <span class="availability-icon">⌕</span>
                  <h3>Consulta a disponibilidade</h3>
                  <p>Seleciona primeiro as datas da tua estadia para visualizar os quartos livres e ocupados.</p>
                </div>
            <?php else: ?>

                <!-- ETAPA 2: UM ÚNICO FORMULÁRIO POST -->
                <form method="POST" id="bookingForm" class="booking-form">
                  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="data_entrada" value="<?= e($dataEntradaBusca) ?>">
                  <input type="hidden" name="data_saida" value="<?= e($dataSaidaBusca) ?>">

                  <section class="form-section rooms-section">
                    <div class="section-title">
                      <span class="step-number">02</span>
                      <div>
                        <h2>Escolhe o teu quarto</h2>
                        <p>Disponibilidade de <?= e($dataEntradaBusca) ?> até <?= e($dataSaidaBusca) ?>.</p>
                      </div>
                    </div>

                    <div class="room-filters">
                      <div class="form-group floor-select-group">
                        <label for="andarSelect">Selecionar andar</label>
                        <select id="andarSelect" class="floor-select">
                          <option value="todos">Todos os andares</option>
                          <?php foreach ($andares as $andar): ?>
                              <option value="<?= (int) $andar['id'] ?>">
                                <?= e($andar['descricao'] ?: 'Andar ' . $andar['numero_andar']) ?>
                              </option>
                          <?php endforeach; ?>
                          <option value="sem-andar">Sem andar definido</option>
                        </select>
                      </div>

                      <div class="room-legend">
                        <span><i class="legend-dot available"></i> Disponível</span>
                        <span><i class="legend-dot occupied"></i> Ocupado</span>
                      </div>
                    </div>

                    <div class="rooms-grid" id="roomsGrid">
                      <?php foreach ($quartos as $quarto): ?>
                          <?php
                          $ocupado = (bool) $quarto['ocupado'];
                          $andarId = $quarto['andar_id'] === null
                            ? 'sem-andar'
                            : (string) $quarto['andar_id'];

                          ?>
                          <label class="room-option <?= $ocupado ? 'room-occupied' : 'room-available' ?>"
                            data-floor="<?= e($andarId) ?>">
                            <input type="radio" name="quarto_id" value="<?= (int) $quarto['id'] ?>"
                              data-price="<?= e((string) $quarto['preco_base']) ?>"
                              data-capacity="<?= (int) $quarto['capacidade'] ?>" <?= $ocupado ? 'disabled' : '' ?>             <?= (string) $dados['quarto_id'] === (string) $quarto['id'] ? 'checked' : '' ?> required>

                            <div class="room-content">
                              <div class="room-top">
                                <span class="room-category"><?= e($quarto['categoria_nome']) ?></span>
                                <?php if ($ocupado): ?>
                                    <span class="room-status occupied">Ocupado</span>
                                <?php else: ?>
                                    <span class="room-status available">Disponível</span>
                                <?php endif; ?>
                              </div>

                              <h3>Quarto <?= e($quarto['numero']) ?></h3>
                              <div class="room-capacity">Até <?= (int) $quarto['capacidade'] ?> hóspedes</div>

                              <div class="room-price">
                                <strong><?= number_format((float) $quarto['preco_base'], 2, ',', '.') ?></strong>
                                <span>Kz / noite</span>
                              </div>
                            </div>
                          </label>
                      <?php endforeach; ?>
                    </div>

                    <div class="no-floor-rooms" id="noFloorRooms" hidden>
                      Não existem quartos neste andar.
                    </div>
                  </section>

                  <section class="form-section">
                    <div class="section-title">
                      <span class="step-number">03</span>
                      <div>
                        <h2>Dados dos hóspedes</h2>
                        <p>Indica quantas pessoas ficarão no quarto.</p>
                      </div>
                    </div>

                    <div class="form-grid">
                      <div class="form-group">
                        <label for="quantidade_pessoas">Número de hóspedes</label>
                        <input type="number" id="quantidade_pessoas" name="quantidade_pessoas" min="1" max="20"
                          value="<?= e($dados['quantidade_pessoas']) ?>" required>
                        <small id="capacityHint">Seleciona primeiro um quarto.</small>
                      </div>
                    </div>
                  </section>

                  <section class="form-section">
                    <div class="section-title">
                      <span class="step-number">04</span>
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
                    Confirmar pedido de reserva <span>→</span>
                  </button>
                  <p class="form-security">
                    A tua reserva será registada como pendente até à confirmação pelo hotel.
                  </p>
                </form>
            <?php endif; ?>
        <?php endif; ?>
      </section>

      <aside class="booking-sidebar">
        <div class="summary-card">
          <h2>Resumo da reserva</h2>
          <div class="summary-hotel">
            <strong><?= e($hotel['nome']) ?></strong>
            <span><?= e($hotel['municipio_nome']) ?></span>
          </div>

          <div class="summary-line"><span>Quarto</span><strong id="summaryRoom">Não selecionado</strong></div>
          <div class="summary-line"><span>Entrada</span><strong
              id="summaryEntry"><?= $datasSelecionadas ? e($dataEntradaBusca) : '--/--/----' ?></strong></div>
          <div class="summary-line"><span>Saída</span><strong
              id="summaryExit"><?= $datasSelecionadas ? e($dataSaidaBusca) : '--/--/----' ?></strong></div>
          <div class="summary-line"><span>Hóspedes</span><strong id="summaryGuests">1</strong></div>
          <div class="summary-line"><span>Noites</span><strong id="summaryNights">0</strong></div>
          <div class="summary-line"><span>Preço por noite</span><strong id="summaryPrice">0,00 Kz</strong></div>

          <div class="summary-total">
            <span>Total estimado</span>
            <strong id="summaryTotal">0,00 Kz</strong>
          </div>

          <p class="summary-note">
            O valor final será calculado novamente pelo servidor com base no preço atual do quarto.
          </p>
        </div>
      </aside>
    </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Filtro de andar: não remove quartos ocupados da grelha.
      const andarSelect = document.getElementById('andarSelect');
      const roomsGrid = document.getElementById('roomsGrid');
      const noFloorRooms = document.getElementById('noFloorRooms');

      if (andarSelect && roomsGrid) {
        const cards = Array.from(roomsGrid.querySelectorAll('.room-option'));

        function filtrarPorAndar() {
          const andar = andarSelect.value;
          let visiveis = 0;

          cards.forEach(card => {
            const mostrar = andar === 'todos' || card.dataset.floor === andar;
            card.hidden = !mostrar;
            if (mostrar) visiveis++;
          });

          noFloorRooms.hidden = visiveis > 0;
          roomsGrid.hidden = visiveis === 0;
        }

        andarSelect.addEventListener('change', filtrarPorAndar);
        filtrarPorAndar();
      }

      // Resumo dinâmico
      const form = document.getElementById('bookingForm');
      if (!form) return;

      const entrada = form.querySelector('input[name="data_entrada"]');
      const saida = form.querySelector('input[name="data_saida"]');
      const pessoas = document.getElementById('quantidade_pessoas');
      const capacityHint = document.getElementById('capacityHint');

      const summaryRoom = document.getElementById('summaryRoom');
      const summaryEntry = document.getElementById('summaryEntry');
      const summaryExit = document.getElementById('summaryExit');
      const summaryGuests = document.getElementById('summaryGuests');
      const summaryNights = document.getElementById('summaryNights');
      const summaryPrice = document.getElementById('summaryPrice');
      const summaryTotal = document.getElementById('summaryTotal');

      let precoDiario = 0;
      let capacidade = 0;

      function moeda(valor) {
        return new Intl.NumberFormat('pt-PT', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).format(valor) + ' Kz';
      }

      function formatarData(valor) {
        if (!valor) return '--/--/----';
        const partes = valor.split('-');
        return partes.length === 3
          ? `${partes[2]}/${partes[1]}/${partes[0]}`
          : '--/--/----';
      }

      function calcularNoites() {
        if (!entrada.value || !saida.value) return 0;
        const inicio = new Date(entrada.value + 'T00:00:00');
        const fim = new Date(saida.value + 'T00:00:00');
        const noites = Math.round((fim - inicio) / 86400000);
        return noites > 0 ? noites : 0;
      }

      function atualizarResumo() {
        const noites = calcularNoites();
        summaryEntry.textContent = formatarData(entrada.value);
        summaryExit.textContent = formatarData(saida.value);
        summaryGuests.textContent = pessoas.value || '1';
        summaryNights.textContent = String(noites);
        summaryPrice.textContent = moeda(precoDiario);
        summaryTotal.textContent = moeda(noites * precoDiario);
      }

      function atualizarQuarto() {
        const selecionado = form.querySelector('input[name="quarto_id"]:checked:not(:disabled)');

        if (!selecionado) {
          precoDiario = 0;
          capacidade = 0;
          summaryRoom.textContent = 'Não selecionado';
          pessoas.removeAttribute('max');
          capacityHint.textContent = 'Seleciona primeiro um quarto.';
          atualizarResumo();
          return;
        }

        precoDiario = Number(selecionado.dataset.price) || 0;
        capacidade = Number(selecionado.dataset.capacity) || 1;

        summaryRoom.textContent = selecionado
          .closest('.room-option')
          .querySelector('h3').textContent;

        pessoas.max = String(capacidade);
        capacityHint.textContent = `Este quarto suporta até ${capacidade} hóspedes.`;

        if (Number(pessoas.value) > capacidade) {
          pessoas.value = String(capacidade);
        }

        atualizarResumo();
      }

      form.querySelectorAll('input[name="quarto_id"]').forEach(radio => {
        radio.addEventListener('change', atualizarQuarto);
      });

      pessoas.addEventListener('input', atualizarResumo);

      form.addEventListener('submit', event => {
        const selecionado = form.querySelector('input[name="quarto_id"]:checked:not(:disabled)');

        if (!selecionado) {
          event.preventDefault();
          alert('Seleciona um quarto disponível.');
          return;
        }

        const quantidade = Number(pessoas.value);
        const limite = Number(selecionado.dataset.capacity);

        if (!Number.isInteger(quantidade) || quantidade < 1 || quantidade > limite) {
          event.preventDefault();
          alert('A quantidade de hóspedes não é válida para este quarto.');
        }
      });

      atualizarQuarto();
    });
  </script>

</body>

</html>