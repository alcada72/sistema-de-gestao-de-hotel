CREATE DATABASE IF NOT EXISTS multiplo_hotel CHARACTER
SET
  utf8mb4 COLLATE utf8mb4_unicode_ci;

USE multiplo_hotel;

-- ============================================================
-- 1. LOCALIZACAO
-- ============================================================
CREATE TABLE
  provincias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE = InnoDB;

CREATE TABLE
  municipios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    provincia_id INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_municipio_provincia FOREIGN KEY (provincia_id) REFERENCES provincias (id),
    UNIQUE (nome, provincia_id),
    UNIQUE (id, provincia_id),
    INDEX idx_municipio_provincia (provincia_id)
  ) ENGINE = InnoDB;

-- ============================================================
-- 2. UTILIZADORES
-- ============================================================
CREATE TABLE
  usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telefone VARCHAR(30),
    senha VARCHAR(255) NOT NULL,
    tipo ENUM ('admin', 'cliente') NOT NULL DEFAULT 'cliente',
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    email_verificado_em TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario_tipo (tipo),
    INDEX idx_usuario_ativo (ativo)
  ) ENGINE = InnoDB;

-- ============================================================
-- 3. HOTEIS
-- ============================================================
CREATE TABLE
  hoteis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    descricao TEXT,
    email VARCHAR(150),
    telefone VARCHAR(30),
    telefone_secundario VARCHAR(30),
    nif VARCHAR(30),
    endereco VARCHAR(255) NOT NULL,
    provincia_id INT NOT NULL,
    municipio_id INT NOT NULL,
    latitude DECIMAL(10, 7),
    longitude DECIMAL(10, 7),
    categoria ENUM (
      '1_estrela',
      '2_estrelas',
      '3_estrelas',
      '4_estrelas',
      '5_estrelas',
      'sem_classificacao'
    ) NOT NULL DEFAULT 'sem_classificacao',
    estado ENUM ('pendente', 'aprovado', 'rejeitado', 'suspenso') NOT NULL DEFAULT 'pendente',
    criado_por INT NOT NULL,
    aprovado_em TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_hotel_provincia FOREIGN KEY (provincia_id) REFERENCES provincias (id),
    CONSTRAINT fk_hotel_municipio FOREIGN KEY (municipio_id, provincia_id) REFERENCES municipios (id, provincia_id),
    CONSTRAINT fk_hotel_criador FOREIGN KEY (criado_por) REFERENCES usuarios (id),
    UNIQUE (id, provincia_id),
    UNIQUE (id, municipio_id),
    INDEX idx_hotel_provincia_estado (provincia_id, estado),
    INDEX idx_hotel_municipio_estado (municipio_id, estado),
    INDEX idx_hotel_nome (nome),
    INDEX idx_hotel_estado (estado)
  ) ENGINE = InnoDB;

-- ============================================================
-- 4. PROPRIETARIOS E FUNCIONARIOS DOS HOTEIS
-- ============================================================
CREATE TABLE
  hotel_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    usuario_id INT NOT NULL,
    cargo ENUM (
      'proprietario',
      'gerente',
      'recepcionista',
      'operador'
    ) NOT NULL DEFAULT 'operador',
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hotel_usuario_hotel FOREIGN KEY (hotel_id) REFERENCES hoteis (id),
    CONSTRAINT fk_hotel_usuario_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    UNIQUE (hotel_id, usuario_id),
    INDEX idx_hotel_usuario_usuario (usuario_id)
  ) ENGINE = InnoDB;

-- ============================================================
-- 5. IMAGENS DOS HOTEIS
-- ============================================================
CREATE TABLE
  hotel_imagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    tipo ENUM ('principal', 'galeria', 'interior', 'exterior') NOT NULL DEFAULT 'galeria',
    ordem INT NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES hoteis (id) ON DELETE CASCADE,
    INDEX idx_imagens_hotel (hotel_id, ordem)
  ) ENGINE = InnoDB;

-- ============================================================
-- 6. ANDARES
-- ============================================================
CREATE TABLE
  andares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    numero_andar INT NOT NULL,
    descricao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES hoteis (id),
    UNIQUE (hotel_id, numero_andar),
    UNIQUE (id, hotel_id)
  ) ENGINE = InnoDB;

