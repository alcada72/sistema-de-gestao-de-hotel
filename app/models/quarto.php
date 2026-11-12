<?php
// app/models/Quarto.php
require_once __DIR__ . '/Model.php';

class Quarto extends Model
{
  const TABELA = 'quartos';
  const ESTADOS = ['ativo', 'manutencao', 'inativo'];

  public function listar(): array
  {
    return $this->all("
      SELECT q.id, q.numero, q.estado,
             c.nome AS categoria, c.capacidade, c.preco_base,
             a.numero_andar, a.descricao AS descricao_andar
      FROM quartos q
      INNER JOIN categorias_quartos c ON c.id = q.categoria_id
      INNER JOIN andares a ON a.id = q.andar_id
      WHERE q.hotel_id = ?
      ORDER BY q.numero ASC
    ", [$this->hotelId]);
  }

  public function listarAtivos(): array
  {
    return $this->all("
      SELECT id, numero, categoria_id
      FROM quartos
      WHERE hotel_id = ? AND estado = 'ativo'
      ORDER BY numero
    ", [$this->hotelId]);
  }

  public function estatisticas(): array
  {
    return $this->one("
      SELECT COUNT(*) AS total,
             SUM(estado = 'ativo') AS ativos,
             SUM(estado = 'manutencao') AS manutencao,
             SUM(estado = 'inativo') AS inativos
      FROM quartos
      WHERE hotel_id = ?
    ", [$this->hotelId]);
  }

  public function buscar(int $id): ?array
  {
    return $this->one(
      "SELECT * FROM quartos WHERE id = ? AND hotel_id = ?",
      [$id, $this->hotelId]
    );
  }

  public function criar(array $dados): int
  {
    $numero = trim($dados['numero'] ?? '');
    $andarId = (int) ($dados['andar_id'] ?? 0);
    $categoriaId = (int) ($dados['categoria_id'] ?? 0);
    $estado = $dados['estado'] ?? 'ativo';

    if ($numero === '' || mb_strlen($numero) > 20) {
      throw new InvalidArgumentException('Número do quarto inválido.');
    }
    if (!in_array($estado, self::ESTADOS, true)) {
      throw new InvalidArgumentException('Estado inválido.');
    }
    if (!$this->pertenceAoHotel('andares', $andarId)) {
      throw new InvalidArgumentException('Andar inválido.');
    }
    if (!$this->pertenceAoHotel('categorias_quartos', $categoriaId)) {
      throw new InvalidArgumentException('Categoria inválida.');
    }
    if ($this->valor("SELECT 1 FROM quartos WHERE hotel_id = ? AND numero = ?", [$this->hotelId, $numero])) {
      throw new DomainException("Já existe um quarto com o número {$numero}.");
    }

    $this->query("
      INSERT INTO quartos (hotel_id, numero, andar_id, categoria_id, estado)
      VALUES (?, ?, ?, ?, ?)
    ", [$this->hotelId, $numero, $andarId, $categoriaId, $estado]);

    return (int) $this->pdo->lastInsertId();
  }

  public function atualizarEstado(int $id, string $estado): bool
  {
    if (!in_array($estado, self::ESTADOS, true)) {
      throw new InvalidArgumentException('Estado inválido.');
    }

    return $this->query(
      "UPDATE quartos SET estado = ? WHERE id = ? AND hotel_id = ?",
      [$estado, $id, $this->hotelId]
    )->rowCount() > 0;
  }

  public function eliminar(int $id): bool
  {
    return $this->query(
      "DELETE FROM quartos WHERE id = ? AND hotel_id = ?",
      [$id, $this->hotelId]
    )->rowCount() > 0;
  }
}