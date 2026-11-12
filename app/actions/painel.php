<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../models/Quarto.php';
require_once __DIR__ . '/../models/Andar.php';
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Reserva.php';
require_once __DIR__ . '/../models/Servico.php';

$hotelId = (int) ($_GET['hotel_id'] ?? 0);

// TODO: validar $_POST['csrf_token'] antes de qualquer ação
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
  match ($_POST['acao'] ?? '') {
    'adicionar_quarto' => (new Quarto($hotelId))->criar($_POST),
    'adicionar_andar' => (new Andar($hotelId))->criar((int) $_POST['numero_andar'], $_POST['descricao'] ?? null),
    'adicionar_categoria' => (new Categoria($hotelId))->criar($_POST),
    'reserva_presencial' => (new Reserva($hotelId))->criarPresencial($_POST),
    'adicionar_servico' => (new Servico($hotelId))->criar($_POST),
    'deletar_servico' => (new Servico($hotelId))->eliminar((int) $_POST['id']),
    default => throw new InvalidArgumentException('Ação desconhecida.'),
  };

  $_SESSION['painel_flash'] = [
    'tipo' => 'success',
    'mensagem' => 'Operação concluída com sucesso.'
  ];

} catch (InvalidArgumentException | DomainException $e) {
  $_SESSION['painel_flash'] = ['tipo' => 'danger', 'mensagem' => $e->getMessage()];
}

painelRedirect($hotelId);