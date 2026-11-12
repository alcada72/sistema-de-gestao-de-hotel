<?php

// app/painel.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/models/Quarto.php';
require_once __DIR__ . '/models/Andar.php';
require_once __DIR__ . '/models/Categoria.php';
require_once __DIR__ . '/models/Reserva.php';
require_once __DIR__ . '/models/Imagem.php';
require_once __DIR__ . '/models/Servico.php';
require_once __DIR__ . '/models/Funcionario.php';
require_once __DIR__ . '/models/Hotel.php';
require_once __DIR__ . '/models/Localizacao.php';
require_once __DIR__ . '/models/RelatorioReservas.php';
require_once __DIR__ . '/models/RelatorioRestaurante.php';

requireLogin('cliente');

$usuario = getCurrentUser();
$hotel = requireHotelAccess(isset($_GET['hotel_id']) ? (int) $_GET['hotel_id'] : null);
$hotelSelecionado = $hotel;
$hotelId = (int) $hotel['id'];
$nomeHotel = $hotel['nome'];
$cargo = $hotel['cargo'];
$hoteis = getUserHotels();

$csrfToken = $_SESSION['csrf_token'] ?? '';
$actionUrl = BASE_URL . '/app/actions/painel.php?hotel_id=' . $hotelId;

$flash = $_SESSION['painel_flash'] ?? null;
unset($_SESSION['painel_flash']);

// ---------- Permissões (definidas uma única vez) ----------
$podeGerirHotel = in_array($cargo, ['proprietario', 'gerente'], true);
$podeGerirFuncionarios = $cargo === 'proprietario';
$podeGerirQuartos = $podeGerirHotel;
$podeGerirReservas = in_array($cargo, ['proprietario', 'gerente', 'recepcionista'], true);
$podeCriarReserva = $podeGerirReservas;
$podeGerirServicos = $podeGerirHotel;
$podeAcederRestaurante = in_array($cargo, ['proprietario', 'gerente', 'recepcionista', 'operador'], true);

// ---------- Dados ----------
$quartos = new Quarto($hotelId);
$andaresM = new Andar($hotelId);
$categoriaM = new Categoria($hotelId);
$reservas = new Reserva($hotelId);
$imagens = new Imagem($hotelId);
$servicosM = new Servico($hotelId);
$funcM = new Funcionario($hotelId);
$hotelM = new Hotel($hotelId);

// ---------- Relatório geral (resumo do mês) ----------
$relatorioDataInicio = $_GET['rel_inicio'] ?? date('Y-m-01');
$relatorioDataFim = $_GET['rel_fim'] ?? date('Y-m-d');

// Protege contra datas inválidas vindas da URL
try {
  $resumoReservas = (new RelatorioReservas($hotelId))->resumoPeriodo($relatorioDataInicio, $relatorioDataFim);
  $resumoRestaurante = (new RelatorioRestaurante($hotelId))->resumoPeriodo($relatorioDataInicio, $relatorioDataFim);
} catch (InvalidArgumentException $e) {
  $relatorioDataInicio = date('Y-m-01');
  $relatorioDataFim = date('Y-m-d');
  $resumoReservas = (new RelatorioReservas($hotelId))->resumoPeriodo($relatorioDataInicio, $relatorioDataFim);
  $resumoRestaurante = (new RelatorioRestaurante($hotelId))->resumoPeriodo($relatorioDataInicio, $relatorioDataFim);
}

// Quartos
$listaQuartos = $quartos->listar();
$quartosAtivos = $quartos->listarAtivos();
$estatisticasQuartos = $quartos->estatisticas();
$totalQuartos = $quartos->total();

// Andares
$listaAndares = $andaresM->listar();
$andares = $andaresM->listarSimples();

// Categorias
$listaCategorias = $categoriaM->listar();
$categorias = $categoriaM->listarAtivas();

// Reservas
$listaReservas = $reservas->listar();
$estatisticasReservas = $reservas->estatisticas();
$totalReservas = $reservas->total();

// Imagens
$imagensHotel = $imagens->listar();
$totalImagens = $imagens->total();

// Serviços
$servicos = $servicosM->listar();
$totalServicos = $servicosM->total();

// Funcionários
$listaFuncionarios = $funcM->listar();
$estatisticasFuncionarios = $funcM->estatisticas();

// Hotel e localização
$dadosHotel = $hotelM->buscar();
$provincias = Localizacao::provincias();
$municipios = Localizacao::municipios((int) $dadosHotel['provincia_id']);
?>

<?php require_once __DIR__ . '/views/layouts/head.php' ?>

<body>

  <div class="dashboard">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/views/layouts/menu.php'; ?>

    <!-- Conteúdo principal -->
    <main class="main-content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?>">
          <?= htmlspecialchars($flash['mensagem']) ?>
        </div>
      <?php endif; ?>

      <?php require_once __DIR__ . '/views/layouts/top_bar.php'; ?>
      <?php require_once __DIR__ . '/views/layouts/tab_menu.php'; ?>

      <section class="page-section active" id="page-dashboard">

        <div class="welcome">

          <div>
            <span class="eyebrow">ESTABELECIMENTO</span>

            <p>
              Aqui podes acompanhar e gerir as operações do teu hotel.
            </p>
          </div>

          <a class="btn-outline" target="_blank"
            href='<?= BASE_URL ?>/hotel?slug=<?= urlencode($hotelSelecionado['slug']) ?>'>
            Ver página pública ↗
          </a>

        </div>

        <!-- Estatísticas -->
        <div class="stats-grid">

          <div class="stat-card">
            <div class="stat-icon purple">▣</div>
            <span>Total de quartos</span>
            <strong><?= $totalQuartos ?></strong>
            <small>Quartos cadastrados</small>
          </div>

          <div class="stat-card">
            <div class="stat-icon blue">▤</div>
            <span>Total de reservas</span>
            <strong><?= $totalReservas ?></strong>
            <small>Reservas registadas</small>
          </div>

          <div class="stat-card">
            <div class="stat-icon orange">▧</div>
            <span>Fotografias</span>
            <strong><?= $totalImagens ?></strong>
            <small>Imagens do hotel</small>
          </div>

          <div class="stat-card">
            <div class="stat-icon green">✧</div>
            <span>Serviços</span>
            <strong><?= $totalServicos ?></strong>
            <small>Serviços cadastrados</small>
          </div>

        </div>


        <!-- Ações rápidas -->
        <section class="section-block">

          <div class="section-heading">
            <div>
              <h2>Ações rápidas</h2>
              <p>Acede rapidamente às principais operações.</p>
            </div>
          </div>

          <div class="actions-grid">

            <button class="action-card" data-page="quartos-adicionar">
              <span class="action-icon">＋</span>
              <strong>Adicionar quarto</strong>
              <small>Cadastrar um novo quarto</small>
            </button>

            <button class="action-card" data-page="imagens-adicionar">
              <span class="action-icon">▧</span>
              <strong>Adicionar imagens</strong>
              <small>Atualizar a galeria do hotel</small>
            </button>

            <button class="action-card" data-page="reserva-presencial">
              <span class="action-icon">♙</span>
              <strong>Nova reserva presencial</strong>
              <small>Registar um cliente na recepção</small>
            </button>

            <button class="action-card" data-page="consultar-reservas">
              <span class="action-icon">▤</span>
              <strong>Consultar reservas</strong>
              <small>Acompanhar as reservas do hotel</small>
            </button>

          </div>

        </section>


        <!-- Módulos -->
        <section class="section-block">

          <div class="section-heading">
            <div>
              <h2>Gestão do estabelecimento</h2>
              <p>Organiza os recursos e os serviços do teu hotel.</p>
            </div>
          </div>

          <div class="modules-grid">

            <button class="module-card" data-page="quartos">
              <span>🛏️</span>
              <div>
                <strong>Quartos</strong>
                <p>Preços, capacidade e disponibilidade.</p>
              </div>
            </button>

            <button class="module-card" data-page="andares">
              <span>🏢</span>
              <div>
                <strong>Andares</strong>
                <p>Organização dos pisos do estabelecimento.</p>
              </div>
            </button>

            <button class="module-card" data-page="servicos">
              <span>🏷️</span>
              <div>
                <strong>Serviços</strong>
                <p>Wi-Fi, piscina, estacionamento e outros.</p>
              </div>
            </button>

            <button class="module-card" data-page="funcionarios">
              <span>👥</span>
              <div>
                <strong>Funcionários</strong>
                <p>Gerir os acessos da equipa.</p>
              </div>
            </button>

          </div>

        </section>

      </section>


      <!-- =====================================
     QUARTOS
