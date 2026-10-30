<section class="page-section active" id="page-dashboard">
  <div class="page-heading">
    <div>
      <p class="eyebrow">VISÃO GERAL</p>
      <h1>Bom dia, Administrador! <span>✦</span></h1>
      <p class="page-description">
        Aqui está o resumo do que acontece no teu hotel hoje.
      </p>
    </div>
    <button class="btn btn-primary" data-open="reservationModal">
      <span>＋</span> Nova reserva
    </button>
  </div>

  <div class="stats-grid">
    <article class="stat-card">
      <div class="stat-top">
        <span class="stat-label">Quartos disponíveis</span>
        <span class="stat-icon blue">▤</span>
      </div>
      <div class="stat-value" id="availableRooms">
        <?= $totalDisponiveis ?>
      </div>
      <div class="stat-footer">
        <span class="positive">● Disponíveis</span>
        <span>de
          <?= $totalQuartos ?> quartos
        </span>
      </div>
      <div class="progress-track">
        <div class="progress-fill blue-fill" style="width: 50%"></div>
      </div>
    </article>

    <article class="stat-card">
      <div class="stat-top">
        <span class="stat-label">Quartos ocupados</span>
        <span class="stat-icon purple">▣</span>
      </div>
      <div class="stat-value" id="occupiedRooms">
        <?= $totalOcupados ?>
      </div>
      <div class="stat-footer">
        <span class="purple-text">41,7%</span>
        <span>taxa de ocupação</span>
      </div>
      <div class="progress-track">
        <div class="progress-fill purple-fill" style="width: 41.7%"></div>
      </div>
    </article>

    <article class="stat-card">
      <div class="stat-top">
        <span class="stat-label">Reservas de hoje</span>
        <span class="stat-icon orange">▦</span>
      </div>
      <div class="stat-value" id="todayReservations">8</div>
      <div class="stat-footer">
        <span class="orange-text">Entradas e saídas</span>
        <span>hoje</span>
      </div>
      <div class="stat-note">Acompanha as chegadas previstas.</div>
    </article>

    <article class="stat-card">
      <div class="stat-top">
        <span class="stat-label">Receita do dia</span>
        <span class="stat-icon green">₭</span>
      </div>
      <div class="stat-value stat-money" id="dailyRevenue">
        185.000 Kz
      </div>
      <div class="stat-footer">
        <span class="positive">Receita registada</span>
        <span>hoje</span>
      </div>
      <div class="stat-note">Valor demonstrativo.</div>
    </article>
  </div>

  <div class="dashboard-grid">
    <section class="panel rooms-panel">
      <div class="panel-heading">
        <div>
          <h2>Estado dos quartos</h2>
          <p>Acompanha a disponibilidade do hotel.</p>
        </div>
        <button class="text-button" data-page="quartos">
          Ver todos ↗
        </button>
      </div>

      <div class="room-legend">
        <span><i class="legend-dot available-dot"></i> Disponível</span>
        <span><i class="legend-dot occupied-dot"></i> Ocupado</span>
        <span><i class="legend-dot maintenance-dot"></i> Manutenção</span>
      </div>

      <div class="room-grid" id="dashboardRoomGrid"></div>
    </section>

    <section class="panel activity-panel">
      <div class="panel-heading">
        <div>
          <h2>Movimentos recentes</h2>
          <p>Últimas atividades do hotel.</p>
        </div>
        <span class="live-indicator">● Ao vivo (protótipo)</span>
      </div>
      <div class="activity-list" id="activityList"></div>
    </section>
  </div>

  <section class="panel reservations-panel">
    <div class="panel-heading">
      <div>
        <h2>Reservas recentes</h2>
        <p>Consulta as últimas reservas registadas.</p>
      </div>
      <button class="text-button" data-page="reservas">
        Ver todas ↗
      </button>
    </div>
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
          </tr>
        </thead>
        <tbody id="recentReservations"></tbody>
      </table>
    </div>
  </section>
</section>