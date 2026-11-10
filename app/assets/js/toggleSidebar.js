const sidebar = document.querySelector("#sidebar");
const showSidebar = document.querySelector("#showSidebar");

const icons = {
  collapsed: `
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
 viewBox="0 0 24 24" fill="none" 
 stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-panel-right-close preview-icon"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m8 9 3 3-3 3"/></svg>
  `,

  expanded: `
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-panel-left-close preview-icon"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="m16 15-3-3 3-3"/></svg>
  `,
};

let isSidebarCollapsed = localStorage.getItem("sidebarCollapsed") === "true";

function updateSidebar(collapsed) {
  isSidebarCollapsed = collapsed;

  sidebar.classList.toggle("collapsed", collapsed);

  showSidebar.innerHTML = collapsed ? icons.collapsed : icons.expanded;

  showSidebar.setAttribute("aria-expanded", String(!collapsed));

  localStorage.setItem("sidebarCollapsed", String(collapsed));
}

function toggleSidebar() {
  updateSidebar(!isSidebarCollapsed);
}

showSidebar.addEventListener("click", toggleSidebar);

updateSidebar(isSidebarCollapsed);
