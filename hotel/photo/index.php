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

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      background: #111;
      color: #fff;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .galeria {
      width: 100%;
      max-width: 1900px;
      padding: 20px;
      text-align: center;
    }

    .galeria h2 {
      margin-bottom: 20px;
      font-size: 24px;
      font-weight: 600;
    }

    .visualizador {
      position: relative;
      width: 100%;
      height: 89vh;
      min-height: 350px;
      background: #000;
      border-radius: 12px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .visualizador img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
    }

    .navegacao {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 45px;
      height: 45px;
      border: none;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      color: white;
      font-size: 25px;
      cursor: pointer;
      transition: .3s;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .navegacao:hover {
      background: rgba(255, 255, 255, 0.7);
      color: #111;
    }

    .anterior {
      left: 15px;
    }

    .proxima {
      right: 15px;
    }

    .contador {
      position: absolute;
      bottom: 15px;
      right: 15px;
      background: rgba(0, 0, 0, .7);
      padding: 8px 14px;
      border-radius: 20px;
      font-size: 14px;
    }

    .miniaturas {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
      flex-wrap: wrap;
      position: absolute;
      bottom: 0;
    }

    .miniatura {
      width: 85px;
      height: 65px;
      border-radius: 7px;
      overflow: hidden;
      cursor: pointer;
      border: 2px solid transparent;
      opacity: .5;
      transition: .3s;
    }

    .miniatura img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .miniatura.ativa {
      border-color: #fff;
      opacity: 1;
    }

    .mensagem {
      padding: 50px 20px;
      color: #ccc;
    }

    @media(max-width: 600px) {

      .galeria {
        padding: 10px;
      }

      .visualizador {
        height: 65vh;
        min-height: 300px;
      }

      .navegacao {
        width: 38px;
        height: 38px;
        font-size: 20px;
      }

      .miniatura {
        width: 60px;
        height: 50px;
      }

    }
  </style>

</head>

<body>

  <div class="galeria">

    <?php if ($totalImagens > 0): ?>

      <div class="visualizador">

        <img
          id="imagemPrincipal"
          src="<?= caminhoImagem($imagens[0]) ?>"
          alt="Imagem do hotel">

        <?php if ($totalImagens > 1): ?>

          <button
            class="navegacao anterior"
            onclick="navegar(-1)"
            aria-label="Imagem anterior">
            &#10094;
          </button>

          <button
            class="navegacao proxima"
            onclick="navegar(1)"
            aria-label="Próxima imagem">
            &#10095;
          </button>

        <?php endif; ?>

        <div class="contador" id="contador">
          1 / <?= $totalImagens ?>
        </div>

        <?php if ($totalImagens > 1): ?>

          <div class="miniaturas" id="miniaturas">

            <?php foreach ($imagens as $index => $imagem): ?>

              <div
                class="miniatura <?= $index === 0 ? 'ativa' : '' ?>"
                onclick="mostrarImagem(<?= $index ?>)">

                <img
                  src="<?= caminhoImagem($imagem) ?>"
                  alt="Miniatura <?= $index + 1 ?>">

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


    // Navegação pelo teclado
    document.addEventListener('keydown', function(event) {

      if (event.key === 'ArrowRight') {
        navegar(1);
      }

      if (event.key === 'ArrowLeft') {
        navegar(-1);
      }

      if (event.key === 'Escape') {
        window.history.back();
      }

    });
  </script>

</body>

</html>