-- ============================================================
-- 7. CATEGORIAS DOS QUARTOS
-- ============================================================
CREATE TABLE
  categorias_quartos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    capacidade INT NOT NULL DEFAULT 2,
    preco_base DECIMAL(12, 2) NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES hoteis (id),
    UNIQUE (hotel_id, nome),
    UNIQUE (id, hotel_id),
    CHECK (capacidade > 0),
    CHECK (preco_base >= 0),
    INDEX idx_categoria_hotel (hotel_id)
  ) ENGINE = InnoDB;

-- ============================================================
-- 8. QUARTOS
-- ============================================================
CREATE TABLE
  quartos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    andar_id INT NULL,
    categoria_id INT NOT NULL,
    numero VARCHAR(20) NOT NULL,
    estado ENUM ('ativo', 'manutencao', 'inativo') NOT NULL DEFAULT 'ativo',
    descricao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quarto_hotel FOREIGN KEY (hotel_id) REFERENCES hoteis (id),
    CONSTRAINT fk_quarto_andar FOREIGN KEY (andar_id, hotel_id) REFERENCES andares (id, hotel_id),
    CONSTRAINT fk_quarto_categoria FOREIGN KEY (categoria_id, hotel_id) REFERENCES categorias_quartos (id, hotel_id),
    UNIQUE (hotel_id, numero),
    UNIQUE (id, hotel_id),
    INDEX idx_quarto_hotel_estado (hotel_id, estado),
    INDEX idx_quarto_categoria (categoria_id)
  ) ENGINE = InnoDB;

-- ============================================================
-- 9. SERVICOS DOS HOTEIS
-- ============================================================
CREATE TABLE
  servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT,
    preco DECIMAL(12, 2) NOT NULL DEFAULT 0,
    tipo ENUM ('gratuito', 'pago') NOT NULL DEFAULT 'gratuito',
    disponivel BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES hoteis (id),
    UNIQUE (hotel_id, nome),
    UNIQUE (id, hotel_id),
    CHECK (preco >= 0),
    INDEX idx_servico_hotel (hotel_id, disponivel)
  ) ENGINE = InnoDB;

-- ============================================================
-- 10. RESERVAS
-- ============================================================
CREATE TABLE
  reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(40) NOT NULL UNIQUE,
    hotel_id INT NOT NULL,
    quarto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    data_entrada DATE NOT NULL,
    data_saida DATE NOT NULL,
    quantidade_pessoas INT NOT NULL DEFAULT 1,
    quantidade_noites INT NOT NULL,
    preco_diaria DECIMAL(12, 2) NOT NULL,
    total DECIMAL(12, 2) NOT NULL,
    estado ENUM (
      'pendente',
      'confirmada',
      'checkin',
      'concluida',
      'cancelada'
    ) NOT NULL DEFAULT 'pendente',
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reserva_hotel FOREIGN KEY (hotel_id) REFERENCES hoteis (id),
    CONSTRAINT fk_reserva_quarto FOREIGN KEY (quarto_id, hotel_id) REFERENCES quartos (id, hotel_id),
    CONSTRAINT fk_reserva_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    UNIQUE (id, hotel_id),
    CHECK (data_saida > data_entrada),
    CHECK (quantidade_pessoas > 0),
    CHECK (quantidade_noites > 0),
    CHECK (preco_diaria >= 0),
    CHECK (total >= 0),
    INDEX idx_reserva_quarto_datas (quarto_id, data_entrada, data_saida, estado),
    INDEX idx_reserva_hotel_estado (hotel_id, estado),
    INDEX idx_reserva_usuario (usuario_id)
  ) ENGINE = InnoDB;

-- ============================================================
-- 11. HOSPEDES / ACOMPANHANTES DAS RESERVAS
-- ============================================================
CREATE TABLE
  reserva_hospedes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    documento VARCHAR(50),
    telefone VARCHAR(30),
    principal BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reserva_id) REFERENCES reservas (id) ON DELETE CASCADE,
    INDEX idx_hospede_reserva (reserva_id)
  ) ENGINE = InnoDB;

-- ============================================================
-- 12. PAGAMENTOS
-- ============================================================
CREATE TABLE
  pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id INT NOT NULL,
    hotel_id INT NOT NULL,
    valor DECIMAL(12, 2) NOT NULL,
    metodo ENUM (
      'dinheiro',
      'transferencia',
      'cartao',
      'referencia',
      'multicaixa_express',
      'outro'
    ) NOT NULL,
    estado ENUM ('pendente', 'pago', 'falhou', 'reembolsado') NOT NULL DEFAULT 'pendente',
    referencia VARCHAR(150),
    pago_em TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagamento_reserva FOREIGN KEY (reserva_id, hotel_id) REFERENCES reservas (id, hotel_id),
    CHECK (valor > 0),
    INDEX idx_pagamento_reserva (reserva_id),
    INDEX idx_pagamento_estado (estado)
  ) ENGINE = InnoDB;

