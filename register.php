<?php

require_once __DIR__ . '/config/auth.php';

$pdo = getDB();

if (isLoggedIn()) {
  header('Location: ' . BASE_URL . '/route.php');
  exit;
}

$erroRegistro = '';
$sucesso = '';

// Criar token CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Processar registo
if (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && ($_POST['acao'] ?? '') === 'register'
) {

  $dados = $_POST['dados'] ?? [];

  $nome = trim($dados['nome'] ?? '');
  $email = trim($dados['email'] ?? '');
  $telefone = trim($dados['telefone'] ?? '');
  $senha = $dados['senha'] ?? '';

  $csrfToken = $_POST['csrf_token'] ?? '';

  if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {

    $erroRegistro = 'Sessão inválida. Atualize a página.';

  } elseif (
    $nome !== '' &&
    filter_var($email, FILTER_VALIDATE_EMAIL) &&
    $telefone !== '' &&
    strlen($senha) >= 6
  ) {

    try {

      $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

      $stmt = $pdo->prepare("
                INSERT INTO usuarios
                    (nome, email, telefone, senha, tipo)
                VALUES
                    (:nome, :email, :telefone, :senha, 'cliente')
            ");

      $stmt->execute([
        'nome' => $nome,
        'email' => $email,
        'telefone' => $telefone,
        'senha' => $senhaHash
      ]);

      if (login($email, $senha)) {

        // Renovar token CSRF
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header('Location: ' . BASE_URL . '/route.php');
        exit;
      }

      $erroRegistro = 'Conta criada, mas não foi possível iniciar a sessão. Faça login.';

    } catch (PDOException $e) {

      if ($e->getCode() === '23000') {
        $erroRegistro = 'Este email já está registado.';
      } else {
        $erroRegistro = 'Não foi possível criar a conta.';
      }
    }

  } else {

    $erroRegistro = 'Verifique os dados. A senha deve ter pelo menos 6 caracteres.';
  }
}

?>

<!DOCTYPE html>
<html lang="pt-AO">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Criar conta | MultiploHotel</title>

  <link rel="stylesheet" href="./assets/css/style.css">

  <style>
    #productLogin {
      display: flex;
      flex-direction: column;
      width: 100%;
      max-width: 390px;
      padding: 30px;
      border-radius: 25px;
    }

    .main-login {
      width: 100%;
      min-height: 100dvh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .main-login button {
      width: 100%;
    }
  </style>
</head>

<body>

  <main class="main-login">

    <form method="POST" id="productLogin" class="form-grid">

      <input type="hidden" name="acao" value="register">

      <input type="hidden" name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

      <div class="form-header">
        <h2>Bem-vindo à MultiploHotel!</h2>
        <p>Crie uma conta para começar.</p>
      </div>

      <?php if ($erroRegistro): ?>
        <div class="alert alert-danger">
          ⚠️ <?= htmlspecialchars($erroRegistro, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <label class="full-field">
        Nome

        <input name="dados[nome]" type="text" required maxlength="120"
          value="<?= htmlspecialchars($_POST['dados']['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </label>

      <label class="full-field">
        Email

        <input name="dados[email]" type="email" required maxlength="120"
          value="<?= htmlspecialchars($_POST['dados']['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </label>

      <label class="full-field">
        Telefone

        <input name="dados[telefone]" type="tel" required maxlength="120"
          value="<?= htmlspecialchars($_POST['dados']['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </label>

      <label class="full-field">
        Palavra-passe

        <input name="dados[senha]" type="password" required minlength="6" maxlength="120">
      </label>

      <div class="modal-actions full-field">
        <button type="submit" class="btn btn-primary">
          Criar conta
        </button>
      </div>

      <p>
        Já tens uma conta?
        <a href="<?= BASE_URL ?>/login.php">Entrar</a>
      </p>

    </form>

  </main>

</body>

</html>