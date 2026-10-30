"use strict";

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

const money = (value) =>
  new Intl.NumberFormat("pt-AO", {
    maximumFractionDigits: 2,
  }).format(Number(value || 0)) + " Kz";

const dateFormat = (value) => {
  if (!value) return "—";
  const [year, month, day] = value.split("-");
  return `${day}/${month}/${year}`;
};

const today = () => {
  const date = new Date();
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, "0"),
    String(date.getDate()).padStart(2, "0"),
  ].join("-");
};

const addDays = (date, amount) => {
  const result = new Date(`${date}T12:00:00`);
  result.setDate(result.getDate() + amount);
  return [
    result.getFullYear(),
    String(result.getMonth() + 1).padStart(2, "0"),
    String(result.getDate()).padStart(2, "0"),
  ].join("-");
};

const initials = (name) =>
  String(name || "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join("");

const dados = window.HOTELIER_DATA || {};

let toastTimer;

const estadoReserva = {
  pendente: "Pendente",
  confirmada: "Confirmada",
  checkin: "Check-in",
  concluida: "Concluída",
  cancelada: "Cancelada",
};
const estadoPedido = {
  pendente: "Pendente",
  preparacao: "Preparação",
  pronto: "Pronto",
  entregue: "Entregue",
  cancelado: "Cancelado",
};
const tipoProduto = {
  prato: "Prato",
  bebida: "Bebida",
  sobremesa: "Sobremesa",
  outro: "Outro",
};

let rooms = (dados.rooms || []).map((room) => ({
  id: Number(room.id),
  number: Number(room.numero),
  type: String(room.tipo || "normal").toLowerCase(),
  price: Number(room.preco || 0),
  capacity: Number(room.capacidade || 1),
  description: room.descricao || "",
  status: String(room.estado || "disponivel").toLowerCase(),
  andar: Number(room.andar_id || 1),
}));

let andares = (dados.andares || []).map((andar) => ({
  id: Number(andar.id),
  number: Number(andar.numero_andar),
  capacity: Number(andar.total_quartos || 1),
  description: andar.descricao || "",
}));

let guests = (dados.guests || []).map((guest) => ({
  id: Number(guest.id),
  name: guest.nome || "",
  phone: guest.telefone || "",
  email: guest.email || "",
  document: guest.documento || "",
}));

let reservations = (dados.reservations || []).map((reservation) => ({
  id: Number(reservation.id),
  guestId: Number(reservation.hospede_id),
  roomId: Number(reservation.quarto_id),
  guestName: reservation.hospede_nome || "",
  roomNumber: Number(reservation.quarto_numero || 0),
  checkin: String(reservation.data_entrada || ""),
  checkout: String(reservation.data_saida || ""),
  price: Number(reservation.preco_diaria || 0),
  people: Number(reservation.quantidade_pessoas || 1),
  status:
    estadoReserva[String(reservation.estado || "pendente").toLowerCase()] ||
    reservation.estado ||
    "Pendente",
}));
let products = (dados.products || []).map((product) => ({
  id: Number(product.id),
  name: product.nome || "",
  category:
    tipoProduto[String(product.tipo || "outro").toLowerCase()] ||
    product.tipo ||
    "Outro",
  categoryName: product.categoria_nome || "",
  price: Number(product.preco || 0),
  description: product.descricao || "",
  available: product.disponivel === true || Number(product.disponivel) === 1,
}));
let orders = (dados.orders || []).map((order) => ({
  id: Number(order.id),
  table:
    order.tipo === "mesa"
      ? `Mesa ${order.numero_mesa || "—"}`
      : order.tipo === "quarto"
        ? `Quarto ${order.quarto_numero || "—"}`
        : "Balcão",
  items: order.itens_resumo || order.items || "Ver detalhes do pedido",
  total: Number(order.total || order.total_pedido || 0),
  status:
    estadoPedido[String(order.estado || "pendente").toLowerCase()] ||
    order.estado ||
    "Pendente",
  guestName: order.hospede_nome || "",
}));
let activities = (dados.activities || []).map((activity) => ({
  icon: activity.icon || "•",
  text: activity.text || "Atividade registada",
  detail: activity.detail || "",
  time: activity.time || "",
}));
const stats = dados.stats || {};
let nextReservationId = Math.max(0, ...reservations.map((item) => item.id)) + 1;
let nextGuestId = Math.max(0, ...guests.map((item) => item.id)) + 1;
let nextProductId = Math.max(0, ...products.map((item) => item.id)) + 1;

const statusClass = {
  Confirmada: "confirmed",
  Pendente: "pending",
  "Check-in": "checkin",
  Concluída: "completed",
  Cancelada: "cancelled",
};

const statusRoomClass = {
  disponivel: "available",
  ocupado: "occupied",
  manutencao: "maintenance",
};

const statusRoomLabel = {
  disponivel: "Disponível",
  ocupado: "Ocupado",
  manutencao: "Manutenção",
};

function escapeHTML(value) {
  return String(value ?? "").replace(
    /[&<>"']/g,
    (char) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[char],
  );
}

function getGuest(id) {
  return guests.find((guest) => guest.id === Number(id));
}

function getRoom(id) {
  return rooms.find((room) => room.id === Number(id));
}

function activeReservationForRoom(roomId) {
  return reservations.some((reservation) => {
    return (
      reservation.roomId === roomId &&
      ["Pendente", "Confirmada", "Check-in"].includes(reservation.status) &&
      reservation.checkin <= today() &&
      reservation.checkout > today()
    );
  });
}

function canBookRoom(room) {
  return (
    room.status === "disponivel" &&
    !reservations.some(
      (reservation) =>
        reservation.roomId === room.id &&
        ["Pendente", "Confirmada", "Check-in"].includes(reservation.status) &&
        reservation.checkin <= today() &&
        reservation.checkout > today(),
    )
  );
}

function showToast(message) {
  const toast = $("#toast");
  if (!toast) {
    console.info(message);
    return;
  }
  toast.textContent = message;
  toast.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 3000);
}

let lastFocusedElement = null;

function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  lastFocusedElement = document.activeElement;
  modal.classList.add("open");
  modal.setAttribute("aria-hidden", "false");
  document.body.style.overflow = "hidden";

  if (id === "reservationModal") {
    const form = $("#reservationForm");
    if (form) {
      form.elements.checkin.value ||= today();
      form.elements.checkout.value ||= addDays(today(), 1);
    }
    renderRoomOptions();

    updateReservationTotal();
  }

  const firstInput = modal.querySelector(
    "input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button",
  );
  if (firstInput) firstInput.focus();
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.remove("open");
  modal.setAttribute("aria-hidden", "true");
  if (!$(".modal-backdrop.open")) {
    document.body.style.overflow = "";
    if (lastFocusedElement && typeof lastFocusedElement.focus === "function")
      lastFocusedElement.focus();
  }
}

