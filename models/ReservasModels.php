<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseModel.php';

class ReservasModels extends BaseModel
{
  protected string $table = 'reservas';
  protected array $fillable = [
    'hospede_id',
    'quarto_id',
    'usuario_id',
    'data_entrada',
    'data_saida',
    'quantidade_pessoas',
    'preco_diaria',
    'estado',
    'observacoes'
  ];

  /**
   * Regista uma reserva e associa um hóspede.
   *
   * Se o hóspede já existir, reutiliza o seu ID.
   * Caso contrário, cria um novo hóspede.
   */
  public function createReserva(array $dados, ?int $usuarioId = null): int
  {
    $nome = trim((string) ($dados['nome'] ?? ''));
    $telefone = trim((string) ($dados['telefone'] ?? ''));
    $email = trim((string) ($dados['email'] ?? ''));
    $documento = trim((string) ($dados['documento'] ?? ''));

    $quartoId = (int) ($dados['quarto_id'] ?? 0);
    $pessoas = (int) ($dados['quantidade_pessoas'] ?? 0);

    $entrada = trim((string) ($dados['data_entrada'] ?? ''));
    $saida = trim((string) ($dados['data_saida'] ?? ''));

    $observacoes = trim((string) ($dados['observacoes'] ?? ''));

    // Validações básicas
    if ($nome === '' || $telefone === '') {
      throw new InvalidArgumentException(
        'O nome e o telefone do hóspede são obrigatórios.'
      );
    }

    if ($quartoId <= 0 || $pessoas <= 0) {
      throw new InvalidArgumentException(
        'Seleciona um quarto e informa a quantidade de hóspedes.'
      );
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new InvalidArgumentException('O email informado é inválido.');
    }

    // Validação das datas
    $dataEntrada = DateTimeImmutable::createFromFormat('!Y-m-d', $entrada);
    $dataSaida = DateTimeImmutable::createFromFormat('!Y-m-d', $saida);

    if (
      !$dataEntrada ||
      !$dataSaida ||
      $dataEntrada->format('Y-m-d') !== $entrada ||
      $dataSaida->format('Y-m-d') !== $saida
    ) {
      throw new InvalidArgumentException('As datas informadas são inválidas.');
    }

    if ($dataEntrada >= $dataSaida) {
      throw new InvalidArgumentException(
        'A data de saída deve ser posterior à data de entrada.'
      );
    }

    try {
      $this->db->beginTransaction();

      // 1. Verificar o quarto
      $stmtQuarto = $this->db->prepare(
        'SELECT id, capacidade, preco, estado
             FROM quartos
             WHERE id = :id
             FOR UPDATE'
      );

      $stmtQuarto->execute(['id' => $quartoId]);

      $quarto = $stmtQuarto->fetch(PDO::FETCH_ASSOC);

      if (!$quarto) {
        throw new RuntimeException('O quarto selecionado não existe.');
      }

      if ($quarto['estado'] === 'manutencao') {
        throw new RuntimeException(
          'Este quarto está em manutenção.'
        );
      }

      if ($pessoas > (int) $quarto['capacidade']) {
        throw new RuntimeException(
          'A quantidade de hóspedes excede a capacidade do quarto.'
        );
      }

      // 2. Verificar disponibilidade no período
      $stmtDisponibilidade = $this->db->prepare(
        "SELECT COUNT(*)
             FROM reservas
             WHERE quarto_id = :quarto_id
               AND estado IN ('pendente', 'confirmada', 'checkin')
               AND data_entrada < :saida
               AND data_saida > :entrada"
      );

      $stmtDisponibilidade->execute([
        'quarto_id' => $quartoId,
        'entrada' => $entrada,
        'saida' => $saida
      ]);

      if ((int) $stmtDisponibilidade->fetchColumn() > 0) {
        throw new RuntimeException(
          'O quarto já possui uma reserva nesse período.'
        );
      }

      // 3. Procurar hóspede pelo telefone
      $stmtHospede = $this->db->prepare(
        'SELECT id
             FROM hospedes
             WHERE telefone = :telefone
             LIMIT 1'
      );

      $stmtHospede->execute(['telefone' => $telefone]);

      $hospedeId = $stmtHospede->fetchColumn();

      // 4. Cadastrar hóspede caso ainda não exista
      if (!$hospedeId) {
        $stmtNovoHospede = $this->db->prepare(
          'INSERT INTO hospedes (nome, telefone, email, documento)
                 VALUES (:nome, :telefone, :email, :documento)'
        );

        $stmtNovoHospede->execute([
          'nome' => $nome,
          'telefone' => $telefone,
          'email' => $email !== '' ? $email : null,
          'documento' => $documento !== '' ? $documento : null
        ]);

        $hospedeId = (int) $this->db->lastInsertId();
      } else {
        $hospedeId = (int) $hospedeId;
      }

      // 5. Criar a reserva
      $reservaId = $this->create([
        'hospede_id' => $hospedeId,
        'quarto_id' => $quartoId,
        'usuario_id' => $usuarioId,
        'data_entrada' => $entrada,
        'data_saida' => $saida,
        'quantidade_pessoas' => $pessoas,
        'preco_diaria' => (float) $quarto['preco'],
        'estado' => 'pendente',
        'observacoes' => $observacoes
      ]);

      $this->db->commit();

      return $reservaId;

    } catch (Throwable $e) {

      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }

      throw $e;
    }
  }

  public function findAllDetalhadas(): array
  {
    $sql = 'SELECT r.*, h.nome AS hospede_nome, h.telefone AS hospede_telefone,
                       q.numero AS quarto_numero, q.tipo AS quarto_tipo,
                       u.nome AS usuario_nome
                FROM reservas r
                INNER JOIN hospedes h ON h.id = r.hospede_id
                INNER JOIN quartos q ON q.id = r.quarto_id
                LEFT JOIN usuarios u ON u.id = r.usuario_id
                ORDER BY r.criado_em DESC';
    return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  public function findByEstado(string $estado): array
  {
    return $this->fetchAllBy('estado', $estado);
  }

  public function findByIdDetalhado(int $id): array|false
  {
    $stmt = $this->db->prepare(
      'SELECT r.*, h.nome AS hospede_nome, q.numero AS quarto_numero,
                    u.nome AS usuario_nome
             FROM reservas r
             INNER JOIN hospedes h ON h.id = r.hospede_id
             INNER JOIN quartos q ON q.id = r.quarto_id
             LEFT JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function updateEstado(int $id, string $estado): bool
  {
    $permitidos = ['pendente', 'confirmada', 'checkin', 'concluida', 'cancelada'];
    if (!in_array($estado, $permitidos, true)) {
      throw new InvalidArgumentException('Estado de reserva inválido.');
    }
    $stmt = $this->db->prepare('UPDATE reservas SET estado = :estado WHERE id = :id');
    $stmt->execute(['estado' => $estado, 'id' => $id]);
    return $stmt->rowCount() > 0;
  }

  public function countByEstado(string $estado): int
  {
    $stmt = $this->db->prepare('SELECT COUNT(*) FROM reservas WHERE estado = :estado');
    $stmt->execute(['estado' => $estado]);
    return (int) $stmt->fetchColumn();
  }
}
