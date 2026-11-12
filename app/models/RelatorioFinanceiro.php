<?php
// app/models/RelatorioFinanceiro.php
require_once __DIR__ . '/Model.php';

/**
 * Relatório financeiro consolidado.
 *
 * Receita de quartos: vem da tabela `pagamentos` (estado = 'pago', por `pago_em`).
 * Receita de restaurante: vem de `pedidos.total` (estado = 'entregue'), porque
 * não existe ligação de `pagamentos` a pedidos — só a `reserva_id`.
 * Se um dia os pedidos passarem a ter pagamento próprio, atualizar aqui.
 */
class RelatorioFinanceiro extends Model
{
  const TABELA = 'pagamentos';
  const METODOS = ['dinheiro', 'transferencia', 'cartao', 'referencia', 'multicaixa_express', 'outro'];
  const ESTADOS_PAGAMENTO = ['pendente', 'pago', 'falhou', 'reembolsado'];

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

    return [$inicio, $fim];
  }

  /**
   * Resumo financeiro do período: receita por origem, pendências e ticket médio.
   */
  public function resumoPeriodo(string $dataInicio, string $dataFim): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);
    $inicioHora = $inicio->format('Y-m-d 00:00:00');
    $fimHora = $fim->format('Y-m-d 23:59:59');

    // Receita de quartos: pagamentos efetivamente recebidos no período
    $pagamentos = $this->one("
      SELECT
        COUNT(*) AS total_transacoes,
        COALESCE(SUM(valor), 0) AS receita_total
      FROM pagamentos
      WHERE hotel_id = ?
        AND estado = 'pago'
        AND pago_em BETWEEN ? AND ?
    ", [$this->hotelId, $inicioHora, $fimHora]);

    // Pendências: pagamentos ainda não liquidados, criados dentro do período
    $pendentes = $this->one("
      SELECT
        COUNT(*) AS total,
        COALESCE(SUM(valor), 0) AS valor_total
      FROM pagamentos
      WHERE hotel_id = ?
        AND estado = 'pendente'
        AND criado_em BETWEEN ? AND ?
    ", [$this->hotelId, $inicioHora, $fimHora]);

    // Falhas: pagamentos que falharam no período (sinal de alerta operacional)
    $falhados = $this->one("
      SELECT
        COUNT(*) AS total,
        COALESCE(SUM(valor), 0) AS valor_total
      FROM pagamentos
      WHERE hotel_id = ?
        AND estado = 'falhou'
        AND criado_em BETWEEN ? AND ?
    ", [$this->hotelId, $inicioHora, $fimHora]);

    // Reembolsos no período
    $reembolsos = $this->one("
      SELECT
        COUNT(*) AS total,
        COALESCE(SUM(valor), 0) AS valor_total
      FROM pagamentos
      WHERE hotel_id = ?
        AND estado = 'reembolsado'
        AND criado_em BETWEEN ? AND ?
    ", [$this->hotelId, $inicioHora, $fimHora]);

    // Receita de restaurante (só pedidos entregues, ver nota no topo do ficheiro)
    $restaurante = $this->one("
      SELECT
        COUNT(*) AS total_transacoes,
        COALESCE(SUM(total), 0) AS receita_total
      FROM pedidos
      WHERE hotel_id = ?
        AND estado = 'entregue'
        AND criado_em BETWEEN ? AND ?
    ", [$this->hotelId, $inicioHora, $fimHora]);

    $receitaQuartos = (float) $pagamentos['receita_total'];
    $receitaRestaurante = (float) $restaurante['receita_total'];
    $receitaTotal = $receitaQuartos + $receitaRestaurante;
    $totalTransacoes = (int) $pagamentos['total_transacoes'] + (int) $restaurante['total_transacoes'];

    return [
      'receita_quartos' => $receitaQuartos,
      'receita_restaurante' => $receitaRestaurante,
      'receita_total' => $receitaTotal,
      'total_transacoes' => $totalTransacoes,
      'ticket_medio_geral' => $totalTransacoes > 0 ? round($receitaTotal / $totalTransacoes, 2) : 0.0,
      'transacoes_quartos' => (int) $pagamentos['total_transacoes'],
      'transacoes_restaurante' => (int) $restaurante['total_transacoes'],

      'pendentes_qtd' => (int) $pendentes['total'],
      'pendentes_valor' => (float) $pendentes['valor_total'],
      'falhados_qtd' => (int) $falhados['total'],
      'falhados_valor' => (float) $falhados['valor_total'],
      'reembolsos_qtd' => (int) $reembolsos['total'],
      'reembolsos_valor' => (float) $reembolsos['valor_total'],
    ];
  }

  /**
   * Receita diária (quartos pagos + restaurante entregue), para gráfico/relatório.
   * Devolve uma linha por dia do período, mesmo sem movimento (receita = 0).
   */
  public function receitaDiaria(string $dataInicio, string $dataFim): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);
    $inicioHora = $inicio->format('Y-m-d 00:00:00');
    $fimHora = $fim->format('Y-m-d 23:59:59');

    $porDiaQuartos = $this->all("
      SELECT DATE(pago_em) AS dia, COALESCE(SUM(valor), 0) AS receita
      FROM pagamentos
      WHERE hotel_id = ?
        AND estado = 'pago'
        AND pago_em BETWEEN ? AND ?
      GROUP BY DATE(pago_em)
    ", [$this->hotelId, $inicioHora, $fimHora]);

    $porDiaRestaurante = $this->all("
      SELECT DATE(criado_em) AS dia, COALESCE(SUM(total), 0) AS receita
      FROM pedidos
      WHERE hotel_id = ?
        AND estado = 'entregue'
        AND criado_em BETWEEN ? AND ?
      GROUP BY DATE(criado_em)
    ", [$this->hotelId, $inicioHora, $fimHora]);

    $mapaQuartos = array_column($porDiaQuartos, 'receita', 'dia');
    $mapaRestaurante = array_column($porDiaRestaurante, 'receita', 'dia');

    $resultado = [];
    $cursor = $inicio;

    while ($cursor <= $fim) {
      $dia = $cursor->format('Y-m-d');

      $receitaQuartos = (float) ($mapaQuartos[$dia] ?? 0);
      $receitaRestaurante = (float) ($mapaRestaurante[$dia] ?? 0);

      $resultado[] = [
        'dia' => $dia,
        'receita_quartos' => $receitaQuartos,
        'receita_restaurante' => $receitaRestaurante,
        'receita_total' => $receitaQuartos + $receitaRestaurante,
      ];

      $cursor = $cursor->modify('+1 day');
    }

    return $resultado;
  }

  /**
   * Receita de quartos por método de pagamento (só pagamentos liquidados).
   */
  public function receitaPorMetodo(string $dataInicio, string $dataFim): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    return $this->all("
      SELECT
        metodo,
        COUNT(*) AS total_transacoes,
        COALESCE(SUM(valor), 0) AS receita_total
      FROM pagamentos
      WHERE hotel_id = ?
        AND estado = 'pago'
        AND pago_em BETWEEN ? AND ?
      GROUP BY metodo
      ORDER BY receita_total DESC
    ", [$this->hotelId, $inicio->format('Y-m-d 00:00:00'), $fim->format('Y-m-d 23:59:59')]);
  }

  /**
   * Receita de quartos por categoria, no período (join via reserva).
   */
  public function receitaPorCategoria(string $dataInicio, string $dataFim): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    return $this->all("
      SELECT
        c.nome AS categoria,
        COUNT(DISTINCT pg.id) AS total_pagamentos,
        COALESCE(SUM(pg.valor), 0) AS receita_total
      FROM pagamentos pg
      INNER JOIN reservas r ON r.id = pg.reserva_id AND r.hotel_id = pg.hotel_id
      INNER JOIN quartos q ON q.id = r.quarto_id
      INNER JOIN categorias_quartos c ON c.id = q.categoria_id
      WHERE pg.hotel_id = ?
        AND pg.estado = 'pago'
        AND pg.pago_em BETWEEN ? AND ?
      GROUP BY c.id, c.nome
      ORDER BY receita_total DESC
    ", [$this->hotelId, $inicio->format('Y-m-d 00:00:00'), $fim->format('Y-m-d 23:59:59')]);
  }

  /**
   * Lista detalhada de pagamentos do período, com filtros opcionais.
   */
  public function listarPagamentos(
    string $dataInicio,
    string $dataFim,
    ?string $estado = null,
    ?string $metodo = null
  ): array {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    $params = [$this->hotelId, $inicio->format('Y-m-d 00:00:00'), $fim->format('Y-m-d 23:59:59')];
    $filtros = '';

    if ($estado !== null) {
      if (!in_array($estado, self::ESTADOS_PAGAMENTO, true)) {
        throw new InvalidArgumentException('Estado de pagamento inválido.');
      }
      $filtros .= ' AND pg.estado = ?';
      $params[] = $estado;
    }

    if ($metodo !== null) {
      if (!in_array($metodo, self::METODOS, true)) {
        throw new InvalidArgumentException('Método de pagamento inválido.');
      }
      $filtros .= ' AND pg.metodo = ?';
      $params[] = $metodo;
    }

    return $this->all("
      SELECT
        pg.id, pg.valor, pg.metodo, pg.estado, pg.referencia,
        pg.pago_em, pg.criado_em,
        r.codigo AS codigo_reserva,
        u.nome AS nome_hospede
      FROM pagamentos pg
      INNER JOIN reservas r ON r.id = pg.reserva_id AND r.hotel_id = pg.hotel_id
      LEFT JOIN usuarios u ON u.id = r.usuario_id
      WHERE pg.hotel_id = ?
        AND pg.criado_em BETWEEN ? AND ?
        {$filtros}
      ORDER BY pg.criado_em DESC
    ", $params);
  }

  /**
   * Regista um novo pagamento para uma reserva.
   */
  public function registarPagamento(int $reservaId, float $valor, string $metodo, ?string $referencia = null): int
  {
    if (!in_array($metodo, self::METODOS, true)) {
      throw new InvalidArgumentException('Método de pagamento inválido.');
    }
    if ($valor <= 0) {
      throw new InvalidArgumentException('O valor do pagamento deve ser positivo.');
    }
    if (!$this->pertenceAoHotel('reservas', $reservaId)) {
      throw new InvalidArgumentException('Reserva inválida.');
    }

    $this->query("
      INSERT INTO pagamentos (reserva_id, hotel_id, valor, metodo, estado, referencia, criado_em)
      VALUES (?, ?, ?, ?, 'pendente', ?, NOW())
    ", [$reservaId, $this->hotelId, $valor, $metodo, $referencia]);

    return (int) $this->pdo->lastInsertId();
  }

  /**
   * Marca um pagamento como pago (ou outro estado final).
   */
  public function atualizarEstadoPagamento(int $pagamentoId, string $estado): bool
  {
    if (!in_array($estado, self::ESTADOS_PAGAMENTO, true)) {
      throw new InvalidArgumentException('Estado de pagamento inválido.');
    }

    $pagoEm = $estado === 'pago' ? date('Y-m-d H:i:s') : null;

    return $this->query("
      UPDATE pagamentos
      SET estado = ?, pago_em = ?
      WHERE id = ? AND hotel_id = ?
    ", [$estado, $pagoEm, $pagamentoId, $this->hotelId])->rowCount() > 0;
  }
}