<?php
// app/models/Localizacao.php
require_once __DIR__ . '/../../config/config.php';

class Localizacao
{
  public static function provincias(): array
  {
    return getDB()
      ->query("SELECT id, nome FROM provincias ORDER BY nome")
      ->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function municipios(int $provinciaId): array
  {
    $stmt = getDB()->prepare("
      SELECT id, nome FROM municipios
      WHERE provincia_id = ?
      ORDER BY nome
    ");
    $stmt->execute([$provinciaId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}