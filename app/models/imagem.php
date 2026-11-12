<?php
// app/models/Imagem.php
require_once __DIR__ . '/Model.php';

class Imagem extends Model
{
  const TABELA = 'hotel_imagens';
  const TIPOS = ['galeria', 'principal', 'interior', 'exterior'];

  public function listar(): array
  {
    return $this->all("
      SELECT id, url, tipo, criado_em
      FROM hotel_imagens
      WHERE hotel_id = ?
      ORDER BY criado_em DESC
    ", [$this->hotelId]);
  }

  /** Guardar o ficheiro é responsabilidade do controller; aqui fica só o registo. */
  public function criar(string $url, string $tipo): int
  {
    if (!in_array($tipo, self::TIPOS, true)) {
      throw new InvalidArgumentException('Tipo de imagem inválido.');
    }

    $this->query("
      INSERT INTO hotel_imagens (hotel_id, url, tipo, criado_em)
      VALUES (?, ?, ?, NOW())
    ", [$this->hotelId, $url, $tipo]);

    return (int) $this->pdo->lastInsertId();
  }

  /** Remove o registo e devolve o URL, para o controller apagar o ficheiro. */
  public function eliminar(int $id): ?string
  {
    $imagem = $this->one(
      "SELECT url FROM hotel_imagens WHERE id = ? AND hotel_id = ?",
      [$id, $this->hotelId]
    );

    if (!$imagem) {
      return null;
    }

    $this->query(
      "DELETE FROM hotel_imagens WHERE id = ? AND hotel_id = ?",
      [$id, $this->hotelId]
    );

    return $imagem['url'];
  }
}