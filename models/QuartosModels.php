<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseModel.php';

class QuartosModels extends BaseModel
{
    protected string $table = 'quartos';
    protected array $fillable = ['andar_id', 'numero', 'tipo', 'preco', 'capacidade', 'estado', 'descricao'];

    public function findByEstado(string $estado): array
    {
        return $this->fetchAllBy('estado', $estado);
    }

    public function findByTipo(string $tipo): array
    {
        return $this->fetchAllBy('tipo', $tipo);
    }

    public function countByEstado(string $estado): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM quartos WHERE estado = :estado');
        $stmt->execute(['estado' => $estado]);
        return (int) $stmt->fetchColumn();
    }

    public function search(string $termo): array
    {
        $stmt = $this->db->prepare(
            'SELECT q.*, a.numero_andar
             FROM quartos q
             LEFT JOIN andares a ON a.id = q.andar_id
             WHERE CAST(q.numero AS CHAR) LIKE :numero
                OR q.tipo LIKE :tipo
                OR q.estado LIKE :estado
                OR q.descricao LIKE :descricao
             ORDER BY q.numero'
        );
        $like = '%' . $termo . '%';
        $stmt->execute([
            'numero' => $like,
            'tipo' => $like,
            'estado' => $like,
            'descricao' => $like,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDisponiveisEntre(string $entrada, string $saida, int $pessoas = 1): array
    {
        $sql = "SELECT q.*, a.numero_andar
                FROM quartos q
                LEFT JOIN andares a ON a.id = q.andar_id
                WHERE q.estado <> 'manutencao'
                  AND q.capacidade >= :pessoas
                  AND NOT EXISTS (
                    SELECT 1 FROM reservas r
                    WHERE r.quarto_id = q.id
                      AND r.estado IN ('pendente', 'confirmada', 'checkin')
                      AND r.data_entrada < :saida
                      AND r.data_saida > :entrada
                  )
                ORDER BY q.preco, q.numero";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pessoas' => $pessoas, 'entrada' => $entrada, 'saida' => $saida]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateEstado(int $id, string $estado): bool
    {
        $permitidos = ['disponivel', 'ocupado', 'manutencao'];
        if (!in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException('Estado de quarto inválido.');
        }
        $stmt = $this->db->prepare('UPDATE quartos SET estado = :estado WHERE id = :id');
        $stmt->execute(['estado' => $estado, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
