<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../../config/auth.php';

requireLogin('cliente');

$pdo = getDB();
$usuario = getCurrentUser();

$hotel = requireHotelAccess(
  isset($_GET['hotel_id']) ? (int) $_GET['hotel_id'] : null
);

$hotelId = (int) $hotel['id'];
$cargo = $hotel['cargo'];

$podeGerirHotel = in_array($cargo, ['proprietario', 'gerente'], true);
$podeGerirQuartos = $podeGerirHotel;
$podeGerirReservas = in_array(
  $cargo,
  ['proprietario', 'gerente', 'recepcionista'],
  true
);

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

function painelRedirect(int $hotelId): never
{
  header('Location: ' . BASE_URL . '/app?hotel_id=' . $hotelId);
  exit;
}

function painelFlash(string $tipo, string $mensagem): void
{
  $_SESSION['painel_flash'] = [
    'tipo' => $tipo,
    'mensagem' => $mensagem
  ];
}

function painelPost(string $campo): string
{
  return trim((string) ($_POST[$campo] ?? ''));
}

function painelValidarCsrf(): void
{
  $token = $_POST['csrf_token'] ?? '';

  if (
    !is_string($token) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $token)
  ) {
    throw new RuntimeException('Sessão expirada. Atualiza a página e tenta novamente.');
  }
}

function painelErro(string $mensagem, int $hotelId): never
{
  painelFlash('erro', $mensagem);
  painelRedirect($hotelId);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  return;
}

