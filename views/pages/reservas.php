<section class="page-section" id="page-reservas">
  <div class="page-heading">
    <div>
      <p class="eyebrow">ALOJAMENTO</p>
      <h1>Reservas</h1>
      <p class="page-description">
        Consulta e regista as reservas dos hóspedes.
      </p>
    </div>
    <button class="btn btn-primary" data-open="reservationModal">
      ＋ Nova reserva
    </button>
  </div>

  <div class="filter-bar">

    <select id="reservationFilter" class="search-input" aria-label="Filtrar reservas">

      <option value="aallld">Todas as reservas</option>

      <?php foreach ($reservas as $reserva): ?>

        <option value="<?= (int) $reserva['id'] ?>">

          <?= htmlspecialchars($reserva['hospede_nome']) ?>
          - Quarto <?= htmlspecialchars($reserva['quarto_numero']) ?>
          - <?= htmlspecialchars($reserva['quarto_tipo']) ?>

        </option>

      <?php endforeach; ?>

    </select>

  </div>
  <section class="panel">
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>HÓSPEDE</th>
            <th>QUARTO</th>
            <th>CHECK-IN</th>
            <th>CHECK-OUT</th>
            <th>VALOR</th>
            <th>ESTADO</th>
            <th>AÇÕES</th>
          </tr>
        </thead>
        <tbody id="allReservations"></tbody>
      </table>
    </div>
  </section>
</section>