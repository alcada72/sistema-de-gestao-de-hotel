"use strict";
const $ = (selector, parent = document) => parent.querySelector(selector);

const $$ = (selector, parent = document) => parent.querySelectorAll(selector);

let lastFocusedElement = null;

function openModal(id) {
  const modal = document.getElementById(id);

  if (!modal) return;

  lastFocusedElement = document.activeElement;

  modal.classList.add("open");
  modal.setAttribute("aria-hidden", "false");

  document.body.style.overflow = "hidden";

  if (id === "reservationModal") {
    const form = document.getElementById("reservationForm");

    if (form) {
      const checkin = form.elements["dados[data_entrada]"];
      const checkout = form.elements["dados[data_saida]"];

      if (checkin && !checkin.value) {
        checkin.value = today();
      }

      if (checkout && !checkout.value) {
        checkout.value = addDays(today(), 1);
      }
    }
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
