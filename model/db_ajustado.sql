CREATE SCHEMA IF NOT EXISTS SICAD;
USE SICAD;

-- =========================
-- TABELA USUARIO
-- =========================

CREATE TABLE usuario (
    ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    num_matricula INT, 
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    cpf VARCHAR(20) NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    telefone VARCHAR(20) NULL,
    assinatura BLOB NULL,
    token VARCHAR(255) NULL,
    email_verificado TINYINT(1) NOT NULL DEFAULT 0
);

-- =========================
-- TABELA VERIFICACAO_EMAIL
-- =========================

CREATE TABLE verificacao_email (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    hash_token VARCHAR(255) NOT NULL,
    expira_em DATETIME NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_verificacao_email_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuario(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================
-- TABELA REDEFINICAO_SENHA
-- =========================

CREATE TABLE redefinicao_senha (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    hash_token VARCHAR(255) NOT NULL,
    expira_em DATETIME NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_redefinicao_senha_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuario(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================
-- TABELA MODALIDADE
-- =========================

CREATE TABLE modalidade (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome ENUM('presencial', 'online', 'hibrido') NOT NULL
);

-- =========================
-- TABELA EVENTO
-- =========================

CREATE TABLE evento (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    descricao TEXT NULL,
    nome VARCHAR(100) NULL,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    responsavel_evento VARCHAR(255) NULL
);

-- =========================
-- TABELA ATIVIDADE
-- =========================

CREATE TABLE atividade (
    ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    informacoes_atividade TEXT NULL,
    carga_horaria INT NULL,
    nome VARCHAR(100) NULL,
    palestrante VARCHAR(100) NULL,
    status_atividade ENUM('confirmada', 'cancelada', 'realizada') NULL,
    num_participantes INT NULL,
    fk_Evento_codigo INT NOT NULL,
    fk_Modalidade_codigo INT NOT NULL,
    codigo_qr_code VARCHAR (255),
    imagem_qr_code BLOB 

    CONSTRAINT fk_atividade_evento
        FOREIGN KEY (fk_Evento_codigo)
        REFERENCES evento(codigo)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_atividade_modalidade
        FOREIGN KEY (fk_Modalidade_codigo)
        REFERENCES modalidade(codigo)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- =========================
-- TABELA TEMPLATECERTIFICADO
-- =========================

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

-- =========================
-- TABELA CERTIFICADO
-- =========================

CREATE TABLE certificado (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    data_emissao DATE NULL,
    texto_certificado TEXT NULL,
    descricao TEXT NULL,
    carga_horaria INT NULL,
    template BLOB NULL,
    qr_code BLOB NULL,
    fk_Usuario_ID INT NOT NULL,
    fk_Atividade_ID INT NOT NULL,
    status_envio TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = não enviado, 1 = enviado',
    codigo_certificado VARCHAR(100) NULL,
    fk_TemplateCertificado_id INT NULL,
    codigo_validacao CHAR(24) NOT NULL,

    CONSTRAINT fk_certificado_usuario
        FOREIGN KEY (fk_Usuario_ID)
        REFERENCES usuario(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_certificado_atividade
        FOREIGN KEY (fk_Atividade_ID)
        REFERENCES atividade(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_certificado_templatecertificado
        FOREIGN KEY (fk_TemplateCertificado_id)
        REFERENCES templatecertificado(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT uk_certificado_codigo_validacao
        UNIQUE (codigo_validacao)
);

-- =========================
-- TABELA API_PAGAMENTO
-- =========================

CREATE TABLE api_pagamento (
    id_proprietario INT NOT NULL PRIMARY KEY,
    valor_total_em_caixa DECIMAL(10,2) NULL,
    secret_key VARCHAR(255) NULL,
    cpf_cnpj VARCHAR(255) NULL,
    num_conta_corrente VARCHAR(30) NULL,
    nome_proprietario VARCHAR(255) NULL,
    telefone_proprietario VARCHAR(30) NULL
);

-- =========================
-- TABELA PAGAMENTO_TIPO_PAGAMENTO
-- =========================

CREATE TABLE pagamento_tipo_pagamento (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    taxa_emolumentos DECIMAL(10,2) NULL,
    descricao ENUM('credito', 'debito', 'pix', 'boleto') NULL,
    fk_API_pagamento_id_proprietario INT NULL,

    CONSTRAINT fk_pagamento_tipo_api_pagamento
        FOREIGN KEY (fk_API_pagamento_id_proprietario)
        REFERENCES api_pagamento(id_proprietario)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- =========================
-- TABELA REALIZA
-- =========================

CREATE TABLE realiza (
    fk_Usuario_ID INT NOT NULL,
    fk_Pagamento_tipo_pagamento_codigo INT NOT NULL,
    valor_pago DECIMAL(10,2) NULL,
    data_vencimento DATE NULL,
    data_do_pagamento DATE NULL,
    status_ ENUM('pendente', 'pago', 'cancelado', 'estornado') NOT NULL,

    CONSTRAINT fk_realiza_usuario
        FOREIGN KEY (fk_Usuario_ID)
        REFERENCES usuario(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_realiza_pagamento_tipo
        FOREIGN KEY (fk_Pagamento_tipo_pagamento_codigo)
        REFERENCES pagamento_tipo_pagamento(codigo)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================
-- TABELA GERENCIA
-- =========================

CREATE TABLE gerencia (
    fk_Usuario_ID INT NOT NULL,
    fk_Evento_codigo INT NOT NULL,

    CONSTRAINT pk_gerencia
        PRIMARY KEY (fk_Usuario_ID, fk_Evento_codigo),

    CONSTRAINT fk_gerencia_usuario
        FOREIGN KEY (fk_Usuario_ID)
        REFERENCES usuario(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_gerencia_evento
        FOREIGN KEY (fk_Evento_codigo)
        REFERENCES evento(codigo)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================
-- TABELA PARTICIPA
-- =========================

CREATE TABLE participa (
    fk_Atividade_ID INT NOT NULL,
    fk_Usuario_ID INT NOT NULL,
    presenca BOOLEAN NOT NULL DEFAULT FALSE,
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_participa
        PRIMARY KEY (fk_Atividade_ID, fk_Usuario_ID),

    CONSTRAINT fk_participa_atividade
        FOREIGN KEY (fk_Atividade_ID)
        REFERENCES atividade(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_participa_usuario
        FOREIGN KEY (fk_Usuario_ID)
        REFERENCES usuario(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================
-- TABELA TIPO
-- =========================

CREATE TABLE tipo (
    codigo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    descricao ENUM('participante', 'organizador', 'administrador_site') NULL
);

-- =========================
-- TABELA E_DO
-- =========================

CREATE TABLE e_do (
    fk_Tipo_codigo INT NOT NULL,
    fk_Usuario_ID INT NOT NULL,

    CONSTRAINT pk_e_do
        PRIMARY KEY (fk_Tipo_codigo, fk_Usuario_ID),

    CONSTRAINT fk_e_do_tipo
        FOREIGN KEY (fk_Tipo_codigo)
        REFERENCES tipo(codigo)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_e_do_usuario
        FOREIGN KEY (fk_Usuario_ID)
        REFERENCES usuario(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================
-- TABELA ASSINATURA
-- =========================

CREATE TABLE assinatura (
    id_assinatura INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_Usuario_ID INT NOT NULL,

    CONSTRAINT fk_assinatura_usuario
        FOREIGN KEY (fk_Usuario_ID)
        REFERENCES usuario(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================
-- TABELA ARQUIVO_CSV
-- =========================

CREATE TABLE arquivo_csv (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome_atividade VARCHAR(100) NULL,
    num_participantes_atividade INT NULL,
    num_participantes_evento INT NULL,
    num_certificados_emitidos INT NULL,
    palestrante_atividade VARCHAR(100) NULL,
    carga_horaria_atividade INT NULL,
    nome_evento VARCHAR(100) NULL
);

-- =========================
-- TABELA IMPORTA
-- =========================

CREATE TABLE importa (
    fk_Usuario_ID INT NOT NULL,
    fk_Arquivo_CSV_id INT NOT NULL,

    CONSTRAINT pk_importa
        PRIMARY KEY (fk_Usuario_ID, fk_Arquivo_CSV_id),

    CONSTRAINT fk_importa_usuario
        FOREIGN KEY (fk_Usuario_ID)
        REFERENCES usuario(ID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_importa_arquivo_csv
        FOREIGN KEY (fk_Arquivo_CSV_id)
        REFERENCES arquivo_csv(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================
-- VIEWS
-- =========================

CREATE OR REPLACE VIEW vw_usuarios_evento AS
SELECT DISTINCT
    u.ID AS usuario_id,
    u.nome AS usuario_nome,
    u.email AS usuario_email,
    e.codigo AS evento_id,
    e.nome AS evento_nome,
    'organizador' AS tipo_vinculo
FROM usuario AS u
JOIN gerencia AS g
    ON u.ID = g.fk_Usuario_ID
JOIN evento AS e
    ON g.fk_Evento_codigo = e.codigo

UNION

SELECT DISTINCT
    u.ID AS usuario_id,
    u.nome AS usuario_nome,
    u.email AS usuario_email,
    e.codigo AS evento_id,
    e.nome AS evento_nome,
    'participante' AS tipo_vinculo
FROM usuario AS u
JOIN participa AS p
    ON u.ID = p.fk_Usuario_ID
JOIN atividade AS a
    ON p.fk_Atividade_ID = a.ID
JOIN evento AS e
    ON a.fk_Evento_codigo = e.codigo;

CREATE OR REPLACE VIEW vw_num_cadastrados_evento AS
SELECT
    v.evento_id,
    v.usuario_id
FROM vw_usuarios_evento AS v;

CREATE OR REPLACE VIEW total_atividades_evento AS
SELECT
    e.codigo AS evento_id,
    COUNT(a.ID) AS total_atividades
FROM evento AS e
LEFT JOIN atividade AS a
    ON a.fk_Evento_codigo = e.codigo
GROUP BY e.codigo;

CREATE OR REPLACE VIEW total_certificados_evento AS
SELECT
    e.codigo AS evento_id,
    COUNT(c.codigo) AS total_certificados
FROM certificado AS c
JOIN atividade AS a
    ON c.fk_Atividade_ID = a.ID
JOIN evento AS e
    ON a.fk_Evento_codigo = e.codigo
GROUP BY e.codigo;