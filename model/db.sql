CREATE SCHEMA IF NOT EXISTS SICAD;
USE SICAD;

-- Tabela de Usuário
CREATE TABLE Usuario (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    cpf VARCHAR(20) NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    telefone VARCHAR(20) NULL,
    assinatura BLOB NULL
);

-- Tabela de Modalidade (deve vir antes de Atividade)
CREATE TABLE Modalidade (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome ENUM('presencial', 'online', 'hibrido') NOT NULL
);

-- Tabela de Evento
CREATE TABLE Evento (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    descricao TEXT,
    nome VARCHAR(100),
    data_inicio DATE,
    data_fim DATE,
    responsavel_evento VARCHAR(255)
);

-- Tabela de Atividade
CREATE TABLE Atividade (
    ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    informacoes_atividade TEXT,
    carga_horaria INT,
    nome VARCHAR(100),
    palestrante VARCHAR(100),
    status_atividade ENUM('confirmada', 'cancelada', 'realizada'),
    num_participantes INT,
    fk_Evento_codigo INT NOT NULL,
    fk_Modalidade_codigo INT NOT NULL,
    FOREIGN KEY (fk_Evento_codigo) REFERENCES Evento(codigo),
    FOREIGN KEY (fk_Modalidade_codigo) REFERENCES Modalidade(codigo)
);

-- Tabela de API de Pagamento
CREATE TABLE API_pagamento (
    id_proprietario INT NOT NULL PRIMARY KEY,
    valor_total_em_caixa DECIMAL(10,2),
    secret_key VARCHAR(255),
    cpf_cnpj VARCHAR(255),
    num_conta_corrente VARCHAR(30),
    nome_proprietario VARCHAR(255),
    telefone_proprietario VARCHAR(30)
);

-- Tabela de Tipo de Pagamento
CREATE TABLE Pagamento_tipo_pagamento (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    taxa_emolumentos DECIMAL(10,2),
    descricao ENUM('credito', 'debito', 'pix', 'boleto'),
    fk_API_pagamento_id_proprietario INT,
    FOREIGN KEY (fk_API_pagamento_id_proprietario) REFERENCES API_pagamento(id_proprietario)
);

-- Tabela realiza (relacionamento entre Usuário e Pagamento)
CREATE TABLE Realiza (
    fk_Usuario_ID INT NOT NULL,
    fk_Pagamento_tipo_pagamento_codigo INT NOT NULL,
    valor_pago DECIMAL(10,2),
    data_vencimento DATE,
    data_do_pagamento DATE,
    status_ ENUM('pendente', 'pago', 'cancelado', 'estornado') NOT NULL,
    FOREIGN KEY (fk_Usuario_ID) REFERENCES Usuario(ID),
    FOREIGN KEY (fk_Pagamento_tipo_pagamento_codigo) REFERENCES Pagamento_tipo_pagamento(codigo)
);

-- Tabela Gerencia (relacionamento entre Usuário e Evento)
CREATE TABLE Gerencia (
    fk_Usuario_ID INT NOT NULL,
    fk_Evento_codigo INT NOT NULL,
    FOREIGN KEY (fk_Usuario_ID) REFERENCES Usuario(ID),
    FOREIGN KEY (fk_Evento_codigo) REFERENCES Evento(codigo)
);

-- Tabela Participa (relacionamento entre Usuário e Atividade)
CREATE TABLE Participa (
    fk_Usuario_ID INT NOT NULL,
    fk_Atividade_ID INT NOT NULL,
    FOREIGN KEY (fk_Usuario_ID) REFERENCES Usuario(ID),
    FOREIGN KEY (fk_Atividade_ID) REFERENCES Atividade(ID)
);

-- Tabela de Certificados
CREATE TABLE Certificado (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    data_emissao DATE,
    texto_certificado TEXT,
    descricao TEXT,
    carga_horaria INT,
    template BLOB,
    qr_code BLOB,
    fk_Usuario_ID INT NOT NULL,
    fk_Atividade_ID INT NOT NULL,
    FOREIGN KEY (fk_Usuario_ID) REFERENCES Usuario(ID),
    FOREIGN KEY (fk_Atividade_ID) REFERENCES Atividade(ID)
);

-- Tabela de Tipos de usuário
CREATE TABLE Tipo (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    descricao ENUM('participante', 'organizador', 'administrador_site')
);

