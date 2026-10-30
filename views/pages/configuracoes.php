<section class="page-section" id="page-configuracoes">
  <div class="page-heading">
    <div>
      <p class="eyebrow">SISTEMA</p>
      <h1>Configurações</h1>
      <p class="page-description">
        Informações gerais do estabelecimento.
      </p>
    </div>
  </div>
  <section class="panel settings-panel">
    <h2>Dados do hotel</h2>
    <p class="page-description">
      Estas informações são apenas locais neste protótipo.
    </p>
    <form id="settingsForm" class="form-grid">
      <label>Nome do hotel
        <input name="hotelName" placeholder="Nome do hotel" value="Hotelier" required /></label>
      <label>Numero de indentificação fiscal
        <input name="hotelNif" placeholder="Ex.: 00000000" required /></label>
      <label>Telefone<input name="phone" placeholder="+244 900 000 000" /></label>
      <label class="full-field">Endereço<input name="address" placeholder="Endereço do hotel" /></label>
      <div class="full-field">
        <button class="btn btn-primary" type="submit">
          Guardar alterações
        </button>
      </div>
    </form>
  </section>
</section>