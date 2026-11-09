<?php

require_once __DIR__ . "/../../config/config.php";

$pdo = getDB();

$hotel_id = trim($_GET['hotel_id'] ?? '');
$imagem_id = trim($_GET['image'] ?? '');

$imagens = [];

try {

  if (!empty($hotel_id)) {

    // Buscar todas as imagens do hotel
    $stmt = $pdo->prepare("
            SELECT * FROM hotel_imagens
            WHERE hotel_id = :hotel_id
            ORDER BY id ASC
        ");

    $stmt->execute([
      'hotel_id' => $hotel_id
    ]);

    $imagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } elseif (!empty($imagem_id)) {

    // Buscar uma imagem específica
    $stmt = $pdo->prepare("
            SELECT * FROM hotel_imagens
            WHERE id = :id
        ");

    $stmt->execute([
      'id' => $imagem_id
    ]);

    $imagem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($imagem) {
      $imagens[] = $imagem;
    }
  }
} catch (PDOException $e) {
  $erro = "Erro ao carregar imagens.";
}

// Identificar o campo que contém o caminho da imagem
// Altera 'imagem' caso a tua coluna tenha outro nome.
function caminhoImagem($imagem)
{

  return htmlspecialchars(
    $imagem['url'] ?? '',
    ENT_QUOTES,
    'UTF-8'
  );
}

$totalImagens = count($imagens);

?>

<!DOCTYPE html>
<html lang="pt">

<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Galeria de Imagens</title>
  <link rel="stylesheet" href="style.css">

</head>

<body>

  <div class="galeria">

    <button onclick="window.history.back()" aria-label="Voltar" class="btn go-back">
      x
    </button>

    <?php if ($totalImagens > 0): ?>

      <div class="visualizador">

        <img id="imagemPrincipal" src="<?= caminhoImagem($imagens[0]) ?>" alt="Imagem do hotel">

        <?php if ($totalImagens > 1): ?>

          <button class="navegacao anterior" onclick="navegar(-1)" aria-label="Imagem anterior">
            &#10094;
          </button>

          <button class="navegacao proxima" onclick="navegar(1)" aria-label="Próxima imagem">
            &#10095;
          </button>

        <?php endif; ?>

        <div class="contador" id="contador">
          1 / <?= $totalImagens ?>
        </div>

        <?php if ($totalImagens > 1): ?>

          <div class="miniaturas" id="miniaturas">

            <?php foreach ($imagens as $index => $imagem): ?>

              <div class="miniatura <?= $index === 0 ? 'ativa' : '' ?>" onclick="mostrarImagem(<?= $index ?>)">

                <img src="<?= caminhoImagem($imagem) ?>" alt="Miniatura <?= $index + 1 ?>">

              </div>

            <?php endforeach; ?>

          </div>

        <?php endif; ?>
      </div>


    <?php else: ?>

      <div class="mensagem">
        <h3>Nenhuma imagem encontrada!</h3>
        <p>Não existem imagens disponíveis para este hotel.</p>
      </div>

    <?php endif; ?>

  </div>


  <script>
    const imagens = <?= json_encode(
      array_map('caminhoImagem', $imagens),
      JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
    ) ?>;

    let indiceAtual = 0;

    const imagemPrincipal = document.getElementById('imagemPrincipal');

    const contador = document.getElementById('contador');

    const miniaturas = document.querySelectorAll('.miniatura');


    // Mostrar imagem selecionada
    function mostrarImagem(indice) {

      if (!imagemPrincipal || imagens.length === 0) return;

      indiceAtual = indice;

      imagemPrincipal.src = imagens[indiceAtual];

      contador.textContent =
        (indiceAtual + 1) + ' / ' + imagens.length;

      // Atualizar miniaturas
      miniaturas.forEach((miniatura, index) => {

        miniatura.classList.toggle(
          'ativa',
          index === indiceAtual
        );

      });

    }


    // Avançar ou recuar
    function navegar(direcao) {

      if (imagens.length <= 1) return;

      indiceAtual += direcao;

      // Voltar para a primeira imagem
      if (indiceAtual >= imagens.length) {
        indiceAtual = 0;
      }

      // Ir para a última imagem
      if (indiceAtual < 0) {
        indiceAtual = imagens.length - 1;
      }

      mostrarImagem(indiceAtual);

    }

    function voltar() {
      window.history.back();
    }

    // Navegação pelo teclado
    document.addEventListener('keydown', function (event) {

      if (event.key === 'ArrowRight') {
        navegar(1);
      }

      if (event.key === 'ArrowLeft') {
        navegar(-1);
      }

      if (event.key === 'Escape') {
        voltar();
      }

    });
  </script>

</body>

</html>