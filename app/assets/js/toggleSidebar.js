const sidebar = document.querySelector("#sidebar");
const showSidebar = document.querySelector("#showSidebar");

const icons = {
  collapsed: `
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
      viewBox="0 0 24 24" fill="none">
      <path d="M21.97 15V9C21.97 4 19.97 2 14.97 2H8.97C3.97 2 1.97 4 1.97 9V15C1.97 20 3.97 22 8.97 22H14.97C19.97 22 21.97 20 21.97 15Z"
        stroke="currentColor" stroke-width="1.5"/>
      <path d="M14.97 2V22" stroke="currentColor" stroke-width="1.5"/>
      <path d="M7.97 9.44L10.53 12L7.97 14.56"
        stroke="currentColor" stroke-width="1.5"/>
    </svg>
  `,

  expanded: `
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
      viewBox="0 0 24 24" fill="none"
      stroke="currentColor" stroke-width="1.5"
      stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2"/>
      <path d="M7.25 10L5.5 12l1.75 2"/>
      <path d="M9.5 21V3"/>
    </svg>
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
