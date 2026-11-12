<?php
// app/actions/relatorios.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../models/RelatorioReservas.php';
require_once __DIR__ . '/../models/RelatorioRestaurante.php';
require_once __DIR__ . '/../models/RelatorioFinanceiro.php';
require_once __DIR__ . '/../../services/ExportadorExcel.php';
require_once __DIR__ . '/../../services/ExportadorPdf.php';

requireLogin('cliente');

$hotelId = (int) ($_GET['hotel_id'] ?? 0);
$hotel = requireHotelAccess($hotelId);
$cargo = $hotel['cargo'];

// Só quem gere o hotel acede aos relatórios
if (!in_array($cargo, ['proprietario', 'gerente'], true)) {
  http_response_code(403);
  exit('Sem permissão para aceder aos relatórios.');
}

$tipo = $_GET['tipo'] ?? '';
$formato = $_GET['formato'] ?? '';
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01'); // primeiro dia do mês atual
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? null;
$estado = $estado !== '' ? $estado : null;

$tipoPedido = $_GET['tipo_pedido'] ?? null;
$tipoPedido = $tipoPedido !== '' ? $tipoPedido : null;

$metodoPagamento = $_GET['metodo'] ?? null;
$metodoPagamento = $metodoPagamento !== '' ? $metodoPagamento : null;

$tiposValidos = ['checkins', 'checkouts', 'movimento', 'restaurante', 'financeiro', 'pagamentos'];
$formatosValidos = ['excel', 'pdf'];

if (!in_array($tipo, $tiposValidos, true) || !in_array($formato, $formatosValidos, true)) {
  http_response_code(400);
  exit('Parâmetros de relatório inválidos.');
}

try {
  switch ($tipo) {

    case 'checkins':
      $model = new RelatorioReservas($hotelId);
      $linhas = $model->checkins($dataInicio, $dataFim);
      $titulo = 'Relatório de Check-ins';
      $cabecalhos = ['Código', 'Hóspede', 'Quarto', 'Entrada', 'Saída', 'Pessoas', 'Noites', 'Total (Kz)', 'Estado'];
      $dados = array_map(fn($r) => [
        $r['codigo'],
        $r['nome_utilizador'] ?? '—',
        $r['numero_quarto'] ?? '—',
        formatarData($r['data_entrada']),
        formatarData($r['data_saida']),
        $r['quantidade_pessoas'],
        $r['quantidade_noites'],
        number_format((float) $r['total'], 2, ',', '.'),
        ucfirst($r['estado']),
      ], $linhas);
      $resumo = $model->resumoPeriodo($dataInicio, $dataFim);
      break;

    case 'checkouts':
      $model = new RelatorioReservas($hotelId);
      $linhas = $model->checkouts($dataInicio, $dataFim);
      $titulo = 'Relatório de Check-outs';
      $cabecalhos = ['Código', 'Hóspede', 'Quarto', 'Entrada', 'Saída', 'Pessoas', 'Noites', 'Total (Kz)', 'Estado'];
      $dados = array_map(fn($r) => [
        $r['codigo'],
        $r['nome_utilizador'] ?? '—',
        $r['numero_quarto'] ?? '—',
        formatarData($r['data_entrada']),
        formatarData($r['data_saida']),
        $r['quantidade_pessoas'],
        $r['quantidade_noites'],
        number_format((float) $r['total'], 2, ',', '.'),
        ucfirst($r['estado']),
      ], $linhas);
      $resumo = $model->resumoPeriodo($dataInicio, $dataFim);
      break;

    case 'movimento':
      $model = new RelatorioReservas($hotelId);
      $linhas = $model->movimentoPeriodo($dataInicio, $dataFim, $estado);
      $titulo = 'Relatório de Movimento de Reservas';
      $cabecalhos = ['Código', 'Hóspede', 'Quarto', 'Movimento', 'Entrada', 'Saída', 'Total (Kz)', 'Estado'];
      $dados = array_map(fn($r) => [
        $r['codigo'],
        $r['nome_utilizador'] ?? '—',
        $r['numero_quarto'] ?? '—',
        $r['tipo_movimento'],
        formatarData($r['data_entrada']),
        formatarData($r['data_saida']),
        number_format((float) $r['total'], 2, ',', '.'),
        ucfirst($r['estado']),
      ], $linhas);
      $resumo = $model->resumoPeriodo($dataInicio, $dataFim);
      break;

    case 'restaurante':
      $model = new RelatorioRestaurante($hotelId);
      $linhas = $model->listarPedidos($dataInicio, $dataFim, $estado, $tipoPedido);
      $titulo = 'Relatório de Pedidos do Restaurante';
      $cabecalhos = ['Pedido', 'Tipo', 'Mesa', 'Cliente', 'Total (Kz)', 'Estado', 'Data'];
      $dados = array_map(fn($p) => [
        '#' . $p['id'],
        ucfirst($p['tipo']),
        $p['numero_mesa'] ?? '—',
        $p['nome_cliente'] ?? $p['nome_utilizador'] ?? '—',
        number_format((float) $p['total'], 2, ',', '.'),
        ucfirst($p['estado']),
        formatarDataHora($p['criado_em']),
      ], $linhas);
      $resumo = $model->resumoPeriodo($dataInicio, $dataFim);
      break;

    case 'financeiro':
      $model = new RelatorioFinanceiro($hotelId);
      $linhas = $model->receitaDiaria($dataInicio, $dataFim);
      $titulo = 'Relatório Financeiro';
      $cabecalhos = ['Data', 'Receita Quartos (Kz)', 'Receita Restaurante (Kz)', 'Receita Total (Kz)'];
      $dados = array_map(fn($d) => [
        formatarData($d['dia']),
        number_format((float) $d['receita_quartos'], 2, ',', '.'),
        number_format((float) $d['receita_restaurante'], 2, ',', '.'),
        number_format((float) $d['receita_total'], 2, ',', '.'),
      ], $linhas);
      $resumo = $model->resumoPeriodo($dataInicio, $dataFim);
      break;

    case 'pagamentos':
      $model = new RelatorioFinanceiro($hotelId);
      $linhas = $model->listarPagamentos($dataInicio, $dataFim, $estado, $metodoPagamento);
      $titulo = 'Relatório de Pagamentos';
      $cabecalhos = ['Reserva', 'Hóspede', 'Valor (Kz)', 'Método', 'Estado', 'Referência', 'Pago em', 'Criado em'];
      $dados = array_map(fn($p) => [
        $p['codigo_reserva'] ?? '—',
        $p['nome_hospede'] ?? '—',
        number_format((float) $p['valor'], 2, ',', '.'),
        ucfirst(str_replace('_', ' ', $p['metodo'])),
        ucfirst($p['estado']),
        $p['referencia'] ?? '—',
        $p['pago_em'] ? formatarDataHora($p['pago_em']) : '—',
        formatarDataHora($p['criado_em']),
      ], $linhas);
      $resumo = $model->resumoPeriodo($dataInicio, $dataFim);
      break;
  }

  $subtitulo = 'Período: ' . formatarData($dataInicio) . ' a ' . formatarData($dataFim);
  $resumoFormatado = montarResumoParaPdf($tipo, $resumo);

  if ($formato === 'excel') {
    ExportadorExcel::exportar(
      "{$titulo} ({$subtitulo})",
      $cabecalhos,
      $dados,
      str_replace(' ', '_', strtolower($titulo)) . '_' . date('Ymd')
    );
  } else {
    ExportadorPdf::exportar(
      $titulo,
      $cabecalhos,
      $dados,
      str_replace(' ', '_', strtolower($titulo)) . '_' . date('Ymd'),
      array_merge(['Período' => $subtitulo], $resumoFormatado)
    );
  }

} catch (InvalidArgumentException $e) {
  http_response_code(400);
  exit(htmlspecialchars($e->getMessage()));
}

