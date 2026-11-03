const menuToggle = document.querySelector("#menuToggle");
const navMenu = document.querySelector("#navMenu");

menuToggle.addEventListener("click", () => {
  const isActive = navMenu.classList.toggle("active");

  menuToggle.setAttribute("aria-expanded", isActive);
  menuToggle.textContent = isActive ? "✕" : "☰";
});

document.querySelectorAll("#navMenu a").forEach((link) => {
  link.addEventListener("click", () => {
    navMenu.classList.remove("active");

    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.textContent = "☰";
  });
});

const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("visible");
        observer.unobserve(entry.target);
      }
    });
  },
  {
    threshold: 0.1,
  },
);

document.querySelectorAll(".hotel-card, .service-card").forEach((card) => {
  card.style.opacity = "0";
  card.style.transform = "translateY(20px)";
  card.style.transition = "opacity .6s ease, transform .6s ease";

  observer.observe(card);
});

const style = document.createElement("style");

style.textContent = `
    .hotel-card.visible,
    .service-card.visible {
        opacity: 1 !important;
        transform: translateY(0) !important;
    }
`;

document.head.appendChild(style);