===================================== -->
      <section class="page-section" id="page-quartos">

        <div class="content-card">
          <div class="toolbar">
            <div>
              <h2>Todos os quartos</h2>
              <p>Lista de quartos do estabelecimento.</p>
            </div>

            <div class="toolbar-actions">
              <input type="search" class="form-control" placeholder="Pesquisar quarto...">

              <select class="form-control">
                <option value="">Todos os estados</option>
                <option>Disponível</option>
                <option>Ocupado</option>
                <option>Manutenção</option>
              </select>
            </div>
          </div>

          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Quarto</th>
                  <th>Categoria</th>
                  <th>Andar</th>
                  <th>Capacidade</th>
                  <th>Preço / noite</th>
                  <th>Estado</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>

                <?php if (empty($listaQuartos)): ?>

                  <tr>
                    <td colspan="7">Ainda não existem quartos registados.</td>
                  </tr>

                <?php else: ?>

                  <?php foreach ($listaQuartos as $quarto): ?>

                    <?php
                    $estadoClasses = [
                      'ativo' => 'success',
                      'manutencao' => 'warning',
                      'inativo' => 'danger'
                    ];

                    $estadoNomes = [
                      'ativo' => 'Ativo',
                      'manutencao' => 'Manutenção',
                      'inativo' => 'Inativo'
                    ];

                    $estado = $quarto['estado'];
                    ?>

                    <tr>

                      <td>
                        <strong>
                          <?= htmlspecialchars($quarto['numero']) ?>
                        </strong>
                      </td>

                      <td>
                        <?= htmlspecialchars($quarto['categoria']) ?>
                      </td>

                      <td>
                        <?= (int) $quarto['numero_andar'] ?>.º Andar
                      </td>

                      <td>
                        <?= (int) $quarto['capacidade'] ?> pessoas
                      </td>

                      <td>
                        <?= number_format(
                          (float) $quarto['preco_base'],
                          2,
                          ',',
                          '.'
                        ) ?> Kz
                      </td>

                      <td>
                        <span class="status-badge <?= $estadoClasses[$estado] ?? 'warning' ?>">
                          <?= $estadoNomes[$estado] ?? htmlspecialchars($estado) ?>
                        </span>
                      </td>

                      <td>
                        <button type="button" class="btn-icon" onclick="alert('ID do quarto: <?= (int) $quarto['id'] ?>')">
                          •••
                        </button>
                      </td>

                    </tr>

                  <?php endforeach; ?>

                <?php endif; ?>

              </tbody>
            </table>
          </div>
        </div>
      </section>


      <!-- =====================================
     ADICIONAR QUARTO
===================================== -->
      <section class="page-section" id="page-quartos-adicionar">

        <form class="content-card form-card" method="POST" action="<?= htmlspecialchars($actionUrl) ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="acao" value="adicionar_quarto">

          <div class="form-section">
            <h2>Informações do quarto</h2>
            <p>Identificação e localização do quarto.</p>

            <div class="form-grid">

              <div class="form-group">
                <label>Número do quarto *</label>
                <input class="form-control" type="text" name="numero" placeholder="Ex.: 101" maxlength="20" required>
              </div>

              <div class="form-group">
                <label>Andar *</label>
                <select class="form-control" name="andar_id" required>
                  <option value="">Selecionar andar</option>

                  <?php foreach ($andares as $andar): ?>
                    <option value="<?= (int) $andar['id'] ?>">
                      <?= (int) $andar['numero_andar'] ?>.º Andar
                    </option>
                  <?php endforeach; ?>

                </select>
              </div>

              <div class="form-group">
                <label>Categoria *</label>
                <select class="form-control" name="categoria_id" required>
                  <option value="">Selecionar categoria</option>

                  <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= (int) $categoria['id'] ?>">
                      <?= htmlspecialchars($categoria['nome']) ?>
                      — <?= number_format((float) $categoria['preco_base'], 2, ',', '.') ?> Kz
                    </option>
                  <?php endforeach; ?>

                </select>
              </div>

              <div class="form-group">
                <label>Estado inicial *</label>
                <select class="form-control" name="estado" required>
                  <option value="ativo">Ativo</option>
                  <option value="manutencao">Manutenção</option>
                  <option value="inativo">Inativo</option>
                </select>
              </div>

            </div>
          </div>

          <div class="form-section">
            <h2>Descrição adicional</h2>

            <div class="form-group">
              <label>Descrição</label>
              <textarea class="form-control" name="descricao" rows="4"
                placeholder="Descreve as características do quarto..."></textarea>
            </div>
          </div>

          <div class="form-footer">
            <button type="button" class="btn-secondary" data-page="quartos">
              Cancelar
            </button>

            <button type="submit" class="btn-primary">
              Guardar quarto
            </button>
          </div>
        </form>

      </section>


      <!-- =====================================
     ANDARES
===================================== -->
      <section class="page-section" id="page-andares">

        <div class="page-header">
          <div>
            <span class="eyebrow">ESTRUTURA</span>
            <h1>Andares</h1>
            <p>Organiza os pisos e acompanha a distribuição dos quartos.</p>
          </div>
          <button class="btn-primary" data-page="adicionar-andares">
            ＋ Adicionar andar
          </button>
        </div>

        <div class="floor-grid">

          <?php if (empty($listaAndares)): ?>

            <p>Este hotel ainda não possui andares registados.</p>

          <?php else: ?>

            <?php foreach ($listaAndares as $andar): ?>

              <article class="floor-card">

                <div class="floor-card-top">
                  <div class="floor-icon">🏢</div>
                  <span class="status-badge success">Registado</span>
                </div>

                <h3>
                  <?= (int) $andar['numero_andar'] ?>.º Andar
                </h3>

                <p>
                  <?= htmlspecialchars($andar['descricao'] ?? 'Sem descrição') ?>
                </p>

                <div class="floor-info">
                  <strong>
                    <?= (int) $andar['total_quartos'] ?> quartos
                  </strong>

                  <span>
                    <?= (int) $andar['quartos_ativos'] ?> ativos
                  </span>
                </div>

              </article>

            <?php endforeach; ?>

          <?php endif; ?>

        </div>
      </section>

      <section class="page-section" id="page-adicionar-andares">

        <div class="page-header">
          <div>
            <span class="eyebrow">ESTRUTURA</span>
            <h1>Adicionar andar</h1>
            <p>Preenche os dados necessários para registar um novo andar.</p>
          </div>
          <button class="btn-secondary" data-page="andares">← Voltar aos andares</button>
        </div>

        <form class="content-card form-card" method="POST" action="<?= htmlspecialchars($actionUrl) ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="acao" value="adicionar_andar">

          <div class="form-section">
            <h2>Informações do andar</h2>
            <p>Identificação e descrição do piso.</p>

            <div class="form-grid">

              <div class="form-group full-width">
                <label>Número do andar *</label>
                <input class="form-control" type="number" name="numero_andar" placeholder="Ex.: 1" min="-5" max="100"
                  required>
              </div>

              <div class="form-group full-width">
                <label>Descrição</label>
                <textarea class="form-control" name="descricao" rows="4"
                  placeholder="Descreve as características do andar..."></textarea>
              </div>

            </div>
          </div>

          <div class="form-footer">
            <button type="button" class="btn-secondary" data-page="andares">
              Cancelar
            </button>

            <button type="submit" class="btn-primary">
              Guardar andar
            </button>
          </div>
        </form>

      </section>


      <!-- =====================================
     CATEGORIAS
