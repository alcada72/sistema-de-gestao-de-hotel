<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseModel.php';

class CategoriasMenuModels extends BaseModel
{
    protected string $table = 'categorias_menu';
    protected array $fillable = ['nome', 'descricao', 'ativo'];

    public function findAtivas(): array
    {
        $stmt = $this->db->query('SELECT * FROM categorias_menu WHERE ativo = 1 ORDER BY nome');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
