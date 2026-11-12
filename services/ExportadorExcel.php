<?php
// app/services/ExportadorExcel.php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Gera um ficheiro .xlsx a partir de cabeçalhos + linhas e envia-o
 * diretamente para download (termina a execução do script).
 */
class ExportadorExcel
{
  /**
   * @param string $titulo     Título do relatório (linha 1 da folha).
   * @param array  $cabecalhos Nomes das colunas, ex.: ['Código', 'Hóspede', 'Total']
   * @param array  $linhas     Lista de arrays associativos ou indexados, uma por linha.
   * @param string $nomeFicheiro Nome do ficheiro a descarregar, sem extensão.
   */
  public static function exportar(
    string $titulo,
    array $cabecalhos,
    array $linhas,
    string $nomeFicheiro = 'relatorio'
  ): void {
    $planilha = new Spreadsheet();
    $folha = $planilha->getActiveSheet();
    $folha->setTitle('Relatório');

    $totalColunas = count($cabecalhos);
    $ultimaColuna = self::letraColuna($totalColunas);

    // Título
    $folha->setCellValue('A1', $titulo);
    $folha->mergeCells("A1:{$ultimaColuna}1");
    $folha->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $folha->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $folha->setCellValue('A2', 'Gerado em: ' . date('d/m/Y H:i'));
    $folha->mergeCells("A2:{$ultimaColuna}2");
    $folha->getStyle('A2')->getFont()->setItalic(true)->setSize(9);

    // Cabeçalhos (linha 4)
    $linhaCabecalho = 4;
    foreach (array_values($cabecalhos) as $i => $nomeColuna) {
      $coluna = self::letraColuna($i + 1);
      $folha->setCellValue("{$coluna}{$linhaCabecalho}", $nomeColuna);
    }

    $folha->getStyle("A{$linhaCabecalho}:{$ultimaColuna}{$linhaCabecalho}")->getFont()->setBold(true);
    $folha->getStyle("A{$linhaCabecalho}:{$ultimaColuna}{$linhaCabecalho}")->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('D8E1FF');

    // Dados
    $linhaAtual = $linhaCabecalho + 1;
    foreach ($linhas as $linha) {
      $valores = array_is_list($linha) ? $linha : array_values($linha);

      foreach ($valores as $i => $valor) {
        $coluna = self::letraColuna($i + 1);
        $folha->setCellValue("{$coluna}{$linhaAtual}", $valor);
      }
      $linhaAtual++;
    }

    // Ajusta a largura das colunas ao conteúdo
    foreach (range(1, $totalColunas) as $i) {
      $folha->getColumnDimension(self::letraColuna($i))->setAutoSize(true);
    }

    // Rodapé com total de linhas
    $folha->setCellValue("A{$linhaAtual}", 'Total de registos: ' . count($linhas));
    $folha->getStyle("A{$linhaAtual}")->getFont()->setBold(true);

    self::enviarDownload($planilha, $nomeFicheiro);
  }

  private static function letraColuna(int $numero): string
  {
    $letra = '';
    while ($numero > 0) {
      $resto = ($numero - 1) % 26;
      $letra = chr(65 + $resto) . $letra;
      $numero = intdiv($numero - $resto, 26);
    }
    return $letra;
  }

  private static function enviarDownload(Spreadsheet $planilha, string $nomeFicheiro): void
  {
    $nomeFicheiro = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nomeFicheiro);

    if (ob_get_level() > 0) {
      ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $nomeFicheiro . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($planilha);
    $writer->save('php://output');
    exit;
  }
}