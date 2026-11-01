<?php
declare(strict_types=1);
require_once __DIR__ . "/config/auth.php";
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/models/QuartosModels.php";
require_once __DIR__ . "/models/HospedesModels.php";
require_once __DIR__ . "/models/ReservasModels.php";
require_once __DIR__ . "/models/ProdutosMenuModels.php";
require_once __DIR__ . "/models/PedidosModels.php";
require_once __DIR__ . "/models/AndaresModels.php";

if (!isLoggedIn()) {
  header('Location: ' . BASE_URL . '/login.php');
  exit;
}

$user = getCurrentUser() ?: null;

$pedido = null;

$quartosModel = new QuartosModels();
$hospedesModel = new HospedesModels();
$reservasModel = new ReservasModels();
$produtosModel = new ProdutosMenuModels();
$pedidosModel = new PedidosModels();
$andaresModel = new AndaresModels();


if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$models = [
  'quarto' => $quartosModel,
  'hospede' => $hospedesModel,
  'produto' => $produtosModel,
  'andar' => $andaresModel,
];

$models = [
  'quarto' => $quartosModel,
  'hospede' => $hospedesModel,
  'produto' => $produtosModel,
  'andar' => $andaresModel,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {

  $acao = $_POST['acao'];

  $token = $_POST['csrf_token'] ?? '';

  if (
    !is_string($token) ||
    !hash_equals($_SESSION['csrf_token'], $token)
  ) {
    http_response_code(403);
    exit('Requisição inválida. Atualiza a página.');
  }

  $dados = $_POST['dados'] ?? [];

  try {

    if (!is_array($dados)) {
      throw new InvalidArgumentException('Dados inválidos.');
    }

    foreach ($dados as $campo => $valor) {
      if (is_array($valor)) {
        throw new InvalidArgumentException('Dados inválidos.');
      }

      if (is_string($valor)) {
        $dados[$campo] = trim($valor);
      }
    }

    if ($acao === 'produto') {

      $categoriaId = (int) ($dados['categoria_id'] ?? 0);

      if (!$produtosModel->categoriaExiste($categoriaId)) {
        throw new InvalidArgumentException(
          'A categoria selecionada não existe ou está inativa.'
        );
      }

      $dados['disponivel'] = (int) ($dados['disponivel'] ?? 0);

      if (!in_array($dados['disponivel'], [0, 1], true)) {
        throw new InvalidArgumentException('Disponibilidade inválida.');
      }
    }

    // Cadastro de reserva
    if ($acao === 'reserva') {

      $usuarioId = isset($user['id'])
        ? (int) $user['id']
        : null;

      $id = $reservasModel->createReserva(
        $dados,
        $usuarioId
      );

      $_SESSION['flash'] = [
        'tipo' => 'sucesso',
        'mensagem' => 'Reserva registada com sucesso. ID: ' . $id
      ];

    } elseif (isset($models[$acao])) {
      // Cadastros simples
      $id = $models[$acao]->create($dados);

      $_SESSION['flash'] = [
        'tipo' => 'sucesso',
        'mensagem' => 'Cadastro realizado com sucesso. ID: ' . $id
      ];

    } else {
      throw new InvalidArgumentException('Ação desconhecida.');
    }

  } catch (Throwable $e) {

    error_log($e->getMessage());

    $_SESSION['flash'] = [
      'tipo' => 'erro',
      'mensagem' => $e instanceof InvalidArgumentException ||
        $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Não foi possível realizar o cadastro.'
    ];
  }

  header('Location: ' . $_SERVER['PHP_SELF']);
  exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$quartos = $quartosModel->findAll();
$hospedes = $hospedesModel->findAll();
$reservas = $reservasModel->findAllDetalhadas();
$produtos = $produtosModel->findAllComCategoria();
$pedidos = $pedidosModel->findAllDetalhados();
$andares = $andaresModel->findAllComTotalQuartos();

$totalQuartos = $quartosModel->countAll();
$totalDisponiveis = $quartosModel->countByEstado('disponivel');
$quartosDisponiveis = $quartosModel->findByEstado('disponivel');
$totalOcupados = $quartosModel->countByEstado('ocupado');
$totalManutencao = $quartosModel->countByEstado('manutencao');

$pdo = getDB();
foreach ($pedidos as &$pedido) {
  $stmtItens = $pdo->prepare(
    'SELECT GROUP_CONCAT(CONCAT(quantidade, " × ", nome_produto) SEPARATOR ", ") AS itens_resumo,
            COALESCE(SUM(quantidade * preco_unitario), 0) AS total_pedido
     FROM itens_pedido WHERE pedido_id = :pedido_id'
  );
  $stmtItens->execute(['pedido_id' => $pedido['id']]);
  $resumo = $stmtItens->fetch(PDO::FETCH_ASSOC) ?: [];
  $pedido['itens_resumo'] = $resumo['itens_resumo'] ?? '';
  $pedido['total_pedido'] = (float) ($resumo['total_pedido'] ?? 0);
}

unset($pedido);

$dadosDashboard = [
  'rooms' => $quartos,
  'guests' => $hospedes,
  'reservations' => $reservas,
  'products' => $produtos,
  'orders' => $pedidos,
  'andares' => $andares,
  'stats' => [
    'totalRooms' => $totalQuartos,
    'availableRooms' => $totalDisponiveis,
    'occupiedRooms' => $totalOcupados,
    'maintenanceRooms' => $totalManutencao,
    'totalGuests' => count($hospedes),
    'totalReservations' => count($reservas),
    'totalProducts' => count($produtos),
  ],
];


?>

<?php require_once __DIR__ . '/views/layouts/header.php'; ?>
<?php require_once __DIR__ . '/views/layouts/sidebar.php'; ?>

<div class="main-wrapper">
  <?php require_once __DIR__ . '/views/layouts/topbar.php'; ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?>">
      <?= htmlspecialchars($flash['mensagem']) ?>
    </div>
  <?php endif; ?>
  <main class="content">

    <?php require_once __DIR__ . '/views/pages/dashboard.php'; ?>
    <?php require_once __DIR__ . '/views/pages/quartos.php'; ?>
    <?php require_once __DIR__ . '/views/pages/reservas.php'; ?>
    <?php require_once __DIR__ . '/views/pages/restaurante.php'; ?>
    <?php require_once __DIR__ . '/views/pages/hospedes.php'; ?>
    <?php require_once __DIR__ . '/views/pages/relatorios.php'; ?>
    <?php require_once __DIR__ . '/views/pages/configuracoes.php'; ?>

    <?php require_once __DIR__ . '/views/layouts/footer.php'; ?>

  </main>
</div>


<!-- Modal de reserva -->
<div class="modal-backdrop" id="reservationModal" aria-hidden="true">
  <section class="modal reserva" role="dialog" aria-modal="true" aria-labelledby="reservationModalTitle">

    <div class="modal-heading">
      <div>
        <p class="eyebrow">ALOJAMENTO</p>
        <h2 id="reservationModalTitle">Nova reserva</h2>
      </div>

      <button class="icon-button" data-close="reservationModal" aria-label="Fechar">✕</button>
    </div>

    <form method="POST" action="" id="reservationForm" class="form-grid">

      <input type="hidden" name="acao" value="reserva">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <!-- Progresso -->
      <div class="step-progress full-field">
        <div class="step-progress-info">
          <span id="stepLabel">Etapa 1 de 4</span>
          <span id="stepPercent">25%</span>
        </div>

        <div class="progress-track">
          <div id="progressBar"></div>
        </div>

        <div class="step-indicators">
          <span class="step-dot active">1</span>
          <span class="step-dot">2</span>
          <span class="step-dot">3</span>
          <span class="step-dot">4</span>
        </div>
      </div>

      <!-- ETAPA 1: Dados do hóspede -->
      <div class="form-step active full-field" data-step="1">

        <div class="step-heading">
          <h3>Dados do hóspede</h3>
          <p>Começa por preencher os dados pessoais do hóspede.</p>
        </div>

        <label>
          Nome do hóspede
          <input type="text" name="dados[nome]" maxlength="120" required placeholder="Nome completo">
        </label>

        <label>
          Telefone
          <input type="tel" name="dados[telefone]" required maxlength="30" placeholder="+244 ...">
        </label>

        <label>
          Email
          <input type="email" name="dados[email]" maxlength="150">
        </label>

        <label>
          Documento
          <input type="text" name="dados[documento]" maxlength="50">
        </label>

      </div>

      <!-- ETAPA 2: Hospedagem -->
      <div class="form-step full-field" data-step="2">

        <div class="step-heading">
          <h3>Detalhes da hospedagem</h3>
          <p>Indica quantas pessoas ficarão hospedadas.</p>
        </div>

        <label>
          Número de hóspedes
          <input type="number" name="dados[quantidade_pessoas]" min="1" value="1" required>
        </label>

        <label>
          Observações
          <textarea name="dados[observacoes]" rows="4" placeholder="Pedidos especiais ou outras informações"></textarea>
        </label>

      </div>

      <!-- ETAPA 3: Quarto e datas -->
      <div class="form-step full-field" data-step="3">

        <div class="step-heading">
          <h3>Quarto e período</h3>
          <p>Seleciona o quarto e define as datas da hospedagem.</p>
        </div>

        <label>
          Data de entrada
          <input type="date" name="dados[data_entrada]" id="checkinInput" required>
        </label>

        <label>
          Data de saída
          <input type="date" name="dados[data_saida]" id="checkoutInput" required>
        </label>


        <div class="full-field">
          <label>Escolhe o teu quarto</label>

          <div class="rooms-grid" id="roomsGrid">


<?php foreach ($quartos as $quarto): ?>

    <label class="room-card <?= $quarto['estado'] === 'ocupado' ? 'room-occupied' : '' ?>">

        <input
            type="radio"
            name="dados[quarto_id]"
            value="<?= (int) $quarto['id'] ?>"
            data-preco="<?= htmlspecialchars((string) $quarto['preco']) ?>"
            data-capacidade="<?= (int) $quarto['capacidade'] ?>"
           <?= $quarto['estado'] !== 'disponivel' ? 'disabled' : '' ?>
            required
        >

        <div class="room-card-content">

            <div class="room-card-header">

                <span class="room-number">
                    Quarto <?= htmlspecialchars((string) $quarto['numero']) ?>
                </span>

                <?php if ($quarto['estado'] === 'ocupado'): ?>
                    <span class="room-status occupied">Ocupado</span>
                <?php else: ?>
                    <span class="room-check">✓</span>
                <?php endif; ?>

            </div>

            <h4>
                <?= htmlspecialchars((string) $quarto['tipo']) ?>
            </h4>

            <div class="room-info">
                <span>👥 <?= (int) $quarto['capacidade'] ?> Camas</span>
            </div>

            <div class="room-price">
                <?= number_format((float) $quarto['preco'], 2, ',', '.') ?>
                <small>Kz / noite</small>
            </div>

        </div>

    </label>

<?php endforeach; ?>

          </div>
        </div>


      </div>

      <!-- ETAPA 4: Confirmação -->
      <div class="form-step full-field" data-step="4">

        <div class="step-heading">
          <h3>Confirmar reserva</h3>
          <p>Verifica os dados antes de registar a reserva.</p>
        </div>

        <div class="reservation-summary">

          <div>
            <span>Hóspede</span>
            <strong id="summaryGuest">-</strong>
          </div>

          <div>
            <span>Quarto</span>
            <strong id="summaryRoom">-</strong>
          </div>

          <div>
            <span>Número de hóspedes</span>
            <strong id="summaryPeople">-</strong>
          </div>

          <div>
            <span>Entrada</span>
            <strong id="summaryCheckin">-</strong>
          </div>

          <div>
            <span>Saída</span>
            <strong id="summaryCheckout">-</strong>
          </div>

          <div>
            <span>Tempo de hospedagem</span>
            <strong id="reservationNights">0 noites</strong>
          </div>

          <div class="summary-total">
            <span>Valor total estimado</span>
            <strong id="reservationTotal">0 Kz</strong>
          </div>

        </div>

      </div>

      <!-- Navegação -->
      <div class="modal-actions full-field">

        <button type="button" class="btn btn-outline" id="prevStep" hidden>
          Voltar
        </button>

        <button type="button" class="btn btn-primary" id="nextStep">
          Continuar
        </button>

        <button type="submit" class="btn btn-primary" id="submitReservation" hidden>
          Registar reserva
        </button>

      </div>

    </form>
  </section>
</div>

<!-- Modal de cadastro de quarto -->
<div class="modal-backdrop" id="roomModal" aria-hidden="true">
  <section class="modal" role="dialog" aria-modal="true" aria-labelledby="roomModalTitle">
    <div class="modal-heading">
      <div>
        <p class="eyebrow">ALOJAMENTO</p>
        <h2 id="roomModalTitle">Cadastrar quarto</h2>
      </div>
      <button class="icon-button" data-close="roomModal" aria-label="Fechar">
        ✕
      </button>
    </div>
    <form method="POST" action="" class="form-grid">

      <input type="hidden" name="acao" value="quarto">

      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <label>
        Número do quarto
        <input name="dados[numero]" type="number" min="1" required>
      </label>

      <label>
        Tipo
        <select name="dados[tipo]" required>
          <option value="normal">Normal</option>
          <option value="vip">VIP</option>
        </select>
      </label>

      <label>
        Andar
        <select name="dados[andar_id]" required>
          <option value="">Seleciona o andar</option>

          <?php foreach ($andares as $andar): ?>
            <option value="<?= (int) $andar['id'] ?>">
              Andar <?= htmlspecialchars((string) $andar['numero_andar']) ?>
              - <?= htmlspecialchars((string) $andar['descricao']) ?>
            </option>
          <?php endforeach; ?>

        </select>
      </label>

      <label>
        Preço por noite (Kz)
        <input name="dados[preco]" type="number" min="0" step="0.01" required>
      </label>

      <label>
        Capacidade
        <input name="dados[capacidade]" type="number" min="1" value="2" required>
      </label>

      <label>
        Estado
        <select name="dados[estado]" required>
          <option value="disponivel">Disponível</option>
          <option value="manutencao">Manutenção</option>
        </select>
      </label>

      <label class="full-field">
        Descrição
        <input name="dados[descricao]" maxlength="200" placeholder="Ex.: quarto com varanda">
      </label>

      <div class="modal-actions full-field">

        <button type="reset" class="btn btn-outline">
          Limpar
        </button>

        <button type="submit" class="btn btn-primary">
          Guardar quarto
        </button>

      </div>

    </form>
  </section>
</div>

<!-- Modal de cadastro de produtos -->
<div class="modal-backdrop" id="productModal" aria-hidden="true">
  <section class="modal" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
    <div class="modal-heading">
      <div>
        <p class="eyebrow">RESTAURANTE</p>
        <h2 id="productModalTitle">Adicionar produto</h2>
      </div>
      <button class="icon-button" data-close="productModal" aria-label="Fechar">
        ✕
      </button>
    </div>

    <form method="POST" action="" id="productForm" class="form-grid">

      <input type="hidden" name="acao" value="produto">

      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <label>
        Nome do produto

        <input type="text" name="dados[nome]" maxlength="120" required>
      </label>

      <label>
        Categoria

        <select name="dados[categoria_id]" required>

          <option value="">Seleciona uma categoria</option>

          <?php
          $categorias = getDB()
            ->query('SELECT id, nome FROM categorias_menu WHERE ativo = 1 ORDER BY nome')
            ->fetchAll(PDO::FETCH_ASSOC);
          ?>

          <?php foreach ($categorias as $categoria): ?>

            <option value="<?= (int) $categoria['id'] ?>">
              <?= htmlspecialchars($categoria['nome']) ?>
            </option>

          <?php endforeach; ?>

        </select>
      </label>

      <label>
        Preço (Kz)

        <input type="number" name="dados[preco]" min="0" step="0.01" required>
      </label>

      <label>
        Tipo

        <select name="dados[tipo]" required>
          <option value="prato">Prato</option>
          <option value="bebida">Bebida</option>
          <option value="sobremesa">Sobremesa</option>
        </select>
      </label>

      <label>
        Disponibilidade

        <select name="dados[disponivel]" required>
          <option value="1">Disponível</option>
          <option value="0">Indisponível</option>
        </select>
      </label>

      <label class="full-field">
        Descrição

        <textarea name="dados[descricao]" maxlength="200" rows="3"></textarea>
      </label>

      <div class="modal-actions full-field">

        <button type="button" class="btn btn-outline" data-close="productModal">
          Cancelar
        </button>

        <button type="submit" class="btn btn-primary">
          Guardar produto
        </button>

      </div>

    </form>

  </section>
</div>

<!-- Modal de detalhes do Quarto -->
<div class="modal-backdrop" id="productDetailModal" aria-hidden="true">
  <section class="modal" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
    <div class="modal-heading">
      <div>
        <p class="eyebrow">QUARTOS</p>
        <h2 id="productModalTitle">Detalhes do Quarto</h2>
      </div>
      <button class="icon-button" data-close="productDetailModal" aria-label="Fechar">
        ✕
      </button>
    </div>
    <div id="productDetailForm" class="form-grid">
      <label>Nome do quarto <input disabled value="101" /></label>

      <label>Tipo <input disabled name="name" value="VIP" /> </label>
      <label>Preço/noite<input disabled value="25.000.00kz" /></label>
      <label>Disponibilidade
        <input disabled value="Disponivel" />
      </label>
      <label class="full-field">Total de cama<input value="4" disabled /></label>

      <div class="modal-actions full-field">
        <button type="button" class="btn btn-warning" data-close="productDetailModal">
          Fechar detalhes
        </button>
        <button type="button" class="btn btn-primary" data-close="productDetailModal">Concluído</button>
      </div>
    </div>
  </section>
</div>


<div class="toast" id="toast" role="status" aria-live="polite"></div>

<?php require_once __DIR__ . '/views/layouts/scripts.php'; ?>