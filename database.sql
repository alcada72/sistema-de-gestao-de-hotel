CREATE DATABASE IF NOT EXISTS hotelaria CHARACTER
SET
  utf8mb4 COLLATE utf8mb4_unicode_ci;

USE hotelaria;

-- 1. Utilizadores do sistema
CREATE TABLE
  IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    cargo ENUM ('admin', 'recepcionista', 'gerente') NOT NULL DEFAULT 'recepcionista',
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE = InnoDB;

CREATE TABLE
  IF NOT EXISTS andares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_andar INT NOT NULL UNIQUE,
    descricao TEXT
  ) ENGINE = InnoDB;

-- 2. Quartos do hotel
CREATE TABLE
  IF NOT EXISTS quartos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    andar_id INT NULL,
    numero INT NOT NULL UNIQUE,
    tipo ENUM ('normal', 'vip') NOT NULL DEFAULT 'normal',
    preco DECIMAL(12, 2) NOT NULL,
    capacidade INT NOT NULL DEFAULT 2,
    estado ENUM ('disponivel', 'ocupado', 'manutencao') NOT NULL DEFAULT 'disponivel',
    descricao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_quarto_preco CHECK (preco >= 0),
    CONSTRAINT chk_quarto_capacidade CHECK (capacidade > 0)
  ) ENGINE = InnoDB;

-- 3. Cadastro dos hóspedes
CREATE TABLE
  IF NOT EXISTS hospedes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    telefone VARCHAR(30),
    email VARCHAR(150),
    documento VARCHAR(50) UNIQUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE = InnoDB;

-- 4. Reservas dos quartos
CREATE TABLE
  IF NOT EXISTS quartos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    andar_id INT NULL,
    numero INT NOT NULL UNIQUE,
    tipo ENUM ('normal', 'vip') NOT NULL DEFAULT 'normal',
    preco DECIMAL(12, 2) NOT NULL,
    capacidade INT NOT NULL DEFAULT 2,
    estado ENUM ('disponivel', 'ocupado', 'manutencao') NOT NULL DEFAULT 'disponivel',
    descricao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quarto_andar FOREIGN KEY (andar_id) REFERENCES andares (id) ON DELETE SET NULL,
    CONSTRAINT chk_quarto_preco CHECK (preco >= 0),
    CONSTRAINT chk_quarto_capacidade CHECK (capacidade > 0)
  ) ENGINE = InnoDB;

CREATE TABLE
  IF NOT EXISTS reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospede_id INT NOT NULL,
    quarto_id INT NOT NULL,
    usuario_id INT NULL,
    data_entrada DATE NOT NULL,
    data_saida DATE NOT NULL,
    quantidade_pessoas INT NOT NULL DEFAULT 1,
    preco_diaria DECIMAL(12, 2) NOT NULL,
    estado ENUM (
      'pendente',
      'confirmada',
      'checkin',
      'concluida',
      'cancelada'
    ) NOT NULL DEFAULT 'pendente',
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reserva_hospede FOREIGN KEY (hospede_id) REFERENCES hospedes (id),
    CONSTRAINT fk_reserva_quarto FOREIGN KEY (quarto_id) REFERENCES quartos (id),
    CONSTRAINT fk_reserva_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
    CONSTRAINT chk_reserva_datas CHECK (data_saida > data_entrada),
    CONSTRAINT chk_reserva_pessoas CHECK (quantidade_pessoas > 0),
    CONSTRAINT chk_reserva_preco CHECK (preco_diaria >= 0),
    INDEX idx_reserva_quarto_datas (quarto_id, data_entrada, data_saida),
    INDEX idx_reserva_hospede (hospede_id),
    INDEX idx_reserva_estado (estado)
  ) ENGINE = InnoDB;

-- 5. Categorias do menu do restaurante
CREATE TABLE
  IF NOT EXISTS categorias_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL UNIQUE,
    descricao TEXT,
    ativo BOOLEAN NOT NULL DEFAULT TRUE
  ) ENGINE = InnoDB;

-- 6. Pratos e bebidas
CREATE TABLE
  IF NOT EXISTS produtos_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT,
    preco DECIMAL(12, 2) NOT NULL,
    tipo ENUM ('prato', 'bebida', 'sobremesa', 'outro') NOT NULL DEFAULT 'prato',
    disponivel BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produto_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_menu (id),
    CONSTRAINT chk_produto_preco CHECK (preco >= 0),
    INDEX idx_produto_categoria (categoria_id)
  ) ENGINE = InnoDB;

-- 7. Pedidos do restaurante
CREATE TABLE
  IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospede_id INT,
    reserva_id INT,
    usuario_id INT,
    tipo ENUM ('mesa', 'quarto', 'balcao') NOT NULL DEFAULT 'mesa',
    numero_mesa INT,
    estado ENUM (
      'pendente',
      'preparacao',
      'pronto',
      'entregue',
      'cancelado'
    ) NOT NULL DEFAULT 'pendente',
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedido_hospede FOREIGN KEY (hospede_id) REFERENCES hospedes (id) ON DELETE SET NULL,
    CONSTRAINT fk_pedido_reserva FOREIGN KEY (reserva_id) REFERENCES reservas (id) ON DELETE SET NULL,
    CONSTRAINT fk_pedido_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
    INDEX idx_pedido_estado (estado),
    INDEX idx_pedido_data (criado_em)
  ) ENGINE = InnoDB;

-- 8. Produtos de cada pedido
CREATE TABLE
  IF NOT EXISTS itens_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT,
    nome_produto VARCHAR(120) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(12, 2) NOT NULL,
    CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE,
    CONSTRAINT fk_item_produto FOREIGN KEY (produto_id) REFERENCES produtos_menu (id) ON DELETE SET NULL,
    CONSTRAINT chk_item_quantidade CHECK (quantidade > 0),
    CONSTRAINT chk_item_preco CHECK (preco_unitario >= 0),
    INDEX idx_item_pedido (pedido_id)
  ) ENGINE = InnoDB;