<?php
// app/models/RelatorioRestaurante.php
require_once __DIR__ . '/Model.php';

/**
 * Relatório de pedidos do restaurante por período.
 * Tabelas: `pedidos` e `itens_pedido`.
 */
class RelatorioRestaurante extends Model
{
  const TABELA = 'pedidos';
  const ESTADOS = ['pendente', 'preparacao', 'pronto', 'entregue', 'cancelado'];
  const TIPOS = ['mesa', 'quarto', 'balcao'];

  /** Estado que conta como venda concluída para efeitos de receita. */
  const ESTADO_CONCLUIDO = 'entregue';

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

    // Inclui o dia inteiro de $fim
    return [$inicio->format('Y-m-d 00:00:00'), $fim->format('Y-m-d 23:59:59')];
  }

  /**
   * Lista de pedidos do período, com filtros opcionais de estado e tipo.
   */
  public function listarPedidos(
    string $dataInicio,
    string $dataFim,
    ?string $estado = null,
    ?string $tipo = null
  ): array {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    $params = [$this->hotelId, $inicio, $fim];
    $filtros = '';

    if ($estado !== null) {
      if (!in_array($estado, self::ESTADOS, true)) {
        throw new InvalidArgumentException('Estado de pedido inválido.');
      }
      $filtros .= ' AND p.estado = ?';
      $params[] = $estado;
    }

    if ($tipo !== null) {
      if (!in_array($tipo, self::TIPOS, true)) {
        throw new InvalidArgumentException('Tipo de pedido inválido.');
      }
      $filtros .= ' AND p.tipo = ?';
      $params[] = $tipo;
    }

    return $this->all("
      SELECT
        p.id, p.tipo, p.numero_mesa, p.nome_cliente, p.telefone_cliente,
        p.estado, p.total, p.observacoes, p.criado_em,
        r.codigo AS codigo_reserva,
        u.nome AS nome_utilizador
      FROM pedidos p
      LEFT JOIN reservas r ON r.id = p.reserva_id AND r.hotel_id = p.hotel_id
      LEFT JOIN usuarios u ON u.id = p.usuario_id
      WHERE p.hotel_id = ?
        AND p.criado_em BETWEEN ? AND ?
        {$filtros}
      ORDER BY p.criado_em DESC
    ", $params);
  }

  /**
   * Pedido com os respetivos itens, para detalhe ou impressão.
   */
  public function detalharPedido(int $pedidoId): ?array
  {
    $pedido = $this->one("
      SELECT p.*, r.codigo AS codigo_reserva, u.nome AS nome_utilizador
      FROM pedidos p
      LEFT JOIN reservas r ON r.id = p.reserva_id AND r.hotel_id = p.hotel_id
      LEFT JOIN usuarios u ON u.id = p.usuario_id
      WHERE p.id = ? AND p.hotel_id = ?
    ", [$pedidoId, $this->hotelId]);

    if (!$pedido) {
      return null;
    }

    $pedido['itens'] = $this->all("
      SELECT nome_produto, quantidade, preco_unitario, subtotal
      FROM itens_pedido
      WHERE pedido_id = ? AND hotel_id = ?
      ORDER BY id ASC
    ", [$pedidoId, $this->hotelId]);

    return $pedido;
  }

  /**
   * Resumo do período: totais por estado, por tipo, receita e ticket médio.
   */
  public function resumoPeriodo(string $dataInicio, string $dataFim): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    $resumo = $this->one("
      SELECT
        COUNT(*) AS total_pedidos,
        SUM(estado = 'pendente')   AS total_pendentes,
        SUM(estado = 'preparacao') AS total_preparacao,
        SUM(estado = 'pronto')     AS total_prontos,
        SUM(estado = 'entregue')   AS total_entregues,
        SUM(estado = 'cancelado')  AS total_cancelados,
        SUM(tipo = 'mesa')   AS total_mesa,
        SUM(tipo = 'quarto') AS total_quarto,
        SUM(tipo = 'balcao') AS total_balcao,
        COALESCE(SUM(CASE WHEN estado = '" . self::ESTADO_CONCLUIDO . "' THEN total ELSE 0 END), 0) AS receita_total
      FROM pedidos
      WHERE hotel_id = ? AND criado_em BETWEEN ? AND ?
    ", [$this->hotelId, $inicio, $fim]);

    $resumo['ticket_medio'] = $resumo['total_entregues'] > 0
      ? round($resumo['receita_total'] / $resumo['total_entregues'], 2)
      : 0.0;

    return $resumo;
  }

  /**
   * Produtos mais vendidos no período (só pedidos entregues).
   */
  public function produtosMaisVendidos(string $dataInicio, string $dataFim, int $limite = 10): array
  {
    [$inicio, $fim] = $this->validarPeriodo($dataInicio, $dataFim);

    return $this->all("
      SELECT
        ip.nome_produto,
        SUM(ip.quantidade) AS quantidade_total,
        SUM(ip.subtotal) AS receita_total
      FROM itens_pedido ip
      INNER JOIN pedidos p ON p.id = ip.pedido_id AND p.hotel_id = ip.hotel_id
      WHERE p.hotel_id = ?
        AND p.estado = '" . self::ESTADO_CONCLUIDO . "'
        AND p.criado_em BETWEEN ? AND ?
      GROUP BY ip.nome_produto
      ORDER BY quantidade_total DESC
      LIMIT ?
    ", [$this->hotelId, $inicio, $fim, $limite]);
  }

  /**
   * Pedidos ligados a uma reserva (consumo de quarto, para fatura final).
   */
  public function pedidosDaReserva(int $reservaId): array
  {
    return $this->all("
      SELECT id, tipo, estado, total, criado_em
      FROM pedidos
      WHERE hotel_id = ? AND reserva_id = ?
      ORDER BY criado_em ASC
    ", [$this->hotelId, $reservaId]);
  }
}