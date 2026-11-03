<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/utils.php';

$pdo = getDB();

function imagemValida($url)
{
  $url = trim((string) $url);

  return $url !== ''
    ? $url
    : BASE_URL . '/assets/images/hotel-placeholder.jpg';
}
$usuario = getCurrentUser();


/**
 * Obter slug
 */
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
  http_response_code(404);
  exit('Hotel não encontrado.');
}

/**
 * Buscar informações do hotel
 */
$sql = "
    SELECT
        h.*,
        p.nome AS provincia,
        m.nome AS municipio
    FROM hoteis h
    LEFT JOIN provincias p ON p.id = h.provincia_id
    LEFT JOIN municipios m ON m.id = h.municipio_id
    WHERE h.slug = :slug
      AND h.estado = 'aprovado'
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['slug' => $slug]);

$hotel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hotel) {
  http_response_code(404);
  exit('Este hotel não existe ou ainda não foi aprovado.');
}

$hotelId = $hotel['id'];

/**
 * Buscar imagens
 */
$stmt = $pdo->prepare("
    SELECT id, url, ordem
    FROM hotel_imagens
    WHERE hotel_id = ?
    ORDER BY ordem ASC, id ASC
");

$stmt->execute([$hotelId]);
$imagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Buscar serviços disponíveis
 */
$stmt = $pdo->prepare("
    SELECT id, nome
    FROM servicos
    WHERE hotel_id = ?
      AND disponivel = 1
    ORDER BY nome ASC
");

$stmt->execute([$hotelId]);
$servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Buscar andares
 */
$stmt = $pdo->prepare("
    SELECT id, numero_andar
    FROM andares
    WHERE hotel_id = ?
    ORDER BY numero_andar ASC
");

$stmt->execute([$hotelId]);
$andares = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        q.*,
        a.numero_andar AS andar_numero,
        c.nome AS categoria_nome,
        c.preco_base AS preco,
        c.capacidade AS capacidade
    FROM quartos q

    LEFT JOIN andares a
        ON a.id = q.andar_id
        AND a.hotel_id = q.hotel_id

    LEFT JOIN categorias_quartos c
        ON c.id = q.categoria_id
        AND c.hotel_id = q.hotel_id

    WHERE q.hotel_id = :hotel_id

    ORDER BY
        a.numero_andar ASC,
        q.numero ASC,
        q.id ASC
");

$stmt->execute([
  'hotel_id' => $hotelId
]);

$quartos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt->execute([$hotelId]);
$quartos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$quartosPorAndar = [];

foreach ($quartos as $quarto) {
  $andarId = $quarto['andar_id'] ?? 0;

  $quartosPorAndar[$andarId][] = $quarto;
}

$imagemPrincipal = $imagens[0]['url'] ?? 'https://cdn1.iconfinder.com/data/icons/location-red/64/HOTEL-place-building-location_pin-placeholder-512.png';

?>

<!DOCTYPE html>
<html lang="pt-AO">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?= e($hotel['nome']) ?> | MultiploHotel</title>

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/hotel.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/btn.css">

</head>

<body>

  <header class="header">

    <div class="container header-content">

      <a href="<?= BASE_URL ?>/index.php" class="logo">
        MultiploHotel
      </a>

      <nav>
        <a href="<?= BASE_URL ?>/index.php">Início</a>
        <a href="<?= BASE_URL ?>/index.php#servicos">Serviços</a>
        <a href="<?= BASE_URL ?>/hotel?slug=<?= urlencode($hotel["slug"]) ?>#quartos">Quartos</a>
      </nav>

      <?php if (!isLoggedIn()): ?>
        <a href="<?= BASE_URL ?>/login.php" class="login-link">
          Entrar
        </a>
      <?php else: ?>
        <div class="avatar">
          <?= e(mb_substr($usuario["nome"], 0, 2)) ?>
        </div>
      <?php endif; ?>

    </div>

  </header>


  <main>

    <!-- Informações principais -->

    <section class="hotel-hero">

      <div class="container">

        <div class="breadcrumb">
          <a href="<?= BASE_URL ?>/index.php">Início</a>
          <span>/</span>
          <span><?= e($hotel['nome']) ?></span>
        </div>

        <div class="hotel-heading">

          <div>
            <?php if (!empty($hotel['categoria'])): ?>
              <span class="hotel-category">
                <?= e($hotel['categoria']) ?>
              </span>
            <?php endif; ?>

            <h1><?= e($hotel['nome']) ?></h1>

            <p class="location">
              📍 <?= e($hotel['endereco']) ?>,
              <?= e($hotel['municipio']) ?>,
              <?= e($hotel['provincia']) ?>
            </p>

          </div>

          <a href="#quartos" class="btn-primary">
            Ver quartos
          </a>

        </div>


        <!-- Galeria -->

        <div class="gallery">

          <div class="gallery-main">
            <img src="<?= e(imagemValida($imagemPrincipal)) ?>" alt="<?= e($hotel['nome']) ?>">
          </div>

          <div class="gallery-side">

            <?php for ($i = 1; $i < count($imagens); $i++): ?>

              <div class="gallery-small">

                <?php if (isset($imagens[$i])): ?>

                  <img src="<?= e(imagemValida($imagens[$i]['url'])) ?>" alt="Imagem do hotel">

                <?php else: ?>

                  <div class="empty-image">
                    Sem imagem
                  </div>

                <?php endif; ?>

              </div>

            <?php endfor; ?>

          </div>

        </div>

      </div>

    </section>


    <div class="container content-layout">

      <div class="main-content">

        <!-- Sobre o hotel -->

        <section class="section">

          <h2>Sobre este hotel</h2>

          <p class="description">
            <?= nl2br(e($hotel['descricao'])) ?>
          </p>

          <div class="hotel-details">

            <div>
              <span>Localização</span>
              <strong><?= e($hotel['provincia']) ?></strong>
            </div>

            <div>
              <span>Município</span>
              <strong><?= e($hotel['municipio']) ?></strong>
            </div>

            <div>
              <span>Categoria</span>
              <strong><?= e($hotel['categoria']) ?></strong>
            </div>

          </div>

        </section>


        <!-- Serviços -->

        <section class="section" id="servicos">

          <h2>Serviços disponíveis</h2>

          <?php if (count($servicos) > 0): ?>

            <div class="services-grid">

              <?php foreach ($servicos as $servico): ?>

                <div class="service-item">

                  <span class="service-icon">✓</span>

                  <span>
                    <?= e($servico['nome']) ?>
                  </span>

                </div>

              <?php endforeach; ?>

            </div>

          <?php else: ?>

            <p class="empty-message">
              Este hotel ainda não possui serviços cadastrados.
            </p>

          <?php endif; ?>

        </section>


        <!-- Quartos -->

        <section class="section" id="quartos">

          <h2>Quartos disponíveis</h2>

          <p class="section-subtitle">
            Conheça os quartos e escolha aquele que melhor
            corresponde às suas necessidades.
          </p>

          <?php if (count($quartos) > 0): ?>

            <?php foreach ($andares as $andar): ?>

              <?php
              $andarId = $andar['id'];
              $quartosDoAndar = $quartosPorAndar[$andarId] ?? [];
              ?>

              <?php if (count($quartosDoAndar) > 0): ?>

                <div class="floor-section">

                  <h3>
                    Andar Nº <?= e($andar['numero_andar']) ?>
                  </h3>

                  <div class="rooms-grid">

                    <?php foreach ($quartosDoAndar as $quarto): ?>

                      <article class="room-card">

                        <div class="room-image">

                          <?php if (!empty($quarto['imagem'])): ?>

                            <img src="<?= e($quarto['imagem']) ?>" alt="<?= e($quarto['nome']) ?>">

                          <?php else: ?>

                            <div class="empty-image">
                              Sem imagem
                            </div>

                          <?php endif; ?>

                        </div>

                        <div class="room-info">

                          <span class="room-category">
                            <?= e($quarto['categoria_nome']) ?>
                          </span>

                          <h4>
                            Quarto <?= e($quarto['numero']) ?>
                          </h4>

                          <p>
                            <?= e($quarto['descricao']) ?>
                          </p>

                          <div class="room-capacity">
                            👤 Capacidade:
                            <?= e($quarto['capacidade']) ?>
                            pessoas
                          </div>

                          <div class="room-footer">

                            <div class="room-price">

                              <strong>
                                <?= number_format(
                                  (float) $quarto['preco'],
                                  2,
                                  ',',
                                  '.'
                                ) ?> Kz
                              </strong>

                              <small>por noite</small>

                            </div>

                            <a href="<?= BASE_URL ?>/hotel/reservar?hotel_id=<?= (int) $hotel['id'] ?>" class="btn-reserve">
                              Reservar
                            </a>

                          </div>

                        </div>

                      </article>

                    <?php endforeach; ?>

                  </div>

                </div>

              <?php endif; ?>

            <?php endforeach; ?>

            <!-- Quartos sem andar associado -->

            <?php if (!empty($quartosPorAndar[0])): ?>

              <div class="floor-section">

                <h3>Outros quartos</h3>

                <div class="rooms-grid">

                  <?php foreach ($quartosPorAndar[0] as $quarto): ?>

                    <article class="room-card">

                      <div class="room-info">

                        <h4><?= e($quarto['nome']) ?></h4>

                        <p><?= e($quarto['descricao']) ?></p>

                        <strong>
                          <?= number_format(
                            (float) $quarto['preco'],
                            2,
                            ',',
                            '.'
                          ) ?> Kz / noite
                        </strong>

                        <a href="reservar.php?quarto_id=<?= (int) $quarto['id'] ?>" class="btn-reserve">
                          Reservar
                        </a>

                      </div>

                    </article>

                  <?php endforeach; ?>

                </div>

              </div>

            <?php endif; ?>

          <?php else: ?>

            <p class="empty-message">
              Este hotel ainda não possui quartos cadastrados.
            </p>

          <?php endif; ?>

        </section>

      </div>


      <!-- Resumo lateral -->

      <aside class="sidebar">

        <div class="booking-card">

          <h3>Gostou deste hotel?</h3>

          <p>
            Consulte os quartos disponíveis e faça a sua reserva.
          </p>

          <a href="<?= BASE_URL ?>/hotel/reservar?hotel_id=<?= (int) $hotel['id'] ?>" class="btn-primary full-width">
            Reservar um Quarto
          </a>
          <br>
          <br>
          <a href="#quartos" class="btn-outline btn full-width">
            Consultar quartos
          </a>

          <div class="booking-info">

            <div>
              <span>Quartos cadastrados</span>
              <strong><?= count($quartos) ?></strong>
            </div>

            <div>
              <span>Serviços disponíveis</span>
              <strong><?= count($servicos) ?></strong>
            </div>

          </div>

        </div>

      </aside>

    </div>

  </main>


  <footer class="footer">

    <div class="container">
      <p>
        &copy; <?= date('Y') ?> MultiploHotel Angola.
        Todos os direitos reservados.
      </p>
    </div>

  </footer>

</body>

</html>