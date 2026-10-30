<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseModel.php';

class PedidosModels extends BaseModel
{
    protected string $table = 'pedidos';
    protected array $fillable = [
        'hospede_id', 'reserva_id', 'usuario_id', 'tipo',
        'numero_mesa', 'estado', 'observacoes'
    ];

    public function findAllDetalhados(): array
    {
        $sql = 'SELECT p.*, h.nome AS hospede_nome, r.quarto_id,
                       q.numero AS quarto_numero, u.nome AS funcionario_nome
                FROM pedidos p
                LEFT JOIN hospedes h ON h.id = p.hospede_id
                LEFT JOIN reservas r ON r.id = p.reserva_id
                LEFT JOIN quartos q ON q.id = r.quarto_id
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                ORDER BY p.criado_em DESC';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEstado(string $estado): array
    {
        return $this->fetchAllBy('estado', $estado);
    }

    public function updateEstado(int $id, string $estado): bool
    {
        $permitidos = ['pendente', 'preparacao', 'pronto', 'entregue', 'cancelado'];
        if (!in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException('Estado do pedido inválido.');
        }
        $stmt = $this->db->prepare('UPDATE pedidos SET estado = :estado WHERE id = :id');
        $stmt->execute(['estado' => $estado, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function countByEstado(string $estado): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM pedidos WHERE estado = :estado');
        $stmt->execute(['estado' => $estado]);
        return (int) $stmt->fetchColumn();
    }
}
