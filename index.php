
<?php

require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/auth.php";
require_once __DIR__ . "/config/utils.php";

$pesquisa = trim($_GET["q"] ?? "");
$provinciaId = $_GET["provincia"] ?? "";

$pdo = getDB();

$user = getCurrentUser();

$stmt = $pdo->query("
    SELECT id, nome
    FROM provincias
    ORDER BY nome ASC
");

$provincias = $stmt->fetchAll();

$sql = "
    SELECT
        h.id,
        h.nome,
        h.slug,
        h.descricao,
        h.endereco,
        h.categoria,
        p.nome AS provincia,
        m.nome AS municipio,

        (
            SELECT hi.url
            FROM hotel_imagens hi
            WHERE hi.hotel_id = h.id
            ORDER BY hi.ordem ASC, hi.id ASC
            LIMIT 1
        ) AS imagem,

        (
            SELECT COUNT(*)
            FROM servicos s
            WHERE s.hotel_id = h.id
            AND s.disponivel = 1
        ) AS total_servicos

    FROM hoteis h

    LEFT JOIN provincias p
        ON p.id = h.provincia_id

    LEFT JOIN municipios m
        ON m.id = h.municipio_id

    WHERE h.estado = 'aprovado'
";

$params = [];

if ($pesquisa !== "") {
    $sql .= "
        AND (
            h.nome LIKE :nome
            OR h.endereco LIKE :endereco
            OR m.nome LIKE :municipio
        )
    ";

    $params["nome"] = "%{$pesquisa}%";
    $params["endereco"] = "%{$pesquisa}%";
    $params["municipio"] = "%{$pesquisa}%";
}

if ($provinciaId !== "" && ctype_digit((string)$provinciaId)) {
    $sql .= " AND h.provincia_id = :provincia";
    $params["provincia"] = (int)$provinciaId;
}

$sql .= " ORDER BY h.criado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$hoteis = $stmt->fetchAll();

$totalHoteis = count($hoteis);

$totalServicos = $pdo->query("
    SELECT COUNT(*)
    FROM servicos
    WHERE disponivel = 1
")->fetchColumn();

if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['acao'] ?? '') === 'login') {

    $dados = $_POST['dados'] ?? [];

    $email = trim($dados['email'] ?? '');
    $senha = $dados['senha'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $erroLogin = 'Sessão inválida. Atualize a página.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) && $senha !== '') {

        if (login($email, $senha)) {
            header('Location: ' . BASE_URL . '/route.php');
            exit;
        }

        $erroLogin = 'Email ou senha incorretos.';

    } else {
        $erroLogin = 'Preencha corretamente todos os campos.';
    }
}

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Encontre hotéis em Angola, conheça os serviços e faça a sua reserva."
    >

    <title>MutiploHotel Angola | Encontre o seu hotel</title>

    <link rel="stylesheet" href="./assets/css/index.css">
    <link rel="stylesheet" href="./assets/css/modalLogin.css">
    <link rel="stylesheet" href="./assets/css/form.css">
    <link rel="stylesheet" href="./assets/css/btn.css">
</head>

