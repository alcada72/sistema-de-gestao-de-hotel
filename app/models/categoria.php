<?php
// app/models/Categoria.php
require_once __DIR__ . '/Model.php';

class Categoria extends Model
{
  const TABELA = 'categorias_quartos';

  public function listar(): array
  {
    return $this->all("
      SELECT c.id, c.nome, c.capacidade, c.preco_base, c.ativo,
             COUNT(q.id) AS total_quartos
      FROM categorias_quartos c
      LEFT JOIN quartos q ON q.categoria_id = c.id AND q.hotel_id = c.hotel_id
      WHERE c.hotel_id = ?
      GROUP BY c.id, c.nome, c.capacidade, c.preco_base, c.ativo
      ORDER BY c.nome
    ", [$this->hotelId]);
  }

  public function listarAtivas(): array
  {
    return $this->all("
      SELECT id, nome, preco_base
      FROM categorias_quartos
      WHERE hotel_id = ? AND ativo = 1
      ORDER BY nome
    ", [$this->hotelId]);
  }

  public function criar(array $dados): int
  {
    $nome = trim($dados['nome'] ?? '');
    $capacidade = (int) ($dados['capacidade'] ?? 0);
    $precoBase = (float) ($dados['preco_base'] ?? -1);
    $ativo = ((int) ($dados['ativo'] ?? 1)) ? 1 : 0;

    if ($nome === '' || mb_strlen($nome) > 50) {
      throw new InvalidArgumentException('Nome da categoria inválido.');
    }
    if ($capacidade < 1 || $capacidade > 20) {
      throw new InvalidArgumentException('Capacidade deve estar entre 1 e 20.');
    }
    if ($precoBase < 0) {
      throw new InvalidArgumentException('Preço base inválido.');
    }

    $this->query("
      INSERT INTO categorias_quartos (hotel_id, nome, capacidade, preco_base, ativo)
      VALUES (?, ?, ?, ?, ?)
    ", [$this->hotelId, $nome, $capacidade, $precoBase, $ativo]);

    return (int) $this->pdo->lastInsertId();
  }
}