-- ============================================================
-- 13. CATEGORIAS DO RESTAURANTE
-- ============================================================
CREATE TABLE
  categorias_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    nome VARCHAR(80) NOT NULL,
    descricao TEXT,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (hotel_id) REFERENCES hoteis (id),
    UNIQUE (hotel_id, nome),
    UNIQUE (id, hotel_id)
  ) ENGINE = InnoDB;

-- ============================================================
-- 14. PRODUTOS DO RESTAURANTE
-- ============================================================
CREATE TABLE
  produtos_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    categoria_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT,
    preco DECIMAL(12, 2) NOT NULL,
    tipo ENUM ('prato', 'bebida', 'sobremesa', 'outro') NOT NULL DEFAULT 'prato',
    disponivel BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES hoteis (id),
    CONSTRAINT fk_produto_categoria FOREIGN KEY (categoria_id, hotel_id) REFERENCES categorias_menu (id, hotel_id),
    UNIQUE (id, hotel_id),
    CHECK (preco >= 0),
    INDEX idx_produto_categoria (categoria_id),
    INDEX idx_produto_hotel (hotel_id, disponivel)
  ) ENGINE = InnoDB;

-- ============================================================
-- 15. PEDIDOS DO RESTAURANTE
-- ============================================================
CREATE TABLE
  pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    reserva_id INT NULL,
    usuario_id INT NULL,
    tipo ENUM ('mesa', 'quarto', 'balcao') NOT NULL DEFAULT 'mesa',
    numero_mesa INT,
    nome_cliente VARCHAR(120),
    telefone_cliente VARCHAR(30),
    estado ENUM (
      'pendente',
      'preparacao',
      'pronto',
      'entregue',
      'cancelado'
    ) NOT NULL DEFAULT 'pendente',
    observacoes TEXT,
    total DECIMAL(12, 2) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES hoteis (id),
    CONSTRAINT fk_pedido_reserva FOREIGN KEY (reserva_id, hotel_id) REFERENCES reservas (id, hotel_id),
    CONSTRAINT fk_pedido_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
    UNIQUE (id, hotel_id),
    CHECK (total >= 0),
    INDEX idx_pedido_hotel_estado (hotel_id, estado),
    INDEX idx_pedido_data (criado_em)
  ) ENGINE = InnoDB;

-- ============================================================
-- 16. ITENS DOS PEDIDOS
-- ============================================================
CREATE TABLE
  itens_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    hotel_id INT NOT NULL,
    produto_id INT NULL,
    nome_produto VARCHAR(120) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(12, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id, hotel_id) REFERENCES pedidos (id, hotel_id) ON DELETE CASCADE,
    CONSTRAINT fk_item_produto FOREIGN KEY (produto_id, hotel_id) REFERENCES produtos_menu (id, hotel_id),
    CHECK (quantidade > 0),
    CHECK (preco_unitario >= 0),
    CHECK (subtotal >= 0),
    INDEX idx_item_pedido (pedido_id)
  ) ENGINE = InnoDB;

