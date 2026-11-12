<?php

require_once __DIR__ . '/../../models/Menu.php';

$menuModel = new Menu();

$menus = $menuModel->getMenuByCargo($cargo);

?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">

  <div class="brand">
    <div class="brand-logo">
      <a href="<?= BASE_URL ?>/app">
        <img src="<?= BASE_URL ?>/assets/img/logo-multiplo.png" alt="">
      </a>
    </div>

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

    <?php foreach ($menus as $index => $menu): ?>

      <button type="button" class="nav-link <?= $index === 0 ? 'active' : '' ?>"
        data-page="<?= htmlspecialchars($menu['chave'], ENT_QUOTES, 'UTF-8') ?>"
        title="<?= htmlspecialchars($menu['nome'], ENT_QUOTES, 'UTF-8') ?>">

        <span class="nav-icon">
          <?= $menu['icone'] ?>
        </span>

        <span class="nav-text">
          <?= htmlspecialchars($menu['nome'], ENT_QUOTES, 'UTF-8') ?>
        </span>

      </button>

    <?php endforeach; ?>

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