-- Tabela e_do (relacionamento entre Tipo e Usuário)
CREATE TABLE e_do (
    fk_Tipo_codigo INT NOT NULL,
    fk_Usuario_ID INT NOT NULL,
    FOREIGN KEY (fk_Tipo_codigo) REFERENCES Tipo(codigo),
    FOREIGN KEY (fk_Usuario_ID) REFERENCES Usuario(ID)
);

-- Tabela Assinatura (relacionada a um usuário)
CREATE TABLE Assinatura (
    id_assinatura INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_Usuario_ID INT NOT NULL,
    FOREIGN KEY (fk_Usuario_ID) REFERENCES Usuario(ID)
);

-- Tabela de Arquivos CSV (importação de dados)
CREATE TABLE Arquivo_CSV (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome_atividade VARCHAR(100),
    num_participantes_atividade INT,
    num_participantes_evento INT,
    num_certificados_emitidos INT,
    palestrante_atividade VARCHAR(100),
    carga_horaria_atividade INT,
    nome_evento VARCHAR(100)
);

-- Tabela importa (relacionamento entre usuário e arquivos importados)
CREATE TABLE Importa (
    fk_Usuario_ID INT NOT NULL,
    fk_Arquivo_CSV_id INT NOT NULL,
    FOREIGN KEY (fk_Usuario_ID) REFERENCES Usuario(ID),
    FOREIGN KEY (fk_Arquivo_CSV_id) REFERENCES Arquivo_CSV(id)
);




-- INSERTS feito por IA

START TRANSACTION;

-- =========================
-- 1) Usuários
-- =========================
INSERT INTO Usuario (nome, email, senha, cpf, telefone)
VALUES
  ('Alice Silva', 'alice@example.com', '$2y$10$P6nZ8cT0U9aB3cD4eF5gOe6Q7r8s9t0u1v2w3x4y5z6A7B8C9D0E', '123.456.789-00', '11999990001'),
  ('Bruno Costa', 'bruno@example.com', '$2y$10$P6nZ8cT0U9aB3cD4eF5gOe6Q7r8s9t0u1v2w3x4y5z6A7B8C9D0E', '987.654.321-00', '11999990002'),
  ('Carla Souza', 'carla@example.com', '$2y$10$P6nZ8cT0U9aB3cD4eF5gOe6Q7r8s9t0u1v2w3x4y5z6A7B8C9D0E', '111.222.333-44', '11999990003');

SET @u_alice = (SELECT ID FROM Usuario WHERE email='alice@example.com');
SET @u_bruno = (SELECT ID FROM Usuario WHERE email='bruno@example.com');
SET @u_carla = (SELECT ID FROM Usuario WHERE email='carla@example.com');

-- =========================
-- 2) Modalidade
-- =========================
INSERT INTO Modalidade (nome) VALUES ('presencial'), ('online'), ('hibrido')
ON DUPLICATE KEY UPDATE nome = VALUES(nome); -- idempotente

SET @m_presencial = (SELECT codigo FROM Modalidade WHERE nome='presencial');
SET @m_online     = (SELECT codigo FROM Modalidade WHERE nome='online');
SET @m_hibrido    = (SELECT codigo FROM Modalidade WHERE nome='hibrido');

-- =========================
-- 3) Evento
-- =========================
INSERT INTO Evento (descricao, nome, data_inicio, data_fim, responsavel_evento)
VALUES ('Evento de tecnologia com trilhas', 'Meu Evento 1', '2025-11-10', '2025-11-12', 'alice@example.com');

SET @ev1 = LAST_INSERT_ID();

-- =========================
-- 4) Atividade (usa Evento + Modalidade)
-- =========================
INSERT INTO Atividade
  (informacoes_atividade, carga_horaria, nome, palestrante, status_atividade, num_participantes, fk_Evento_codigo, fk_Modalidade_codigo)
VALUES
  ('Palestra sobre IA', 2, 'Inteligência Artificial na Prática', 'Dr. Pedro Rocha', 'confirmada', 50, @ev1, @m_presencial),
  ('Oficina de robótica', 4, 'Robótica com Arduino', 'Profa. Carla Mendes', 'confirmada', 30, @ev1, @m_hibrido);

SET @atv_ia   = (SELECT ID FROM Atividade WHERE nome='Inteligência Artificial na Prática' ORDER BY ID DESC LIMIT 1);
SET @atv_rob  = (SELECT ID FROM Atividade WHERE nome='Robótica com Arduino' ORDER BY ID DESC LIMIT 1);

