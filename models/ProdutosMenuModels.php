<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseModel.php';

class ProdutosMenuModels extends BaseModel
{
  protected string $table = 'produtos_menu';
  protected array $fillable = ['categoria_id', 'nome', 'descricao', 'preco', 'tipo', 'disponivel'];

  public function findAllComCategoria(): array
  {
    $sql = 'SELECT p.*, c.nome AS categoria_nome
                FROM produtos_menu p
                INNER JOIN categorias_menu c ON c.id = p.categoria_id
                ORDER BY c.nome, p.nome';
    return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  public function findDisponiveis(): array
  {
    $sql = 'SELECT p.*, c.nome AS categoria_nome
                FROM produtos_menu p
                INNER JOIN categorias_menu c ON c.id = p.categoria_id
                WHERE p.disponivel = 1 AND c.ativo = 1
                ORDER BY c.nome, p.nome';
    return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  public function search(string $termo): array
  {
    $stmt = $this->db->prepare(
      'SELECT p.*, c.nome AS categoria_nome
             FROM produtos_menu p
             INNER JOIN categorias_menu c ON c.id = p.categoria_id
             WHERE p.nome LIKE :nome OR p.tipo LIKE :tipo OR c.nome LIKE :categoria
             ORDER BY p.nome'
    );
    $like = '%' . $termo . '%';
    $stmt->execute([
      'nome' => $like,
      'tipo' => $like,
      'categoria' => $like,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function categoriaExiste(int $categoriaId): bool
  {
    $stmt = $this->db->prepare(
      'SELECT COUNT(*)
         FROM categorias_menu
         WHERE id = :id AND ativo = 1'
    );

    $stmt->execute(['id' => $categoriaId]);

    return (int) $stmt->fetchColumn() > 0;
  }
}
