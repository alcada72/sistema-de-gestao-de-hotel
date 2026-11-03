<?php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/*
|--------------------------------------------------------------------------
| AUTENTICAÇÃO
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
  return isset($_SESSION['user_id']);
}

function isAdmin(): bool
{
  return isLoggedIn()
    && ($_SESSION['user_tipo'] ?? null) === 'admin';
}

/**
 * Verifica se o utilizador é cliente na plataforma.
 * Dentro de um hotel, o seu papel passa a ser o cargo desse hotel.
 */
function isCliente(): bool
{
  return isLoggedIn()
    && ($_SESSION['user_tipo'] ?? null) === 'cliente'
    && empty($_SESSION['hotel_id']);
}

/**
 * Verifica se existe um contexto de hotel ativo.
 */
function isHotelUser(): bool
{
  return isLoggedIn()
    && !empty($_SESSION['hotel_id'])
    && !empty($_SESSION['hotel_cargo']);
}

/*
|--------------------------------------------------------------------------
| CARGOS DO HOTEL
|--------------------------------------------------------------------------
*/

function isProprietario(): bool
{
  return isHotelUser()
    && $_SESSION['hotel_cargo'] === 'proprietario';
}

function isGerente(): bool
{
  return isHotelUser()
    && $_SESSION['hotel_cargo'] === 'gerente';
}

function isRecepcionista(): bool
{
  return isHotelUser()
    && $_SESSION['hotel_cargo'] === 'recepcionista';
}

function isOperador(): bool
{
  return isHotelUser()
    && $_SESSION['hotel_cargo'] === 'operador';
}

/**
 * Proprietário e gerente podem administrar o estabelecimento.
 */
function canManageHotel(): bool
{
  return isProprietario() || isGerente();
}

/*
|--------------------------------------------------------------------------
| PROTEGER PÁGINAS
|--------------------------------------------------------------------------
*/

/**
 * $tipo refere-se ao tipo da conta em usuarios.tipo.
 * Exemplo: requireLogin('admin') ou requireLogin('cliente').
 */
function requireLogin(?string $tipo = null): void
{
  if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
  }

  if ($tipo !== null && ($_SESSION['user_tipo'] ?? null) !== $tipo) {
    http_response_code(403);
    exit('Não tens permissão para aceder a esta página.');
  }
}

/*
|--------------------------------------------------------------------------
| HOTÉIS DO UTILIZADOR
|--------------------------------------------------------------------------
*/

/**
 * Retorna todos os hotéis aos quais o utilizador está associado.
 */