-- =========================
-- 5) API de pagamento (PK = id_proprietario; usaremos a Alice)
-- =========================
INSERT INTO API_pagamento
  (id_proprietario, valor_total_em_caixa, secret_key, cpf_cnpj, num_conta_corrente, nome_proprietario, telefone_proprietario)
VALUES
  (@u_alice, 0.00, 'sk_test_ABC123', '12345678900', '000123-4', 'Alice Silva', '11999990001')
ON DUPLICATE KEY UPDATE valor_total_em_caixa = VALUES(valor_total_em_caixa);

-- =========================
-- 6) Tipos de pagamento
-- =========================
INSERT INTO Pagamento_tipo_pagamento
  (taxa_emolumentos, descricao, fk_API_pagamento_id_proprietario)
VALUES
  (2.50, 'credito', @u_alice),
  (1.80, 'debito',  @u_alice),
  (0.00, 'pix',     @u_alice),
  (3.50, 'boleto',  @u_alice);

SET @pg_credito = (SELECT codigo FROM Pagamento_tipo_pagamento WHERE descricao='credito' AND fk_API_pagamento_id_proprietario=@u_alice LIMIT 1);
SET @pg_debito  = (SELECT codigo FROM Pagamento_tipo_pagamento WHERE descricao='debito'  AND fk_API_pagamento_id_proprietario=@u_alice LIMIT 1);
SET @pg_pix     = (SELECT codigo FROM Pagamento_tipo_pagamento WHERE descricao='pix'     AND fk_API_pagamento_id_proprietario=@u_alice LIMIT 1);
SET @pg_boleto  = (SELECT codigo FROM Pagamento_tipo_pagamento WHERE descricao='boleto'  AND fk_API_pagamento_id_proprietario=@u_alice LIMIT 1);

-- =========================
-- 7) Realiza (pagamentos efetuados pelos usuários)
-- =========================
INSERT INTO Realiza
  (fk_Usuario_ID, fk_Pagamento_tipo_pagamento_codigo, valor_pago, data_vencimento, data_do_pagamento, status_)
VALUES
  (@u_bruno, @pg_pix,    0.00, CURDATE(),      CURDATE(),      'pago'),
  (@u_carla, @pg_credito,99.90, DATE_ADD(CURDATE(), INTERVAL 7 DAY), NULL, 'pendente');

-- =========================
-- 8) Gerencia (quem gerencia o evento)
-- =========================
INSERT INTO Gerencia (fk_Usuario_ID, fk_Evento_codigo)
VALUES
  (@u_alice, @ev1),
  (@u_bruno, @ev1);

-- =========================
-- 9) Participa (quem participa de qual atividade)
-- =========================
INSERT INTO Participa (fk_Usuario_ID, fk_Atividade_ID)
VALUES
  (@u_alice, @atv_ia),
  (@u_bruno, @atv_ia),
  (@u_carla, @atv_rob);

-- =========================
-- 10) Certificado (para participantes)
-- =========================
INSERT INTO Certificado
  (data_emissao, texto_certificado, descricao, carga_horaria, template, qr_code, fk_Usuario_ID, fk_Atividade_ID)
VALUES
  (CURDATE(), 'Certificamos que Alice Silva participou da atividade "Inteligência Artificial na Prática".', 'Palestra sobre IA', 2, NULL, NULL, @u_alice, @atv_ia),
  (CURDATE(), 'Certificamos que Bruno Costa participou da atividade "Inteligência Artificial na Prática".', 'Palestra sobre IA', 2, NULL, NULL, @u_bruno, @atv_ia),
  (CURDATE(), 'Certificamos que Carla Souza participou da atividade "Robótica com Arduino".',                'Oficina de Robótica', 4, NULL, NULL, @u_carla, @atv_rob);

-- =========================
-- 11) Tipo (papéis)
-- =========================
INSERT INTO Tipo (descricao) VALUES ('participante'), ('organizador'), ('administrador_site')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

SET @t_part = (SELECT codigo FROM Tipo WHERE descricao='participante'       LIMIT 1);
SET @t_org  = (SELECT codigo FROM Tipo WHERE descricao='organizador'        LIMIT 1);
SET @t_adm  = (SELECT codigo FROM Tipo WHERE descricao='administrador_site' LIMIT 1);

