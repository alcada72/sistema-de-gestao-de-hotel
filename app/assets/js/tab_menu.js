document.addEventListener("DOMContentLoaded", () => {
  const menus = document.querySelectorAll(".nav-link");
  const groups = document.querySelectorAll(".tabmenu-group");

  // Selecionar tabmenu
  function selecttabmenu(item) {
    const group = item.closest(".tabmenu-group");

    if (!group) return;

    group.querySelectorAll(".tab_menu_link").forEach((link) => {
      link.classList.remove("is-active");
    });

    item.classList.add("is-active");

    const section = item.dataset.section;

    // Navegar para a página correspondente
    if (typeof window.navigate === "function") {
      window.navigate(section);
    }
  }

  // Clicar no menu principal
  menus.forEach((menu) => {
    menu.addEventListener("click", () => {
      const menuKey = menu.dataset.page;

      menus.forEach((item) => {
        item.classList.remove("is-active");
      });

      menu.classList.add("is-active");

      let activeGroup = null;

      groups.forEach((group) => {
        const isActive = group.dataset.parent === menuKey;

        group.hidden = !isActive;

        if (isActive) {
          activeGroup = group;
        }
      });

      const firstItem = activeGroup?.querySelector(".tab_menu_link");

      if (firstItem) {
        selecttabmenu(firstItem);
      }
    });
  });

  // Clicar nos tabmenus
  document.addEventListener("click", (event) => {
    const item = event.target.closest(".tab_menu_link");

    if (!item) return;

    selecttabmenu(item);
  });

  // Inicialização
  const activeMenu = document.querySelector(".nav-link.is-active");

  if (activeMenu) {
    const menuKey = activeMenu.dataset.page;

    groups.forEach((group) => {
      group.hidden = group.dataset.parent !== menuKey;
    });

    const activeGroup = document.querySelector(
      `.tabmenu-group[data-parent="${CSS.escape(menuKey)}"]`,
    );

    const firstItem = activeGroup?.querySelector(".tab_menu_link");

    if (firstItem) {
      selecttabmenu(firstItem);
    }
  }
});