===================================== -->
      <section class="page-section" id="page-categorias">

        <div class="page-header">
          <div>
            <span class="eyebrow">ALOJAMENTO</span>
            <h1>Categorias de quartos</h1>
            <p>Define categorias, capacidade e preços base para os quartos.</p>
          </div>
          <button class="btn-primary" data-page="adicionar-categorias">＋ Nova categoria</button>
        </div>

        <div class="category-grid">

          <?php if (empty($listaCategorias)): ?>

            <p>Ainda não existem categorias de quartos.</p>

          <?php else: ?>

            <?php foreach ($listaCategorias as $categoria): ?>

              <article class="category-card">

                <div class="category-card-icon">🛏️</div>

                <span class="status-badge <?= $categoria['ativo'] ? 'success' : 'danger' ?>">
                  <?= $categoria['ativo'] ? 'Ativa' : 'Inativa' ?>
                </span>

                <h3>
                  <?= htmlspecialchars($categoria['nome']) ?>
                </h3>

                <div class="category-details">

                  <div>
                    <small>Capacidade</small>
                    <strong>
                      <?= (int) $categoria['capacidade'] ?> pessoas
                    </strong>
                  </div>

                  <div>
                    <small>Preço base</small>
                    <strong>
                      <?= number_format(
                        (float) $categoria['preco_base'],
                        2,
                        ',',
                        '.'
                      ) ?> Kz
                    </strong>
                  </div>

                  <div>
                    <small>Quartos</small>
                    <strong>
                      <?= (int) $categoria['total_quartos'] ?>
                    </strong>
                  </div>

                </div>

              </article>

            <?php endforeach; ?>

          <?php endif; ?>

        </div>
      </section>

      <section class="page-section" id="page-adicionar-categorias">

        <div class="page-header">
          <div>
            <span class="eyebrow">ALOJAMENTO</span>
            <h1>Adicionar categoria</h1>
            <p>Preenche os dados necessários para registar uma nova categoria de quarto.</p>
          </div>
          <button class="btn-secondary" data-page="categorias">← Voltar às categorias</button>
        </div>

        <form class="content-card form-card" method="POST" action="<?= htmlspecialchars($actionUrl) ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="acao" value="adicionar_categoria">

          <div class="form-section">
            <h2>Informações da categoria</h2>
            <p>Define o nome, capacidade e preço base do quarto.</p>

            <div class="form-grid">

              <div class="form-group full-width">
                <label>Nome da categoria *</label>
                <input class="form-control" type="text" name="nome" placeholder="Ex.: Standard, Deluxe..."
                  maxlength="50" required>
              </div>

              <div class="form-group">
                <label>Capacidade máxima *</label>
                <input class="form-control" type="number" name="capacidade" placeholder="Ex.: 2" min="1" max="20"
                  required>
              </div>

              <div class="form-group">
                <label>Preço base por noite (Kz) *</label>
                <input class="form-control" type="number" name="preco_base" placeholder="Ex.: 5000.00" min="0"
                  step="0.01" required>
              </div>

              <div class="form-group full-width">
                <label>Ativa</label>
                <select class="form-control" name="ativo">
                  <option value="1">Sim</option>
                  <option value="0">Não</option>
                </select>
              </div>

            </div>
          </div>

          <div class="form-footer">
            <button type="button" class="btn-secondary" data-page="categorias">
              Cancelar
            </button>

            <button type="submit" class="btn-primary">
              Guardar categoria
            </button>
          </div>
        </form>
      </section>

      <!-- =====================================
     RESERVAS
===================================== -->
      <section class="page-section" id="page-reservas">

        <div class="page-header">
          <div>
            <span class="eyebrow">OPERAÇÕES</span>
            <h1>Reservas</h1>
            <p>Controla as reservas, entradas e saídas dos hóspedes.</p>
          </div>
        </div>

        <div class="content-card">
          <div class="toolbar">
            <div>
              <h2>Lista de reservas</h2>
              <p>Consulta os dados das reservas registadas.</p>
            </div>
            <select class="form-control full-width " id="selectReserva">
              <option value="">Selecione Uma reserva </option>
              <?php foreach ($listaReservas as $reserva): ?>

                <option value="<?= $reserva['codigo'] ?>">
                  <?= $reserva['codigo'] ?> - <?= $reserva['nome_utilizador'] ?> - Quarto
                  <?= $reserva['numero_quarto'] ?>
                </option>
              <?php endforeach; ?>
            </select>

          </div>

          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Hóspede</th>
                  <th>Quarto</th>
                  <th>Entrada</th>
                  <th>Saída</th>
                  <th>Total</th>
                  <th>Estado</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody id="tbreserva">

              </tbody>
            </table>
          </div>
        </div>
      </section>


      <!-- =====================================
     RESERVA PRESENCIAL