-- ============================================================
-- . Menu
-- ============================================================
CREATE TABLE
  menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    chave VARCHAR(100) NOT NULL UNIQUE,
    icone VARCHAR(100) DEFAULT NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT (1) NOT NULL DEFAULT 1
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
  menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    link VARCHAR(255) NOT NULL UNIQUE,
    icone VARCHAR(100) DEFAULT NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT (1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
  menu_permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    cargo ENUM (
      'proprietario',
      'gerente',
      'recepcionista',
      'operador'
    ) NOT NULL DEFAULT 'proprietario',
    UNIQUE KEY uk_menu_cargo (menu_id, cargo),
    CONSTRAINT fk_menu_permissoes_menu FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

ALTER TABLE menu_permissoes ADD UNIQUE KEY uq_menu_cargo (menu_id, cargo);

INSERT INTO
  menu (nome, chave, icone, ordem)
VALUES
  (
    'Principal',
    'dashboard',
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
    1
  ),
  (
    'Quartos',
    'quartos',
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/><path d="M6 8a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2"/></svg>',
    2
  ),
  (
    'Reservas',
    'reservas',
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 15 2 2 4-4"/></svg>',
    3
  ),
  (
    'Restaurante',
    'restaurante',
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7a4 4 0 0 0 4 4h1V2"/><path d="M6 2v20"/><path d="M14 2v8a4 4 0 0 0 4 4h1V2"/><path d="M18 14v8"/></svg>',
    4
  ),
  (
    'Serviços',
    'servicos',
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 15 5 19 5 19 9 22 12 19 15 19 19 15 19 12 22 9 19 5 19 5 15 2 12 5 9 5 5 9 5Z"/><circle cx="12" cy="12" r="3"/></svg>',
    5
  ),
  (
    'Funcionários',
    'funcionarios',
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    6
  ),
  (
    'Relatórios',
    'relatorios',
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-5 5"/><path d="M19 9h-5"/><path d="M19 9v5"/></svg>',
    7
  ),
  (
    'Hotel',
    'hotel',
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22V7l9-5 9 5v15"/><path d="M9 22v-5h6v5"/><path d="M7 10h2m6 0h2M7 14h2m6 0h2"/></svg>',
    8
  );

-- ============================================================
-- 17. SEED - PROVINCIAS DE ANGOLA
-- ============================================================
INSERT IGNORE INTO provincias (nome)
VALUES
  ('Bengo'),
  ('Benguela'),
  ('Bié'),
  ('Cabinda'),
  ('Cuando'),
  ('Cubango'),
  ('Cuanza Norte'),
  ('Cuanza Sul'),
  ('Cunene'),
  ('Huambo'),
  ('Huíla'),
  ('Icolo e Bengo'),
  ('Luanda'),
  ('Lunda Norte'),
  ('Lunda Sul'),
  ('Malanje'),
  ('Moxico'),
  ('Moxico Leste'),
  ('Namibe'),
  ('Uíge'),
  ('Zaire');

-- ============================================================
-- 18. SEED - MUNICIPIOS DE EXEMPLO
-- ============================================================
INSERT IGNORE INTO municipios (nome, provincia_id)
SELECT
  dados.nome,
  p.id
FROM
  (
    SELECT
      'Luanda' AS nome,
      'Luanda' AS provincia
    UNION ALL
    SELECT
      'Viana',
      'Luanda'
    UNION ALL
    SELECT
      'Cacuaco',
      'Luanda'
    UNION ALL
    SELECT
      'Cazenga',
      'Luanda'
    UNION ALL
    SELECT
      'Talatona',
      'Luanda'
    UNION ALL
    SELECT
      'Belas',
      'Luanda'
    UNION ALL
    SELECT
      'Benguela',
      'Benguela'
    UNION ALL
    SELECT
      'Lobito',
      'Benguela'
    UNION ALL
    SELECT
      'Huambo',
      'Huambo'
    UNION ALL
    SELECT
      'Lubango',
      'Huíla'
    UNION ALL
    SELECT
      'Moçâmedes',
      'Namibe'
    UNION ALL
    SELECT
      'Malanje',
      'Malanje'
    UNION ALL
    SELECT
      'Uíge',
      'Uíge'
    UNION ALL
    SELECT
      'Mbanza Kongo',
      'Zaire'
    UNION ALL
    SELECT
      'Saurimo',
      'Lunda Sul'
    UNION ALL
    SELECT
      'Dundo',
      'Lunda Norte'
    UNION ALL
    SELECT
      'Menongue',
      'Cubango'
    UNION ALL
    SELECT
      'Cuito',
      'Bié'
    UNION ALL
    SELECT
      'Ndalatando',
      'Cuanza Norte'
    UNION ALL
    SELECT
      'Sumbe',
      'Cuanza Sul'
    UNION ALL
    SELECT
      'Ondjiva',
      'Cunene'
    UNION ALL
    SELECT
      'Cabinda',
      'Cabinda'
  ) AS dados
  INNER JOIN provincias p ON p.nome = dados.provincia;

-- ============================================================
-- 19. SEED - ADMINISTRADOR DA PLATAFORMA
-- ============================================================
INSERT IGNORE INTO usuarios (nome, email, telefone, senha, tipo)
VALUES
  (
    'Administrador da Plataforma',
    'admin@hotelaria.ao',
    '+244900000000',
    '$2y$12$/fGNd4QRt4aCsUfjgekJI.aYlo.KEcqUM37LDV8QvWDiAt/WcwiES',
    'admin'
  );

-- ============================================================
-- 20. SEED - PROPRIETARIO
-- ============================================================
INSERT IGNORE INTO usuarios (nome, email, telefone, senha, tipo)
VALUES
  (
    'Proprietario Demo',
    'proprietario@hotelaria.ao',
    '+244911111111',
    '$2y$12$/fGNd4QRt4aCsUfjgekJI.aYlo.KEcqUM37LDV8QvWDiAt/WcwiES',
    'cliente'
  );

-- ============================================================
-- 21. SEED - HOTEL DEMONSTRATIVO
-- ============================================================
INSERT IGNORE INTO hoteis (
  nome,
  slug,
  descricao,
  email,
  telefone,
  nif,
  endereco,
  provincia_id,
  municipio_id,
  categoria,
  estado,
  criado_por
)
SELECT
  'Hotel Demo Luanda',
  'hotel-demo-luanda',
  'Hotel demonstrativo da plataforma de hotelaria.',
  'hotel@demo.ao',
  '+244922222222',
  '5000000000',
  'Centro de Luanda',
  p.id,
  m.id,
  '3_estrelas',
  'pendente',
  u.id
FROM
  provincias p
  INNER JOIN municipios m ON m.provincia_id = p.id
  AND m.nome = 'Luanda'
  INNER JOIN usuarios u ON u.email = 'proprietario@hotelaria.ao'
WHERE
  p.nome = 'Luanda';

-- ============================================================
-- 22. SEED - ASSOCIAR PROPRIETARIO AO HOTEL
-- ============================================================
INSERT IGNORE INTO hotel_usuarios (hotel_id, usuario_id, cargo)
SELECT
  h.id,
  u.id,
  'proprietario'
FROM
  hoteis h
  INNER JOIN usuarios u ON u.email = 'proprietario@hotelaria.ao'
WHERE
  h.slug = 'hotel-demo-luanda';

-- ============================================================
-- 23. SEED - CATEGORIAS DE QUARTOS
-- ============================================================
INSERT IGNORE INTO categorias_quartos (hotel_id, nome, descricao, capacidade, preco_base)
SELECT
  id,
  'Standard',
  'Quarto standard para duas pessoas.',
  2,
  25000
FROM
  hoteis
WHERE
  slug = 'hotel-demo-luanda';

INSERT IGNORE INTO categorias_quartos (hotel_id, nome, descricao, capacidade, preco_base)
SELECT
  id,
  'Suite',
  'Suite com maior conforto e espaço.',
  3,
  45000
FROM
  hoteis
WHERE
  slug = 'hotel-demo-luanda';

-- ============================================================
-- 24. SEED - ANDAR
-- ============================================================
INSERT IGNORE INTO andares (hotel_id, numero_andar, descricao)
SELECT
  id,
  1,
  'Primeiro andar'
FROM
  hoteis
WHERE
  slug = 'hotel-demo-luanda';

-- ============================================================
-- 25. SEED - QUARTOS
-- ============================================================
INSERT IGNORE INTO quartos (hotel_id, andar_id, categoria_id, numero)
SELECT
  h.id,
  a.id,
  c.id,
  '101'
FROM
  hoteis h
  INNER JOIN andares a ON a.hotel_id = h.id
  AND a.numero_andar = 1
  INNER JOIN categorias_quartos c ON c.hotel_id = h.id
  AND c.nome = 'Standard'
WHERE
  h.slug = 'hotel-demo-luanda';

INSERT IGNORE INTO quartos (hotel_id, andar_id, categoria_id, numero)
SELECT
  h.id,
  a.id,
  c.id,
  '102'
FROM
  hoteis h
  INNER JOIN andares a ON a.hotel_id = h.id
  AND a.numero_andar = 1
  INNER JOIN categorias_quartos c ON c.hotel_id = h.id
  AND c.nome = 'Suite'
WHERE
  h.slug = 'hotel-demo-luanda';

-- ============================================================
-- 26. SEED - SERVICOS
-- ============================================================
INSERT IGNORE INTO servicos (hotel_id, nome, descricao, preco, tipo)
SELECT
  id,
  'Wi-Fi',
  'Internet sem fios disponível para hóspedes.',
  0,
  'gratuito'
FROM
  hoteis
WHERE
  slug = 'hotel-demo-luanda';

INSERT IGNORE INTO servicos (hotel_id, nome, descricao, preco, tipo)
SELECT
  id,
  'Lavandaria',
  'Serviço de lavagem de roupas.',
  5000,
  'pago'
FROM
  hoteis
WHERE
  slug = 'hotel-demo-luanda';

INSERT IGNORE INTO servicos (hotel_id, nome, descricao, preco, tipo)
SELECT
  id,
  'Estacionamento',
  'Estacionamento para hóspedes.',
  0,
  'gratuito'
FROM
  hoteis
WHERE
  slug = 'hotel-demo-luanda';