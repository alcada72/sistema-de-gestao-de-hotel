<?php
// app/models/RelatorioReservas.php
require_once __DIR__ . '/Model.php';

/**
 * Relatório de reservas, check-ins e check-outs por período.
 * Todas as datas são strings 'Y-m-d'.
 */
class RelatorioReservas extends Model
{
  const TABELA = 'reservas';

  /** Valida e normaliza um intervalo de datas. */
  private function validarPeriodo(string $dataInicio, string $dataFim): array
  {
    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $dataInicio);
    $fim = DateTimeImmutable::createFromFormat('!Y-m-d', $dataFim);

    if (!$inicio || !$fim) {
      throw new InvalidArgumentException('Datas do relatório inválidas.');
    }
    if ($fim < $inicio) {
      throw new InvalidArgumentException('A data final não pode ser anterior à data inicial.');
    }

    return [$inicio->format('Y-m-d'), $fim->format('Y-m-d')];
  }

  /**
   * Reservas cuja entrada (check-in) cai dentro do período.
   */
  public function checkins(string $dataInicio, string $dataFim): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    return $this->all("
      SELECT r.codigo, r.data_entrada, r.data_saida,
             r.quantidade_pessoas, r.quantidade_noites,
             r.preco_diaria, r.total, r.estado,
             q.numero AS numero_quarto,
             u.nome AS nome_utilizador
      FROM reservas r
      LEFT JOIN quartos q ON q.id = r.quarto_id
      LEFT JOIN usuarios u ON u.id = r.usuario_id
      WHERE r.hotel_id = ?
        AND r.data_entrada BETWEEN ? AND ?
      ORDER BY r.data_entrada ASC
    ", [$this->hotelId, $inicio, $fim]);
  }

  /**
   * Reservas cuja saída (check-out) cai dentro do período.
   */
  public function checkouts(string $dataInicio, string $dataFim): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    return $this->all("
      SELECT r.codigo, r.data_entrada, r.data_saida,
             r.quantidade_pessoas, r.quantidade_noites,
             r.preco_diaria, r.total, r.estado,
             q.numero AS numero_quarto,
             u.nome AS nome_utilizador
      FROM reservas r
      LEFT JOIN quartos q ON q.id = r.quarto_id
      LEFT JOIN usuarios u ON u.id = r.usuario_id
      WHERE r.hotel_id = ?
        AND r.data_saida BETWEEN ? AND ?
      ORDER BY r.data_saida ASC
    ", [$this->hotelId, $inicio, $fim]);
  }

  /**
   * Todas as reservas com atividade (entrada OU saída) no período,
   * com uma coluna indicando o tipo de movimento.
   * Útil para um relatório único de "movimento do período".
   */
  public function movimentoPeriodo(string $dataInicio, string $dataFim, ?string $estado = null): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    $params = [$this->hotelId, $inicio, $fim, $inicio, $fim];
    $filtroEstado = '';

    if ($estado !== null) {
      if (!in_array($estado, Reserva::ESTADOS, true)) {
        throw new InvalidArgumentException('Estado inválido.');
      }
      $filtroEstado = ' AND r.estado = ?';
      $params[] = $estado;
    }

    return $this->all("
      SELECT r.codigo, r.data_entrada, r.data_saida,
             r.quantidade_pessoas, r.quantidade_noites,
             r.preco_diaria, r.total, r.estado,
             q.numero AS numero_quarto,
             u.nome AS nome_utilizador,
             CASE
               WHEN r.data_entrada BETWEEN ? AND ? THEN 'check-in'
               ELSE 'check-out'
             END AS tipo_movimento
      FROM reservas r
      LEFT JOIN quartos q ON q.id = r.quarto_id
      LEFT JOIN usuarios u ON u.id = r.usuario_id
      WHERE r.hotel_id = ?
        AND (r.data_entrada BETWEEN ? AND ? OR r.data_saida BETWEEN ? AND ?)
        {$filtroEstado}
      ORDER BY r.data_entrada ASC
    ", array_merge(
      [$inicio, $fim, $this->hotelId, $inicio, $fim, $inicio, $fim],
      $estado !== null ? [$estado] : []
    ));
  }

  /**
   * Resumo agregado do período: totais, ocupação e receita.
   */
  public function resumoPeriodo(string $dataInicio, string $dataFim): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    $resumo = $this->one("
      SELECT
        COUNT(*) AS total_reservas,
        SUM(r.data_entrada BETWEEN ? AND ?) AS total_checkins,
        SUM(r.data_saida BETWEEN ? AND ?) AS total_checkouts,
        SUM(r.estado = 'cancelada') AS total_canceladas,
        COALESCE(SUM(CASE WHEN r.estado != 'cancelada' THEN r.total ELSE 0 END), 0) AS receita_total,
        COALESCE(SUM(CASE WHEN r.estado != 'cancelada' THEN r.quantidade_noites ELSE 0 END), 0) AS total_noites
      FROM reservas r
      WHERE r.hotel_id = ?
        AND (r.data_entrada BETWEEN ? AND ? OR r.data_saida BETWEEN ? AND ?)
    ", [$inicio, $fim, $inicio, $fim, $this->hotelId, $inicio, $fim, $inicio, $fim]);

    $totalQuartos = (int) $this->valor(
      "SELECT COUNT(*) FROM quartos WHERE hotel_id = ? AND estado = 'ativo'",
      [$this->hotelId]
    );

    $dias = (new DateTimeImmutable($inicio))->diff(new DateTimeImmutable($fim))->days + 1;
    $capacidadeNoites = $totalQuartos * $dias;

    $resumo['taxa_ocupacao'] = $capacidadeNoites > 0
      ? round(($resumo['total_noites'] / $capacidadeNoites) * 100, 1)
      : 0.0;

    return $resumo;
  }
}