-- =========================
-- 12) e_do (vínculo tipo ↔ usuário)
-- =========================
INSERT INTO e_do (fk_Tipo_codigo, fk_Usuario_ID)
VALUES
  (@t_org,  @u_alice),
  (@t_part, @u_bruno),
  (@t_part, @u_carla);

-- =========================
-- 13) Assinatura
-- =========================
INSERT INTO Assinatura (fk_Usuario_ID)
VALUES (@u_alice), (@u_bruno);

-- =========================
-- 14) Arquivo_CSV (exemplo de import)
-- =========================
INSERT INTO Arquivo_CSV
  (nome_atividade, num_participantes_atividade, num_participantes_evento, num_certificados_emitidos, palestrante_atividade, carga_horaria_atividade, nome_evento)
VALUES
  ('Inteligência Artificial na Prática', 50, 80, 0, 'Dr. Pedro Rocha', 2, 'Meu Evento 1'),
  ('Robótica com Arduino',               30, 80, 0, 'Profa. Carla Mendes', 4, 'Meu Evento 1');

SET @csv1 = (SELECT id FROM Arquivo_CSV WHERE nome_atividade='Inteligência Artificial na Prática' ORDER BY id DESC LIMIT 1);
SET @csv2 = (SELECT id FROM Arquivo_CSV WHERE nome_atividade='Robótica com Arduino'               ORDER BY id DESC LIMIT 1);

-- =========================
-- 15) Importa (quem importou qual arquivo)
-- =========================
INSERT INTO Importa (fk_Usuario_ID, fk_Arquivo_CSV_id)
VALUES (@u_alice, @csv1), (@u_bruno, @csv2);

CREATE VIEW sicad.total_atividades_evento AS
SELECT
  e.codigo AS evento_id,
  COUNT(a.ID) AS total_atividades
FROM sicad.evento AS e
LEFT JOIN sicad.atividade AS a
  ON a.fk_Evento_codigo = e.codigo
GROUP BY e.codigo;

CREATE VIEW sicad.total_certificados_evento AS
SELECT
  e.codigo AS evento_id,
  COUNT(c.codigo) AS total_certificados
FROM sicad.certificado AS c
JOIN sicad.atividade   AS a ON c.fk_Atividade_ID = a.ID
JOIN sicad.evento      AS e ON a.fk_Evento_codigo = e.codigo
GROUP BY e.codigo;


CREATE OR REPLACE VIEW sicad.vw_num_cadastrados_evento AS
SELECT
  v.evento_id,
  v.usuario_id
FROM sicad.vw_usuarios_evento AS v;


CREATE OR REPLACE VIEW sicad.vw_usuarios_evento AS
SELECT DISTINCT
  u.ID    AS usuario_id,
  u.nome  AS usuario_nome,
  u.email AS usuario_email,
  e.codigo AS evento_id,
  e.nome   AS evento_nome,
  'organizador' AS tipo_vinculo
FROM sicad.usuario   AS u
JOIN sicad.gerencia  AS g ON u.ID = g.fk_Usuario_ID
JOIN sicad.evento    AS e ON g.fk_Evento_codigo = e.codigo
UNION
SELECT DISTINCT
  u.ID    AS usuario_id,
  u.nome  AS usuario_nome,
  u.email AS usuario_email,
  e.codigo AS evento_id,
  e.nome   AS evento_nome,
  'participante' AS tipo_vinculo
FROM sicad.usuario    AS u
JOIN sicad.participa  AS p ON u.ID = p.fk_Usuario_ID
JOIN sicad.atividade  AS a ON p.fk_Atividade_ID = a.ID
JOIN sicad.evento     AS e ON a.fk_Evento_codigo = e.codigo;


CREATE TABLE templatecertificado (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    json JSON NOT NULL,
    imagem_preview BLOB NULL,
    imagem_render BLOB NULL,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fk_Atividade_ID INT NOT NULL,

    CONSTRAINT fk_templatecertificado_atividade
        FOREIGN KEY (fk_Atividade_ID)
        REFERENCES atividade(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


ALTER TABLE atividade
ADD COLUMN status_envio TINYINT(1) NOT NULL DEFAULT 0
COMMENT '0 = não enviado, 1 = enviado';


ALTER TABLE certificado
  ADD UNIQUE KEY uk_certificado_codigo (codigo);
  
 ALTER TABLE certificado
  MODIFY codigo_validacao CHAR(24) NOT NULL;

ALTER TABLE certificado
  ADD UNIQUE KEY uk_certificado_validacao (codigo_validacao);
