<section class="page-section" id="page-restaurante">
  <div class="page-heading">
    <div>
      <p class="eyebrow">ALIMENTAÇÃO</p>
      <h1>Restaurante</h1>
      <p class="page-description">
        Gere o menu, os pratos e os preços.
      </p>
    </div>
    <button class="btn btn-primary" id="addProductBtn">
      ＋ Adicionar produto
    </button>
  </div>

  <div class="restaurant-banner">
    <div>
      <span class="restaurant-tag">HOTELIER · RESTAURANTE</span>
      <h2>Sabores que fazem a diferença.</h2>
      <p>Organiza os pratos e as bebidas disponíveis no teu hotel.</p>
    </div>
    <div class="restaurant-decoration">♨</div>
  </div>

  <div class="filter-bar">
    <input class="search-input" id="productSearch" placeholder="⌕  Procurar prato ou bebida..." />
    <select id="productCategoryFilter" aria-label="Filtrar categoria">
      <option value="todos">Todas as categorias</option>
      <option value="Prato">Pratos principais</option>
      <option value="Bebida">Bebidas</option>
      <option value="Sobremesa">Sobremesas</option>
    </select>
  </div>

  <div class="product-grid" id="productGrid"></div>

  <section class="panel orders-panel">
    <div class="panel-heading">
      <div>
        <h2>Pedidos recentes</h2>
        <p>Exemplo de acompanhamento dos pedidos.</p>
      </div>
    </div>
    <div id="ordersList" class="orders-list"></div>
  </section>
</section>