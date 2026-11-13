-- =====================================================================
-- ContaFácil — Estrutura do banco de dados
-- MySQL 8.0.16+ (necessário para as restrições CHECK)
--
-- ATENÇÃO: este script RECRIA o banco do zero (DROP TABLE).
-- Não execute em uma base que contenha dados que devam ser mantidos.
--
-- Os dados de demonstração (usuários, clientes, obrigações e documentos)
-- ficam em database/seed.sql. Aqui há apenas o catálogo de tipos de
-- obrigação, necessário para o funcionamento do sistema.
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS contafacil
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE contafacil;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS documentos;
DROP TABLE IF EXISTS obrigacoes;
DROP TABLE IF EXISTS tipos_obrigacao;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- usuarios
-- Perfis ativos na versão 1: admin e contador.
-- O perfil 'cliente' está reservado para o portal da versão 2: exige
-- cliente_id e é recusado no login pela aplicação (config auth.perfis_ativos).
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome          VARCHAR(120)  NOT NULL,
    email         VARCHAR(160)  NOT NULL,
    senha_hash    VARCHAR(255)  NOT NULL,
    perfil        ENUM('admin', 'contador', 'cliente') NOT NULL DEFAULT 'contador',
    cliente_id    INT UNSIGNED  NULL,
    ativo         TINYINT(1)    NOT NULL DEFAULT 1,
    ultimo_login  DATETIME      NULL,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_cliente (cliente_id),

    -- Somente o perfil 'cliente' é vinculado a um cliente, e sempre é.
    CONSTRAINT ck_usuarios_perfil_cliente CHECK (
        (perfil = 'cliente'  AND cliente_id IS NOT NULL) OR
        (perfil <> 'cliente' AND cliente_id IS NULL)
    ),
    CONSTRAINT ck_usuarios_ativo CHECK (ativo IN (0, 1))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- clientes
