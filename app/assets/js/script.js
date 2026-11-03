document.addEventListener("DOMContentLoaded", () => {
  const pages = document.querySelectorAll(".page-content");
  const navigationItems = document.querySelectorAll("[data-page]");

  function mostrarPagina(nome) {
    let encontrada = false;

    pages.forEach((page) => {
      const ativa = page.dataset.page === nome;

      page.style.display = ativa ? "block" : "none";

      if (ativa) encontrada = true;
    });

    if (!encontrada) {
      nome = "dashboard";

      pages.forEach((page) => {
        page.style.display = page.dataset.page === nome ? "block" : "none";
      });
    }

    navigationItems.forEach((item) => {
      item.classList.toggle("active", item.dataset.page === nome);
    });
  }

  navigationItems.forEach((item) => {
    item.addEventListener("click", () => {
      mostrarPagina(item.dataset.page);
    });
  });

  mostrarPagina("dashboard");
});
