const form = document.getElementById("reservationForm");

const steps = [...document.querySelectorAll(".form-step")];
const dots = [...document.querySelectorAll(".step-dot")];

const nextBtn = document.getElementById("nextStep");
const prevBtn = document.getElementById("prevStep");
const submitBtn = document.getElementById("submitReservation");

const progressBar = document.getElementById("progressBar");
const stepLabel = document.getElementById("stepLabel");
const stepPercent = document.getElementById("stepPercent");

let currentStep = 1;
const totalSteps = steps.length;

function showStep(step) {
  currentStep = step;

  steps.forEach((item, index) => {
    item.classList.toggle("active", index === step - 1);
  });

  dots.forEach((dot, index) => {
    dot.classList.toggle("active", index === step - 1);
    dot.classList.toggle("completed", index < step - 1);
  });

  const percent = Math.round((step / totalSteps) * 100);

  progressBar.style.width = percent + "%";
  stepLabel.textContent = `Etapa ${step} de ${totalSteps}`;
  stepPercent.textContent = percent + "%";

  prevBtn.hidden = step === 1;
  nextBtn.hidden = step === totalSteps;
  submitBtn.hidden = step !== totalSteps;

  if (step === 4) {
    atualizarResumo();
  }
}

function validarEtapa() {
  const activeStep = steps[currentStep - 1];

  const campos = activeStep.querySelectorAll("input, select, textarea");

  for (const campo of campos) {
    if (!campo.checkValidity()) {
      campo.reportValidity();
      return false;
    }
  }
  if (currentStep === 3) {
    const quarto = getSelectedRoom();

    if (!quarto) {
      alert("Seleciona um quarto para continuar.");
      return false;
    }

    const entrada = document.getElementById("checkinInput").value;
    const saida = document.getElementById("checkoutInput").value;

    if (
      !entrada ||
      !saida ||
      new Date(saida + "T00:00:00") <= new Date(entrada + "T00:00:00")
    ) {
      alert("Verifica as datas de entrada e saída.");
      return false;
    }

    const capacidade = Number(quarto.dataset.capacidade);

    const pessoas = Number(form.elements["dados[quantidade_pessoas]"].value);

    if (pessoas > capacidade) {
      alert(`Este quarto suporta apenas ${capacidade} hóspedes.`);
      return false;
    }
  }
  return true;
}

nextBtn.addEventListener("click", () => {
  if (!validarEtapa()) return;

  if (currentStep < totalSteps) {
    showStep(currentStep + 1);
  }
});

prevBtn.addEventListener("click", () => {
  if (currentStep > 1) {
    showStep(currentStep - 1);
  }
});

const roomInputs = document.querySelectorAll('input[name="dados[quarto_id]"]');

const checkinInput = document.getElementById("checkinInput");
const checkoutInput = document.getElementById("checkoutInput");

function getSelectedRoom() {
  return document.querySelector('input[name="dados[quarto_id]"]:checked');
}

function calcularValor() {
  const quarto = getSelectedRoom();

  const preco = Number(quarto?.dataset.preco) || 0;

  if (!quarto || !checkinInput.value || !checkoutInput.value) {
    return { noites: 0, total: 0, preco };
  }

  const entrada = new Date(checkinInput.value + "T00:00:00");
  const saida = new Date(checkoutInput.value + "T00:00:00");

  const noites = Math.round((saida - entrada) / 86400000);

  if (noites <= 0) {
    return { noites: 0, total: 0, preco };
  }

  return {
    noites,
    total: preco * noites,
    preco,
  };
}

function atualizarResumo() {
  const resultado = calcularValor();

  const nome = form.elements["dados[nome]"].value;
  const pessoas = form.elements["dados[quantidade_pessoas]"].value;

  const quarto = getSelectedRoom();

  document.getElementById("summaryRoom").textContent = quarto
    ? quarto.closest(".room-card").querySelector(".room-number").textContent
    : "-";

  document.getElementById("summaryGuest").textContent = nome;

  document.getElementById("summaryPeople").textContent = pessoas;

  document.getElementById("summaryCheckin").textContent = checkinInput.value;

  document.getElementById("summaryCheckout").textContent = checkoutInput.value;

  document.getElementById("reservationNights").textContent =
    resultado.noites + " noite(s)";

  document.getElementById("reservationTotal").textContent =
    resultado.total.toLocaleString("pt-AO") + " Kz";
}

checkinInput.addEventListener("change", atualizarResumo);
checkoutInput.addEventListener("change", atualizarResumo);

console.log(document.querySelectorAll(".room-tile"));

showStep(1);
