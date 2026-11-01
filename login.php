<?php
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
  header('Location: ' . BASE_URL . '/app.php');
  exit;
}

$error = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email !== '' && $password !== '') {
      $user = login($email, $password);

      if ($user) {
        header('Location: ' . BASE_URL . '/app.php');
        exit;
      } else {
        $error = 'Email ou palavra-passe incorretos.';
      }
    } else {
      $error = 'Preencha todos os campos.';
    }
  }
}
?>



<!doctype html>
<html lang="pt">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login</title>
  <link rel="stylesheet" href="./assets/css/style.css" />
  <link rel="stylesheet" href="./assets/css/login.css" />
</head>

<body>
  <main class="main-login">
    <form method="POST" id="productLogin" class="form-grid">
      <input type="hidden" name="action" value="login">
      <div class="form-header">
        <h2>Bem-vindo de volta!</h2>
        <p>Entre para gerênciar</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger">
          ⚠️ <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($sucesso): ?>
        <div class="alert alert-success">
          ✅ <?= htmlspecialchars($sucesso) ?>
        </div>
      <?php endif; ?>

      <label class="full-field">Email<input name="email" required maxlength="120" /></label>
      <label class="full-field">Palavra passe<input name="password" required maxlength="120" /></label>

      <div class="modal-actions full-field">
        <button type="submit" class="btn btn-primary">Entar</button>
      </div>
    </form>
  </main>
</body>

</html>