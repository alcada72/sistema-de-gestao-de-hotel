<?php

require_once __DIR__ . '/config/auth.php';

// 1. Verificar autenticação
if (!isLoggedIn()) {
  header('Location: ' . BASE_URL . '/login.php');
  exit;
}

// 2. Administrador da plataforma
if (isAdmin()) {
  header('Location: ' . BASE_URL . '/admin/');
  exit;
}

// 3. Cliente
if (isCliente()) {

  // Verificar se possui hotéis associados
  $hoteis = getUserHotels();

  if (!empty($hoteis)) {
    // Utilizador associado a pelo menos um hotel
    header('Location: ' . BASE_URL . '/app/');
    exit;
  }

  // Cliente sem associação a hotel
  header('Location: ' . BASE_URL . '/');
  exit;
}

// 4. Tipo de utilizador desconhecido
logout();