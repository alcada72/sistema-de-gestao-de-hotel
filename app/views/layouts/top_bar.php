<header class="topbar">
  <div class="topbar-left">
    <button class="menu-toggle" onclick="toggleSidebar()">
      ☰
    </button>

    <div class="hotel-selector">
      <select onchange="selecionarHotel(this.value)">

        <?php foreach ($hoteis as $hotel): ?>

        <option value="<?= (int) $hotel['id'] ?>" <?= (int) $hotel['id'] === $hotelId ? 'selected' : '' ?>>
          <?= htmlspecialchars($hotel['nome']) ?>
        </option>

        <?php endforeach; ?>

      </select>
    </div>
  </div>

  <div class="topbar-right">
    <span class="status-dot"></span>
    <span>Área do hotel</span>
  </div>

</header>