try {
  painelValidarCsrf();

  $acao = painelPost('acao');

  /*
  |--------------------------------------------------------------------------
  | 1. ADICIONAR QUARTO
  |--------------------------------------------------------------------------
  */
  if ($acao === 'adicionar_quarto') {

    if (!$podeGerirQuartos) {
      throw new RuntimeException('Não tens permissão para adicionar quartos.');
    }

    $numero = painelPost('numero');
    $andarId = (int) painelPost('andar_id');
    $categoriaId = (int) painelPost('categoria_id');
    $estado = painelPost('estado');
    $descricao = painelPost('descricao');

    if ($numero === '' || $andarId <= 0 || $categoriaId <= 0) {
      throw new RuntimeException('Preenche todos os campos obrigatórios.');
    }

    if (!in_array($estado, ['ativo', 'manutencao', 'inativo'], true)) {
      throw new RuntimeException('Estado do quarto inválido.');
    }

    // Confirma que o andar pertence ao hotel.
    $stmt = $pdo->prepare("
            SELECT id
            FROM andares
            WHERE id = :andar
              AND hotel_id = :hotel
        ");
    $stmt->execute([
      'andar' => $andarId,
      'hotel' => $hotelId
    ]);

    if (!$stmt->fetchColumn()) {
      throw new RuntimeException('O andar selecionado não pertence a este hotel.');
    }

    // Confirma que a categoria pertence ao hotel e está ativa.
    $stmt = $pdo->prepare("
            SELECT id
            FROM categorias_quartos
            WHERE id = :categoria
              AND hotel_id = :hotel
              AND ativo = 1
        ");
    $stmt->execute([
      'categoria' => $categoriaId,
      'hotel' => $hotelId
    ]);

    if (!$stmt->fetchColumn()) {
      throw new RuntimeException('A categoria selecionada não é válida.');
    }

    $stmt = $pdo->prepare("
            INSERT INTO quartos (
                hotel_id,
                andar_id,
                categoria_id,
                numero,
                estado,
                descricao
            ) VALUES (
                :hotel,
                :andar,
                :categoria,
                :numero,
                :estado,
                :descricao
            )
        ");

    $stmt->execute([
      'hotel' => $hotelId,
      'andar' => $andarId,
      'categoria' => $categoriaId,
      'numero' => $numero,
      'estado' => $estado,
      'descricao' => $descricao ?: null
    ]);

    painelFlash('sucesso', 'Quarto registado com sucesso.');
    painelRedirect($hotelId);
  }

  /*
  |--------------------------------------------------------------------------
  | 2. RESERVA PRESENCIAL
  |--------------------------------------------------------------------------
  */
  if ($acao === 'reserva_presencial') {

    if (!$podeGerirReservas) {
      throw new RuntimeException('Não tens permissão para registar reservas.');
    }

    $nome = painelPost('nome_hospede');
    $telefone = painelPost('telefone');
    $documento = painelPost('documento');
    $quartoId = (int) painelPost('quarto_id');
    $pessoas = (int) painelPost('quantidade_pessoas');
    $entrada = painelPost('data_entrada');
    $saida = painelPost('data_saida');
    $valorRecebido = (float) str_replace(',', '.', painelPost('valor_recebido') ?: '0');
    $metodo = painelPost('metodo_pagamento');
    $observacoes = painelPost('observacoes');

    if (
      $nome === '' ||
      $telefone === '' ||
      $quartoId <= 0 ||
      $pessoas <= 0 ||
      $entrada === '' ||
      $saida === ''
    ) {
      throw new RuntimeException('Preenche os campos obrigatórios da reserva.');
    }

    $dataEntrada = DateTime::createFromFormat('!Y-m-d', $entrada);
    $dataSaida = DateTime::createFromFormat('!Y-m-d', $saida);

    if (
      !$dataEntrada ||
      !$dataSaida ||
      $dataEntrada->format('Y-m-d') !== $entrada ||
      $dataSaida->format('Y-m-d') !== $saida ||
      $saida <= $entrada
    ) {
      throw new RuntimeException('O período da reserva é inválido.');
    }

    if ($valorRecebido < 0) {
      throw new RuntimeException('O valor recebido não pode ser negativo.');
    }

    $pdo->beginTransaction();

    // Bloqueia o quarto durante a validação e criação da reserva.
    $stmt = $pdo->prepare("
            SELECT
                q.id,
                q.estado,
                c.capacidade,
                c.preco_base
            FROM quartos q
            INNER JOIN categorias_quartos c
                ON c.id = q.categoria_id
               AND c.hotel_id = q.hotel_id
            WHERE q.id = :quarto
              AND q.hotel_id = :hotel
            FOR UPDATE
        ");

    $stmt->execute([
      'quarto' => $quartoId,
      'hotel' => $hotelId
    ]);

    $quarto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quarto || $quarto['estado'] !== 'ativo') {
      throw new RuntimeException('O quarto não está disponível para reservas.');
    }

    if ($pessoas > (int) $quarto['capacidade']) {
      throw new RuntimeException('O número de hóspedes ultrapassa a capacidade do quarto.');
    }

    // Reservas pendentes, confirmadas e em check-in ocupam o quarto.
    $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM reservas
            WHERE quarto_id = :quarto
              AND hotel_id = :hotel
              AND estado IN ('pendente', 'confirmada', 'checkin')
              AND data_entrada < :saida
              AND data_saida > :entrada
        ");

    $stmt->execute([
      'quarto' => $quartoId,
      'hotel' => $hotelId,
      'entrada' => $entrada,
      'saida' => $saida
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
      throw new RuntimeException('O quarto já possui uma reserva nesse período.');
    }

    $noites = (int) $dataEntrada->diff($dataSaida)->days;
    $precoDiaria = (float) $quarto['preco_base'];
    $total = $noites * $precoDiaria;

    if ($valorRecebido > $total) {
      throw new RuntimeException('O valor recebido não pode ultrapassar o total da reserva.');
    }

    if (
      $valorRecebido > 0 && !in_array($metodo, [
        'dinheiro',
        'transferencia',
        'cartao',
        'referencia',
        'multicaixa_express',
        'outro'
      ], true)
    ) {
      throw new RuntimeException('Seleciona um método de pagamento válido.');
    }

    $codigo = 'RES-' . strtoupper(bin2hex(random_bytes(6)));

    // O utilizador associado é o funcionário que registou a reserva.
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
                :codigo,
                :hotel,
                :quarto,
                :usuario,
                :entrada,
                :saida,
                :pessoas,
                :noites,
                :preco,
                :total,
                'pendente',
                :observacoes
            )
        ");

    $stmt->execute([
      'codigo' => $codigo,
      'hotel' => $hotelId,
      'quarto' => $quartoId,
      'usuario' => (int) $usuario['id'],
      'entrada' => $entrada,
      'saida' => $saida,
      'pessoas' => $pessoas,
      'noites' => $noites,
      'preco' => $precoDiaria,
      'total' => $total,
      'observacoes' => $observacoes ?: null
    ]);

    $reservaId = (int) $pdo->lastInsertId();

    // Guarda os dados reais do hóspede.
    $stmt = $pdo->prepare("
            INSERT INTO reserva_hospedes (
                reserva_id,
                nome,
                documento,
                telefone,
                principal
            ) VALUES (
                :reserva,
                :nome,
                :documento,
                :telefone,
                1
            )
        ");

    $stmt->execute([
      'reserva' => $reservaId,
      'nome' => $nome,
      'documento' => $documento ?: null,
      'telefone' => $telefone
    ]);

    // Só cria pagamento quando existe valor recebido.
    if ($valorRecebido > 0) {
      $stmt = $pdo->prepare("
                INSERT INTO pagamentos (
                    reserva_id,
                    hotel_id,
                    valor,
                    metodo,
                    estado,
                    pago_em
                ) VALUES (
                    :reserva,
                    :hotel,
                    :valor,
                    :metodo,
                    'pago',
                    NOW()
                )
            ");

      $stmt->execute([
        'reserva' => $reservaId,
        'hotel' => $hotelId,
        'valor' => $valorRecebido,
        'metodo' => $metodo
      ]);
    }

    $pdo->commit();

    painelFlash(
      'sucesso',
      'Reserva ' . $codigo . ' registada com sucesso.'
    );

    painelRedirect($hotelId);
  }

  /*
  |--------------------------------------------------------------------------
  | 3. ADICIONAR FOTOGRAFIAS
  |--------------------------------------------------------------------------
  */
  if ($acao === 'adicionar_imagens') {

    if (!$podeGerirHotel) {
      throw new RuntimeException('Não tens permissão para gerir as fotografias.');
    }

    $tipo = painelPost('tipo');

    if (!in_array($tipo, ['principal', 'galeria', 'interior', 'exterior'], true)) {
      throw new RuntimeException('Tipo de imagem inválido.');
    }

    if (
      empty($_FILES['imagens']) ||
      !isset($_FILES['imagens']['name']) ||
      !is_array($_FILES['imagens']['name'])
    ) {
      throw new RuntimeException('Seleciona pelo menos uma fotografia.');
    }

    $diretorio = __DIR__ . '/../../uploads/hoteis/' . $hotelId . '/';
    $urlBase = BASE_URL . '/uploads/hoteis/' . $hotelId . '/';

    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true)) {
      throw new RuntimeException('Não foi possível preparar a pasta das fotografias.');
    }

    $permitidos = [
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $guardadas = [];

    try {
      $pdo->beginTransaction();

      $ordemStmt = $pdo->prepare("
                SELECT COALESCE(MAX(ordem), 0)
                FROM hotel_imagens
                WHERE hotel_id = :hotel
            ");
      $ordemStmt->execute(['hotel' => $hotelId]);
      $ordem = (int) $ordemStmt->fetchColumn();

      foreach ($_FILES['imagens']['name'] as $i => $nomeOriginal) {
        $erro = $_FILES['imagens']['error'][$i] ?? UPLOAD_ERR_NO_FILE;

        if ($erro === UPLOAD_ERR_NO_FILE) {
          continue;
        }

        if ($erro !== UPLOAD_ERR_OK) {
          throw new RuntimeException('Ocorreu um erro durante o envio de uma fotografia.');
        }

        $tmp = $_FILES['imagens']['tmp_name'][$i];
        $tamanho = (int) $_FILES['imagens']['size'][$i];

        if ($tamanho <= 0 || $tamanho > 5 * 1024 * 1024) {
          throw new RuntimeException('Cada fotografia deve ter no máximo 5 MB.');
        }

        $mime = $finfo->file($tmp);

        if (!isset($permitidos[$mime]) || getimagesize($tmp) === false) {
          throw new RuntimeException('Formato de fotografia não permitido.');
        }

        $nome = bin2hex(random_bytes(16)) . '.' . $permitidos[$mime];
        $destino = $diretorio . $nome;

        if (!move_uploaded_file($tmp, $destino)) {
          throw new RuntimeException('Não foi possível guardar uma fotografia.');
        }

        $guardadas[] = $destino;
        $ordem++;

        $stmt = $pdo->prepare("
                    INSERT INTO hotel_imagens (
                        hotel_id,
                        url,
                        tipo,
                        ordem
                    ) VALUES (
                        :hotel,
                        :url,
                        :tipo,
                        :ordem
                    )
                ");

        $stmt->execute([
          'hotel' => $hotelId,
          'url' => $urlBase . $nome,
          'tipo' => $tipo,
          'ordem' => $ordem
        ]);
      }

      if (count($guardadas) === 0) {
        throw new RuntimeException('Seleciona pelo menos uma fotografia válida.');
      }

      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      foreach ($guardadas as $ficheiro) {
        if (is_file($ficheiro)) {
          unlink($ficheiro);
        }
      }

      throw $e;
    }

    painelFlash('sucesso', 'Fotografias adicionadas com sucesso.');
    painelRedirect($hotelId);
  }

  if ($acao === 'deletar_imagens') {
    if (!$podeGerirHotel) {
      throw new RuntimeException('Não tens permissão Para remover serviço.');
    }

    $id = (int) painelPost('id');

    if (!$id) {
      throw new RuntimeException('Preenche todos os campos.');
    }
    try {
      $stmt = $pdo->prepare('DELETE FROM hotel_imagens WHERE id = :id');
      $stmt->execute([
        'id' => (int) $id
      ]);

      painelFlash('sucesso', 'Sucesso Imagem eliminada com sucesso.');

      painelRedirect($hotelId);
    } catch (\Throwable $e) {

      painelErro($e->getMessage(), $hotelId);
    }
  }

  /*
  |--------------------------------------------------------------------------
  | 4. ATUALIZAR DADOS DO HOTEL
  |--------------------------------------------------------------------------
  */
  if ($acao === 'atualizar_hotel') {

    if (!$podeGerirHotel) {
      throw new RuntimeException('Não tens permissão para atualizar os dados do hotel.');
    }

    $nome = painelPost('nome');
    $categoria = painelPost('categoria');
    $descricao = painelPost('descricao');
    $telefone = painelPost('telefone');
    $email = painelPost('email');
    $telefoneSecundario = painelPost('telefone_secundario');
    $endereco = painelPost('endereco');
    $provinciaId = (int) painelPost('provincia_id');
    $municipioId = (int) painelPost('municipio_id');

    $categoriasValidas = [
      '1_estrela',
      '2_estrelas',
      '3_estrelas',
      '4_estrelas',
      '5_estrelas',
      'sem_classificacao'
    ];

    if ($nome === '' || $endereco === '' || $provinciaId <= 0 || $municipioId <= 0) {
      throw new RuntimeException('Preenche o nome e a localização do hotel.');
    }

    if (!in_array($categoria, $categoriasValidas, true)) {
      throw new RuntimeException('Categoria do hotel inválida.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('O email indicado não é válido.');
    }

    $stmt = $pdo->prepare("
            SELECT id
            FROM municipios
            WHERE id = :municipio
              AND provincia_id = :provincia
        ");

    $stmt->execute([
      'municipio' => $municipioId,
      'provincia' => $provinciaId
    ]);

    if (!$stmt->fetchColumn()) {
      throw new RuntimeException('O município não pertence à província selecionada.');
    }

    $stmt = $pdo->prepare("
            UPDATE hoteis
            SET
                nome = :nome,
                categoria = :categoria,
                descricao = :descricao,
                telefone = :telefone,
                telefone_secundario = :telefone_secundario,
                email = :email,
                endereco = :endereco,
                provincia_id = :provincia,
                municipio_id = :municipio
            WHERE id = :hotel
        ");

    $stmt->execute([
      'nome' => $nome,
      'categoria' => $categoria,
      'descricao' => $descricao ?: null,
      'telefone' => $telefone ?: null,
      'telefone_secundario' => $telefoneSecundario ?: null,
      'email' => $email ?: null,
      'endereco' => $endereco,
      'provincia' => $provinciaId,
      'municipio' => $municipioId,
      'hotel' => $hotelId
    ]);

    painelFlash('sucesso', 'Dados do hotel atualizados com sucesso.');
    painelRedirect($hotelId);
  }

  /* 
   Adcionar serviço
  */
  if ($acao === 'adicionar_servico') {

    if (!$podeGerirHotel) {
      throw new RuntimeException(
        'Não tens permissão para adicionar serviços.'
      );
    }

    $nome = painelPost('nome');
    $tipo = painelPost('tipo');
    $precoTexto = str_replace(',', '.', painelPost('preco'));
    $descricao = painelPost('descricao');

    if ($nome === '') {
      throw new RuntimeException('Preenche o nome do serviço.');
    }

    if (!in_array($tipo, ['gratuito', 'pago'], true)) {
      throw new RuntimeException('Tipo de serviço inválido.');
    }

    if ($precoTexto === '' || !is_numeric($precoTexto)) {
      throw new RuntimeException('Indica um preço válido.');
    }

    $preco = (float) $precoTexto;

    if ($preco < 0) {
      throw new RuntimeException('O preço não pode ser negativo.');
    }

    if ($tipo === 'gratuito') {
      $preco = 0;
    }

    if ($tipo === 'pago' && $preco <= 0) {
      throw new RuntimeException(
        'Um serviço pago deve ter um preço superior a zero.'
      );
    }

    $stmt = $pdo->prepare("
        INSERT INTO servicos (
            hotel_id,
            nome,
            tipo,
          preco,
            descricao
        ) VALUES (
            :hotel,
            :nome,
            :tipo,
            :preco,
            :descricao
        )
    ");

    $stmt->execute([
      'hotel' => $hotelId,
      'nome' => $nome,
      'tipo' => $tipo,
      'preco' => $preco,
      'descricao' => $descricao ?: null
    ]);

    painelFlash('sucesso', 'Serviço adicionado com sucesso.');

    painelRedirect($hotelId);
  }

  /* Deletar servico */

  if ($acao === 'deletar_servico') {
    if (!$podeGerirHotel) {
      throw new RuntimeException('Não tens permissão Para remover serviço.');
    }

    $id = (int) painelPost('id');

    if (!$id) {
      throw new RuntimeException('Preenche todos os campos.');
    }
    try {
      $stmt = $pdo->prepare('DELETE FROM servicos WHERE id = :id');
      $stmt->execute([
        'id' => (int) $id
      ]);

      painelFlash('sucesso', 'Sucesso serviço eliminado com sucesso.');

      painelRedirect($hotelId);
    } catch (\Throwable $e) {

      painelErro($e->getMessage(), $hotelId);
    }
  }

  /* Adcionar andar */
  if ($acao === 'adicionar_andar') {
    if (!$podeGerirQuartos) {
      throw new RuntimeException('Não tens permissão para adicionar andares.');
    }

    $numeroAndar = painelPost('numero_andar');
    $descricao = painelPost('descricao');

    if ($numeroAndar === '') {
      throw new RuntimeException('Preenche o número do andar.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO andares (
            hotel_id,
            numero_andar,
            descricao
        ) VALUES (
            :hotel,
            :numero,
            :descricao
        )
    ");

    $stmt->execute([
      'hotel' => $hotelId,
      'numero' => $numeroAndar,
      'descricao' => $descricao ?: null
    ]);

    painelFlash('sucesso', 'Andar adicionado com sucesso.');

    painelRedirect($hotelId);
  }

  /* Adcionar Categoria */
  if ($acao === 'adicionar_categoria') {
    if (!$podeGerirQuartos) {
      throw new RuntimeException('Não tens permissão para adicionar categorias.');
    }

    $nome = painelPost('nome');
    $capacidade = (int) painelPost('capacidade');
    $precoBaseTexto = str_replace(',', '.', painelPost('preco_base'));
    $descricao = painelPost('descricao');

    if ($nome === '' || $capacidade <= 0 || $precoBaseTexto === '' || !is_numeric($precoBaseTexto)) {
      throw new RuntimeException('Preenche todos os campos obrigatórios da categoria.');
    }

    $precoBase = (float) $precoBaseTexto;

    if ($precoBase < 0) {
      throw new RuntimeException('O preço base não pode ser negativo.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO categorias_quartos (
            hotel_id,
            nome,
            capacidade,
            preco_base,
            descricao
        ) VALUES (
            :hotel,
            :nome,
            :capacidade,
            :preco_base,
            :descricao
        )
    ");

    $stmt->execute([
      'hotel' => $hotelId,
      'nome' => $nome,
      'capacidade' => $capacidade,
      'preco_base' => $precoBase,
      'descricao' => $descricao ?: null
    ]);

    painelFlash('sucesso', 'Categoria adicionada com sucesso.');

    painelRedirect($hotelId);
  }

  /*
  |--------------------------------------------------------------------------
  | ADICIONAR FUNCIONÁRIO
  |--------------------------------------------------------------------------
  */
  if ($acao === 'adicionar_funcionario') {
    if (!$podeGerirHotel) {
      throw new RuntimeException('Não tens permissão para adicionar funcionarios.');
    }

    // Apenas o proprietário pode adicionar funcionários.
    if ($cargo !== 'proprietario') {
      throw new RuntimeException(
        'Não tens permissão para adicionar funcionários.'
      );
    }

    $nome = painelPost('nome');
    $email = strtolower(painelPost('email'));
    $telefone = painelPost('telefone');
    $novoCargo = painelPost('cargo');

    // Validar campos obrigatórios.
    if (
      $nome === '' ||
      $email === '' ||
      $telefone === '' ||
      $novoCargo === ''
    ) {
      throw new RuntimeException(
        'Preenche todos os campos obrigatórios.'
      );
    }

    // Validar email.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('O email indicado não é válido.');
    }

    // Validar telefone.
    if (!preg_match('/^\+?[0-9]{9,15}$/', $telefone)) {
      throw new RuntimeException('O número de telefone não é válido.');
    }

    // Cargos permitidos para os funcionários.
    $cargosPermitidos = [
      'gerente',
      'recepcionista',
      'operador'
    ];

    if (!in_array($novoCargo, $cargosPermitidos, true)) {
      throw new RuntimeException('Cargo selecionado inválido.');
    }

    // Verificar se o email ou telefone já está registado.
    $stmt = $pdo->prepare("
        SELECT id
        FROM usuarios
        WHERE email = :email
           OR telefone = :telefone
        LIMIT 1
    ");

    $stmt->execute([
      'email' => $email,
      'telefone' => $telefone
    ]);

    if ($stmt->fetchColumn()) {
      throw new RuntimeException(
        'Este email ou número de telefone já está cadastrado.'
      );
    }

    // Gerar senha inicial.
    $senhaInicial = bin2hex(random_bytes(6));
    $senhaHash = password_hash($telefone, PASSWORD_DEFAULT);

    try {

      $pdo->beginTransaction();

      // Criar utilizador.
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
        'nome' => $nome,
        'email' => $email,
        'telefone' => $telefone,
        'senha' => $senhaHash
      ]);

      $novoUsuarioId = (int) $pdo->lastInsertId();

      // Associar funcionário ao hotel.
      $stmt = $pdo->prepare("
            INSERT INTO hotel_usuarios (
                hotel_id,
                usuario_id,
                cargo
            ) VALUES (
                :hotel_id,
                :usuario_id,
                :cargo
            )
        ");

      $stmt->execute([
        'hotel_id' => $hotelId,
        'usuario_id' => $novoUsuarioId,
        'cargo' => $novoCargo
      ]);

      $pdo->commit();
    } catch (Throwable $e) {

      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      throw $e;
    }

    painelFlash(
      'sucesso',
      'Funcionário cadastrado com sucesso. Senha inicial: ' . $senhaInicial
    );

    painelRedirect($hotelId);
  }
  painelRedirect($hotelId);
} catch (Throwable $e) {

  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  painelErro($e->getMessage(), $hotelId);
}
