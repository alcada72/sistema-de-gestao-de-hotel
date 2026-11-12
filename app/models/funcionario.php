<?php
// app/models/Funcionario.php
require_once __DIR__ . '/Model.php';

class Funcionario extends Model
{
  const TABELA = 'hotel_usuarios';
  const CARGOS = ['proprietario', 'gerente', 'recepcionista', 'operador'];
  // O proprietário não se atribui nem altera por aqui
  const CARGOS_ATRIBUIVEIS = ['gerente', 'recepcionista', 'operador'];

  public function listar(): array
  {
    return $this->all("
      SELECT hu.id, hu.cargo,
             u.id AS usuario_id, u.nome, u.email, u.telefone, u.ativo
      FROM hotel_usuarios hu
      INNER JOIN usuarios u ON u.id = hu.usuario_id
      WHERE hu.hotel_id = ?
      ORDER BY FIELD(hu.cargo, 'proprietario', 'gerente', 'recepcionista', 'operador'),
               u.nome ASC
    ", [$this->hotelId]);
  }

  public function estatisticas(): array
  {
    return $this->one("
      SELECT COUNT(*) AS total,
             SUM(cargo = 'proprietario') AS proprietarios,
             SUM(cargo = 'gerente') AS gerentes,
             SUM(cargo = 'recepcionista') AS recepcionistas,
             SUM(cargo = 'operador') AS operadores
      FROM hotel_usuarios
      WHERE hotel_id = ?
    ", [$this->hotelId]);
  }

  public function buscarUtilizadorPorEmail(string $email): ?array
  {
    return $this->one(
      "SELECT id, nome, email FROM usuarios WHERE email = ?",
      [trim($email)]
    );
  }

  /** Associa um utilizador já existente ao hotel. */
  public function adicionar(int $usuarioId, string $cargo): int
  {
    if (!in_array($cargo, self::CARGOS_ATRIBUIVEIS, true)) {
      throw new InvalidArgumentException('Cargo inválido.');
    }
    if (
      $this->valor(
        "SELECT 1 FROM hotel_usuarios WHERE hotel_id = ? AND usuario_id = ?",
        [$this->hotelId, $usuarioId]
      )
    ) {
      throw new DomainException('Este utilizador já está associado ao hotel.');
    }

    $this->query("
      INSERT INTO hotel_usuarios (hotel_id, usuario_id, cargo)
      VALUES (?, ?, ?)
    ", [$this->hotelId, $usuarioId, $cargo]);

    return (int) $this->pdo->lastInsertId();
  }

  public function alterarCargo(int $hotelUsuarioId, string $cargo): bool
  {
    if (!in_array($cargo, self::CARGOS_ATRIBUIVEIS, true)) {
      throw new InvalidArgumentException('Cargo inválido.');
    }
    if ($this->cargoDe($hotelUsuarioId) === 'proprietario') {
      throw new DomainException('O cargo de proprietário não pode ser alterado.');
    }

    return $this->query(
      "UPDATE hotel_usuarios SET cargo = ? WHERE id = ? AND hotel_id = ?",
      [$cargo, $hotelUsuarioId, $this->hotelId]
    )->rowCount() > 0;
  }

  public function remover(int $hotelUsuarioId): bool
  {
    if ($this->cargoDe($hotelUsuarioId) === 'proprietario') {
      throw new DomainException('O proprietário não pode ser removido.');
    }

    return $this->query(
      "DELETE FROM hotel_usuarios WHERE id = ? AND hotel_id = ?",
      [$hotelUsuarioId, $this->hotelId]
    )->rowCount() > 0;
  }

  private function cargoDe(int $hotelUsuarioId): ?string
  {
    $cargo = $this->valor(
      "SELECT cargo FROM hotel_usuarios WHERE id = ? AND hotel_id = ?",
      [$hotelUsuarioId, $this->hotelId]
    );

    return $cargo === false ? null : $cargo;
  }
}