"use strict";

(() => {
  let $ = (selector, parent = document) => parent.querySelector(selector);

  let $$ = (selector, parent = document) => parent.querySelectorAll(selector);

  function navigate(page) {
    const target = document.getElementById(`page-${page}`);

    if (!target) {
      console.warn(`Página não encontrada: page-${page}`);
      return;
    }

    // Mostrar página selecionada
    $$(".page-section").forEach((section) => {
      section.classList.toggle("active", section === target);
    });

    // Atualizar menu principal
    $$(".nav-link").forEach((button) => {
      button.classList.toggle("active", button.dataset.page === page);
    });

    // Fechar sidebar em dispositivos móveis
    $("#sidebar")?.classList.remove("open");

    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  }

  // Disponibilizar navegação para outros scripts
  window.navigate = navigate;

  // Eventos dos menus principais
  $$(".nav-link").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();

      const page = button.dataset.page;

      navigate(page);
    });
  });
})();
