<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseModel.php';

class ItensPedidoModels extends BaseModel
{
    protected string $table = 'itens_pedido';
    protected array $fillable = ['pedido_id', 'produto_id', 'nome_produto', 'quantidade', 'preco_unitario'];

    public function findByPedido(int $pedidoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, p.nome AS nome_atual_produto
             FROM itens_pedido i
             LEFT JOIN produtos_menu p ON p.id = i.produto_id
             WHERE i.pedido_id = :pedido_id
             ORDER BY i.id'
        );
        $stmt->execute(['pedido_id' => $pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function calcularTotalPedido(int $pedidoId): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(quantidade * preco_unitario), 0)
             FROM itens_pedido WHERE pedido_id = :pedido_id'
        );
        $stmt->execute(['pedido_id' => $pedidoId]);
        return (float) $stmt->fetchColumn();
    }

    public function deleteByPedido(int $pedidoId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM itens_pedido WHERE pedido_id = :pedido_id');
        $stmt->execute(['pedido_id' => $pedidoId]);
        return $stmt->rowCount() > 0;
    }
}