function getUserHotels(): array
{
  if (!isLoggedIn() || ($_SESSION['user_tipo'] ?? null) !== 'cliente') {
    return [];
  }

  $pdo = getDB();

  $stmt = $pdo->prepare("
        SELECT
            h.id,
            h.nome,
            h.slug,
            h.estado,
            hu.cargo,
            hu.ativo AS associacao_ativa
        FROM hotel_usuarios hu
        INNER JOIN hoteis h ON h.id = hu.hotel_id
        WHERE hu.usuario_id = ?
          AND hu.ativo = 1
        ORDER BY h.nome ASC
    ");

  $stmt->execute([$_SESSION['user_id']]);

  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| CONTEXTO DO HOTEL
|--------------------------------------------------------------------------
*/

/**
 * Seleciona e valida o hotel que o utilizador pretende gerir.
 *
 * Se $hotelId não for informado:
 * 1. Tenta utilizar o hotel selecionado anteriormente.
 * 2. Caso não exista, seleciona o primeiro hotel associado.
 *
 * Retorna os dados do hotel e o cargo do utilizador.
 */
function requireHotelAccess($hotelId = null): array
{
  requireLogin('cliente');

  $pdo = getDB();

  $userId = (int) $_SESSION['user_id'];

  // Identificar hotel pretendido.
  if ($hotelId !== null && $hotelId !== '') {
    $hotelId = (int) $hotelId;
  } else {
    $hotelId = (int) ($_SESSION['hotel_id'] ?? 0);
  }

  /*
   * Se não houver hotel selecionado,
   * obter o primeiro hotel associado.
   */
  if ($hotelId <= 0) {
    $stmt = $pdo->prepare("
            SELECT hotel_id
            FROM hotel_usuarios
            WHERE usuario_id = ?
              AND ativo = 1
            ORDER BY id ASC
            LIMIT 1
        ");

    $stmt->execute([$userId]);

    $hotelId = (int) $stmt->fetchColumn();
  }

  if ($hotelId <= 0) {
    http_response_code(403);
    exit('Não estás associado a nenhum hotel.');
  }

  /*
   * Validar a associação e obter o cargo.
   * Não basta conhecer o ID de um hotel.
   */
  $stmt = $pdo->prepare("
        SELECT
            h.id,
            h.nome,
            h.slug,
            h.estado,
            hu.cargo,
            hu.ativo AS associacao_ativa
        FROM hotel_usuarios hu
        INNER JOIN hoteis h ON h.id = hu.hotel_id
        WHERE hu.usuario_id = ?
          AND hu.hotel_id = ?
          AND hu.ativo = 1
        LIMIT 1
    ");

  $stmt->execute([$userId, $hotelId]);

  $hotel = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$hotel) {
    http_response_code(403);
    exit('Não tens permissão para gerir este hotel.');
  }

  /*
   * Atualizar o contexto da sessão.
   */
  $_SESSION['hotel_id'] = (int) $hotel['id'];
  $_SESSION['hotel_cargo'] = $hotel['cargo'];

  return $hotel;
}

/**
 * Mudar de hotel.
 * A associação é validada novamente na base de dados.
 */
function selectHotel(int $hotelId): array
{
  return requireHotelAccess($hotelId);
}

/**
 * Sair do contexto de gestão do hotel.
 * O utilizador continua autenticado como cliente.
 */
function clearHotelContext(): void
{
  unset(
    $_SESSION['hotel_id'],
    $_SESSION['hotel_cargo']
  );
}

/*
|--------------------------------------------------------------------------
| PROTEGER MÓDULOS CONFORME O CARGO
|--------------------------------------------------------------------------
*/

/**
 * Exemplos:
 *
 * requireHotelRole(['proprietario', 'gerente']);
 * requireHotelRole(['proprietario', 'gerente', 'recepcionista']);
 */
function requireHotelRole(array $cargos): array
{
  $hotel = requireHotelAccess();

  if (!in_array($hotel['cargo'], $cargos, true)) {
    http_response_code(403);
    exit('O teu cargo não permite realizar esta operação.');
  }

  return $hotel;
}

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

function login($email, $senha)
{
  $pdo = getDB();

  $stmt = $pdo->prepare("
        SELECT
            id,
            nome,
            email,
            senha,
            tipo,
            ativo
        FROM usuarios
        WHERE email = ?
        LIMIT 1
    ");

  $stmt->execute([$email]);

  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (
    !$user ||
    (int) $user['ativo'] !== 1 ||
    !password_verify($senha, $user['senha'])
  ) {
    return false;
  }

  session_regenerate_id(true);

  /*
   * Guardar apenas o tipo global do utilizador.
   * Gerente, recepcionista e proprietário
   * não são tipos da tabela usuarios.
   */
  $_SESSION['user_id'] = (int) $user['id'];
  $_SESSION['user_nome'] = $user['nome'];
  $_SESSION['user_email'] = $user['email'];
  $_SESSION['user_tipo'] = $user['tipo'];
  $_SESSION['user_ativo'] = (int) $user['ativo'];

  // O contexto do hotel é selecionado separadamente.
  clearHotelContext();

  return $user;
}

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function logout(): void
{
  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params['path'],
      $params['domain'],
      $params['secure'],
      $params['httponly']
    );
  }

  session_destroy();

  header('Location: ' . BASE_URL . '/index.php');
  exit;
}

function getCurrentUser(): ?array
{
  if (!isLoggedIn()) {
    return null;
  }

  return [
    'id' => $_SESSION['user_id'],
    'nome' => $_SESSION['user_nome'],
    'email' => $_SESSION['user_email'],
    'tipo' => $_SESSION['user_tipo'],
    'ativo' => $_SESSION['user_ativo'],
    'hotel_id' => $_SESSION['hotel_id'] ?? null,
    'hotel_cargo' => $_SESSION['hotel_cargo'] ?? null
  ];
}