// ---------- Funções auxiliares ----------

function formatarData(?string $data): string
{
  if (!$data) {
    return '—';
  }
  $d = DateTimeImmutable::createFromFormat('Y-m-d', substr($data, 0, 10));
  return $d ? $d->format('d/m/Y') : $data;
}

function formatarDataHora(?string $data): string
{
  if (!$data) {
    return '—';
  }
  $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data);
  return $d ? $d->format('d/m/Y H:i') : $data;
}

function montarResumoParaPdf(string $tipo, array $resumo): array
{
  if ($tipo === 'financeiro') {
    return [
      'Receita de quartos (Kz)' => number_format((float) $resumo['receita_quartos'], 2, ',', '.'),
      'Receita de restaurante (Kz)' => number_format((float) $resumo['receita_restaurante'], 2, ',', '.'),
      'Receita total (Kz)' => number_format((float) $resumo['receita_total'], 2, ',', '.'),
      'Total de transações' => $resumo['total_transacoes'],
      'Ticket médio geral (Kz)' => number_format((float) $resumo['ticket_medio_geral'], 2, ',', '.'),
      'Pagamentos pendentes' => $resumo['pendentes_qtd']
        . ' (' . number_format((float) $resumo['pendentes_valor'], 2, ',', '.') . ' Kz)',
      'Pagamentos falhados' => $resumo['falhados_qtd']
        . ' (' . number_format((float) $resumo['falhados_valor'], 2, ',', '.') . ' Kz)',
      'Reembolsos' => $resumo['reembolsos_qtd']
        . ' (' . number_format((float) $resumo['reembolsos_valor'], 2, ',', '.') . ' Kz)',
    ];
  }

  if ($tipo === 'restaurante') {
    return [
      'Total de pedidos' => $resumo['total_pedidos'],
      'Entregues' => $resumo['total_entregues'],
      'Cancelados' => $resumo['total_cancelados'],
      'Mesa / Quarto / Balcão' => "{$resumo['total_mesa']} / {$resumo['total_quarto']} / {$resumo['total_balcao']}",
      'Receita total (Kz)' => number_format((float) $resumo['receita_total'], 2, ',', '.'),
      'Ticket médio (Kz)' => number_format((float) $resumo['ticket_medio'], 2, ',', '.'),
    ];
  }

  return [
    'Total de reservas' => $resumo['total_reservas'],
    'Check-ins' => $resumo['total_checkins'],
    'Check-outs' => $resumo['total_checkouts'],
    'Canceladas' => $resumo['total_canceladas'],
    'Receita total (Kz)' => number_format((float) $resumo['receita_total'], 2, ',', '.'),
    'Taxa de ocupação' => $resumo['taxa_ocupacao'] . '%',
  ];
}