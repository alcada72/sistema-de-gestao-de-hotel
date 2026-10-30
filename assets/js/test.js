"use strict";

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

const dados = window.HOTELIER_DATA;

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

const initials = (name) =>
  String(name || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join("");

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
  type: room.tipo,
  price: Number(room.preco),
  capacity: Number(room.capacidade),
  description: room.descricao || "",
  status: room.estado,
}));

let guests = (dados.guests || []).map((guest) => ({
  id: Number(guest.id),
  name: guest.nome,
  phone: guest.telefone || "",
  email: guest.email || "",
  document: guest.documento || "",
}));

let reservations = (dados.reservations || []).map((reservation) => ({
  id: Number(reservation.id),
  guestId: Number(reservation.hospede_id),
  roomId: Number(reservation.quarto_id),
  guestName: reservation.hospede_nome,
  roomNumber: Number(reservation.quarto_numero),
  checkin: reservation.data_entrada,
  checkout: reservation.data_saida,
  price: Number(reservation.preco_diaria),
  people: Number(reservation.quantidade_pessoas),
  status: estadoReserva[reservation.estado] || reservation.estado,
}));

let products = (dados.products || []).map((product) => ({
  id: Number(product.id),
  name: product.nome,
  category: tipoProduto[product.tipo] || product.tipo,
  categoryName: product.categoria_nome,
  price: Number(product.preco),
  description: product.descricao || "",
  available: Boolean(Number(product.disponivel)),
}));

let orders = (dados.orders || []).map((order) => ({
  id: Number(order.id),
  table:
    order.tipo === "mesa"
      ? `Mesa ${order.numero_mesa || "—"}`
      : order.tipo === "quarto"
        ? `Quarto ${order.quarto_numero || "—"}`
        : "Balcão",
  items: "",
  total: 0,
  status: estadoPedido[order.estado] || order.estado,
  guestName: order.hospede_nome || "",
}));

const activities = [];
const stats = dados.stats || {};

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

function getGuest(id) {
  return guests.find((guest) => guest.id === Number(id));
}

function getRoom(id) {
  return rooms.find((room) => room.id === Number(id));
}
