<?php
// app/models/Hotel.php
require_once __DIR__ . '/Model.php';

class Hotel extends Model
{
  const TABELA = 'hoteis';
  const CATEGORIAS = [
    'sem_classificacao',
    '1_estrela',
    '2_estrelas',
    '3_estrelas',
    '4_estrelas',
    '5_estrelas',
  ];

  // O NIF não é editável
  const CAMPOS_EDITAVEIS = [
    'nome',
    'categoria',
    'descricao',
    'telefone',
    'telefone_secundario',
    'email',
    'provincia_id',
    'municipio_id',
    'endereco',
  ];

  public function buscar(): ?array
  {
    return $this->one("SELECT * FROM hoteis WHERE id = ?", [$this->hotelId]);
  }

  public function atualizar(array $dados): bool
  {
    // Só aceita campos da lista branca
    $campos = array_intersect_key($dados, array_flip(self::CAMPOS_EDITAVEIS));

    if (!$campos) {
      return false;
    }
    if (isset($campos['nome']) && trim($campos['nome']) === '') {
      throw new InvalidArgumentException('O nome do hotel é obrigatório.');
    }
    if (isset($campos['categoria']) && !in_array($campos['categoria'], self::CATEGORIAS, true)) {
      throw new InvalidArgumentException('Categoria inválida.');
    }
    if (isset($campos['provincia_id'], $campos['municipio_id'])) {
      $pertence = $this->valor(
        "SELECT 1 FROM municipios WHERE id = ? AND provincia_id = ?",
        [(int) $campos['municipio_id'], (int) $campos['provincia_id']]
      );
      if (!$pertence) {
        throw new InvalidArgumentException('O município não pertence à província indicada.');
      }
    }

    // Nomes das colunas vêm da lista branca, por isso são seguros no SQL
    $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($campos)));
    $params = array_values($campos);
    $params[] = $this->hotelId;

    $this->query("UPDATE hoteis SET {$sets} WHERE id = ?", $params);

    return true;
  }
}