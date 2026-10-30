<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseModel.php';

class HospedesModels extends BaseModel
{
    protected string $table = 'hospedes';
    protected array $fillable = ['nome', 'telefone', 'email', 'documento'];

    public function search(string $termo): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM hospedes
             WHERE nome LIKE :nome OR telefone LIKE :telefone
                OR email LIKE :email OR documento LIKE :documento
             ORDER BY nome'
        );
        $like = '%' . $termo . '%';
        $stmt->execute([
            'nome' => $like,
            'telefone' => $like,
            'email' => $like,
            'documento' => $like,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
