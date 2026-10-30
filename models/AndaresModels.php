<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseModel.php';

class AndaresModels extends BaseModel
{
    protected string $table = 'andares';
    protected array $fillable = ['numero_andar', 'descricao'];

    public function findAllComTotalQuartos(): array
    {
        $sql = 'SELECT a.*, COUNT(q.id) AS total_quartos
                FROM andares a
                LEFT JOIN quartos q ON q.andar_id = a.id
                GROUP BY a.id
                ORDER BY a.numero_andar';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
