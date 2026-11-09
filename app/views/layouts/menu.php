<!-- Sidebar -->
<aside class="sidebar" id="sidebar">

  <div class="brand">
    <span>MultiploHotel</span>
  </div>

  <div class="hotel-selector">

    <label>Estabelecimento</label>

    <select onchange="selecionarHotel(this.value)">

      <?php foreach ($hoteis as $hotel): ?>

        <option value="<?= (int) $hotel['id'] ?>" <?= (int) $hotel['id'] === $hotelId ? 'selected' : '' ?>>
          <?= htmlspecialchars($hotel['nome']) ?>
        </option>

      <?php endforeach; ?>

    </select>

  </div>

  <nav class="navigation">

    <span class="nav-label">PRINCIPAL</span>

    <button class="nav-link active" data-page="dashboard">
      <span>▦</span> Visão geral
    </button>

    <span class="nav-label">ALOJAMENTO</span>

    <?php if ($podeGerirQuartos): ?>

      <button class="nav-link" data-page="quartos">
        <span>▣</span> Quartos
      </button>

    <?php endif; ?>

    <button class="nav-link" data-page="andares">
      <span>▤</span> Andares
    </button>

    <button class="nav-link" data-page="categorias">
      <span>▧</span> Categorias
    </button>

    <button class="nav-link" data-page="imagens">
      <span>▧</span> Fotografias
    </button>

    <span class="nav-label">OPERAÇÕES</span>

    <button class="nav-link" data-page="reservas">
      <span>▣</span> Reservas
    </button>

    <button class="nav-link" data-page="reserva-presencial">
      <span>＋</span> Reserva presencial
    </button>

    <button class="nav-link" data-page="servicos">
      <span>✧</span> Serviços
    </button>

    <button class="nav-link" data-page="restaurante">
      <span>♨</span> Restaurante
    </button>

    <span class="nav-label">GESTÃO</span>

    <?php if ($podeGerirFuncionarios): ?>

      <button class="nav-link" data-page="funcionarios">
        <span>♙</span> Funcionários
      </button>

    <?php endif; ?>

    <?php if ($podeGerirHotel): ?>

      <button class="nav-link" data-page="hotel">
        <span>⚙</span> Dados do hotel
      </button>

    <?php endif; ?>

  </nav>

  <div class="sidebar-footer">

    <div class="user-info">
      <div class="avatar">
        <?= strtoupper(substr($usuario['nome'], 0, 1)) ?>
      </div>

      <div>
        <strong>
          <?= htmlspecialchars($usuario['nome']) ?>
        </strong>

        <small>
          <?= htmlspecialchars(ucfirst($cargo)) ?>
        </small>
      </div>
    </div>

    <button class="logout btn" onclick="location.href='<?= BASE_URL ?>/logout.php'">
      Sair da conta
    </button>

  </div>

</aside>