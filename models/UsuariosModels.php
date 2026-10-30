<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseModel.php';

class UsuariosModels extends BaseModel
{
    protected string $table = 'usuarios';
    protected array $fillable = ['nome', 'email', 'senha', 'cargo', 'ativo'];

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findAtivos(): array
    {
        $stmt = $this->db->query('SELECT id, nome, email, cargo, ativo, criado_em FROM usuarios WHERE ativo = 1 ORDER BY nome');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        if (!empty($data['senha'])) {
            $data['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }
        return parent::create($data);
    }

    public function update(int $id, array $data): bool
    {
        if (!empty($data['senha'])) {
            $data['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        } else {
            unset($data['senha']);
        }
        return parent::update($id, $data);
    }
}
