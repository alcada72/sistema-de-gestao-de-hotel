<?php
// app/services/ExportadorPdf.php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Gera um PDF a partir de cabeçalhos + linhas e envia-o para download.
 */
class ExportadorPdf
{
  /**
   * @param string $titulo       Título do relatório.
   * @param array  $cabecalhos   Nomes das colunas.
   * @param array  $linhas       Lista de arrays associativos ou indexados.
   * @param string $nomeFicheiro Nome do ficheiro a descarregar, sem extensão.
   * @param array  $resumo       Pares rótulo => valor mostrados antes da tabela (opcional).
   */
  public static function exportar(
    string $titulo,
    array $cabecalhos,
    array $linhas,
    string $nomeFicheiro = 'relatorio',
    array $resumo = []
  ): void {
    $html = self::montarHtml($titulo, $cabecalhos, $linhas, $resumo);

    $opcoes = new Options();
    $opcoes->set('isRemoteEnabled', false);
    $opcoes->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($opcoes);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $nomeFicheiro = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nomeFicheiro);

    if (ob_get_level() > 0) {
      ob_end_clean();
    }

    $dompdf->stream($nomeFicheiro . '.pdf', ['Attachment' => true]);
    exit;
  }

  private static function montarHtml(string $titulo, array $cabecalhos, array $linhas, array $resumo): string
  {
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    $html = '<html><head><meta charset="UTF-8"><style>
      body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #222; }
      h1 { font-size: 16px; margin-bottom: 2px; }
      .subtitulo { font-size: 9px; color: #666; margin-bottom: 14px; }
      .resumo { margin-bottom: 14px; }
      .resumo span { display: inline-block; margin-right: 18px; }
      .resumo strong { color: #000; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
      th { background: #d8e1ff; font-weight: bold; }
      tr:nth-child(even) td { background: #f7f8fc; }
      tfoot td { font-weight: bold; border-top: 2px solid #999; }
    </style></head><body>';

    $html .= '<h1>' . $e($titulo) . '</h1>';
    $html .= '<div class="subtitulo">Gerado em ' . date('d/m/Y H:i') . '</div>';

    if ($resumo) {
      $html .= '<div class="resumo">';
      foreach ($resumo as $rotulo => $valor) {
        $html .= '<span>' . $e($rotulo) . ': <strong>' . $e($valor) . '</strong></span>';
      }
      $html .= '</div>';
    }

    $html .= '<table><thead><tr>';
    foreach ($cabecalhos as $nomeColuna) {
      $html .= '<th>' . $e($nomeColuna) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($linhas as $linha) {
      $valores = array_is_list($linha) ? $linha : array_values($linha);
      $html .= '<tr>';
      foreach ($valores as $valor) {
        $html .= '<td>' . $e($valor) . '</td>';
      }
      $html .= '</tr>';
    }

    $html .= '</tbody><tfoot><tr><td colspan="' . count($cabecalhos) . '">Total de registos: '
      . count($linhas) . '</td></tr></tfoot></table></body></html>';

    return $html;
  }
}