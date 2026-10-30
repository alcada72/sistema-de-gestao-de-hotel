<?php

require_once __DIR__ . '/config.php';

session_start();

function isLoggedIn()
{
  return isset($_SESSION['user_id']);
}

function isAdmin()
{
  return isLoggedIn() && $_SESSION['user_tipo'] === 'admin';
}

function isGerente()
{
  return isLoggedIn() && $_SESSION['user_tipo'] === 'gerente';
}

function isRecepcionista()
{
  return isLoggedIn() && $_SESSION['user_tipo'] === 'recepcionista';
}

function requireLogin($tipo = null)
{
  if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
  }
  if ($tipo && $_SESSION['user_tipo'] !== $tipo) {
    header('Location: ' . BASE_URL . '/app.php');
    exit;
  }
}

function login($email, $senha)
{
  $pdo = getDB();
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
  $stmt->execute([$email]);
  $user = $stmt->fetch();


  if ($user && password_verify($senha, $user['senha'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nome'] = $user['nome'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_tipo'] = $user['cargo'];
    return $user;
  }
  return false;
}

function logout()
{
  session_destroy();
  header('Location: ' . BASE_URL . '/index.php');
  exit;
}

function getCurrentUser()
{
  if (!isLoggedIn())
    return null;
  return [
    'id' => $_SESSION['user_id'],
    'nome' => $_SESSION['user_nome'],
    'email' => $_SESSION['user_email'],
    'tipo' => $_SESSION['user_tipo'],
  ];
}

function generateSlug($text)
{
  $text = strtolower(trim($text));
  $text = preg_replace('/[àáâãäå]/u', 'a', $text);
  $text = preg_replace('/[èéêë]/u', 'e', $text);
  $text = preg_replace('/[ìíîï]/u', 'i', $text);
  $text = preg_replace('/[òóôõö]/u', 'o', $text);
  $text = preg_replace('/[ùúûü]/u', 'u', $text);
  $text = preg_replace('/[ç]/u', 'c', $text);
  $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
  $text = preg_replace('/[\s-]+/', '-', $text);
  return $text . '-' . substr(md5(uniqid()), 0, 6);
}