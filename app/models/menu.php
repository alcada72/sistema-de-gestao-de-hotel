<?php

require_once __DIR__ . '/../../config/config.php';

class Menu
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = getDB();
  }

  /**
   * Listar todos os menus ativos.
   */
  public function getAll(): array
  {
    $stmt = $this->pdo->query("
            SELECT *
            FROM menu
            WHERE ativo = 1
            ORDER BY ordem ASC
        ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Buscar menu pelo ID.
   */
  public function findById(int $id): ?array
  {
    $stmt = $this->pdo->prepare("
            SELECT *
            FROM menu
            WHERE id = :id
            LIMIT 1
        ");

    $stmt->execute(['id' => $id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /**
   * Buscar menu pela chave.
   */
  public function findByKey(string $chave): ?array
  {
    $stmt = $this->pdo->prepare("
            SELECT *
            FROM menu
            WHERE chave = :chave
            LIMIT 1
        ");

    $stmt->execute(['chave' => $chave]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /**
   * Criar menu.
   */
  public function create(array $data): int
  {
    $stmt = $this->pdo->prepare("
            INSERT INTO menu (
                nome,
                chave,
                icone,
                ordem,
                ativo
            ) VALUES (
                :nome,
                :chave,
                :icone,
                :ordem,
                :ativo
            )
        ");

    $stmt->execute([
      'nome' => $data['nome'],
      'chave' => $data['chave'],
      'icone' => $data['icone'] ?? null,
      'ordem' => $data['ordem'] ?? 0,
      'ativo' => $data['ativo'] ?? 1
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  /**
   * Atualizar menu.
   */
  public function update(int $id, array $data): bool
  {
    $stmt = $this->pdo->prepare("
            UPDATE menu SET
                nome = :nome,
                chave = :chave,
                icone = :icone,
                ordem = :ordem,
                ativo = :ativo
            WHERE id = :id
        ");

    return $stmt->execute([
      'id' => $id,
      'nome' => $data['nome'],
      'chave' => $data['chave'],
      'icone' => $data['icone'] ?? null,
      'ordem' => $data['ordem'] ?? 0,
      'ativo' => $data['ativo'] ?? 1
    ]);
  }

  /**
   * Eliminar menu.
   * Os itens são eliminados pela FK ON DELETE CASCADE.
   */
  public function delete(int $id): bool
  {
    $stmt = $this->pdo->prepare("
            DELETE FROM menu
            WHERE id = :id
        ");

    return $stmt->execute(['id' => $id]);
  }

  /**
   * Listar itens de um menu.
   */
  public function getItems(int $menuId): array
  {
    $stmt = $this->pdo->prepare("
            SELECT *
            FROM menu_items
            WHERE menu_id = :menu_id
              AND ativo = 1
            ORDER BY ordem ASC
        ");

    $stmt->execute(['menu_id' => $menuId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Buscar item pelo ID.
   */
  public function findItemById(int $id): ?array
  {
    $stmt = $this->pdo->prepare("
            SELECT *
            FROM menu_items
            WHERE id = :id
            LIMIT 1
        ");

    $stmt->execute(['id' => $id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /**
   * Criar item de menu.
   */
  public function createItem(array $data): int
  {
    $stmt = $this->pdo->prepare("
            INSERT INTO menu_items (
                menu_id,
                nome,
                link,
                icone,
                ordem,
                ativo
            ) VALUES (
                :menu_id,
                :nome,
                :link,
                :icone,
                :ordem,
                :ativo
            )
        ");

    $stmt->execute([
      'menu_id' => $data['menu_id'],
      'nome' => $data['nome'],
      'link' => $data['link'],
      'icone' => $data['icone'] ?? null,
      'ordem' => $data['ordem'] ?? 0,
      'ativo' => $data['ativo'] ?? 1
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  /**
   * Atualizar item de menu.
   */
  public function updateItem(int $id, array $data): bool
  {
    $stmt = $this->pdo->prepare("
            UPDATE menu_items SET
                menu_id = :menu_id,
                nome = :nome,
                link = :link,
                icone = :icone,
                ordem = :ordem,
                ativo = :ativo
            WHERE id = :id
        ");

    return $stmt->execute([
      'id' => $id,
      'menu_id' => $data['menu_id'],
      'nome' => $data['nome'],
      'link' => $data['link'],
      'icone' => $data['icone'] ?? null,
      'ordem' => $data['ordem'] ?? 0,
      'ativo' => $data['ativo'] ?? 1
    ]);
  }

  /**
   * Eliminar item.
   */
  public function deleteItem(int $id): bool
  {
    $stmt = $this->pdo->prepare("
            DELETE FROM menu_items
            WHERE id = :id
        ");

    return $stmt->execute(['id' => $id]);
  }

  /**
   * Definir permissões de um menu.
   */
  public function setPermissions(int $menuId, array $cargos): void
  {
    $this->pdo->beginTransaction();

    try {
      $stmt = $this->pdo->prepare("
                DELETE FROM menu_permissoes
                WHERE menu_id = :menu_id
            ");

      $stmt->execute(['menu_id' => $menuId]);

      $stmt = $this->pdo->prepare("
                INSERT INTO menu_permissoes (menu_id, cargo)
                VALUES (:menu_id, :cargo)
            ");

      foreach (array_unique($cargos) as $cargo) {
        $stmt->execute([
          'menu_id' => $menuId,
          'cargo' => $cargo
        ]);
      }

      $this->pdo->commit();

    } catch (Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }

  /**
   * Buscar permissões de um menu.
   */
  public function getPermissions(int $menuId): array
  {
    $stmt = $this->pdo->prepare("
            SELECT cargo
            FROM menu_permissoes
            WHERE menu_id = :menu_id
        ");

    $stmt->execute(['menu_id' => $menuId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
  }

  private const CARGOS_VALIDOS = ['proprietario', 'gerente', 'recepcionista', 'operador'];

  /**
   * Normaliza o cargo: minúsculas, sem espaços nem acentos.
   */
  private function normalizarCargo(string $cargo): string
  {
    $cargo = mb_strtolower(trim($cargo), 'UTF-8');

    return strtr($cargo, [
      'á' => 'a',
      'à' => 'a',
      'ã' => 'a',
      'â' => 'a',
      'é' => 'e',
      'ê' => 'e',
      'í' => 'i',
      'ó' => 'o',
      'õ' => 'o',
      'ô' => 'o',
      'ú' => 'u',
      'ç' => 'c',
    ]);
  }

  /**
   * Obter menu completo (com itens ativos) conforme o cargo.
   */
  public function getMenuByCargo(string $cargo): array
  {
    $cargo = $this->normalizarCargo($cargo);

    if (!in_array($cargo, self::CARGOS_VALIDOS, true)) {
      return [];
    }

    $stmt = $this->pdo->prepare("
  SELECT m.id, m.nome, m.chave, m.icone
  FROM menu m
  WHERE m.ativo = 1
    AND EXISTS (
      SELECT 1 FROM menu_permissoes mp
      WHERE mp.menu_id = m.id AND mp.cargo = :cargo
    )
  ORDER BY m.ordem ASC, m.id ASC
");


    $stmt->execute(['cargo' => $cargo]);


    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
  }
}