"use strict";

const selectReserva = document.getElementById("selectReserva");
const formFilterReservas = document.getElementById("formFilterReservas");

const codigoInput = document.getElementById("codigoReserva");
const nomeInput = document.getElementById("nomeHospede");
const dataInput = document.getElementById("dataEntrada");
const estadoInput = document.getElementById("estadoReserva");

const tbody = document.getElementById("tbodyConsultarReservas");
const resultCount = document.getElementById("resultCount");

const datas = window.DATAS || {};

const reservasData = Array.isArray(datas.reservas) ? datas.reservas : [];

const formatarData = (data) => {
  if (!data) return "N/A";

  const partes = data.split("-");

  if (partes.length !== 3) return "N/A";

  return `${partes[2]}/${partes[1]}/${partes[0]}`;
};

const normalizar = (texto) => {
  return String(texto || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
};

const renderReservas = (data) => {
  const tbReserva = document.getElementById("tbreserva");

  if (!tbReserva) {
    console.error("Tabela tbreserva não encontrada!");
    return;
  }

  tbReserva.innerHTML = "";
  if (data.length === 0) {
    tbReserva.innerHTML = `
      <tr>
         <td colspan="8">Ainda não existem reservas.</td>
      </tr>
    `;
    return;
  }

  data.forEach((reserva) => {
    const linha = document.createElement("tr");

    const estadoNome = reserva.estado || "Desconhecido";

    const total = Number(reserva.total || 0);

    linha.innerHTML = `
            <td>
                <strong>${reserva.codigo ?? "N/A"}</strong>
            </td>

            <td>${reserva.nome_utilizador ?? "Não identificado"}</td>

            <td>${reserva.numero_quarto ?? "N/A"}</td>

            <td>${formatarData(reserva.data_entrada)}</td>

            <td>${formatarData(reserva.data_saida)}</td>

            <td>${total.toLocaleString("pt-PT", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            })} Kz</td>

            <td>
                <span class="status-badge ${estadoNome}">
                    ${estadoNome}
                </span>
            </td>

            <td>
                <button type="button" class="btn-icon"
                    data-codigo="${String(reserva.codigo ?? "")}">
                    •••
                </button>
            </td>
        `;

    tbReserva.appendChild(linha);
  });
};

function findReserva() {
  const codigo = selectReserva.value;

  const resultado = reservasData.filter(
    (reserva) => String(reserva.codigo) === codigo,
  );
  renderReservas(resultado);
}

function pesquisarReservas() {
  const codigo = normalizar(codigoInput.value);
  const nome = normalizar(nomeInput.value);
  const data = dataInput.value;
  const estado = normalizar(estadoInput.value);

  const resultado = reservasData.filter((reserva) => {
    const correspondeCodigo =
      !codigo || normalizar(reserva.codigo).includes(codigo);

    const correspondeNome =
      !nome || normalizar(reserva.nome_utilizador).includes(nome);

    const correspondeData = !data || reserva.data_entrada === data;

    const correspondeEstado = !estado || normalizar(reserva.estado) === estado;

    return (
      correspondeCodigo &&
      correspondeNome &&
      correspondeData &&
      correspondeEstado
    );
  });

  renderTabelaConsulta(resultado);
}

function renderTabelaConsulta(data) {
  tbody.innerHTML = "";

  resultCount.textContent = `${data.length} resultados`;

  if (data.length === 0) {
    tbody.innerHTML = `
            <tr>
                <td colspan="6">Nenhuma reserva encontrada.</td>
            </tr>
        `;
    return;
  }

  data.forEach((reserva) => {
    const linha = document.createElement("tr");
    const total = Number(reserva.total || 0);
    const estadoNome = reserva.estado || "Desconhecido";

    linha.innerHTML = `
            <td>
                <strong>${reserva.codigo ?? "N/A"}</strong>
            </td>

            <td>${reserva.nome_utilizador ?? "Não identificado"}</td>

            <td>${reserva.numero_quarto ?? "N/A"}</td>

            <td>${formatarData(reserva.data_entrada)}</td>

            <td>${formatarData(reserva.data_saida)}</td>

            <td>${total.toLocaleString("pt-PT", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            })} Kz</td>

            <td>
                <span class="status-badge ${estadoNome}">
                    ${estadoNome}
                </span>
            </td>

            <td>
                <button type="button" class="btn-icon"
                    data-codigo="${String(reserva.codigo ?? "")}">
                    •••
                </button>
            </td>
        `;

    tbody.appendChild(linha);
  });
}

selectReserva.addEventListener("change", findReserva);

formFilterReservas.addEventListener("submit", (event) => {
  event.preventDefault();
  pesquisarReservas();
});

formFilterReservas.addEventListener("reset", () => {
  setTimeout(() => {
    renderTabelaConsulta([]);
  }, 0);
});
