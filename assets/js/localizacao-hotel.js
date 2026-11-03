"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const provincia = document.getElementById("provincia_id");
  const municipio = document.getElementById("municipio_id");

  if (!provincia || !municipio) return;

  provincia.addEventListener("change", async () => {
    const provinciaId = provincia.value;

    municipio.innerHTML = '<option value="">A carregar...</option>';
    municipio.disabled = true;

    try {
      const url = new URL(
        `${window.BASE_URL || ""}/api/municipios.php`,
        window.location.origin,
      );

      url.searchParams.set("provincia_id", provinciaId);
      url.searchParams.set(
        "hotel_id",
        new URLSearchParams(window.location.search).get("hotel_id"),
      );

      const response = await fetch(url);
      if (!response.ok) {
        throw new Error("Erro ao carregar municípios.");
      }

      const municipios = await response.json();

      municipio.innerHTML = '<option value="">Selecionar município</option>';

      municipios.forEach((item) => {
        const option = document.createElement("option");
        option.value = item.id;
        option.textContent = item.nome;
        municipio.appendChild(option);
      });
    } catch (error) {
      municipio.innerHTML =
        '<option value="">Não foi possível carregar</option>';
      console.error(error);
    } finally {
      municipio.disabled = false;
    }
  });
});
