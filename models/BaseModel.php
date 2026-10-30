<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

abstract class BaseModel
{
  protected PDO $db;
  protected string $table;
  protected array $fillable = [];

  public function __construct()
  {
    $this->db = getDB();
  }

  public function find(int $id): array|false
  {
    $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function findAll(): array
  {
    $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function create(array $data): int
  {
    $data = $this->onlyFillable($data);
    if (!$data) {
      throw new InvalidArgumentException('Nenhum campo válido foi enviado.');
    }

    $columns = array_keys($data);
    $quotedColumns = implode(', ', array_map(fn($c) => "`$c`", $columns));
    $placeholders = implode(', ', array_map(fn($c) => ":$c", $columns));

    $sql = "INSERT INTO {$this->table} ($quotedColumns) VALUES ($placeholders)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute($data);

    return (int) $this->db->lastInsertId();
  }

  public function update(int $id, array $data): bool
  {
    $data = $this->onlyFillable($data);
    if (!$data) {
      throw new InvalidArgumentException('Nenhum campo válido foi enviado para atualização.');
    }

    $sets = [];
    foreach (array_keys($data) as $column) {
      $sets[] = "`$column` = :$column";
    }

    $data['__id'] = $id;
    $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = :__id";
    $stmt = $this->db->prepare($sql);
    $stmt->execute($data);

    return $stmt->rowCount() > 0;
  }

  public function delete(int $id): bool
  {
    $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
    $stmt->execute(['id' => $id]);
    return $stmt->rowCount() > 0;
  }

  public function countAll(): int
  {
    return (int) $this->db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
  }

  protected function onlyFillable(array $data): array
  {
    return array_intersect_key($data, array_flip($this->fillable));
  }

  protected function fetchAllBy(string $column, mixed $value): array
  {
    // $column deve ser definido pelo próprio Model, nunca diretamente pelo utilizador.
    $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE `$column` = :value ORDER BY id DESC");
    $stmt->execute(['value' => $value]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
