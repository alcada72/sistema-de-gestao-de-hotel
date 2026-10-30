<aside class="sidebar" id="sidebar">
  <a class="brand" href="#" data-page="dashboard">
    <span class="brand-icon">H</span>
    <span>Hotelier<small>GESTÃO DE HOTELARIA</small></span>
  </a>

  <div class="sidebar-label">MENU PRINCIPAL</div>

  <nav class="navigation">
    <button class="nav-link active" data-page="dashboard">
      <span class="nav-icon">▦</span> Dashboard
    </button>
    <button class="nav-link" data-page="quartos">
      <span class="nav-icon">▤</span> Quartos
      <span class="nav-count">
        <?= $totalQuartos ?>
      </span>
    </button>
    <button class="nav-link" data-page="reservas">
      <span class="nav-icon">▣</span> Reservas
    </button>
    <button class="nav-link" data-page="restaurante">
      <span class="nav-icon">♨</span> Restaurante
    </button>
    <button class="nav-link" data-page="hospedes">
      <span class="nav-icon">♙</span> Hóspedes
    </button>
  </nav>

  <div class="sidebar-label management-label">GESTÃO</div>

  <nav class="navigation">
    <button class="nav-link" data-page="relatorios">
      <span class="nav-icon">▥</span> Relatórios
    </button>
    <button class="nav-link" data-page="configuracoes">
      <span class="nav-icon">⚙</span> Configurações
    </button>
  </nav>

  <div class="sidebar-bottom">
    <!--       <div class="support-card">
        <div class="support-icon">✦</div>
        <strong>Precisa de ajuda?</strong>
        <p>Consulte o suporte do sistema.</p>
        <button id="supportBtn" class="support-button">Obter ajuda ↗</button>
      </div>
 -->
    <div class="user-profile">
      <div class="avatar">
        <?= $user['nome'][0] ?>
      </div>
      <div class="user-info">
        <strong>
          <?= $user['nome'] ?>
        </strong>
        <span>
          <?= $user['tipo'] === 'admin' ? 'Administrador' : 'Recepcionista' ?>
        </span>
      </div>
      <button class="icon-button" id="profileBtn" aria-label="Perfil">

        <a href="<?= BASE_URL ?>/logout.php" style="color:#fff;border-color:rgba(255,255,255,.4)">Sair</a>
      </button>
    </div>
  </div>
</aside>