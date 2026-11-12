<?php
// app/models/Servico.php
require_once __DIR__ . '/Model.php';

class Servico extends Model
{
  const TABELA = 'servicos';
  const TIPOS = ['gratuito', 'pago'];

  public function listar(): array
  {
    return $this->all("
      SELECT id, nome, descricao, preco, tipo, disponivel, criado_em
      FROM servicos
      WHERE hotel_id = ?
      ORDER BY criado_em DESC
    ", [$this->hotelId]);
  }

  public function criar(array $dados): int
  {
    $nome = trim($dados['nome'] ?? '');
    $tipo = $dados['tipo'] ?? 'gratuito';
    $descricao = trim($dados['descricao'] ?? '') ?: null;
    $preco = $tipo === 'pago' ? (float) ($dados['preco'] ?? 0) : 0.0;

    if ($nome === '' || mb_strlen($nome) > 120) {
      throw new InvalidArgumentException('Nome do serviço inválido.');
    }
    if (!in_array($tipo, self::TIPOS, true)) {
      throw new InvalidArgumentException('Tipo de serviço inválido.');
    }
    if ($tipo === 'pago' && $preco <= 0) {
      throw new InvalidArgumentException('Indica um preço para serviços pagos.');
    }

    $this->query("
      INSERT INTO servicos (hotel_id, nome, descricao, preco, tipo, disponivel, criado_em)
      VALUES (?, ?, ?, ?, ?, 1, NOW())
    ", [$this->hotelId, $nome, $descricao, $preco, $tipo]);

    return (int) $this->pdo->lastInsertId();
  }

  public function alternarDisponivel(int $id): bool
  {
    return $this->query(
      "UPDATE servicos SET disponivel = NOT disponivel WHERE id = ? AND hotel_id = ?",
      [$id, $this->hotelId]
    )->rowCount() > 0;
  }

  public function eliminar(int $id): bool
  {
    return $this->query(
      "DELETE FROM servicos WHERE id = ? AND hotel_id = ?",
      [$id, $this->hotelId]
    )->rowCount() > 0;
  }
}