===================================== -->
      <section class="page-section" id="page-reserva-presencial">

        <div class="page-header">
          <div>
            <span class="eyebrow">RECEPÇÃO</span>
            <h1>Nova reserva presencial</h1>
            <p>Regista uma reserva efetuada diretamente na recepção.</p>
          </div>
        </div>


        <form class="content-card form-card" method="POST" action="<?= htmlspecialchars($actionUrl) ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="acao" value="reserva_presencial">

          <div class="form-section">
            <h2>Dados do hóspede</h2>

            <div class="form-grid">

              <div class="form-group">
                <label>Nome completo *</label>
                <input class="form-control" type="text" name="nome_hospede" placeholder="Nome do hóspede"
                  maxlength="120" required>
              </div>

              <div class="form-group">
                <label>Telefone *</label>
                <input class="form-control" type="tel" name="telefone" placeholder="+244 9XX XXX XXX" maxlength="30"
                  required>
              </div>

              <div class="form-group">
                <label>Documento de identificação</label>
                <input class="form-control" type="text" name="documento" maxlength="50"
                  placeholder="Número do documento">
              </div>

            </div>
          </div>

          <div class="form-section">
            <h2>Detalhes da estadia</h2>

            <div class="form-grid">

              <div class="form-group">
                <label>Quarto *</label>
                <select class="form-control" name="quarto_id" required>
                  <option value="">Selecionar quarto</option>

                  <?php foreach ($quartosAtivos as $quarto): ?>
                    <?php
                    $categoriaQuarto = null;

                    foreach ($categorias as $categoria) {
                      if ((int) $categoria['id'] === (int) $quarto['categoria_id']) {
                        $categoriaQuarto = $categoria;
                        break;
                      }
                    }
                    ?>

                    <option value="<?= (int) $quarto['id'] ?>">
                      <?= htmlspecialchars($quarto['numero']) ?>
                      <?= $categoriaQuarto
                        ? ' - ' . htmlspecialchars($categoriaQuarto['nome'])
                        : '' ?>
                    </option>
                  <?php endforeach; ?>

                </select>
              </div>

              <div class="form-group">
                <label>Número de hóspedes *</label>
                <input class="form-control" type="number" name="quantidade_pessoas" min="1" value="1" required>
              </div>

              <div class="form-group">
                <label>Data de entrada *</label>
                <input class="form-control" type="date" name="data_entrada" min="<?= date('Y-m-d') ?>" required>
              </div>

              <div class="form-group">
                <label>Data de saída *</label>
                <input class="form-control" type="date" name="data_saida"
                  min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
              </div>

            </div>
          </div>

          <div class="form-section">
            <h2>Pagamento</h2>

            <div class="form-grid">

              <div class="form-group">
                <label>Valor recebido (Kz)</label>
                <input class="form-control" type="number" name="valor_recebido" min="0" step="0.01" value="0">
              </div>

              <div class="form-group">
                <label>Método de pagamento</label>
                <select class="form-control" name="metodo_pagamento">
                  <option value="">Selecionar método</option>
                  <option value="dinheiro">Dinheiro</option>
                  <option value="transferencia">Transferência bancária</option>
                  <option value="cartao">Cartão</option>
                  <option value="referencia">Referência</option>
                  <option value="multicaixa_express">Multicaixa Express</option>
                  <option value="outro">Outro</option>
                </select>
              </div>

            </div>

            <small>
              O valor total será calculado automaticamente no servidor,
              usando o preço base da categoria e o número de noites.
            </small>
          </div>

          <div class="form-section">
            <h2>Observações</h2>

            <div class="form-group">
              <textarea class="form-control" name="observacoes" rows="3"
                placeholder="Informações adicionais sobre a reserva..."></textarea>
            </div>
          </div>

          <div class="form-footer">
            <button type="button" class="btn-secondary" data-page="reservas">
              Cancelar
            </button>

            <button type="submit" class="btn-primary">
              Registar reserva
            </button>
          </div>
        </form>

      </section>

      <!-- =====================================
     CONSULTAR RESERVAS
     ===================================== -->
      <section class="page-section" id="page-consultar-reservas">
        <div class="page-header">
          <div>
            <span class="eyebrow">CONSULTAS</span>
            <h1>Consultar reservas</h1>
            <p>Pesquisa reservas por código, hóspede, período ou estado.</p>
          </div>
          <button class="btn-secondary" data-page="reservas">Ver todas as reservas</button>
        </div>

        <div class="content-card">

          <form class="form-section" id="formFilterReservas">
            <h2>Filtros de pesquisa</h2>

            <div class="form-grid">
              <div class="form-group">
                <label for="codigoReserva">Código da reserva</label>
                <input id="codigoReserva" class="form-control" type="search" placeholder="Ex.: RES-001">
              </div>

              <div class="form-group">
                <label for="nomeHospede">Nome do hóspede</label>
                <input id="nomeHospede" class="form-control" type="text" placeholder="Nome do hóspede">
              </div>

              <div class="form-group">
                <label for="dataEntrada">Data de entrada</label>
                <input id="dataEntrada" class="form-control" type="date">
              </div>

              <div class="form-group">
                <label for="estadoReserva">Estado</label>
                <select id="estadoReserva" class="form-control">
                  <option value="">Todos os estados</option>
                  <option value="pendente">Pendente</option>
                  <option value="confirmada">Confirmada</option>
                  <option value="checkin">Check-in</option>
                  <option value="concluida">Concluída</option>
                  <option value="cancelada">Cancelada</option>
                </select>
              </div>
            </div>

            <div class="filter-actions">
              <button type="reset" class="btn-secondary">
                Limpar filtros
              </button>

              <button type="submit" class="btn-primary">
                ⌕ Buscar reservas
              </button>
            </div>
          </form>

        </div>

        <div class="content-card">
          <div class="toolbar">
            <div>
              <h2>Resultados</h2>
              <p>Reservas correspondentes à pesquisa.</p>
            </div>
            <span class="result-count" id="resultCount">0 resultados</span>

          </div>

          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Hóspede</th>
                  <th>Quarto</th>
                  <th>Entrada</th>
                  <th>Saída</th>
                  <th>Total</th>
                  <th>Estado</th>
                  <th>Ações</th>
                </tr>
              </thead>

              <tbody id="tbodyConsultarReservas">
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- =====================================
     FOTOGRAFIAS
       ===================================== -->
      <section class="page-section" id="page-imagens">
        <div class="page-header">
          <div>
            <span class="eyebrow">APRESENTAÇÃO</span>
            <h1>Fotografias</h1>
            <p>Organiza e gere as imagens utilizadas na página pública do hotel.</p>
          </div>

        </div>

        <div class="content-card">
          <div class="toolbar">
            <div>
              <h2>Galeria do estabelecimento</h2>
              <p>Organiza as imagens por tipo.</p>
            </div>
            <select class="form-control" hidden>
              <option>Todas as imagens</option>
              <option>Principal</option>
              <option>Galeria</option>
              <option>Interior</option>
              <option>Exterior</option>
            </select>
          </div>

          <div class="gallery-grid">
            <?php foreach ($imagensHotel as $imagem): ?>
              <div class="gallery-item">
                <a target="_blank" href="<?= BASE_URL ?>/hotel/photo?hotel_id=<?= $hotelId ?>?image=<?= $imagem['id'] ?>">
                  <div class="gallery-placeholder">
                    <img src="<?= htmlspecialchars($imagem['url']) ?>" alt="Imagem do hotel">
                  </div>
                </a>
                <div class="gallery-caption">
                  <div><strong><?= htmlspecialchars($imagem['tipo']) ?></strong><small>Imagem carregada</small></div>
                  <form method="POST" action="<?= htmlspecialchars($actionUrl) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="acao" value="deletar_imagens">
                    <input type="text" id="<?= $imagem['id'] ?>" hidden name="id" value="<?= $imagem['id'] ?>">
                    <button class="delete btn-icon" type="submit">❌</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>

          </div>
        </div>
      </section>

      <!-- =====================================
     ADICIONAR FOTOGRAFIAS
===================================== -->
      <section class="page-section" id="page-imagens-adicionar">
        <div class="page-header">
          <div>
            <span class="eyebrow">GALERIA</span>
            <h1>Adicionar fotografias</h1>
            <p>Carrega imagens para apresentar o teu estabelecimento.</p>
          </div>
        </div>

        <form class="content-card form-card" method="POST" action="<?= htmlspecialchars($actionUrl) ?>"
          enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="acao" value="adicionar_imagens">

          <div class="form-section">
            <h2>Carregar imagens</h2>
            <p>Seleciona as fotografias que pretendes adicionar.</p>

            <label class="upload-zone">
              <input type="file" name="imagens[]" accept=".jpg,.jpeg,.png,.webp" multiple required hidden>

              <span class="upload-icon">↑</span>
              <strong>Selecionar fotografias</strong>
              <small>Clica para escolher imagens do teu dispositivo.</small>
              <small>JPG, PNG e WebP — máximo de 5 MB por imagem.</small>
            </label>
          </div>

          <div class="form-section">
            <h2>Classificação</h2>

            <div class="form-grid">
              <div class="form-group">
                <label for="tipo">Tipo de imagem</label>

                <select class="form-control" id="tipo" name="tipo" required>
                  <option value="galeria">Galeria</option>
                  <option value="principal">Imagem principal</option>
                  <option value="interior">Interior</option>
                  <option value="exterior">Exterior</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-footer">
            <button type="button" class="btn-secondary" data-page="imagens">
              Cancelar
            </button>

            <button type="submit" class="btn-primary">
              Guardar fotografias
            </button>
          </div>
        </form>

      </section>

      <!-- =====================================
     SERVIÇOS
