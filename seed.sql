-- =====================================================
-- SEED INICIAL - HOTELIER
-- Base de dados: hotelaria
-- =====================================================
USE hotelaria;

START TRANSACTION;

-- 1. Andares
INSERT INTO
  andares (numero_andar, descricao)
VALUES
  (0, 'Rés-do-chão'),
  (1, 'Primeiro andar'),
  (2, 'Segundo andar') ON DUPLICATE KEY
UPDATE descricao =
VALUES
  (descricao);

-- 2. Quartos
INSERT INTO
  quartos (
    andar_id,
    numero,
    tipo,
    preco,
    capacidade,
    estado,
    descricao
  )
VALUES
  (
    (
      SELECT
        id
      FROM
        andares
      WHERE
        numero_andar = 0
    ),
    101,
    'normal',
    15000.00,
    2,
    'disponivel',
    'Quarto normal no rés-do-chão'
  ),
  (
    (
      SELECT
        id
      FROM
        andares
      WHERE
        numero_andar = 0
    ),
    102,
    'normal',
    15000.00,
    2,
    'disponivel',
    'Quarto normal no rés-do-chão'
  ),
  (
    (
      SELECT
        id
      FROM
        andares
      WHERE
        numero_andar = 1
    ),
    201,
    'normal',
    20000.00,
    2,
    'disponivel',
    'Quarto normal no primeiro andar'
  ),
  (
    (
      SELECT
        id
      FROM
        andares
      WHERE
        numero_andar = 1
    ),
    202,
    'vip',
    35000.00,
    3,
    'disponivel',
    'Quarto VIP no primeiro andar'
  ),
  (
    (
      SELECT
        id
      FROM
        andares
      WHERE
        numero_andar = 2
    ),
    301,
    'vip',
    45000.00,
    4,
    'disponivel',
    'Quarto VIP no segundo andar'
  ) ON DUPLICATE KEY
UPDATE andar_id =
VALUES
  (andar_id),
  tipo =
VALUES
  (tipo),
  preco =
VALUES
  (preco),
  capacidade =
VALUES
  (capacidade),
  descricao =
VALUES
  (descricao);

-- 3. Categorias do restaurante
INSERT INTO
  categorias_menu (nome, descricao)
VALUES
  ('Pratos principais', 'Refeições principais'),
  ('Bebidas', 'Bebidas frias e quentes'),
  ('Sobremesas', 'Doces e sobremesas') ON DUPLICATE KEY
UPDATE descricao =
VALUES
  (descricao);

-- 4. Produtos do restaurante
INSERT INTO
  produtos_menu (categoria_id, nome, descricao, preco, tipo)
VALUES
  (
    (
      SELECT
        id
      FROM
        categorias_menu
      WHERE
        nome = 'Pratos principais'
    ),
    'Frango grelhado',
    'Frango grelhado acompanhado',
    6500.00,
    'prato'
  ),
  (
    (
      SELECT
        id
      FROM
        categorias_menu
      WHERE
        nome = 'Pratos principais'
    ),
    'Peixe grelhado',
    'Peixe grelhado com acompanhamento',
    8000.00,
    'prato'
  ),
  (
    (
      SELECT
        id
      FROM
        categorias_menu
      WHERE
        nome = 'Bebidas'
    ),
    'Água mineral',
    'Garrafa de água mineral',
    500.00,
    'bebida'
  ),
  (
    (
      SELECT
        id
      FROM
        categorias_menu
      WHERE
        nome = 'Bebidas'
    ),
    'Sumo natural',
    'Sumo natural da casa',
    1500.00,
    'bebida'
  ),
  (
    (
      SELECT
        id
      FROM
        categorias_menu
      WHERE
        nome = 'Sobremesas'
    ),
    'Salada de frutas',
    'Sobremesa de frutas frescas',
    2000.00,
    'sobremesa'
  );

-- 5. Hóspedes de demonstração
INSERT INTO
  hospedes (nome, telefone, email, documento)
VALUES
  (
    'João Manuel',
    '923000001',
    'joao@example.com',
    'SEED-DOC-001'
  ),
  (
    'Maria José',
    '923000002',
    'maria@example.com',
    'SEED-DOC-002'
  ),
  (
    'Pedro António',
    '923000003',
    'pedro@example.com',
    'SEED-DOC-003'
  ) ON DUPLICATE KEY
UPDATE nome =
VALUES
  (nome),
  telefone =
VALUES
  (telefone),
  email =
VALUES
  (email);

-- 6. Administrador
-- Cria o administrador com password_hash() no PHP.
-- Não coloques uma palavra-passe em texto simples nesta tabela.
COMMIT;