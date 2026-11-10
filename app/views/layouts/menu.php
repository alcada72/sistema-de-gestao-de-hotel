<!-- Sidebar -->
<aside class="sidebar" id="sidebar">

  <div class="brand">
    <span>MultiploHotel</span>
    <button type="button" id="showSidebar" aria-label="Alternar menu" aria-expanded="true">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2" />
        <path d="M7.25 10L5.5 12l1.75 2" />
        <path d="M9.5 21V3" />
      </svg>
    </button>
  </div>


  <nav class="navigation">

    <span class="nav-label">PRINCIPAL</span>

    <button class="nav-link active" data-page="dashboard" data-title="Visão geral">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 23 23"
          fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="lucide lucide-panels-top-left preview-icon">
          <rect width="18" height="18" x="3" y="3" rx="2" />
          <path d="M3 9h18" />
          <path d="M9 21V9" />
        </svg></span>
      <span class="nav-text">Visão geral</span>
    </button>
    <span class="nav-label">ALOJAMENTO</span>

    <?php if ($podeGerirQuartos): ?>

    <button class="nav-link" data-page="quartos" data-title="Quartos">
      <span class="nav-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="lucide lucide-door-closed-cog preview-icon">
          <path d="m14.305 19.53.923-.382" />
          <path d="m15.229 16.852-.924-.383" />
          <path d="m16.852 15.228-.383-.923" />
          <path d="m16.852 20.773-.383.924" />
          <path d="M19 10.35V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16" />
          <path d="m19.148 15.228.383-.923" />
          <path d="m19.53 21.697-.382-.924" />
          <path d="M2 21h8.58" />
          <path d="m20.773 16.852.922-.383" />
          <path d="m20.773 19.148.922.383" />
          <path d="M9 12h.01" />
          <circle cx="18" cy="18" r="3" />
        </svg>
      </span>
      <span class="nav-text">Quartos</span>
    </button>


    <?php endif; ?>

    <button class="nav-link" data-page="andares" data-title="Andares">
      <span class="nav-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-warehouse preview-icon">
          <path d="M18 21V10a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v11" />
          <path
            d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 1.132-1.803l7.95-3.974a2 2 0 0 1 1.837 0l7.948 3.974A2 2 0 0 1 22 8z" />
          <path d="M6 13h12" />
          <path d="M6 17h12" />
        </svg>
      </span>
      <span class="nav-text">Andares</span>
    </button>

    <button class="nav-link" data-page="categorias" data-title="Categorias">
      <span class="nav-icon">

        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-tags preview-icon">
          <path
            d="M13.172 2a2 2 0 0 1 1.414.586l6.71 6.71a2.4 2.4 0 0 1 0 3.408l-4.592 4.592a2.4 2.4 0 0 1-3.408 0l-6.71-6.71A2 2 0 0 1 6 9.172V3a1 1 0 0 1 1-1z" />
          <path d="M2 7v6.172a2 2 0 0 0 .586 1.414l6.71 6.71a2.4 2.4 0 0 0 3.191.193" />
          <circle cx="10.5" cy="6.5" r=".5" fill="currentColor" />
        </svg>

      </span>
      <span class="nav-text">Categorias</span>
    </button>

    <button class="nav-link" data-page="imagens" data-title="Fotografias">
      <span class="nav-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-images preview-icon">
          <path d="m22 11-1.296-1.296a2.4 2.4 0 0 0-3.408 0L11 16" />
          <path d="M4 8a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2" />
          <circle cx="13" cy="7" r="1" fill="currentColor" />
          <rect x="8" y="2" width="14" height="14" rx="2" />
        </svg>
      </span>
      <span class="nav-text">Fotografias</span>
    </button>

    <span class="nav-label">OPERAÇÕES</span>

    <button class="nav-link" data-page="reservas" data-title="Reservas">
      <span class="nav-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-laser preview-icon">
          <path d="m13 8 .95-.95" />
          <path d="M13.95 16.95 13 16" />
          <path d="M22 12h-9" />
          <path d="M4 12H2" />
          <path d="M4.05 16.95 5 16" />
          <path d="m5 8-.95-.95" />
          <path d="M9 12h.01" />
          <path d="M9 19v-2" />
          <path d="M9 7V5" />
        </svg>
      </span>
      <span class="nav-text">Reservas</span>
    </button>

    <button class="nav-link" data-page="reserva-presencial" data-title="Reserva presencial">
      <span class="nav-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-plus preview-icon">
          <path d="M12 7v6" />
          <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20" />
          <path d="M9 10h6" />
        </svg>
      </span>
      <span class="nav-text">Reserva presencial</span>
    </button>

    <button class="nav-link" data-page="servicos" data-title="Servicos">
      <span class="nav-icon">

        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="lucide lucide-square-library preview-icon">
          <rect width="18" height="18" x="3" y="3" rx="2" />
          <path d="M7 7v10" />
          <path d="M11 7v10" />
          <path d="m15 7 2 10" />
        </svg>

      </span>
      <span class="nav-text">Servicos</span>
    </button>

    <button class="nav-link" data-page="restaurante" data-title="Restaurante">
      <span class="nav-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-soup preview-icon">
          <path d="M11.248 3c.273.1.808.53.747 1.36-.05.83-.938 1.2-.989 2.02-.06.78.333 1.24.727 1.62" />
          <path d="M16.252 3c.268.1.794.53.745 1.36-.06.83-.923 1.2-.993 2.02-.05.78.338 1.24.725 1.62" />
          <path d="M19.5 12 22 6" />
          <path d="M4 12a1 1 0 00-.99 1.133A9 9 0 0012 21a9 9 0 008.99-7.867A1 1 0 0020 12z" />
          <path d="M6.252 3c.268.1.794.53.745 1.36-.06.83-.923 1.2-.993 2.02-.05.78.338 1.24.735 1.62" />
          <path d="M7 21h10" />
        </svg>
      </span>
      <span class="nav-text">Restaurante</span>
    </button>

    <span class="nav-label">GESTÃO</span>

    <?php if ($podeGerirFuncionarios): ?>

    <button class="nav-link" data-page="funcionarios" data-title="Funcionários">
      <span class="nav-icon">

        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="lucide lucide-users-round preview-icon">
          <path d="M18 21a8 8 0 0 0-16 0" />
          <circle cx="10" cy="8" r="5" />
          <path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3" />
        </svg>

      </span>
      <span class="nav-text">Funcionários</span>
    </button>

    <?php endif; ?>

    <?php if ($podeGerirHotel): ?>

    <button class="nav-link" data-page="relatorio" data-title="Relatórios">
      <span class="nav-icon" title="Relatorio">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="lucide lucide-chart-spline preview-icon">
          <path d="M3 3v16a2 2 0 0 0 2 2h16" />
          <path d="M7 16c.5-2 1.5-7 4-7 2 0 2 3 4 3 2.5 0 4.5-5 5-7" />
        </svg>
      </span>
      <span class="nav-text">Relatorio</span>
    </button>

    <button class="nav-link" data-page="hotel" data-title="Dados do hotel">
      <span class="nav-icon" title="Dados do hotel">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bolt preview-icon">
          <path
            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
          <circle cx="12" cy="12" r="4" />
        </svg>
      </span>
      <span class="nav-text">Dados do hotel</span>
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

    <button class="logout btn" data-title="Sair da conta" onclick="location.href='<?= BASE_URL ?>/logout.php'">

      <span title="Sair da conta" class="logout-icon">↪</span>
      <span class="logout-text">Sair da conta</span>

    </button>
  </div>

</aside>