===================================== -->
      <section class="page-section" id="page-servicos">

        <div class="page-header">
          <div>
            <span class="eyebrow">COMODIDADES</span>
            <h1>Serviços</h1>
            <p>Apresenta os serviços e comodidades disponíveis no estabelecimento.</p>
          </div>

          <?php if ($podeGerirHotel): ?>
            <button class="btn-primary" data-page="adicionar-servico">
              ＋ Adicionar serviço
            </button>
          <?php endif; ?>
        </div>

        <div class="service-grid">

          <?php if (empty($servicos)): ?>

            <div class="content-card">
              <h3>Nenhum serviço registado</h3>
              <p>Ainda não existem serviços cadastrados neste hotel.</p>
            </div>

          <?php else: ?>

            <?php foreach ($servicos as $servico): ?>

              <?php
              $icone = match ($servico['nome']) {
                'Wi-Fi' => '📶',
                'Piscina' => '🏊',
                'Estacionamento' => '🚗',
                'Lavandaria' => '👕',
                'Restaurante' => '🍽️',
                'Ginásio' => '🏋️',
                default => '✨'
              };
              ?>

              <article class="service-card">
                <div class="servicos-icon-content">

                  <div class="service-icon">
                    <?= $icone ?>
                  </div>

                  <div class="service-accoes">
                    <form method="POST" action="<?= htmlspecialchars($actionUrl) ?>">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                      <input type="hidden" name="acao" value="deletar_servico">
                      <input type="text" id="<?= $servico['id'] ?>" hidden name="id" value="<?= $servico['id'] ?>">
                      <button class="delete " type="submit">Deletar</button>
                    </form>
                  </div>
                </div>

                <div class="service-card-content">

                  <span class="status-badge <?= $servico['disponivel'] ? 'success' : 'danger' ?>">
                    <?= $servico['disponivel'] ? 'Ativo' : 'Indisponível' ?>
                  </span>

                  <h3>
                    <?= htmlspecialchars($servico['nome'], ENT_QUOTES, 'UTF-8') ?>
                  </h3>

                  <p>
                    <?= htmlspecialchars(
                      $servico['descricao'] ?: 'Sem descrição disponível.',
                      ENT_QUOTES,
                      'UTF-8'
                    ) ?>
                  </p>

                  <div class="service-price">
                    <?php if ($servico['tipo'] === 'gratuito'): ?>

                      <strong>Gratuito</strong>

                    <?php else: ?>

                      <strong>
                        <?= number_format((float) $servico['preco'], 2, ',', '.') ?>
                        Kz
                      </strong>

                    <?php endif; ?>
                  </div>

                </div>

              </article>

            <?php endforeach; ?>

          <?php endif; ?>

        </div>
      </section>

      <!-- =====================================
     ADICIONAR SERVIÇO
===================================== -->
      <section class="page-section" id="page-adicionar-servico">

        <div class="page-header">
          <div>
            <span class="eyebrow">COMODIDADES</span>
            <h1>Adicionar serviço</h1>
            <p>Regista um novo serviço disponibilizado pelo hotel.</p>
          </div>
        </div>

        <form class="content-card form-card" method="POST"
          action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

          <!-- Corrigido: adicionar_servico -->
          <input type="hidden" name="acao" value="adicionar_servico">

          <div class="form-section">

            <h2>Informações do serviço</h2>

            <p>Preenche os dados necessários para registar um novo serviço.</p>

            <div class="form-grid">

              <div class="form-group">
                <label>Nome do serviço *</label>

                <input class="form-control" type="text" name="nome" placeholder="Ex.: Wi-Fi gratuito" maxlength="120"
                  required>
              </div>

              <div class="form-group">
                <label>Tipo de serviço *</label>

                <select class="form-control" name="tipo" required>
                  <option value="gratuito">Gratuito</option>
                  <option value="pago">Pago</option>
                </select>
              </div>

              <div class="form-group">
                <label>Preço do serviço (Kz) *</label>

                <input class="form-control" type="number" name="preco" placeholder="Ex.: 5000" min="0" step="0.01">
              </div>

              <div class="form-group">
                <label>Descrição</label>

                <textarea class="form-control" name="descricao" rows="4"
                  placeholder="Descreve as características do serviço..."></textarea>
              </div>

            </div>

            <div class="form-footer">

              <button type="reset" class="btn-secondary">
                Limpar
              </button>

              <button type="submit" class="btn-primary">
                Guardar serviço
              </button>

            </div>

          </div>

        </form>

      </section>


      <!-- =====================================
     RESTAURANTE
===================================== -->
      <section class="page-section" id="page-restaurante">

        <div class="page-header">
          <div>
            <span class="eyebrow">RESTAURAÇÃO</span>
            <h1>Restaurante</h1>
            <p>Gere os produtos, categorias e pedidos do restaurante.</p>
          </div>
          <button class="btn-primary">＋ Novo produto</button>
        </div>

        <div class="content-card">
          <div class="toolbar">
            <div>
              <h2>Pedidos recentes</h2>
              <p>Acompanha o estado dos pedidos do restaurante.</p>
            </div>
            <select class="form-control">
              <option>Todos os pedidos</option>
              <option>Pendente</option>
              <option>Em preparação</option>
              <option>Concluído</option>
              <option>Cancelado</option>
            </select>
          </div>

          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Pedido</th>
                  <th>Cliente</th>
                  <th>Data</th>
                  <th>Total</th>
                  <th>Estado</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>#PED-001</td>
                  <td>João Manuel</td>
                  <td>02/11/2026</td>
                  <td>12.500 Kz</td>
                  <td><span class="status-badge warning">Pendente</span></td>
                  <td><button class="btn-icon">•••</button></td>
                </tr>
                <tr>
                  <td>#PED-002</td>
                  <td>Maria José</td>
                  <td>02/11/2026</td>
                  <td>18.000 Kz</td>
                  <td><span class="status-badge info">Preparação</span></td>
                  <td><button class="btn-icon">•••</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>


      <!-- =====================================
     FUNCIONÁRIOS
