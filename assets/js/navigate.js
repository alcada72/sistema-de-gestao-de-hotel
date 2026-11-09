"use strict";

(() => {
  let $ = (selector, parent = document) => parent.querySelector(selector);

  let $$ = (selector, parent = document) => parent.querySelectorAll(selector);

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
      activeLink?.textContent.trim() ||
      { dashboard: "Dashboard" }[page] ||
      page;

    $("#sidebar")?.classList.remove("open");
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  $$("[data-page]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      navigate(button.dataset.page);
    });
  });
})();
