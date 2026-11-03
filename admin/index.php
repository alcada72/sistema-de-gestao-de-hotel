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

    $nome = trim($_POST["nome"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $endereco = trim($_POST["endereco"] ?? "");
    $provinciaId = $_POST["provincia_id"] ?? "";
    $municipioId = $_POST["municipio_id"] ?? "";
    $categoria = trim($_POST["categoria"] ?? "");

    if (
      $nome === "" ||
      $categoria === "" ||
      $endereco === "" ||
      !ctype_digit((string) $provinciaId) ||
      !ctype_digit((string) $municipioId)
    ) {
      redirectWithMessage(
        "Preencha corretamente todos os campos obrigatórios.",
        "error"
      );
    }

    if (
      mb_strlen($nome) > 150 ||
      mb_strlen($endereco) > 255 ||
      mb_strlen($descricao) > 5000
    ) {
      redirectWithMessage(
        "Um dos campos ultrapassou o limite permitido.",
        "error"
      );
    }

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

    if (!$stmt->fetch()) {
      redirectWithMessage(
        "O município selecionado não pertence à província.",
        "error"
      );
    }

    $slug = generateSlug($nome);

    try {

      $stmt = $pdo->prepare("
    INSERT INTO hoteis (
        nome,
        slug,
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
        "descricao" => $descricao ?: null,
        "endereco" => $endereco,
        "provincia" => (int) $provinciaId,
        "municipio" => (int) $municipioId,
        "categoria" => $categoria,
        "criado_por" => $admin["id"]
      ]);

      redirectWithMessage(
        "Hotel cadastrado com sucesso! O registo ficou pendente de aprovação."
      );

    } catch (PDOException $e) {

      error_log($e->getMessage());

      redirectWithMessage(
        "Não foi possível cadastrar o hotel. Verifique os dados.",
        "error"
      );
    }
  }

  if ($action === "approve_hotel") {

    $hotelId = $_POST["hotel_id"] ?? "";

    if (!ctype_digit((string) $hotelId)) {
      redirectWithMessage("Hotel inválido.", "error");
    }

    $stmt = $pdo->prepare("
            UPDATE hoteis
            SET estado = 'aprovado'
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

</head>

<body>

  <div class="dashboard">

    <!-- SIDEBAR -->

    <aside class="sidebar" id="sidebar">

      <div class="sidebar-logo">
        Multiplo<span>Hotel.</span>
      </div>

      <div class="admin-profile">

        <div class="avatar">
          <?= e(mb_substr($admin["nome"], 0, 1)) ?>
        </div>

        <div>
          <strong><?= e($admin["nome"]) ?></strong>
          <small>Administrador</small>
        </div>

      </div>

      <nav class="sidebar-nav">

        <a href="#dashboard" class="active">
          <span>▦</span>
          Dashboard
        </a>

        <a href="#hoteis">
          <span>⌂</span>
          Hotéis
        </a>

        <a href="#novo-hotel">
          <span>＋</span>
          Cadastrar hotel
        </a>

      </nav>

      <div class="sidebar-bottom">

        <a href="../index.php">
          ← Voltar ao site
        </a>

        <a href="../logout.php" class="logout">
          Sair da conta
        </a>

      </div>

    </aside>


    <!-- CONTEÚDO -->

    <main class="main-content" id="dashboard">

      <header class="topbar">

        <button id="menuToggle" class="menu-toggle">
          ☰
        </button>

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

      <section class="stats-grid">

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


      <!-- CADASTRAR HOTEL -->

      <section class="panel" id="novo-hotel">

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

              <label for="nome">Nome do hotel *</label>

              <input type="text" name="nome" id="nome" placeholder="Ex: Hotel Palmeiras" maxlength="150" required>

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


      <!-- LISTAGEM -->

      <section class="panel" id="hoteis">

        <div class="panel-heading">

          <div>
            <span class="section-label">ESTABELECIMENTOS</span>
            <h2>Hotéis cadastrados</h2>
            <p>Consulte e aprove os estabelecimentos.</p>
          </div>

          <span class="total-badge">
            <?= $totalHoteis ?> hotéis
          </span>

        </div>


        <div class="table-container">

          <table>

            <thead>

              <tr>
                <th>Hotel</th>
                <th>Localização</th>
                <th>Categoria</th>
                <th>Estado</th>
                <th>Ação</th>
              </tr>

            </thead>

            <tbody>

              <?php if (count($hoteis) > 0): ?>

                <?php foreach ($hoteis as $hotel): ?>

                  <tr>

                    <td>
                      <strong><?= e($hotel["nome"]) ?></strong>
                      <small>#<?= (int) $hotel["id"] ?></small>
                    </td>

                    <td>
                      <?= e($hotel["municipio"]) ?>,
                      <?= e($hotel["provincia"]) ?>
                    </td>

                    <td>
                      <?= e($hotel["categoria"] ?: "Não definida") ?>
                    </td>

                    <td>

                      <span class="status <?= e($hotel["estado"]) ?>">
                        <?= e(ucfirst($hotel["estado"])) ?>
                      </span>

                    </td>

                    <td>

                      <?php if ($hotel["estado"] === "pendente"): ?>

                        <form method="POST" class="inline-form">

                          <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">

                          <input type="hidden" name="action" value="approve_hotel">

                          <input type="hidden" name="hotel_id" value="<?= (int) $hotel["id"] ?>">

                          <button type="submit" class="btn-approve"
                            onclick="return confirm('Confirmas a aprovação deste hotel?')">
                            Aprovar
                          </button>

                        </form>

                      <?php else: ?>

                        <span class="no-action">—</span>

                      <?php endif; ?>

                    </td>

                  </tr>

                <?php endforeach; ?>

              <?php else: ?>

                <tr>
                  <td colspan="5" class="empty-table">
                    Ainda não existem hotéis cadastrados.
                  </td>
                </tr>

              <?php endif; ?>

            </tbody>

          </table>

        </div>

      </section>


      <footer class="dashboard-footer">
        Hotelaria Angola © <?= date("Y") ?> — Painel administrativo
      </footer>

    </main>

  </div>


  <script>

    const municipios = <?= json_encode(
      $municipios,
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

    const provinciaSelect = document.querySelector("#provincia");
    const municipioSelect = document.querySelector("#municipio");

    provinciaSelect.addEventListener("change", function () {

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

  </script>

</body>

</html>