===================================== -->
      <section class="page-section" id="page-funcionarios">

        <div class="page-header">
          <div>
            <span class="eyebrow">EQUIPA</span>
            <h1>Funcionários</h1>
            <p>Gere os utilizadores associados ao estabelecimento e os respetivos cargos.</p>
          </div>
          <button class="btn-primary" data-page="adicionar-funcionario">＋ Adicionar funcionário</button>
        </div>

        <div class="content-card">
          <div class="toolbar">
            <div>
              <h2>Equipa do hotel</h2>
              <p>Consulta os membros e os respetivos níveis de acesso.</p>
            </div>
            <div class="toolbar-actions">
              <input type="search" class="form-control" placeholder="Pesquisar funcionário...">
              <select class="form-control">
                <option>Todos os cargos</option>
                <option>Proprietário</option>
                <option>Gerente</option>
                <option>Recepcionista</option>
                <option>Operador</option>
              </select>
            </div>
          </div>

          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Funcionário</th>
                  <th>Email</th>
                  <th>Cargo</th>
                  <th>Estado</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>

                <?php if (empty($listaFuncionarios)): ?>

                  <tr>
                    <td colspan="5">Ainda não existem funcionários associados.</td>
                  </tr>

                <?php else: ?>

                  <?php foreach ($listaFuncionarios as $funcionario): ?>

                    <tr>

                      <td>
                        <strong>
                          <?= htmlspecialchars($funcionario['nome']) ?>
                        </strong>
                      </td>

                      <td>
                        <?= htmlspecialchars($funcionario['email']) ?>
                      </td>

                      <td>
                        <?= htmlspecialchars(ucfirst($funcionario['cargo'])) ?>
                      </td>

                      <td>
                        <span class="status-badge <?= $funcionario['ativo'] ? 'success' : 'danger' ?>">
                          <?= $funcionario['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                      </td>

                      <td>
                        <button type="button" class="btn-icon"
                          onclick="alert('Utilizador: <?= (int) $funcionario['usuario_id'] ?>')">
                          •••
                        </button>
                      </td>

                    </tr>

                  <?php endforeach; ?>

                <?php endif; ?>

              </tbody>
            </table>
          </div>
        </div>
      </section>


      <!-- =====================================
     ADICIONAR FUNCIONÁRIOS
===================================== -->
      <section class="page-section" id="page-adicionar-funcionario">

        <div class="page-header">
          <div>
            <span class="eyebrow">EQUIPA</span>
            <h1>Adicionar funcionário</h1>
            <p>Regista um novo funcionário para este hotel.</p>
          </div>
        </div>

        <form class="content-card form-card" method="POST"
          action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="acao" value="adicionar_funcionario">

          <div class="form-section">

            <h2>Informações do funcionário</h2>

            <p>Preenche os dados necessários para registar um novo membro da equipa.</p>

            <div class="form-grid">

              <div class="form-group">
                <label for="nome">Nome do funcionário *</label>

                <input class="form-control" type="text" id="nome" name="nome" placeholder="Ex.: João Manuel"
                  maxlength="120" autocomplete="name" required>
              </div>

              <div class="form-group">
                <label for="email">E-mail *</label>

                <input class="form-control" type="email" id="email" name="email" placeholder="Ex.: exemplo@gmail.com"
                  maxlength="150" autocomplete="email" required>
              </div>

              <div class="form-group">
                <label for="telefone">Telefone *</label>

                <input class="form-control" type="tel" id="telefone" name="telefone" placeholder="Ex.: 923000000"
                  maxlength="16" autocomplete="tel" required>
              </div>

              <div class="form-group">
                <label for="cargo">Cargo do funcionário *</label>

                <select class="form-control" id="cargo" name="cargo" required>
                  <option value="recepcionista">Recepcionista</option>
                  <option value="operador">Operador</option>
                  <option value="gerente">Gerente</option>
                </select>
              </div>

            </div>

            <div class="form-footer">

              <button type="reset" class="btn-secondary">
                Limpar formulário
              </button>

              <button type="submit" class="btn-primary">
                Salvar funcionário
              </button>

            </div>

          </div>

        </form>

      </section>


      <!-- =====================================
     DADOS DO HOTEL
===================================== -->
      <section class="page-section" id="page-hotel">

        <div class="page-header">
          <div>
            <span class="eyebrow">CONFIGURAÇÕES</span>
            <h1>Dados do hotel</h1>
            <p>Atualiza as informações que identificam o teu estabelecimento.</p>
          </div>
          <span class="status-badge success">Estabelecimento ativo</span>
        </div>


        <form class="content-card form-card" method="POST" action="<?= htmlspecialchars($actionUrl) ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="acao" value="atualizar_hotel">

          <div class="form-section">
            <h2>Informações gerais</h2>
            <p>Dados de identificação e apresentação pública.</p>

            <div class="form-grid">

              <div class="form-group">
                <label>Nome do hotel *</label>
                <input class="form-control" type="text" name="nome" maxlength="150"
                  value="<?= htmlspecialchars($dadosHotel['nome']) ?>" required>
              </div>

              <div class="form-group">
                <label>NIF do hotel *</label>
                <input class="form-control" disabled type="text" maxlength="150"
                  value="<?= htmlspecialchars($dadosHotel['nif']) ?>" required>
              </div>

              <div class="form-group">
                <label>Categoria</label>
                <select class="form-control" name="categoria">
                  <?php
                  $categoriasHotel = [
                    'sem_classificacao' => 'Sem classificação',
                    '1_estrela' => '1 estrela',
                    '2_estrelas' => '2 estrelas',
                    '3_estrelas' => '3 estrelas',
                    '4_estrelas' => '4 estrelas',
                    '5_estrelas' => '5 estrelas'
                  ];
                  ?>

                  <?php foreach ($categoriasHotel as $valor => $rotulo): ?>
                    <option value="<?= htmlspecialchars($valor) ?>" <?= $dadosHotel['categoria'] === $valor ? 'selected' : '' ?>>
                      <?= htmlspecialchars($rotulo) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group full-width">
                <label>Descrição</label>
                <textarea class="form-control" name="descricao"
                  rows="4"><?= htmlspecialchars($dadosHotel['descricao'] ?? '') ?></textarea>
              </div>

            </div>
          </div>

          <div class="form-section">
            <h2>Contactos</h2>

            <div class="form-grid">

              <div class="form-group">
                <label>Telefone</label>
                <input class="form-control" type="tel" name="telefone"
                  value="<?= htmlspecialchars($dadosHotel['telefone'] ?? '') ?>">
              </div>

              <div class="form-group">
                <label>Telefone secundário</label>
                <input class="form-control" type="tel" name="telefone_secundario"
                  value="<?= htmlspecialchars($dadosHotel['telefone_secundario'] ?? '') ?>">
              </div>

              <div class="form-group">
                <label>Email</label>
                <input class="form-control" type="email" name="email"
                  value="<?= htmlspecialchars($dadosHotel['email'] ?? '') ?>">
              </div>

            </div>
          </div>

          <div class="form-section">
            <h2>Localização</h2>

            <div class="form-grid">

              <div class="form-group">
                <label>Província *</label>

                <select class="form-control" name="provincia_id" id="provincia_id" required>
                  <?php foreach ($provincias as $provincia): ?>
                    <option value="<?= (int) $provincia['id'] ?>" <?= (int) $dadosHotel['provincia_id'] === (int) $provincia['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($provincia['nome']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Município *</label>

                <select class="form-control" name="municipio_id" id="municipio_id" required>
                  <?php foreach ($municipios as $municipio): ?>
                    <option value="<?= (int) $municipio['id'] ?>" <?= (int) $dadosHotel['municipio_id'] === (int) $municipio['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($municipio['nome']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group full-width">
                <label>Endereço *</label>
                <input class="form-control" type="text" name="endereco"
                  value="<?= htmlspecialchars($dadosHotel['endereco']) ?>" required>
              </div>

            </div>
          </div>

          <div class="form-footer">
            <button type="reset" class="btn-secondary">
              Repor alterações
            </button>

            <button type="submit" class="btn-primary">
              Guardar alterações
            </button>
          </div>
        </form>

      </section>

      <!-- =====================================
   RELATÓRIOS — MENU
===================================== -->
      <section class="page-section" id="page-relatorio">

        <?php if ($podeGerirHotel): ?>

          <div class="page-header">
            <div>
              <span class="eyebrow">GESTÃO</span>
              <h1>Relatório geral</h1>
              <p>Resumo do desempenho do hotel no período selecionado.</p>
            </div>
          </div>

          <!-- Filtro de período -->
          <div class="content-card">
            <form class="form-section" method="GET">
              <input type="hidden" name="hotel_id" value="<?= (int) $hotelId ?>">

              <h2>Período</h2>

              <div class="form-grid">

                <div class="form-group">
                  <label for="rel_inicio">Data inicial</label>
                  <input class="form-control" type="date" id="rel_inicio" name="rel_inicio"
                    value="<?= htmlspecialchars($relatorioDataInicio) ?>">
                </div>

                <div class="form-group">
                  <label for="rel_fim">Data final</label>
                  <input class="form-control" type="date" id="rel_fim" name="rel_fim"
                    value="<?= htmlspecialchars($relatorioDataFim) ?>">
                </div>

              </div>

              <div class="form-footer">
                <button type="submit" class="btn-primary">Atualizar</button>
              </div>
            </form>
          </div>

          <!-- KPIs de reservas -->
          <div class="section-heading">
            <div>
              <h2>Reservas</h2>
              <p><?= htmlspecialchars(date('d/m/Y', strtotime($relatorioDataInicio))) ?>
                a <?= htmlspecialchars(date('d/m/Y', strtotime($relatorioDataFim))) ?></p>
            </div>
          </div>

          <div class="stats-grid">

            <div class="stat-card">
              <div class="stat-icon blue">▤</div>
              <span>Total de reservas</span>
              <strong><?= (int) $resumoReservas['total_reservas'] ?></strong>
              <small><?= (int) $resumoReservas['total_canceladas'] ?> canceladas</small>
            </div>

            <div class="stat-card">
              <div class="stat-icon purple">🛎️</div>
              <span>Check-ins no período</span>
              <strong><?= (int) $resumoReservas['total_checkins'] ?></strong>
              <small>Entradas registadas</small>
            </div>

            <div class="stat-card">
              <div class="stat-icon orange">🧳</div>
              <span>Check-outs no período</span>
              <strong><?= (int) $resumoReservas['total_checkouts'] ?></strong>
              <small>Saídas registadas</small>
            </div>

            <div class="stat-card">
              <div class="stat-icon green">✧</div>
              <span>Taxa de ocupação</span>
              <strong><?= $resumoReservas['taxa_ocupacao'] ?>%</strong>
              <small><?= (int) $resumoReservas['total_noites'] ?> noites vendidas</small>
            </div>

            <div class="stat-card">
              <div class="stat-icon blue">Kz</div>
              <span>Receita de reservas</span>
              <strong><?= number_format((float) $resumoReservas['receita_total'], 2, ',', '.') ?></strong>
              <small>Total do período</small>
            </div>

          </div>

          <!-- KPIs de restaurante -->
          <div class="section-heading">
            <div>
              <h2>Restaurante</h2>
              <p>Pedidos de mesa, quarto e balcão no mesmo período.</p>
            </div>
          </div>

          <div class="stats-grid">

            <div class="stat-card">
              <div class="stat-icon purple">🍽️</div>
              <span>Total de pedidos</span>
              <strong><?= (int) $resumoRestaurante['total_pedidos'] ?></strong>
              <small><?= (int) $resumoRestaurante['total_cancelados'] ?> cancelados</small>
            </div>

            <div class="stat-card">
              <div class="stat-icon green">✓</div>
              <span>Pedidos entregues</span>
              <strong><?= (int) $resumoRestaurante['total_entregues'] ?></strong>
              <small><?= (int) $resumoRestaurante['total_pendentes'] + (int) $resumoRestaurante['total_preparacao'] + (int) $resumoRestaurante['total_prontos'] ?>
                em curso</small>
            </div>

            <div class="stat-card">
              <div class="stat-icon orange">Kz</div>
              <span>Receita do restaurante</span>
              <strong><?= number_format((float) $resumoRestaurante['receita_total'], 2, ',', '.') ?></strong>
              <small>Ticket médio: <?= number_format((float) $resumoRestaurante['ticket_medio'], 2, ',', '.') ?>
                Kz</small>
            </div>

            <div class="stat-card">
              <div class="stat-icon blue">▦</div>
              <span>Mesa / Quarto / Balcão</span>
              <strong><?= (int) $resumoRestaurante['total_mesa'] ?> / <?= (int) $resumoRestaurante['total_quarto'] ?> /
                <?= (int) $resumoRestaurante['total_balcao'] ?></strong>
              <small>Pedidos por tipo</small>
            </div>

          </div>

          <!-- Atalhos para os relatórios detalhados/exportáveis -->
          <div class="section-heading">
            <div>
              <h2>Relatórios detalhados</h2>
              <p>Exporta em Excel ou PDF, com filtros próprios de cada um.</p>
            </div>
          </div>

          <div class="modules-grid">

            <button class="module-card" data-page="relatorio-checkins">
              <span>🛎️</span>
              <div>
                <strong>Check-ins</strong>
                <p>Reservas com entrada no período.</p>
              </div>
            </button>

            <button class="module-card" data-page="relatorio-checkouts">
              <span>🧳</span>
              <div>
                <strong>Check-outs</strong>
                <p>Reservas com saída no período.</p>
              </div>
            </button>

            <button class="module-card" data-page="relatorio-movimento">
              <span>📋</span>
              <div>
                <strong>Movimento de reservas</strong>
                <p>Check-ins e check-outs juntos.</p>
              </div>
            </button>

            <button class="module-card" data-page="relatorio-restaurante">
              <span>🍽️</span>
              <div>
                <strong>Restaurante</strong>
                <p>Pedidos de mesa, quarto e balcão.</p>
              </div>
            </button>

          </div>

        <?php else: ?>

          <div class="content-card">
            <h3>Sem permissão</h3>
            <p>Apenas proprietários e gerentes podem aceder aos relatórios.</p>
          </div>

        <?php endif; ?>

      </section>

      <!-- =====================================
   RELATÓRIO — FINANCEIRO
===================================== -->
      <section class="page-section" id="page-relatorio-financeiro">

        <div class="page-header">
          <div>
            <span class="eyebrow">RELATÓRIOS</span>
            <h1>Relatório financeiro</h1>
            <p>Receita diária de quartos e restaurante, consolidada.</p>
          </div>
          <button class="btn-secondary" data-page="relatorios">← Voltar aos relatórios</button>
        </div>

        <div class="content-card">
          <form class="form-section" method="GET" action="<?= BASE_URL ?>/app/actions/relatorios.php" target="_blank">
            <input type="hidden" name="hotel_id" value="<?= (int) $hotelId ?>">
            <input type="hidden" name="tipo" value="financeiro">

            <h2>Período</h2>

            <div class="form-grid">

              <div class="form-group">
                <label for="financeiro_data_inicio">Data inicial *</label>
                <input class="form-control" type="date" id="financeiro_data_inicio" name="data_inicio"
                  value="<?= date('Y-m-01') ?>" required>
              </div>

              <div class="form-group">
                <label for="financeiro_data_fim">Data final *</label>
                <input class="form-control" type="date" id="financeiro_data_fim" name="data_fim"
                  value="<?= date('Y-m-d') ?>" required>
              </div>

            </div>

            <div class="form-footer">
              <button type="submit" class="btn-secondary" name="formato" value="excel">⤓ Exportar Excel</button>
              <button type="submit" class="btn-primary" name="formato" value="pdf">⤓ Exportar PDF</button>
            </div>
          </form>
        </div>

      </section>

      <?php if ($podeGerirHotel): ?>

        <!-- =====================================
     RELATÓRIO — CHECK-INS
  ===================================== -->
        <section class="page-section" id="page-relatorio-checkins">

          <div class="page-header">
            <div>
              <span class="eyebrow">RELATÓRIOS</span>
              <h1>Relatório de check-ins</h1>
              <p>Reservas cuja entrada cai dentro do período escolhido.</p>
            </div>
            <button class="btn-secondary" data-page="relatorios">← Voltar aos relatórios</button>
          </div>

          <div class="content-card">
            <form class="form-section" method="GET" action="<?= BASE_URL ?>/app/actions/relatorios.php" target="_blank">
              <input type="hidden" name="hotel_id" value="<?= (int) $hotelId ?>">
              <input type="hidden" name="tipo" value="checkins">

              <h2>Filtros</h2>

              <div class="form-grid">

                <div class="form-group">
                  <label for="checkins_estado">Estado (opcional)</label>
                  <select class="form-control" id="checkins_estado" name="estado">
                    <option value="">Todos os estados</option>
                    <option value="pendente">Pendente</option>
                    <option value="confirmada">Confirmada</option>
                    <option value="checkin">Check-in</option>
                    <option value="concluida">Concluída</option>
                    <option value="cancelada">Cancelada</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="checkins_data_inicio">Data inicial *</label>
                  <input class="form-control" type="date" id="checkins_data_inicio" name="data_inicio"
                    value="<?= date('Y-m-01') ?>" required>
                </div>

                <div class="form-group">
                  <label for="checkins_data_fim">Data final *</label>
                  <input class="form-control" type="date" id="checkins_data_fim" name="data_fim"
                    value="<?= date('Y-m-d') ?>" required>
                </div>

              </div>

              <div class="form-footer">
                <button type="submit" class="btn-secondary" name="formato" value="excel">⤓ Exportar Excel</button>
                <button type="submit" class="btn-primary" name="formato" value="pdf">⤓ Exportar PDF</button>
              </div>
            </form>
          </div>

        </section>


        <!-- =====================================
     RELATÓRIO — CHECK-OUTS
  ===================================== -->
        <section class="page-section" id="page-relatorio-checkouts">

          <div class="page-header">
            <div>
              <span class="eyebrow">RELATÓRIOS</span>
              <h1>Relatório de check-outs</h1>
              <p>Reservas cuja saída cai dentro do período escolhido.</p>
            </div>
            <button class="btn-secondary" data-page="relatorios">← Voltar aos relatórios</button>
          </div>

          <div class="content-card">
            <form class="form-section" method="GET" action="<?= BASE_URL ?>/app/actions/relatorios.php" target="_blank">
              <input type="hidden" name="hotel_id" value="<?= (int) $hotelId ?>">
              <input type="hidden" name="tipo" value="checkouts">

              <h2>Filtros</h2>

              <div class="form-grid">

                <div class="form-group">
                  <label for="checkouts_estado">Estado (opcional)</label>
                  <select class="form-control" id="checkouts_estado" name="estado">
                    <option value="">Todos os estados</option>
                    <option value="pendente">Pendente</option>
                    <option value="confirmada">Confirmada</option>
                    <option value="checkin">Check-in</option>
                    <option value="concluida">Concluída</option>
                    <option value="cancelada">Cancelada</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="checkouts_data_inicio">Data inicial *</label>
                  <input class="form-control" type="date" id="checkouts_data_inicio" name="data_inicio"
                    value="<?= date('Y-m-01') ?>" required>
                </div>

                <div class="form-group">
                  <label for="checkouts_data_fim">Data final *</label>
                  <input class="form-control" type="date" id="checkouts_data_fim" name="data_fim"
                    value="<?= date('Y-m-d') ?>" required>
                </div>

              </div>

              <div class="form-footer">
                <button type="submit" class="btn-secondary" name="formato" value="excel">⤓ Exportar Excel</button>
                <button type="submit" class="btn-primary" name="formato" value="pdf">⤓ Exportar PDF</button>
              </div>
            </form>
          </div>

        </section>


        <!-- =====================================
     RELATÓRIO — MOVIMENTO DE RESERVAS
  ===================================== -->
        <section class="page-section" id="page-relatorio-movimento">

          <div class="page-header">
            <div>
              <span class="eyebrow">RELATÓRIOS</span>
              <h1>Movimento de reservas</h1>
              <p>Check-ins e check-outs do período, num único relatório.</p>
            </div>
            <button class="btn-secondary" data-page="relatorios">← Voltar aos relatórios</button>
          </div>

          <div class="content-card">
            <form class="form-section" method="GET" action="<?= BASE_URL ?>/app/actions/relatorios.php" target="_blank">
              <input type="hidden" name="hotel_id" value="<?= (int) $hotelId ?>">
              <input type="hidden" name="tipo" value="movimento">

              <h2>Filtros</h2>

              <div class="form-grid">

                <div class="form-group">
                  <label for="movimento_estado">Estado (opcional)</label>
                  <select class="form-control" id="movimento_estado" name="estado">
                    <option value="">Todos os estados</option>
                    <option value="pendente">Pendente</option>
                    <option value="confirmada">Confirmada</option>
                    <option value="checkin">Check-in</option>
                    <option value="concluida">Concluída</option>
                    <option value="cancelada">Cancelada</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="movimento_data_inicio">Data inicial *</label>
                  <input class="form-control" type="date" id="movimento_data_inicio" name="data_inicio"
                    value="<?= date('Y-m-01') ?>" required>
                </div>

                <div class="form-group">
                  <label for="movimento_data_fim">Data final *</label>
                  <input class="form-control" type="date" id="movimento_data_fim" name="data_fim"
                    value="<?= date('Y-m-d') ?>" required>
                </div>

              </div>

              <div class="form-footer">
                <button type="submit" class="btn-secondary" name="formato" value="excel">⤓ Exportar Excel</button>
                <button type="submit" class="btn-primary" name="formato" value="pdf">⤓ Exportar PDF</button>
              </div>
            </form>
          </div>

        </section>


        <!-- =====================================
     RELATÓRIO — RESTAURANTE
  ===================================== -->
        <section class="page-section" id="page-relatorio-restaurante">

          <div class="page-header">
            <div>
              <span class="eyebrow">RELATÓRIOS</span>
              <h1>Relatório de restaurante</h1>
              <p>Pedidos de mesa, quarto (room service) e balcão.</p>
            </div>
            <button class="btn-secondary" data-page="relatorios">← Voltar aos relatórios</button>
          </div>

          <div class="content-card">
            <form class="form-section" method="GET" action="<?= BASE_URL ?>/app/actions/relatorios.php" target="_blank">
              <input type="hidden" name="hotel_id" value="<?= (int) $hotelId ?>">
              <input type="hidden" name="tipo" value="restaurante">

              <h2>Filtros</h2>

              <div class="form-grid">

                <div class="form-group">
                  <label for="restaurante_estado">Estado (opcional)</label>
                  <select class="form-control" id="restaurante_estado" name="estado">
                    <option value="">Todos os estados</option>
                    <option value="pendente">Pendente</option>
                    <option value="preparacao">Em preparação</option>
                    <option value="pronto">Pronto</option>
                    <option value="entregue">Entregue</option>
                    <option value="cancelado">Cancelado</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="restaurante_tipo_pedido">Tipo de pedido (opcional)</label>
                  <select class="form-control" id="restaurante_tipo_pedido" name="tipo_pedido">
                    <option value="">Todos os tipos</option>
                    <option value="mesa">Mesa</option>
                    <option value="quarto">Quarto (room service)</option>
                    <option value="balcao">Balcão</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="restaurante_data_inicio">Data inicial *</label>
                  <input class="form-control" type="date" id="restaurante_data_inicio" name="data_inicio"
                    value="<?= date('Y-m-01') ?>" required>
                </div>

                <div class="form-group">
                  <label for="restaurante_data_fim">Data final *</label>
                  <input class="form-control" type="date" id="restaurante_data_fim" name="data_fim"
                    value="<?= date('Y-m-d') ?>" required>
                </div>

              </div>

              <div class="form-footer">
                <button type="submit" class="btn-secondary" name="formato" value="excel">⤓ Exportar Excel</button>
                <button type="submit" class="btn-primary" name="formato" value="pdf">⤓ Exportar PDF</button>
              </div>
            </form>
          </div>

        </section>

      <?php endif; ?>
    </main>

  </div>

  <?php require_once __DIR__ . '/views/layouts/scripts.php' ?>

</body>

</html>