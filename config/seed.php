<?php

require_once __DIR__ . '/../config/config.php';

$db = getDB();

$nome = 'Alçada Manuel Kilundica';
$email = 'alcada300@gmail.com';
$senha = 'alc945107';
$cargo = 'admin';

$hash = password_hash($senha, PASSWORD_DEFAULT);

$sql = "
    INSERT INTO usuarios (nome, email, senha, cargo)
    VALUES (:nome, :email, :senha, :cargo)
    ON DUPLICATE KEY UPDATE
        nome = VALUES(nome),
        cargo = VALUES(cargo)
";

$stmt = $db->prepare($sql);

$stmt->execute([
  ':nome' => $nome,
  ':email' => $email,
  ':senha' => $hash,
  ':cargo' => $cargo,
]);

echo "Seed do administrador executado com sucesso!";
echo PHP_EOL;
echo "Email: " . $email;
echo PHP_EOL;
echo "Palavra-passe inicial: " . $senha;