function navigate(page) {
  const target = document.getElementById(`page-${page}`);
  if (!target) return;

  $$(".page-section").forEach((section) => {
    section.classList.toggle("active", section === target);
  });

  $$(".nav-link").forEach((button) => {
    button.classList.toggle("active", button.dataset.page === page);
  });

  const activeLink = $(`.nav-link[data-page="${page}"]`);
  $("#breadcrumbTitle").textContent =
    activeLink?.textContent.trim() || { dashboard: "Dashboard" }[page] || page;

  $("#sidebar")?.classList.remove("open");
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function renderDashboardRooms() {
  const grid = $("#dashboardRoomGrid");
  const visibleRooms = rooms.slice(0, 12);

  grid.innerHTML = visibleRooms
    .map(
      (room) => `
    <button type="button"
      class="room-tile ${statusRoomClass[room.status]}"
      data-room-id="${room.id}"
      title="Quarto ${room.number} · ${statusRoomLabel[room.status]}">
      ${room.type === "vip" ? '<span class="vip-tag">VIP</span>' : ""}
      <span class="room-number">${room.number}</span>
      <span class="room-status">${statusRoomLabel[room.status]}</span>
    </button>
  `,
    )
    .join("");
}

function showRoomDetails(roomId) {
  const room = getRoom(roomId);
  const modal = $("#productDetailModal");
  if (!room || !modal) return;
  const fields = modal.querySelectorAll("#productDetailForm input");
  if (fields[0]) fields[0].value = `Quarto ${room.number}`;
  if (fields[1]) fields[1].value = room.type.toUpperCase();
  if (fields[2]) fields[2].value = `${money(room.price)} / noite`;
  if (fields[3]) fields[3].value = statusRoomLabel[room.status] || room.status;
  if (fields[4]) fields[4].value = String(room.capacity);
  if (fields[5]) fields[5].value = String(room.andar);
  const heading = $("#productModalTitle");
  if (heading) heading.textContent = `Detalhes do Quarto ${room.number}`;
  openModal("productDetailModal");
}

function renderAllRooms() {
  const query = $("#roomSearch").value.trim().toLowerCase();
  const type = $("#roomTypeFilter").value;
  const status = $("#roomStatusFilter").value;

  const filtered = rooms.filter((room) => {
    const matchesQuery = String(room.number).includes(query);
    const matchesType = type === "todos" || room.type === type;
    const matchesStatus = status === "todos" || room.status === status;

    return matchesQuery && matchesType && matchesStatus;
  });

  $("#allRoomsGrid").innerHTML = filtered.length
    ? filtered
        .map(
          (room) => `
      <article role="button" tabindex="0" data-room-detail="${room.id}" class="room-tile ${statusRoomClass[room.status]}">
        ${room.type === "vip" ? '<span class="vip-tag">VIP</span>' : ""}
        <span class="room-number">Quarto ${room.number}</span>
        <span class="room-type-label">${room.type.toUpperCase()}</span>
        <span class="room-price">${money(room.price)} / noite</span>
        <span class="room-status">${statusRoomLabel[room.status]}</span>
        <span class="room-action">${room.status === "manutencao" ? "Em manutenção" : room.status === "ocupado" ? "Quarto ocupado" : "Quarto disponível"}</span>
      </article>
    `,
        )
        .join("")
    : '<div class="empty-state">Nenhum quarto encontrado.</div>';
}

function renderActivities() {
  $("#activityList").innerHTML = activities
    .map(
      (activity) => `
    <div class="activity-item">
      <div class="activity-icon">${activity.icon}</div>
      <div>
        <p>${escapeHTML(activity.text)}</p>
        <small>${escapeHTML(activity.detail)} · ${escapeHTML(activity.time)}</small>
      </div>
    </div>
  `,
    )
    .join("");
}

function reservationRow(reservation, withActions = false) {
  const guest = getGuest(reservation.guestId);
  const room = getRoom(reservation.roomId);

  if (!guest || !room) return "";

  const totalNights = Math.max(
    0,
    Math.round(
      (new Date(`${reservation.checkout}T12:00:00`) -
        new Date(`${reservation.checkin}T12:00:00`)) /
        86400000,
    ),
  );

  return `
    <tr>
      <td>
        <div class="guest-cell">
          <span class="guest-avatar">${escapeHTML(initials(guest.name))}</span>
          <span><span class="guest-name">${escapeHTML(guest.name)}</span>
            <small class="guest-subtitle">${escapeHTML(guest.phone)}</small>
          </span>
        </div>
      </td>
      <td>${room.number} <span class="guest-subtitle">${room.type.toUpperCase()}</span></td>
      <td>${dateFormat(reservation.checkin)}</td>
      <td>${dateFormat(reservation.checkout)}</td>
      <td>${money(reservation.price * totalNights)}</td>
      <td><span class="status-badge ${statusClass[reservation.status] || ""}">${escapeHTML(reservation.status)}</span></td>
      ${
        withActions
          ? `<td>
        ${reservation.status === "Pendente" ? `<button class="action-button" data-res-action="confirm" data-id="${reservation.id}">Confirmar</button>` : ""}
        ${["Pendente", "Confirmada"].includes(reservation.status) ? `<button class="action-button danger" data-res-action="cancel" data-id="${reservation.id}">Cancelar</button>` : ""}
      </td>`
          : ""
      }
    </tr>
  `;
}

function renderReservations() {
  const query = $("#reservationSearch").value.trim().toLowerCase();
  const status = $("#reservationStatusFilter").value;

  const filtered = reservations.filter((reservation) => {
    const guest = getGuest(reservation.guestId);
    const room = getRoom(reservation.roomId);
    const text = `${guest?.name || ""} ${room?.number || ""}`.toLowerCase();

    return (
      text.includes(query) &&
      (status === "todas" || reservation.status === status)
    );
  });

  $("#allReservations").innerHTML = filtered.length
    ? filtered.map((reservation) => reservationRow(reservation, true)).join("")
    : '<tr><td colspan="7" class="empty-state">Nenhuma reserva encontrada.</td></tr>';

  const recent = [...reservations].reverse().slice(0, 5);
  $("#recentReservations").innerHTML = recent.length
    ? recent.map((reservation) => reservationRow(reservation)).join("")
    : '<tr><td colspan="6">Ainda não existem reservas.</td></tr>';
}

function renderGuests() {
  const query = $("#guestSearch").value.trim().toLowerCase();

  const filtered = guests.filter((guest) =>
    `${guest.name} ${guest.phone} ${guest.email}`.toLowerCase().includes(query),
  );

  $("#guestsTable").innerHTML = filtered.length
    ? filtered
        .map(
          (guest) => `
      <tr>
        <td><div class="guest-cell">
          <span class="guest-avatar">${escapeHTML(initials(guest.name))}</span>
          <span class="guest-name">${escapeHTML(guest.name)}</span>
        </div></td>
        <td>${escapeHTML(guest.phone)}</td>
        <td>${escapeHTML(guest.email || "—")}</td>
        <td>${escapeHTML(guest.document || "—")}</td>
      </tr>
    `,
        )
        .join("")
    : '<tr><td colspan="4" class="empty-state">Nenhum hóspede encontrado.</td></tr>';
}

function renderProducts() {
  const query = $("#productSearch").value.trim().toLowerCase();
  const category = $("#productCategoryFilter").value;

  const filtered = products.filter(
    (product) =>
      `${product.name} ${product.description}`.toLowerCase().includes(query) &&
      (category === "todos" || product.category === category),
  );

  const icons = { Prato: "🍽", Bebida: "🥤", Sobremesa: "🍰" };

  $("#productGrid").innerHTML = filtered.length
    ? filtered
        .map(
          (product) => `
      <article class="product-card">
        <div class="product-art ${product.category.toLowerCase()}">${icons[product.category] || "🍴"}</div>
        <span class="product-category">${escapeHTML(product.category)}</span>
        <h3>${escapeHTML(product.name)}</h3>
        <p>${escapeHTML(product.description || "Sem descrição.")}</p>
        <div class="product-bottom">
          <span class="product-price">${money(product.price)}</span>
          <span class="product-stock ${product.available ? "" : "unavailable"}">
            ${product.available ? "● Disponível" : "● Indisponível"}
          </span>
        </div>
        <div style="margin-top:14px">
          <button class="action-button" data-product-action="toggle" data-id="${product.id}">
            ${product.available ? "Indisponibilizar" : "Disponibilizar"}
          </button>
        </div>
      </article>
    `,
        )
        .join("")
    : '<div class="empty-state">Nenhum produto encontrado.</div>';
}

function renderOrders() {
  $("#ordersList").innerHTML = orders
    .map(
      (order) => `
    <div class="order-item">
      <div>
        <strong>Pedido #${order.id} · ${escapeHTML(order.table)}</strong>
        <p>${escapeHTML(order.items)}</p>
      </div>
      <span>${order.total.toLocaleString("pt-AO")} Kz</span>
    </div>
  `,
    )
    .join("");
}

function renderReports() {
  $("#reportRooms").textContent = rooms.length;
  $("#reportGuests").textContent = guests.length;
  $("#reportReservations").textContent = reservations.length;
  $("#reportProducts").textContent = products.length;

  const types = [
    { name: "Normais", rooms: rooms.filter((r) => r.type === "normal") },
    { name: "VIP", rooms: rooms.filter((r) => r.type === "vip") },
  ];

  $("#reportBars").innerHTML = types
    .map(({ name, rooms: items }) => {
      const occupied = items.filter((room) => room.status === "ocupado").length;
      const percentage = items.length
        ? Math.round((occupied / items.length) * 100)
        : 0;

      return `
      <div class="report-row">
        <span>${name}</span>
        <div class="report-track"><div class="report-fill" style="width:${percentage}%"></div></div>
        <strong>${percentage}%</strong>
      </div>
    `;
    })
    .join("");
}

function updateStats() {
  const available = rooms.filter((room) => room.status === "disponivel").length;
  const occupied = rooms.filter((room) => room.status === "ocupado").length;

  $("#availableRooms").textContent = available;
  $("#occupiedRooms").textContent = occupied;
  $("#todayReservations").textContent = reservations.filter(
    (reservation) =>
      reservation.checkin === today() || reservation.checkout === today(),
  ).length;

  const revenue = orders
    .filter((order) => order.status === "Entregue")
    .reduce((sum, order) => sum + order.total, 0);

  $("#dailyRevenue").textContent = money(revenue);
  $(".nav-count").textContent = rooms.length;
}

function renderRoomOptions() {
  const select = $("#reservationRoom");
  const previousValue = select.value;

  const availableRooms = rooms.filter(canBookRoom);

  select.innerHTML =
    '<option value="">Seleciona um quarto disponível</option>' +
    availableRooms
      .map(
        (room) => `
      <option value="${room.id}" data-price="${room.price}" data-capacity="${room.capacity}">
       A${room.andar}   Quarto ${room.number} · ${room.type.toUpperCase()} · ${money(room.price)}/noite
      </option>
    `,
      )
      .join("");

  if (availableRooms.some((room) => String(room.id) === previousValue)) {
    select.value = previousValue;
  }
}

function updateReservationTotal() {
  const form = $("#reservationForm");
  const roomId = Number(form.elements.room.value);
  const room = getRoom(roomId);
  const checkin = form.elements.checkin.value;
  const checkout = form.elements.checkout.value;

  if (!room || !checkin || !checkout || checkout <= checkin) {
    $("#reservationTotal").textContent = money(0);
    $("#reservationNights").textContent =
      "Seleciona um quarto e datas válidas.";
    return;
  }

  const nights = Math.round(
    (new Date(`${checkout}T12:00:00`) - new Date(`${checkin}T12:00:00`)) /
      86400000,
  );

  $("#reservationTotal").textContent = money(nights * room.price);
  $("#reservationNights").textContent =
    `${nights} noite(s) × ${money(room.price)} por noite`;
}

function refresh() {
  renderDashboardRooms();
  renderAllRooms();
  renderReservations();
  renderGuests();
  renderProducts();
  renderOrders();
  renderActivities();
  renderReports();
  updateStats();
}

function resetForm(form) {
  form.reset();
}

$$("[data-page]").forEach((button) => {
  button.addEventListener("click", (event) => {
    event.preventDefault();
    navigate(button.dataset.page);
  });
});

$$("[data-open]").forEach((button) => {
  button.addEventListener("click", () => openModal(button.dataset.open));
});

$$("[data-close]").forEach((button) => {
  button.addEventListener("click", () => closeModal(button.dataset.close));
});

$$(".modal-backdrop").forEach((modal) => {
  modal.addEventListener("click", (event) => {
    if (event.target === modal) closeModal(modal.id);
  });
});

document.addEventListener("keydown", (event) => {
  if (
    (event.key === "Enter" || event.key === " ") &&
    event.target.matches("[data-room-detail]")
  ) {
    event.preventDefault();
    showRoomDetails(Number(event.target.dataset.roomDetail));
  }
  if (event.key === "Escape") {
    $$(".modal-backdrop.open").forEach((modal) => closeModal(modal.id));
    $("#sidebar")?.classList.remove("open");
  }
});

$("#menuBtn")?.addEventListener("click", () => {
  $("#sidebar")?.classList.toggle("open");
});

$("#roomSearch").addEventListener("input", renderAllRooms);
$("#roomTypeFilter").addEventListener("change", renderAllRooms);
$("#roomStatusFilter").addEventListener("change", renderAllRooms);

$("#reservationSearch").addEventListener("input", renderReservations);
$("#reservationStatusFilter").addEventListener("change", renderReservations);

$("#guestSearch").addEventListener("input", renderGuests);
$("#productSearch").addEventListener("input", renderProducts);
$("#productCategoryFilter").addEventListener("change", renderProducts);

$("#reservationRoom").addEventListener("change", updateReservationTotal);
$("#checkinInput").addEventListener("change", () => {
  const checkout = $("#checkoutInput");
  if (!checkout.value || checkout.value <= $("#checkinInput").value) {
    checkout.value = addDays($("#checkinInput").value, 1);
  }
  updateReservationTotal();
});
$("#checkoutInput").addEventListener("change", updateReservationTotal);

$("#addRoomBtn").addEventListener("click", () => openModal("roomModal"));

$("#addProductBtn").addEventListener("click", () => openModal("productModal"));

document.addEventListener("click", (event) => {
  const roomDetail = event.target.closest("[data-room-detail]");
  if (roomDetail) {
    showRoomDetails(Number(roomDetail.dataset.roomDetail));
    return;
  }
  const roomButton = event.target.closest("[data-room-id]");

  if (roomButton) {
    navigate("quartos");
    $("#roomSearch").value = "";
    $("#roomTypeFilter").value = "todos";
    $("#roomStatusFilter").value = "todos";
    renderAllRooms();
  }

  const reservationButton = event.target.closest("[data-res-action]");

  if (reservationButton) {
    const reservation = reservations.find(
      (item) => item.id === Number(reservationButton.dataset.id),
    );

    if (!reservation) return;

    if (reservationButton.dataset.resAction === "confirm") {
      const room = getRoom(reservation.roomId);

      const overlap = reservations.some(
        (other) =>
          other.id !== reservation.id &&
          other.roomId === reservation.roomId &&
          ["Pendente", "Confirmada", "Check-in"].includes(other.status) &&
          reservation.checkin < other.checkout &&
          reservation.checkout > other.checkin,
      );

      if (overlap || room?.status === "manutencao") {
        showToast("Não é possível confirmar: quarto indisponível.");
        return;
      }

      reservation.status = "Confirmada";
      showToast("Reserva confirmada.");
    }

    if (reservationButton.dataset.resAction === "cancel") {
      reservation.status = "Cancelada";
      showToast("Reserva cancelada.");
    }

    refresh();
  }

  const productButton = event.target.closest("[data-product-action]");

  if (productButton) {
    const product = products.find(
      (item) => item.id === Number(productButton.dataset.id),
    );

    if (!product) return;

    product.available = !product.available;
    renderProducts();
    renderReports();
    showToast(
      product.available
        ? "Produto disponibilizado."
        : "Produto indisponibilizado.",
    );
  }
});

$("#addGuestBtn")?.addEventListener("click", () => {
  const name = prompt("Nome completo do hóspede:");
  if (name === null || !name.trim()) return;

  const phone = prompt("Telefone do hóspede:");
  if (phone === null || !phone.trim()) return;

  const email = prompt("E-mail (opcional):") || "";

  guests.push({
    id: nextGuestId++,
    name: name.trim(),
    phone: phone.trim(),
    email: email.trim(),
    document: "",
  });

  refresh();
  showToast("Hóspede cadastrado com sucesso.");
});

$("#notificationBtn")?.addEventListener("click", () => {
  showToast(
    "Tens " +
      reservations.filter((r) => r.status === "Pendente").length +
      " reserva(s) pendente(s).",
  );
});

$("#profileBtn")?.addEventListener("click", () => {
  showToast("Sessão demonstrativa do administrador.");
});

refresh();
