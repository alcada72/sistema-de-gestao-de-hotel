<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/utils.php";

// Verificar sessão
if ((!isLoggedIn()) || !isAdmin() || !isset($_SESSION["user_id"])) {
  header("Location: ../login.php");
  exit;
}

$pdo = getDB();

requireLogin("admin");

$admin = getCurrentUser();

if (
  !$admin ||
  $admin["tipo"] !== "admin" ||
  (int) $admin["ativo"] !== 1
) {
  session_destroy();

  header("Location: ../login.php");
  exit;
}

function redirectWithMessage(string $message, string $type = "success"): void
{
  $_SESSION["flash"] = [
    "message" => $message,
    "type" => $type
  ];

  header("Location: index.php");
  exit;
}

// Criar token CSRF
if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  // Validar CSRF
  $token = $_POST["csrf_token"] ?? "";

  if (
    !is_string($token) ||
    !hash_equals($_SESSION["csrf_token"], $token)
  ) {
    redirectWithMessage("Pedido inválido. Atualize a página.", "error");
  }

  $action = $_POST["action"] ?? "";

  if ($action === "create_hotel") {

    // Dados do proprietário
    $nomeProprietario = trim($_POST["nome_proprietario"] ?? "");
    $emailProprietario = strtolower(trim($_POST["email_proprietario"] ?? ""));
    $telefoneProprietario = trim($_POST["telefone_proprietario"] ?? "");

    // Dados do hotel
    $nome = trim($_POST["nome"] ?? "");
    $nif = trim($_POST["nif"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $endereco = trim($_POST["endereco"] ?? "");

    $provinciaId = $_POST["provincia_id"] ?? "";
    $municipioId = $_POST["municipio_id"] ?? "";
    $categoria = trim($_POST["categoria"] ?? "");

    // Validar campos obrigatórios
    if (
      $nomeProprietario === "" ||
      $emailProprietario === "" ||
      $telefoneProprietario === "" ||
      $nome === "" ||
      $nif === "" ||
      $endereco === "" ||
      $categoria === "" ||
      !ctype_digit((string) $provinciaId) ||
      !ctype_digit((string) $municipioId)
    ) {
      redirectWithMessage(
        "Preencha corretamente todos os campos obrigatórios.",
        "error"
      );
    }

    // Validar email
    if (!filter_var($emailProprietario, FILTER_VALIDATE_EMAIL)) {
      redirectWithMessage("O email do proprietário é inválido.", "error");
    }

    // Validar telefone
    if (!preg_match('/^\+?[0-9]{9,15}$/', $telefoneProprietario)) {
      redirectWithMessage("O telefone do proprietário é inválido.", "error");
    }

    // Limites dos campos
    if (
      mb_strlen($nomeProprietario) > 150 ||
      mb_strlen($emailProprietario) > 150 ||
      mb_strlen($nome) > 150 ||
      mb_strlen($nif) > 50 ||
      mb_strlen($endereco) > 255 ||
      mb_strlen($descricao) > 5000
    ) {
      redirectWithMessage(
        "Um dos campos ultrapassou o limite permitido.",
        "error"
      );
    }

    // Validar categoria
    $categoriasPermitidas = [
      "1_estrela",
      "2_estrelas",
      "3_estrelas",
      "4_estrelas",
      "5_estrelas",
      "sem_classificacao"
    ];

    if (!in_array($categoria, $categoriasPermitidas, true)) {
      redirectWithMessage("Categoria de hotel inválida.", "error");
    }

    // Verificar se o município pertence à província
    $stmt = $pdo->prepare("
        SELECT id
        FROM municipios
        WHERE id = :municipio
        AND provincia_id = :provincia
        LIMIT 1
    ");

    $stmt->execute([
      "municipio" => (int) $municipioId,
      "provincia" => (int) $provinciaId
    ]);

    if (!$stmt->fetchColumn()) {
      redirectWithMessage(
        "O município selecionado não pertence à província.",
        "error"
      );
    }

    // Verificar email e telefone existentes
    $stmt = $pdo->prepare("
        SELECT id
        FROM usuarios
        WHERE email = :email
           OR telefone = :telefone
        LIMIT 1
    ");

    $stmt->execute([
      "email" => $emailProprietario,
      "telefone" => $telefoneProprietario
    ]);

    if ($stmt->fetch()) {
      redirectWithMessage(
        "O email ou telefone do proprietário já está registado.",
        "error"
      );
    }

    // Verificar NIF existente
    $stmt = $pdo->prepare("
        SELECT id
        FROM hoteis
        WHERE nif = :nif
        LIMIT 1
    ");

    $stmt->execute(["nif" => $nif]);

    if ($stmt->fetch()) {
      redirectWithMessage("Este NIF já está associado a outro hotel.", "error");
    }

    try {

      $pdo->beginTransaction();

      /*
       * 1. Criar conta do proprietário
       */

      // Gerar senha inicial aleatória
      $senhaInicial = bin2hex(random_bytes(6));
      $senhaHash = password_hash($telefoneProprietario, PASSWORD_DEFAULT);

      $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                nome,
                email,
                telefone,
                senha,
                tipo
            ) VALUES (
                :nome,
                :email,
                :telefone,
                :senha,
                'cliente'
            )
        ");

      $stmt->execute([
        "nome" => $nomeProprietario,
        "email" => $emailProprietario,
        "telefone" => $telefoneProprietario,
        "senha" => $senhaHash
      ]);

      $proprietarioId = (int) $pdo->lastInsertId();

      /*
       * 2. Criar hotel
       */

      // Gerar slug único
      $slugBase = generateSlug($nome);
      $slug = $slugBase;
      $contador = 1;

      $stmt = $pdo->prepare("
            SELECT id
            FROM hoteis
            WHERE slug = :slug
            LIMIT 1
        ");

      while (true) {

        $stmt->execute(["slug" => $slug]);

        if (!$stmt->fetchColumn()) {
          break;
        }

        $slug = $slugBase . "-" . $contador;
        $contador++;
      }

      $stmt = $pdo->prepare("
            INSERT INTO hoteis (
                nome,
                slug,
                nif,
                descricao,
                endereco,
                provincia_id,
                municipio_id,
                categoria,
                estado,
                criado_por
            ) VALUES (
                :nome,
                :slug,
                :nif,
                :descricao,
                :endereco,
                :provincia,
                :municipio,
                :categoria,
                'pendente',
                :criado_por
            )
        ");

      $stmt->execute([
        "nome" => $nome,
        "slug" => $slug,
        "nif" => $nif,
        "descricao" => $descricao ?: null,
        "endereco" => $endereco,
        "provincia" => (int) $provinciaId,
        "municipio" => (int) $municipioId,
        "categoria" => $categoria,
        "criado_por" => $admin["id"]
      ]);

      $hotelId = (int) $pdo->lastInsertId();

      /*
       * 3. Associar proprietário ao hotel
       */

      $stmt = $pdo->prepare("
            INSERT INTO hotel_usuarios (
                hotel_id,
                usuario_id,
                cargo
            ) VALUES (
                :hotel_id,
                :usuario_id,
                'proprietario'
            )
        ");

      $stmt->execute([
        "hotel_id" => $hotelId,
        "usuario_id" => $proprietarioId
      ]);

      /*
       * 4. Confirmar transação
       */

      $pdo->commit();

      // Guardar credenciais temporariamente na mensagem
      redirectWithMessage(
        "Hotel cadastrado com sucesso! O hotel está pendente de aprovação. " .
        "Conta do proprietário: " . $emailProprietario .
        " | Senha inicial: " . $senhaInicial
      );
    } catch (Throwable $e) {

      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      error_log($e->getMessage());

      redirectWithMessage(
        "Não foi possível cadastrar o hotel. Verifique os dados e tente novamente.",
        "error"
      );
    }
  }

  if ($action === "approve_hotel") {

    $hotelId = $_POST["hotel_id"] ?? "";

    if (!ctype_digit((string) $hotelId) || (int) $hotelId <= 0) {
      redirectWithMessage("Hotel inválido.", "error");
    }

    $stmt = $pdo->prepare("
        UPDATE hoteis
        SET
            estado = 'aprovado',
            aprovado_em = NOW()
        WHERE id = :id
        AND estado = 'pendente'
    ");

    $stmt->execute([
      "id" => (int) $hotelId
    ]);

    if ($stmt->rowCount() > 0) {
      redirectWithMessage("Hotel aprovado com sucesso!");
    }

    redirectWithMessage(
      "Hotel não encontrado ou já não está pendente.",
      "error"
    );
  }

  // Suspender hotel
  if ($action === "suspend_hotel") {

    $hotelId = $_POST["hotel_id"] ?? "";

    if (!ctype_digit((string) $hotelId) || (int) $hotelId <= 0) {
      redirectWithMessage("Hotel inválido.", "error");
    }

    $stmt = $pdo->prepare("
        UPDATE hoteis
        SET estado = 'suspenso'
        WHERE id = :id
        AND estado = 'aprovado'
    ");

    $stmt->execute(["id" => (int) $hotelId]);

    redirectWithMessage(
      $stmt->rowCount() > 0
      ? "Hotel suspenso com sucesso."
      : "Não foi possível suspender este hotel.",
      $stmt->rowCount() > 0 ? "success" : "error"
    );
  }

  // Reativar hotel
  if ($action === "activate_hotel") {

    $hotelId = $_POST["hotel_id"] ?? "";

    if (!ctype_digit((string) $hotelId) || (int) $hotelId <= 0) {
      redirectWithMessage("Hotel inválido.", "error");
    }

    $stmt = $pdo->prepare("
        UPDATE hoteis
        SET estado = 'aprovado'
        WHERE id = :id
        AND estado = 'suspenso'
    ");

    $stmt->execute(["id" => (int) $hotelId]);

    redirectWithMessage(
      $stmt->rowCount() > 0
      ? "Hotel reativado com sucesso."
      : "Não foi possível reativar este hotel.",
      $stmt->rowCount() > 0 ? "success" : "error"
    );
  }

  // Rejeitar hotel
  if ($action === "reject_hotel") {

    $hotelId = $_POST["hotel_id"] ?? "";

    if (!ctype_digit((string) $hotelId) || (int) $hotelId <= 0) {
      redirectWithMessage("Hotel inválido.", "error");
    }

    $stmt = $pdo->prepare("
        UPDATE hoteis
        SET estado = 'rejeitado'
        WHERE id = :id
        AND estado = 'pendente'
    ");

    $stmt->execute(["id" => (int) $hotelId]);

    redirectWithMessage(
      $stmt->rowCount() > 0
      ? "Hotel rejeitado."
      : "Não foi possível rejeitar este hotel.",
      $stmt->rowCount() > 0 ? "success" : "error"
    );
  }
}

$estatisticas = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(estado = 'aprovado') AS aprovados,
        SUM(estado = 'pendente') AS pendentes
    FROM hoteis
")->fetch();

$totalHoteis = (int) ($estatisticas["total"] ?? 0);
$totalAprovados = (int) ($estatisticas["aprovados"] ?? 0);
$totalPendentes = (int) ($estatisticas["pendentes"] ?? 0);

$provincias = $pdo->query("
    SELECT id, nome
    FROM provincias
    ORDER BY nome ASC
")->fetchAll();

$municipios = $pdo->query("
    SELECT id, nome, provincia_id
    FROM municipios
    ORDER BY nome ASC
")->fetchAll();

$stmt = $pdo->query("
    SELECT
        h.id,
        h.nome,
        h.slug,
        h.endereco,
        h.categoria,
        h.estado,
        h.criado_em,
        p.nome AS provincia,
        m.nome AS municipio

    FROM hoteis h

    LEFT JOIN provincias p
        ON p.id = h.provincia_id

    LEFT JOIN municipios m
        ON m.id = h.municipio_id

    ORDER BY
        CASE h.estado
            WHEN 'pendente' THEN 1
            WHEN 'aprovado' THEN 2
            ELSE 3
        END,
        h.id DESC
");

$hoteis = $stmt->fetchAll();

// Hotel selecionado na listagem
$hotelSelecionadoId = filter_input(
  INPUT_GET,
  'hotel_id',
  FILTER_VALIDATE_INT
);

$hotelSelecionado = null;

if ($hotelSelecionadoId && $hotelSelecionadoId > 0) {

  $stmt = $pdo->prepare("
        SELECT
            h.id,
            h.nome,
            h.slug,
            h.nif,
            h.descricao,
            h.endereco,
            h.categoria,
            h.estado,
            h.criado_em,
            h.aprovado_em,
            p.nome AS provincia,
            m.nome AS municipio,
            u.nome AS nome_proprietario,
            u.email AS email_proprietario,
            u.telefone AS telefone_proprietario
        FROM hoteis h

        LEFT JOIN provincias p
            ON p.id = h.provincia_id

        LEFT JOIN municipios m
            ON m.id = h.municipio_id

        LEFT JOIN hotel_usuarios hu
            ON hu.hotel_id = h.id
            AND hu.cargo = 'proprietario'

        LEFT JOIN usuarios u
            ON u.id = hu.usuario_id

        WHERE h.id = :hotel_id
        LIMIT 1
    ");

  $stmt->execute([
    "hotel_id" => $hotelSelecionadoId
  ]);

  $hotelSelecionado = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$hotelSelecionado) {
    redirectWithMessage("Hotel não encontrado.", "error");
  }
}

// Mensagem temporária
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);

?>

<!DOCTYPE html>
<html lang="pt">

<head>

  <meta charset="UTF-8">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Painel Administrativo | Hotelaria</title>

  <link rel="stylesheet" href="./assets/css/style.css">
  <?php require_once __DIR__ . '/../config/favicon.php' ?>


</head>

<body>

  <div class="dashboard">

    <!-- SIDEBAR -->

    <aside class="sidebar" id="sidebar">

      <div class="sidebar-logo">
        Multiplo<span>Hotel.</span>
      </div>

      <div class="admin-profile">
        <div>
          <strong><?= e($admin["nome"]) ?></strong>
          <small>Administrador</small>
        </div>

      </div>

      <nav class="navigation ">

        <button class="nav-link active" data-page="dashboard">
          <span>▦</span>
          Dashboard
        </button>

        <button class="nav-link" data-page="hoteis">
          <span>⌂</span>
          Hotéis
        </button>

        <button class="nav-link" data-page="novo-hotel">
          <span>＋</span>
          Cadastrar hotel
        </button>

      </nav>

      <div class="sidebar-bottom">

        <a href="../logout.php" class="logout">
          Sair da conta
        </a>

      </div>

    </aside>


    <!-- CONTEÚDO -->

    <main class="main-content">

      <header class="topbar">

        <button id="menuToggle" class="menu-toggle">
          ☰
        </button>
        <div class="breadcrumb">
          <span>/</span>
          <strong id="breadcrumbTitle">Dashboard</strong>
        </div>

        <div>
          <span>Painel administrativo</span>
          <h1>Visão geral</h1>
        </div>

        <div class="topbar-user">
          <?= e($admin["nome"]) ?>
        </div>

      </header>

      <?php if ($flash): ?>

      <div class="alert <?= e($flash["type"]) ?>">
        <?= e($flash["message"]) ?>
      </div>

      <?php endif; ?>

      <!-- ESTATÍSTICAS -->
      <div class="page-section active" id="page-dashboard">
        <section class=" stats-grid">

          <article class="stat-card">

            <div class="stat-icon">⌂</div>

            <div>
              <span>Total de hotéis</span>
              <strong><?= $totalHoteis ?></strong>
            </div>

          </article>

          <article class="stat-card">

            <div class="stat-icon approved-icon">✓</div>

            <div>
              <span>Hotéis aprovados</span>
              <strong><?= $totalAprovados ?></strong>
            </div>

          </article>

          <article class="stat-card">

            <div class="stat-icon pending-icon">◷</div>

            <div>
              <span>Pendentes</span>
              <strong><?= $totalPendentes ?></strong>
            </div>

          </article>

        </section>
      </div>

      <!-- CADASTRAR HOTEL -->
      <div class="page-section" id="page-novo-hotel">
        <section class="panel">

          <div class="panel-heading">

            <div>
              <span class="section-label">GESTÃO</span>
              <h2>Cadastrar novo hotel</h2>

              <p>
                Registe um estabelecimento existente
                na plataforma.
              </p>
            </div>

          </div>


          <form method="POST" class="hotel-form">

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">

            <input type="hidden" name="action" value="create_hotel">

            <div class="form-grid">
              <div class="form-group full">
                Informações do Proprietário
              </div>

              <div class="form-group">
                <label for="nome_proprietario">Nome do proprietário *</label>

                <input type="text" name="nome_proprietario" id="nome_proprietario" placeholder="Ex: John Doe"
                  maxlength="150" required>
              </div>

              <div class="form-group">
                <label for="email_proprietario">Email do proprietário *</label>

                <input type="email" name="email_proprietario" id="email_proprietario"
                  placeholder="Ex: exemplo@gmail.com" maxlength="150" required>
              </div>

              <div class="form-group">
                <label for="telefone_proprietario">Telefone do proprietário *</label>

                <input type="tel" name="telefone_proprietario" id="telefone_proprietario"
                  placeholder="Ex: +244923000000" maxlength="16" required>
              </div>
              <span class="form-group full">Informações do Hotel</span>


              <div class="form-group ">
                <label for="nome">Nome do hotel *</label>
                <input type="text" name="nome" id="nome" placeholder="Ex: Hotel Palmeiras" maxlength="150" required>
              </div>

              <div class="form-group">
                <label for="nif">NIF do hotel *</label>

                <input type="text" name="nif" id="nif" placeholder="Ex: 036847LD054" maxlength="50" required>
              </div>


              <div class="form-group">

                <label for="provincia">Província *</label>

                <select name="provincia_id" id="provincia" required>

                  <option value="">Selecione a província</option>

                  <?php foreach ($provincias as $provincia): ?>

                  <option value="<?= (int) $provincia["id"] ?>">
                    <?= e($provincia["nome"]) ?>
                  </option>

                  <?php endforeach; ?>

                </select>

              </div>


              <div class="form-group">

                <label for="municipio">Município *</label>

                <select name="municipio_id" id="municipio" required disabled>

                  <option value="">Selecione primeiro a província</option>

                </select>

              </div>


              <div class="form-group">

                <label for="endereco">Endereço *</label>

                <input type="text" name="endereco" id="endereco" placeholder="Rua, bairro ou avenida" maxlength="255"
                  required>

              </div>


              <div class="form-group">

                <label for="categoria">Categoria</label>

                <select name="categoria" id="categoria" required>
                  <option value="">Selecione</option>
                  <option value="1_estrela">1 estrela</option>
                  <option value="2_estrelas">2 estrelas</option>
                  <option value="3_estrelas">3 estrelas</option>
                  <option value="4_estrelas">4 estrelas</option>
                  <option value="5_estrelas">5 estrelas</option>
                  <option value="sem_classificacao">Sem classificação</option>
                </select>

              </div>


              <div class="form-group full">

                <label for="descricao">Descrição</label>

                <textarea name="descricao" id="descricao" rows="5" maxlength="5000"
                  placeholder="Apresente o estabelecimento..."></textarea>

              </div>

            </div>


            <div class="form-footer">

              <p>
                <strong>Nota:</strong>
                O hotel ficará pendente até ser aprovado.
              </p>

              <button type="submit" class="btn-primary">
                + Cadastrar hotel
              </button>

            </div>

          </form>

        </section>
      </div>

      <!-- LISTAGEM DE HOTÉIS -->
      <div class="page-section" id="page-hoteis">
        <section class="panel">

          <div class="panel-heading">
            <div>
              <span class="section-label">ESTABELECIMENTOS</span>
              <h2>Gestão de hotéis</h2>
              <p>Seleciona um estabelecimento para consultar os seus detalhes.</p>
            </div>

            <span class="total-badge">
              <?= $totalHoteis ?> hotéis
            </span>
          </div>

          <!-- SELETOR -->

          <form method="GET" onsubmit="event.preventDefault()" action="index.php" class="hotel-selector-form">

            <div class="form-group">
              <div class="hotel-selector-form">
                <label for="hotel_id">Selecionar hotel</label>

                <select name="hotel_id" id="hotel_id" class="hotel-selector">
                  <option value="">Selecione um hotel</option>

                  <?php foreach ($hoteis as $hotel): ?>
                  <option value="<?= (int) $hotel['id'] ?>">
                    <?= e($hotel['nome']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div id="hotel-options">
                <p>Selecione um hotel para visualizar as opções.</p>
              </div>
            </div>

            <?php if ($hotelSelecionado): ?>
            <a href="index.php#hoteis" class="btn-clear-selection">
              Limpar seleção
            </a>
            <?php endif; ?>

          </form>

          <div id="hotel-details">
            <div class="hotel-selection-empty">
              <div class="empty-icon">⌂</div>
              <h3>Nenhum hotel selecionado</h3>
              <p>Seleciona um hotel na lista acima para ver os detalhes.</p>
            </div>
          </div>

        </section>
      </div>

      <footer class="dashboard-footer">
        Hotelaria Angola © <?= date("Y") ?> — Painel administrativo
      </footer>

    </main>

  </div>


  <script src="<?= BASE_URL ?>/assets/js/navigate.js"></script>
  <script>
  const municipios = <?= json_encode(
      $municipios,
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

  const provinciaSelect = document.querySelector("#provincia");
  const municipioSelect = document.querySelector("#municipio");

  provinciaSelect.addEventListener("change", function() {

    const provinciaId = this.value;

    municipioSelect.innerHTML =
      '<option value="">Selecione o município</option>';

    municipioSelect.disabled = !provinciaId;

    if (!provinciaId) return;

    const filtrados = municipios.filter(municipio =>
      String(municipio.provincia_id) === String(provinciaId)
    );

    filtrados.forEach(municipio => {

      const option = document.createElement("option");

      option.value = municipio.id;
      option.textContent = municipio.nome;

      municipioSelect.appendChild(option);

    });

    if (filtrados.length === 0) {
      municipioSelect.innerHTML =
        '<option value="">Nenhum município cadastrado</option>';
    }

  });

  const menuToggle = document.querySelector("#menuToggle");
  const sidebar = document.querySelector("#sidebar");

  menuToggle.addEventListener("click", () => {
    sidebar.classList.toggle("open");
  });

  sidebar.querySelectorAll("nav a").forEach(link => {

    link.addEventListener("click", () => {
      sidebar.classList.remove("open");
    });

  });

  const hoteis = <?= json_encode(
      $hoteis,
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;
  const csrfToken = <?= json_encode($_SESSION["csrf_token"]) ?>;
  const baseUrl = <?= json_encode(BASE_URL) ?>;

  const hotelSelect = document.querySelector("#hotel_id");
  const hotelDetails = document.querySelector("#hotel-details");

  const esc = (v) =>
    String(v ?? "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;"
    } [c]));

  const item = (label, value, fallback = "Não informado") =>
    `<div class="detail-item">
     <span>${label}</span>
     <strong>${esc(value || fallback)}</strong>
   </div>`;

  function actionForm(action, id, label, cls, msg) {
    return `
    <form method="POST" class="inline-form">
      <input type="hidden" name="csrf_token" value="${esc(csrfToken)}">
      <input type="hidden" name="action" value="${action}">
      <input type="hidden" name="hotel_id" value="${id}">
      <button type="submit" class="btn-action ${cls}"
        onclick="return confirm('${msg}')">${label}</button>
    </form>`;
  }

  function renderActions(h) {
    let html = `
    <a href="${esc(baseUrl)}/hotel?slug=${encodeURIComponent(h.slug)}"
       target="_blank" rel="noopener noreferrer"
       class="btn-action btn-view">Visualizar página pública</a>`;

    switch (h.estado) {
      case "pendente":
        html += actionForm("approve_hotel", h.id, "Aprovar hotel", "btn-approve", "Desejas aprovar este hotel?");
        html += actionForm("reject_hotel", h.id, "Rejeitar hotel", "btn-reject", "Desejas rejeitar este hotel?");
        break;
      case "aprovado":
        html += actionForm("suspend_hotel", h.id, "Suspender hotel", "btn-suspend", "Desejas suspender este hotel?");
        break;
      case "suspenso":
        html += actionForm("activate_hotel", h.id, "Reativar hotel", "btn-activate", "Desejas reativar este hotel?");
        break;
      default:
        html += `<span class="no-action">Não existem ações disponíveis para este estado.</span>`;
    }
    return html;
  }

  function renderHotel(h) {
    const estado = h.estado.charAt(0).toUpperCase() + h.estado.slice(1);
    const localizacao = [h.municipio, h.provincia].filter(Boolean).join(", ");

    hotelDetails.innerHTML = `
    <div class="selected-hotel">
      <div class="selected-hotel-header">
        <div>
          <span class="section-label">HOTEL SELECIONADO</span>
          <h2>${esc(h.nome)}</h2>
          <p>Código #${esc(h.id)}</p>
        </div>
        <span class="status ${esc(h.estado)}">${esc(estado)}</span>
      </div>

      <div class="hotel-details-grid">
        ${item("Nome do hotel", h.nome)}
        ${item("NIF", h.nif)}
        ${item("Categoria", h.categoria, "Não definida")}
        ${item("Localização", localizacao)}
        ${item("Endereço", h.endereco)}
        ${item("Data de cadastro", h.criado_em, "Não disponível")}
        ${item("Data de aprovação", h.aprovado_em, "Ainda não aprovado")}
        ${item("Slug", h.slug)}
      </div>

      <div class="detail-description">
        <span>Descrição</span>
        <p>${esc(h.descricao || "Sem descrição disponível.")}</p>
      </div>

      <div class="owner-details">
        <h3>Informações do proprietário</h3>
        <div class="hotel-details-grid">
          ${item("Nome", h.nome_proprietario, "Não associado")}
          ${item("Email", h.email_proprietario)}
          ${item("Telefone", h.telefone_proprietario)}
        </div>
      </div>

      <div class="selected-hotel-actions">${renderActions(h)}</div>
    </div>`;
  }

  hotelSelect.addEventListener("change", function() {
    const hotel = hoteis.find((h) => String(h.id) === this.value);

    if (!hotel) {
      hotelDetails.innerHTML = `
      <div class="hotel-selection-empty">
        <div class="empty-icon">⌂</div>
        <h3>Nenhum hotel selecionado</h3>
        <p>Seleciona um hotel na lista acima para ver os detalhes.</p>
      </div>`;
      return;
    }
    renderHotel(hotel);
  });
  </script>

</body>

</html>