-- cpf_cnpj armazenado somente com dígitos (11 para PF, 14 para PJ).
-- ---------------------------------------------------------------------
CREATE TABLE clientes (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    razao_social      VARCHAR(160) NOT NULL,
    nome_fantasia     VARCHAR(160) NULL,
    tipo_pessoa       ENUM('PF', 'PJ') NOT NULL,
    cpf_cnpj          VARCHAR(14)  NOT NULL,
    regime_tributario ENUM('MEI', 'Simples Nacional', 'Lucro Presumido', 'Lucro Real', 'Pessoa Física') NOT NULL,
    email             VARCHAR(160) NULL,
    telefone          VARCHAR(20)  NULL,
    responsavel_id    INT UNSIGNED NOT NULL,
    status            ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    observacoes       TEXT         NULL,
    criado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_clientes_cpf_cnpj (cpf_cnpj),
    KEY idx_clientes_razao_social (razao_social),
    KEY idx_clientes_status (status),
    KEY idx_clientes_responsavel (responsavel_id),

    CONSTRAINT fk_clientes_responsavel
        FOREIGN KEY (responsavel_id) REFERENCES usuarios (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT ck_clientes_documento CHECK (
        cpf_cnpj REGEXP '^[0-9]+$' AND (
            (tipo_pessoa = 'PF' AND CHAR_LENGTH(cpf_cnpj) = 11) OR
            (tipo_pessoa = 'PJ' AND CHAR_LENGTH(cpf_cnpj) = 14)
        )
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Vínculo usuário -> cliente (portal da versão 2).
-- Adicionado após a criação de 'clientes' porque as duas tabelas se referenciam.
-- Sem ON DELETE/ON UPDATE explícito (padrão do MySQL = RESTRICT): o MySQL não
-- permite CASCADE/SET NULL em colunas usadas por CHECK (ck_usuarios_perfil_cliente).
ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuarios_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes (id);

-- ---------------------------------------------------------------------
-- tipos_obrigacao (catálogo)
-- ---------------------------------------------------------------------
CREATE TABLE tipos_obrigacao (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome          VARCHAR(80)  NOT NULL,
    descricao     VARCHAR(255) NULL,
    periodicidade ENUM('mensal', 'trimestral', 'anual', 'eventual') NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_tipos_obrigacao_nome (nome)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- obrigacoes
-- A situação "atrasada" NÃO é gravada: é calculada pelo Radar
-- (data_vencimento < hoje e status em aberto).
-- competencia é sempre o 1º dia do mês de referência.
-- ---------------------------------------------------------------------
CREATE TABLE obrigacoes (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    cliente_id        INT UNSIGNED  NOT NULL,
    tipo_obrigacao_id INT UNSIGNED  NOT NULL,
    competencia       DATE          NOT NULL,
    data_vencimento   DATE          NOT NULL,
    status            ENUM('pendente', 'em_andamento', 'concluida', 'cancelada') NOT NULL DEFAULT 'pendente',
    data_conclusao    DATE          NULL,
    valor             DECIMAL(12,2) NULL,
    responsavel_id    INT UNSIGNED  NOT NULL,
    observacoes       TEXT          NULL,
    criado_em         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Impede a mesma obrigação duplicada para o mesmo cliente e competência.
    UNIQUE KEY uk_obrigacoes_cliente_tipo_competencia (cliente_id, tipo_obrigacao_id, competencia),
    -- Necessária para a FK composta de documentos (garante o mesmo cliente).
    UNIQUE KEY uk_obrigacoes_id_cliente (id, cliente_id),
    KEY idx_obrigacoes_status_vencimento (status, data_vencimento),
    KEY idx_obrigacoes_tipo (tipo_obrigacao_id),
    KEY idx_obrigacoes_responsavel (responsavel_id),

    CONSTRAINT fk_obrigacoes_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_obrigacoes_tipo
        FOREIGN KEY (tipo_obrigacao_id) REFERENCES tipos_obrigacao (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_obrigacoes_responsavel
        FOREIGN KEY (responsavel_id) REFERENCES usuarios (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    CONSTRAINT ck_obrigacoes_competencia CHECK (DAYOFMONTH(competencia) = 1),
    CONSTRAINT ck_obrigacoes_conclusao CHECK (
        (status = 'concluida'  AND data_conclusao IS NOT NULL) OR
        (status <> 'concluida' AND data_conclusao IS NULL)
    ),
    CONSTRAINT ck_obrigacoes_valor CHECK (valor IS NULL OR valor >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- documentos
-- Fluxo: solicitado -> recebido -> aprovado | rejeitado
--   (rejeitado volta a 'recebido' quando um novo arquivo é registrado).
-- Na versão 1 o contador registra o recebimento (registrado_por) e informa
-- o canal; o canal 'sistema' fica reservado para o portal da versão 2.
-- ---------------------------------------------------------------------
CREATE TABLE documentos (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id        INT UNSIGNED NOT NULL,
    obrigacao_id      INT UNSIGNED NULL,
    titulo            VARCHAR(160) NOT NULL,
    descricao         TEXT         NULL,
    status            ENUM('solicitado', 'recebido', 'aprovado', 'rejeitado') NOT NULL DEFAULT 'solicitado',
    prazo_envio       DATE         NULL,
    canal_recebimento ENUM('email', 'whatsapp', 'presencial', 'sistema') NULL,
    arquivo_original  VARCHAR(255) NULL,
    arquivo_armazenado VARCHAR(64) NULL,
    mime_type         VARCHAR(100) NULL,
    tamanho_bytes     INT UNSIGNED NULL,
    registrado_por    INT UNSIGNED NULL,
    recebido_em       DATETIME     NULL,
    analisado_por     INT UNSIGNED NULL,
    analisado_em      DATETIME     NULL,
    motivo_rejeicao   TEXT         NULL,
    criado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_documentos_arquivo (arquivo_armazenado),
    KEY idx_documentos_cliente_status (cliente_id, status),
    KEY idx_documentos_status_prazo (status, prazo_envio),
    KEY idx_documentos_obrigacao (obrigacao_id, cliente_id),
    KEY idx_documentos_registrado_por (registrado_por),
    KEY idx_documentos_analisado_por (analisado_por),

    CONSTRAINT fk_documentos_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    -- FK composta: um documento só pode ser vinculado a uma obrigação
    -- do MESMO cliente. Quando obrigacao_id é NULL, a FK não é verificada.
    CONSTRAINT fk_documentos_obrigacao
        FOREIGN KEY (obrigacao_id, cliente_id) REFERENCES obrigacoes (id, cliente_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_documentos_registrado_por
        FOREIGN KEY (registrado_por) REFERENCES usuarios (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_documentos_analisado_por
        FOREIGN KEY (analisado_por) REFERENCES usuarios (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    -- Solicitado: ainda sem arquivo. Demais status: arquivo e data de recebimento obrigatórios.
    CONSTRAINT ck_documentos_arquivo CHECK (
        (status = 'solicitado' AND arquivo_armazenado IS NULL AND recebido_em IS NULL) OR
        (status <> 'solicitado' AND arquivo_armazenado IS NOT NULL AND recebido_em IS NOT NULL)
    ),
    -- Aprovado/rejeitado exigem data de análise; rejeitado exige motivo.
    CONSTRAINT ck_documentos_analise CHECK (
        (status IN ('solicitado', 'recebido') AND analisado_em IS NULL) OR
        (status = 'aprovado'  AND analisado_em IS NOT NULL) OR
        (status = 'rejeitado' AND analisado_em IS NOT NULL AND motivo_rejeicao IS NOT NULL)
    ),
    CONSTRAINT ck_documentos_tamanho CHECK (tamanho_bytes IS NULL OR tamanho_bytes > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Dados iniciais necessários: catálogo de tipos de obrigação
-- ---------------------------------------------------------------------
INSERT INTO tipos_obrigacao (nome, descricao, periodicidade) VALUES
    ('DAS',            'Documento de Arrecadação do Simples Nacional',                         'mensal'),
    ('DAS-MEI',        'Guia mensal do Microempreendedor Individual',                          'mensal'),
    ('DCTFWeb',        'Declaração de Débitos e Créditos Tributários Federais Previdenciários', 'mensal'),
    ('FGTS Digital',   'Recolhimento mensal do FGTS',                                          'mensal'),
    ('EFD-Reinf',      'Escrituração Fiscal Digital de Retenções e Outras Informações Fiscais', 'mensal'),
    ('EFD ICMS/IPI',   'Escrituração Fiscal Digital do ICMS e do IPI (SPED Fiscal)',           'mensal'),
    ('DASN-SIMEI',     'Declaração Anual do Simples Nacional para o MEI',                      'anual'),
    ('DEFIS',          'Declaração de Informações Socioeconômicas e Fiscais (Simples Nacional)', 'anual'),
    ('ECD',            'Escrituração Contábil Digital',                                        'anual'),
    ('ECF',            'Escrituração Contábil Fiscal',                                         'anual'),
    ('DIRPF',          'Declaração do Imposto de Renda da Pessoa Física',                      'anual');
