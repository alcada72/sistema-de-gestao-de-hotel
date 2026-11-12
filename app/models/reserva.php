<?php
// app/models/Reserva.php
require_once __DIR__ . '/Model.php';

class Reserva extends Model
{
  const TABELA = 'reservas';
  const ESTADOS = ['pendente', 'confirmada', 'checkin', 'concluida', 'cancelada'];
  const ESTADOS_OCUPADOS = ['pendente', 'confirmada', 'checkin'];

  public function listar(): array
  {
    return $this->all("
      SELECT r.id, r.codigo, r.data_entrada, r.data_saida,
             r.quantidade_pessoas, r.quantidade_noites, r.preco_diaria,
             r.total, r.estado, r.criado_em,
             q.numero AS numero_quarto,
             u.nome AS nome_utilizador
      FROM reservas r
      LEFT JOIN quartos q ON q.id = r.quarto_id
      LEFT JOIN usuarios u ON u.id = r.usuario_id
      WHERE r.hotel_id = ?
      ORDER BY r.criado_em DESC
    ", [$this->hotelId]);
  }

  public function estatisticas(): array
  {
    return $this->one("
      SELECT COUNT(*) AS total,
             SUM(estado = 'pendente') AS pendentes,
             SUM(estado = 'confirmada') AS confirmadas,
             SUM(estado = 'checkin') AS checkins,
             SUM(estado = 'concluida') AS concluidas,
             SUM(estado = 'cancelada') AS canceladas
      FROM reservas
      WHERE hotel_id = ?
    ", [$this->hotelId]);
  }

  public function buscarPorCodigo(string $codigo): ?array
  {
    return $this->one(
      "SELECT * FROM reservas WHERE codigo = ? AND hotel_id = ?",
      [$codigo, $this->hotelId]
    );
  }

  /**
   * Cria uma reserva feita na recepção.
   * O total é calculado aqui, nunca vem do formulário.
   */
  public function criarPresencial(array $dados): array
  {
    // '!' põe a hora a zero, senão o cálculo de noites falha
    $entrada = DateTimeImmutable::createFromFormat('!Y-m-d', $dados['data_entrada'] ?? '');
    $saida = DateTimeImmutable::createFromFormat('!Y-m-d', $dados['data_saida'] ?? '');
    $hoje = new DateTimeImmutable('today');

    if (!$entrada || !$saida || $entrada < $hoje || $saida <= $entrada) {
      throw new InvalidArgumentException('Datas inválidas.');
    }

    $quarto = $this->one("
      SELECT q.id, c.capacidade, c.preco_base
      FROM quartos q
      INNER JOIN categorias_quartos c ON c.id = q.categoria_id
      WHERE q.id = ? AND q.hotel_id = ? AND q.estado = 'ativo'
    ", [(int) ($dados['quarto_id'] ?? 0), $this->hotelId]);

    if (!$quarto) {
      throw new InvalidArgumentException('Quarto inválido ou indisponível.');
    }

    $pessoas = (int) ($dados['quantidade_pessoas'] ?? 1);
    if ($pessoas < 1 || $pessoas > (int) $quarto['capacidade']) {
      throw new InvalidArgumentException('Número de hóspedes acima da capacidade do quarto.');
    }

    if (!$this->quartoLivre((int) $quarto['id'], $entrada, $saida)) {
      throw new DomainException('O quarto já está reservado nesse período.');
    }

    $noites = (int) $entrada->diff($saida)->days;
    $precoDiaria = (float) $quarto['preco_base'];
    $total = $noites * $precoDiaria;
    $codigo = 'RES-' . strtoupper(bin2hex(random_bytes(3)));

    $this->query("
      INSERT INTO reservas
        (hotel_id, codigo, quarto_id, data_entrada, data_saida,
         quantidade_pessoas, quantidade_noites, preco_diaria, total, estado, criado_em)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmada', NOW())
    ", [
      $this->hotelId,
      $codigo,
      (int) $quarto['id'],
      $entrada->format('Y-m-d'),
      $saida->format('Y-m-d'),
      $pessoas,
      $noites,
      $precoDiaria,
      $total,
    ]);

    return [
      'id' => (int) $this->pdo->lastInsertId(),
      'codigo' => $codigo,
      'total' => $total,
    ];
  }

  public function atualizarEstado(int $id, string $estado): bool
  {
    if (!in_array($estado, self::ESTADOS, true)) {
      throw new InvalidArgumentException('Estado inválido.');
    }

    return $this->query(
      "UPDATE reservas SET estado = ? WHERE id = ? AND hotel_id = ?",
      [$estado, $id, $this->hotelId]
    )->rowCount() > 0;
  }

  /** Verifica sobreposição de datas com reservas ativas do quarto. */
  private function quartoLivre(int $quartoId, DateTimeImmutable $entrada, DateTimeImmutable $saida): bool
  {
    $marcas = implode(',', array_fill(0, count(self::ESTADOS_OCUPADOS), '?'));

    $sql = "
      SELECT COUNT(*) FROM reservas
      WHERE quarto_id = ?
        AND estado IN ($marcas)
        AND data_entrada < ?
        AND data_saida > ?
    ";

    $params = array_merge(
      [$quartoId],
      self::ESTADOS_OCUPADOS,
      [$saida->format('Y-m-d'), $entrada->format('Y-m-d')]
    );

    return (int) $this->valor($sql, $params) === 0;
  }
}