<body>

    <!-- HEADER -->
    <header class="header">

        <div class="container navbar">

            <a href="#home" class="logo">
                Multiplo<span>Hotel</span>
            </a>

            <button
                class="menu-toggle"
                id="menuToggle"
                aria-label="Abrir menu"
                aria-expanded="false"
            >
                ☰
            </button>

            <nav class="nav-menu" id="navMenu">

                <a href="#home">Início</a>

                <a href="#hoteis">Hotéis</a>

                <a href="#servicos">Serviços</a>

                <a href="#sobre">Sobre nós</a>

            </nav>

            <div class="nav-actions">
               <?php if (!isLoggedIn()): ?>
                <a data-open="loginModal" class=" btn">
                    Entrar
                </a>

                <a href="register.php" target="_self" class="btn-register">
                    Crair conta
                </a>
                <?php endif; ?>

                <?php if (isLoggedIn()): ?>
                    <a class="hidden" >
                        <?= e($user['nome'] ?? 'Usuário') ?>
                    </a>

                    <a href="<?= BASE_URL ?>/cliente">
                      <div class="avatar">
                         <?= e(mb_substr($user["nome"], 0, 1)) ?>
                      </div>
                    </a>
                <?php endif; ?>

            </div>

        </div>

    </header>


    <!-- HERO -->
    <section class="hero" id="home">

        <div class="hero-overlay"></div>

        <div class="container hero-content">

            <span class="hero-label">
                DESCUBRA ANGOLA
            </span>

            <h1>
                Encontre o lugar perfeito
                para a sua próxima estadia.
            </h1>

            <p>
                Descubra hotéis, explore serviços e reserve
                a sua próxima experiência em Angola.
            </p>

            <!-- PESQUISA -->
            <form action="#hoteis" method="GET" class="search-box">

                <div class="search-field">

                    <label for="q">Onde pretende ficar?</label>

                    <input
                        type="text"
                        id="q"
                        name="q"
                        placeholder="Nome do hotel ou município..."
                        value="<?= e($pesquisa) ?>"
                    >

                </div>

                <div class="search-field">

                    <label for="provincia">Província</label>

                    <select name="provincia" id="provincia">

                        <option value="">Todas as províncias</option>

                        <?php foreach ($provincias as $provincia): ?>

                            <option
                                value="<?= e($provincia['id']) ?>"
                                <?= (string)$provinciaId === (string)$provincia['id'] ? 'selected' : '' ?>
                            >
                                <?= e($provincia['nome']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button type="submit" class="btn-search">
                    Pesquisar hotéis
                </button>

            </form>

        </div>

    </section>


    <!-- ESTATÍSTICAS -->
    <section class="statistics">

        <div class="container statistics-grid">

            <div class="stat-item">
                <strong><?= $totalHoteis ?>+</strong>
                <span>Hotéis encontrados</span>
            </div>

            <div class="stat-item">
                <strong><?= (int)$totalServicos ?>+</strong>
                <span>Serviços disponíveis</span>
            </div>

            <div class="stat-item">
                <strong>18+</strong>
                <span>Províncias para explorar</span>
            </div>

        </div>

    </section>


    <!-- HOTEIS -->
    <section class="section hotels-section" id="hoteis">

        <div class="container">

            <div class="section-heading">

                <div>
                    <span class="section-label">
                        ACOMODAÇÕES
                    </span>

                    <h2>Explore os nossos hotéis</h2>

                    <p>
                        Descubra lugares especiais para a sua estadia.
                    </p>
                </div>

                <span class="results-count">
                    <?= $totalHoteis ?> resultados
                </span>

            </div>


            <div class="hotel-grid">

                <?php if (count($hoteis) > 0): ?>

                    <?php foreach ($hoteis as $hotel): ?>

                        <article class="hotel-card">

                            <div class="hotel-image">

                                <?php if (!empty($hotel["imagem"])): ?>

                                    <img
                                        src="<?= e($hotel["imagem"]) ?>"
                                        alt="<?= e($hotel["nome"]) ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <div class="image-placeholder">
                                        <span>Hotelaria</span>
                                    </div>

                                <?php endif; ?>

                                <span class="hotel-category">
                                    <?= e($hotel["categoria"] ?: "Hotel") ?>
                                </span>

                            </div>

                            <div class="hotel-content">

                                <div class="hotel-location">
                                    <span>📍</span>

                                    <?= e($hotel["municipio"]) ?>,
                                    <?= e($hotel["provincia"]) ?>
                                </div>

                                <h3>
                                    <?= e($hotel["nome"]) ?>
                                </h3>

                                <p class="hotel-description">

                                    <?= e(
                                        mb_strimwidth(
                                            $hotel["descricao"] ?? "",
                                            0,
                                            50,
                                            "..."
                                        )
                                    ) ?>

                                </p>

                                <div class="hotel-info">

                                    <span>
                                        ✦ <?= (int)$hotel["total_servicos"] ?>
                                        serviços
                                    </span>

                                </div>

                                <a
                                    href="hotel?slug=<?= urlencode($hotel["slug"]) ?>"
                                    class="btn-hotel"
                                >
                                    Explorar hotel
                                    <span>→</span>
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">

                        <h3>Nenhum hotel encontrado</h3>

                        <p>
                            Experimente pesquisar outra localização
                            ou remover os filtros.
                        </p>

                        <a href="<?= BASE_URL ?>/index.php" class="btn-register">
                            Ver todos os hotéis
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>


    <!-- SERVIÇOS -->
    <section class="section services-section" id="servicos">

        <div class="container">

            <div class="section-heading centered">

                <span class="section-label">
                    EXPERIÊNCIAS
                </span>

                <h2>Mais do que uma estadia</h2>

                <p>
                    Encontre serviços que tornam a sua experiência
                    ainda mais confortável.
                </p>

            </div>

            <div class="services-grid">

                <article class="service-card">
                    <div class="service-icon">🛏️</div>
                    <h3>Quartos confortáveis</h3>
                    <p>
                        Encontre acomodações para diferentes
                        necessidades e orçamentos.
                    </p>
                </article>

                <article class="service-card">
                    <div class="service-icon">🍽️</div>
                    <h3>Restaurantes</h3>
                    <p>
                        Descubra hotéis que oferecem experiências
                        gastronómicas.
                    </p>
                </article>

                <article class="service-card">
                    <div class="service-icon">🏊</div>
                    <h3>Lazer e bem-estar</h3>
                    <p>
                        Explore piscinas, ginásios e outros
                        serviços disponíveis.
                    </p>
                </article>

                <article class="service-card">
                    <div class="service-icon">🚗</div>
                    <h3>Serviços adicionais</h3>
                    <p>
                        Conheça as comodidades e serviços
                        oferecidos por cada hotel.
                    </p>
                </article>

            </div>

        </div>

    </section>


    <!-- SOBRE -->
    <section class="about-section" id="sobre">

        <div class="container about-content">

            <div>
                <span class="section-label">
                    HOTELARIA ANGOLA
                </span>

                <h2>
                    A sua próxima experiência
                    começa aqui.
                </h2>

                <p>
                    Somos uma plataforma que aproxima hotéis
                    e clientes, facilitando a descoberta de
                    acomodações e a realização de reservas
                    em diferentes regiões de Angola.
                </p>

                <a href="#hoteis" class="btn-register">
                    Encontrar um hotel
                </a>
            </div>

            <div class="about-highlight">
           <!--      <span>✦</span> -->
                <h3>Descubra. Compare. Reserve.</h3>
                <p>
                    Tudo o que precisa para encontrar
                    a sua próxima estadia.
                </p>
            </div>

        </div>

    </section>


    <!-- CTA PARA HOTEIS -->
    <section class="partner-section">

        <div class="container partner-content">

            <div>
                <span class="section-label">
                    SEJA NOSSO PARCEIRO
                </span>

                <h2>
                    Tem um hotel?
                    Apresente-o ao mundo.
                </h2>

                <p>
                    Cadastre o seu estabelecimento, apresente
                    os seus serviços e alcance novos clientes.
                </p>
            </div>

            <a href="registrar.php" class="btn-partner">
                Entre em contacto →
            </a>

        </div>

    </section>


    <!-- FOOTER -->
    <footer class="footer">

        <div class="container footer-content">

            <div>
                <a href="#home" class="logo footer-logo">
                    Hotel<span>aria.</span>
                </a>

                <p>
                    Descubra os melhores lugares para
                    ficar em Angola.
                </p>
            </div>

            <div class="footer-links">

                <a href="#home">Início</a>
                <a href="#hoteis">Hotéis</a>
                <a href="#servicos">Serviços</a>
                <a href="#sobre">Sobre nós</a>

            </div>

        </div>

        <div class="footer-bottom">
            © <?= date("Y") ?> MultiploHotel Angola. Todos os direitos reservados.
        </div>

    </footer>


   <div class="modal-backdrop" id="loginModal" aria-hidden="true">
  <section class="modal" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
        <div class="modal-heading">
      <div>
        <p class="eyebrow">Faça login, para continuar</p>
        <h2 id="loginModalTitle">Login</h2>
      </div>
      <button class="icon-button" data-close="loginModal" aria-label="Fechar">
        ✕
      </button>
    </div>
     <form method="post" class="form-grid" >

      <input type="hidden" name="acao" value="login">

      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <label class="full-field">
        E-mail
        <input name="dados[email]" placeholder="Digite o seu e-mail..." type="email" required>
      </label>

      <label class="full-field">
        Senha
        <input name="dados[senha]" placeholder="Digite a sua senha..." type="password" required>
      </label>
      
      <div class="modal-actions col full-field">

        <button type="submit" class="btn btn-primary full-field full-width">
          Fazer login
        </button>
      </div>
      <p>
        Não tens uma conta?
        <a href="<?= BASE_URL ?>/register.php">Criar conta</a>
      </p>
     </form>
  </section>

    </div>

    <script src="./assets/js/index.js"></script>
    <script src="./assets/js/modal.js"></script>

</body>
</html>
