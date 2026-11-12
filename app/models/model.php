<?php
// app/models/Model.php
require_once __DIR__ . '/../../config/config.php';

abstract class Model
{
  protected const TABELA = '';

  protected PDO $pdo;
  protected int $hotelId;

  public function __construct(int $hotelId)
  {
    $this->pdo = getDB();
    $this->hotelId = $hotelId;
  }

  protected function query(string $sql, array $params = []): PDOStatement
  {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
  }

  protected function all(string $sql, array $params = []): array
  {
    return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
  }

  protected function one(string $sql, array $params = []): ?array
  {
    return $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  protected function valor(string $sql, array $params = []): mixed
  {
    return $this->query($sql, $params)->fetchColumn();
  }

  /** Confirma que um registo existe e pertence a este hotel. */
  protected function pertenceAoHotel(string $tabela, int $id): bool
  {
    return (bool) $this->valor(
      "SELECT 1 FROM {$tabela} WHERE id = ? AND hotel_id = ?",
      [$id, $this->hotelId]
    );
  }

  /** Total de registos do hotel na tabela definida por cada subclasse. */
  public function total(): int
  {
    if (static::TABELA === '') {
      throw new LogicException('A subclasse deve definir a constante TABELA.');
    }

    return (int) $this->valor(
      "SELECT COUNT(*) FROM " . static::TABELA . " WHERE hotel_id = ?",
      [$this->hotelId]
    );
  }
}