<?php
// app/models/Andar.php
require_once __DIR__ . '/Model.php';

class Andar extends Model
{
  const TABELA = 'andares';

  public function listar(): array
  {
    return $this->all("
      SELECT a.id, a.numero_andar, a.descricao,
             COUNT(q.id) AS total_quartos,
             SUM(q.estado = 'ativo') AS quartos_ativos
      FROM andares a
      LEFT JOIN quartos q ON q.andar_id = a.id AND q.hotel_id = a.hotel_id
      WHERE a.hotel_id = ?
      GROUP BY a.id, a.numero_andar, a.descricao
      ORDER BY a.numero_andar
    ", [$this->hotelId]);
  }

  public function listarSimples(): array
  {
    return $this->all(
      "SELECT id, numero_andar FROM andares WHERE hotel_id = ? ORDER BY numero_andar",
      [$this->hotelId]
    );
  }

  public function criar(int $numero, ?string $descricao = null): int
  {
    if ($numero < -5 || $numero > 100) {
      throw new InvalidArgumentException('Número de andar fora do intervalo permitido.');
    }
    if ($this->valor("SELECT 1 FROM andares WHERE hotel_id = ? AND numero_andar = ?", [$this->hotelId, $numero])) {
      throw new DomainException("O {$numero}.º andar já existe.");
    }

    $this->query("
      INSERT INTO andares (hotel_id, numero_andar, descricao)
      VALUES (?, ?, ?)
    ", [$this->hotelId, $numero, $descricao ?: null]);

    return (int) $this->pdo->lastInsertId();
  }

  public function eliminar(int $id): bool
  {
    if (!$this->pertenceAoHotel('andares', $id)) {
      return false;
    }
    if ((int) $this->valor("SELECT COUNT(*) FROM quartos WHERE andar_id = ?", [$id]) > 0) {
      throw new DomainException('Não é possível eliminar um andar que tem quartos.');
    }

    return $this->query(
      "DELETE FROM andares WHERE id = ? AND hotel_id = ?",
      [$id, $this->hotelId]
    )->rowCount() > 0;
  }
}