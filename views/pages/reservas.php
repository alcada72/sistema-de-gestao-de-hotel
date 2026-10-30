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
    <input class="search-input" id="reservationSearch" placeholder="⌕  Procurar hóspede ou quarto..." />
    <select id="reservationStatusFilter" aria-label="Filtrar reservas">
      <option value="todas">Todos os estados</option>
      <option value="Confirmada">Confirmada</option>
      <option value="Pendente">Pendente</option>
      <option value="Check-in">Check-in</option>
      <option value="Concluída">Concluída</option>
      <option value="Cancelada">Cancelada</option>
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