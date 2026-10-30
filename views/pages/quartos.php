<section class="page-section" id="page-quartos">
  <div class="page-heading">
    <div>
      <p class="eyebrow">ALOJAMENTO</p>
      <h1>Gestão de quartos</h1>
      <p class="page-description">
        Gere os quartos, preços e disponibilidade.
      </p>
    </div>
    <button class="btn btn-primary" id="addRoomBtn">
      ＋ Cadastrar quarto
    </button>
  </div>

  <div class="filter-bar">
    <input class="search-input" id="roomSearch" placeholder="⌕  Procurar quarto..." />
    <select id="roomTypeFilter" aria-label="Filtrar por tipo">
      <option value="todos">Todos os tipos</option>
      <option value="normal">Normal</option>
      <option value="vip">VIP</option>
    </select>
    <select id="roomStatusFilter" aria-label="Filtrar por estado">
      <option value="todos">Todos os estados</option>
      <option value="disponivel">Disponível</option>
      <option value="ocupado">Ocupado</option>
      <option value="manutencao">Manutenção</option>
    </select>
  </div>

  <div class="room-grid full-room-grid" id="allRoomsGrid">
    <?php foreach ($quartos as $quarto): ?>
      <article role="button" tabindex="0" data-room-detail="<?= htmlspecialchars($quarto['id']) ?>"
        class="room-tile <?= htmlspecialchars($quarto['estado']) ?>">

        <?php if ($quarto['tipo'] === 'vip'): ?>
          <span class="vip-tag">VIP</span>
        <?php endif; ?>

        <span class="room-number">
          Quarto <?= htmlspecialchars($quarto['numero']) ?>
        </span>

        <span class="room-type-label">
          <?= strtoupper(htmlspecialchars($quarto['tipo'])) ?>
        </span>

        <span class="room-price">
          <?= number_format((float) $quarto['preco'], 2, ',', '.') ?> / noite
        </span>

        <span class="room-status">
          <?php
          $statusRoomLabel = [
            'manutencao' => 'Em manutenção',
            'ocupado' => 'Ocupado',
            'disponivel' => 'Disponível'
          ];

          echo $statusRoomLabel[$quarto['estado']] ?? 'Desconhecido';
          ?>
        </span>

        <span class="room-action">
          <?php
          switch ($quarto['estado']) {
            case 'manutencao':
              echo 'Em manutenção';
              break;

            case 'ocupado':
              echo 'Quarto ocupado';
              break;

            case 'disponivel':
              echo 'Quarto disponível';
              break;

            default:
              echo 'Estado desconhecido';
          }
          ?>
        </span>

      </article>
    <?php endforeach; ?>
  </div>
</section>