-- =====================================================================
-- ContaFácil — Dados de demonstração (seed)
--
-- USO EXCLUSIVO EM DESENVOLVIMENTO / AVALIAÇÃO. NÃO USAR EM PRODUÇÃO.
--
-- Pré-requisito: database/schema.sql já executado.
--
-- CREDENCIAIS DE DEMONSTRAÇÃO
--   Perfil       E-mail                       Senha
--   admin        admin@contafacil.test        Admin@123
--   contador     contador@contafacil.test     Contador@123
--
-- As senhas estão gravadas apenas como hash bcrypt (password_hash do PHP),
-- compatíveis com password_verify().
--
-- DADOS FICTÍCIOS
--   Todos os nomes, e-mails (domínios .test/.example), telefones e
--   CPFs/CNPJs são inventados. Os CPFs/CNPJs foram gerados apenas para
--   ter dígitos verificadores válidos e não pertencem a pessoas reais.
--
-- DATAS RELATIVAS
--   Vencimentos, prazos e competências são calculados a partir de CURDATE().
--   Assim, o Radar Contábil sempre mostra todas as situações (atrasadas,
--   vencendo hoje, nos próximos dias, documentos pendentes etc.),
--   em qualquer dia em que o script for executado.
--
-- PODE SER EXECUTADO MAIS DE UMA VEZ
--   - Usuários e clientes de demonstração são atualizados (sem duplicar),
--     identificados pelo e-mail e pelo CPF/CNPJ.
--   - Obrigações e documentos DOS CLIENTES DE DEMONSTRAÇÃO são apagados e
--     recriados com datas atualizadas. Dados de outros clientes não são tocados.
--   - Reexecutar redefine as senhas dos usuários de demonstração.
--
-- Os arquivos físicos dos documentos de demonstração NÃO existem em
-- storage/uploads: os registros servem para o Radar, o Dashboard e as listagens.
-- =====================================================================

SET NAMES utf8mb4;
USE contafacil;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Limpeza dos dados dependentes dos clientes de demonstração
-- ---------------------------------------------------------------------
DELETE d FROM documentos d
  JOIN clientes c ON c.id = d.cliente_id
 WHERE c.cpf_cnpj IN ('11222333000181', '22333444000181', '33444555000181', '44555666000181',
                      '55666777000181', '66777888000181', '77888999000181', '88999000000198',
                      '99000111000165', '10111213000144', '11223344002', '55667788012');

DELETE o FROM obrigacoes o
  JOIN clientes c ON c.id = o.cliente_id
 WHERE c.cpf_cnpj IN ('11222333000181', '22333444000181', '33444555000181', '44555666000181',
                      '55666777000181', '66777888000181', '77888999000181', '88999000000198',
                      '99000111000165', '10111213000144', '11223344002', '55667788012');

-- ---------------------------------------------------------------------
-- 2. Usuários (perfis ativos na versão 1: admin e contador)
-- ---------------------------------------------------------------------
INSERT INTO usuarios (nome, email, senha_hash, perfil, cliente_id, ativo) VALUES
    ('Administrador ContaFácil', 'admin@contafacil.test',
     '$2y$10$62XOYzJXAcVq4fd8UNVibu8gUGoRzGggFKpbRuOlFVcEL0y4JEa4C', 'admin', NULL, 1),
    ('Contador Demonstração', 'contador@contafacil.test',
     '$2y$10$wpDVunWM8wn9NACP/XLlyeCx57o7M0M9yO1PHcOWqNOlSR54hiwv6', 'contador', NULL, 1)
ON DUPLICATE KEY UPDATE
    nome       = VALUES(nome),
    senha_hash = VALUES(senha_hash),
    perfil     = VALUES(perfil),
    cliente_id = VALUES(cliente_id),
    ativo      = VALUES(ativo);

SET @admin    = (SELECT id FROM usuarios WHERE email = 'admin@contafacil.test');
SET @contador = (SELECT id FROM usuarios WHERE email = 'contador@contafacil.test');

-- ---------------------------------------------------------------------
-- 3. Clientes (10 PJ + 2 PF; 1 inativo; responsáveis divididos
--    entre admin e contador para demonstrar o filtro "meus clientes")
-- ---------------------------------------------------------------------
INSERT INTO clientes
    (razao_social, nome_fantasia, tipo_pessoa, cpf_cnpj, regime_tributario,
     email, telefone, responsavel_id, status, observacoes)
VALUES
    ('Padaria Pão Dourado Ltda', 'Pão Dourado', 'PJ', '11222333000181', 'Simples Nacional',
     'contato@paodourado.example', '(86) 90000-0001', @contador, 'ativo', 'Cliente com histórico de envio atrasado de documentos.'),
    ('Oficina Mecânica Roda Viva Ltda', 'Roda Viva', 'PJ', '22333444000181', 'Simples Nacional',
     'financeiro@rodaviva.example', '(86) 90000-0002', @contador, 'ativo', NULL),
    ('Bela Forma Estética MEI', 'Studio Bela Forma', 'PJ', '33444555000181', 'MEI',
     'studio@belaforma.example', '(86) 90000-0003', @contador, 'ativo', NULL),
    ('TechNova Soluções em Software Ltda', 'TechNova', 'PJ', '44555666000181', 'Lucro Presumido',
     'adm@technova.example', '(86) 90000-0004', @admin, 'ativo', NULL),
    ('Distribuidora Vale do Parnaíba Ltda', 'Vale Distribuidora', 'PJ', '55666777000181', 'Lucro Real',
     'fiscal@valedistribuidora.example', '(86) 90000-0005', @contador, 'ativo', 'Grande volume de notas fiscais de entrada.'),
    ('Construtora Horizonte Ltda', 'Horizonte', 'PJ', '66777888000181', 'Lucro Presumido',
     'contato@horizonte.example', '(86) 90000-0006', @contador, 'ativo', NULL),
    ('Clínica Vida Plena Ltda', 'Vida Plena', 'PJ', '77888999000181', 'Simples Nacional',
     'adm@vidaplena.example', '(86) 90000-0007', @admin, 'ativo', NULL),
    ('Livraria Página Nova MEI', 'Página Nova', 'PJ', '88999000000198', 'MEI',
     'livraria@paginanova.example', '(86) 90000-0008', @admin, 'ativo', NULL),
    ('Agência Pixel Criativo Ltda', 'Pixel Criativo', 'PJ', '99000111000165', 'Simples Nacional',
     'ola@pixelcriativo.example', '(86) 90000-0009', @contador, 'ativo', NULL),
    ('Restaurante Sabor da Terra Ltda', 'Sabor da Terra', 'PJ', '10111213000144', 'Simples Nacional',
     'contato@sabordaterra.example', '(86) 90000-0010', @admin, 'inativo', 'Encerrou as atividades; mantido para histórico.'),
    ('João Exemplo da Silva', NULL, 'PF', '11223344002', 'Pessoa Física',
     'joao.exemplo@email.example', '(86) 90000-0011', @admin, 'ativo', NULL),
    ('Maria Fictícia Costa', NULL, 'PF', '55667788012', 'Pessoa Física',
     'maria.ficticia@email.example', '(86) 90000-0012', @contador, 'ativo', NULL)
ON DUPLICATE KEY UPDATE
    razao_social      = VALUES(razao_social),
    nome_fantasia     = VALUES(nome_fantasia),
    tipo_pessoa       = VALUES(tipo_pessoa),
    regime_tributario = VALUES(regime_tributario),
    email             = VALUES(email),
    telefone          = VALUES(telefone),
    responsavel_id    = VALUES(responsavel_id),
    status            = VALUES(status),
    observacoes       = VALUES(observacoes);

SET @c_padaria     = (SELECT id FROM clientes WHERE cpf_cnpj = '11222333000181');
SET @c_oficina     = (SELECT id FROM clientes WHERE cpf_cnpj = '22333444000181');
SET @c_belaforma   = (SELECT id FROM clientes WHERE cpf_cnpj = '33444555000181');
SET @c_technova    = (SELECT id FROM clientes WHERE cpf_cnpj = '44555666000181');
SET @c_distrib     = (SELECT id FROM clientes WHERE cpf_cnpj = '55666777000181');
SET @c_construtora = (SELECT id FROM clientes WHERE cpf_cnpj = '66777888000181');
SET @c_clinica     = (SELECT id FROM clientes WHERE cpf_cnpj = '77888999000181');
SET @c_livraria    = (SELECT id FROM clientes WHERE cpf_cnpj = '88999000000198');
SET @c_agencia     = (SELECT id FROM clientes WHERE cpf_cnpj = '99000111000165');
SET @c_restaurante = (SELECT id FROM clientes WHERE cpf_cnpj = '10111213000144');
SET @c_joao        = (SELECT id FROM clientes WHERE cpf_cnpj = '11223344002');
SET @c_maria       = (SELECT id FROM clientes WHERE cpf_cnpj = '55667788012');

-- ---------------------------------------------------------------------
-- 4. Tipos de obrigação (cadastrados pelo schema.sql)
-- ---------------------------------------------------------------------
SET @t_das    = (SELECT id FROM tipos_obrigacao WHERE nome = 'DAS');
SET @t_dasmei = (SELECT id FROM tipos_obrigacao WHERE nome = 'DAS-MEI');
SET @t_dctf   = (SELECT id FROM tipos_obrigacao WHERE nome = 'DCTFWeb');
SET @t_fgts   = (SELECT id FROM tipos_obrigacao WHERE nome = 'FGTS Digital');
SET @t_reinf  = (SELECT id FROM tipos_obrigacao WHERE nome = 'EFD-Reinf');
SET @t_icms   = (SELECT id FROM tipos_obrigacao WHERE nome = 'EFD ICMS/IPI');
SET @t_defis  = (SELECT id FROM tipos_obrigacao WHERE nome = 'DEFIS');
SET @t_ecd    = (SELECT id FROM tipos_obrigacao WHERE nome = 'ECD');
SET @t_ecf    = (SELECT id FROM tipos_obrigacao WHERE nome = 'ECF');
SET @t_dirpf  = (SELECT id FROM tipos_obrigacao WHERE nome = 'DIRPF');

-- Competências (sempre o 1º dia do mês, exigido pelo CHECK):
--   @comp1 = mês anterior, @comp2 = dois meses atrás, @comp3 = três meses atrás,
--   @ano_ant = ano-calendário anterior (obrigações anuais).
SET @comp1   = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01');
SET @comp2   = DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01');
SET @comp3   = DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m-01');
SET @ano_ant = DATE_FORMAT(CURDATE() - INTERVAL 1 YEAR,  '%Y-01-01');

-- ---------------------------------------------------------------------
-- 5. Obrigações (32 registros)
--    Situação no Radar indicada no comentário de cada linha.
--    Regra do CHECK: concluida <=> data_conclusao preenchida.
-- ---------------------------------------------------------------------
INSERT INTO obrigacoes
    (cliente_id, tipo_obrigacao_id, competencia, data_vencimento, status, data_conclusao, valor, responsavel_id, observacoes)
VALUES
    -- Padaria Pão Dourado: cliente de maior risco
    (@c_padaria, @t_das,  @comp1, CURDATE() - INTERVAL 5 DAY,  'pendente',     NULL, 1850.40, @contador, 'Aguardando extrato bancário do cliente.'),            -- ATRASADA (bloqueada por documento)
    (@c_padaria, @t_fgts, @comp1, CURDATE(),                   'pendente',     NULL,  920.00, @contador, NULL),                                                  -- VENCE HOJE (bloqueada por documento)
    (@c_padaria, @t_dctf, @comp1, CURDATE() + INTERVAL 4 DAY,  'em_andamento', NULL,    NULL, @contador, NULL),                                                  -- vence em 4 dias
    (@c_padaria, @t_das,  @comp2, CURDATE() - INTERVAL 36 DAY, 'concluida',    CURDATE() - INTERVAL 38 DAY, 1720.15, @contador, NULL),                          -- concluída no prazo

    -- Oficina Roda Viva
    (@c_oficina, @t_dctf, @comp1, CURDATE() + INTERVAL 2 DAY,  'pendente',     NULL,    NULL, @contador, NULL),                                                  -- vence em 2 dias
    (@c_oficina, @t_das,  @comp1, CURDATE() + INTERVAL 9 DAY,  'pendente',     NULL,  640.00, @contador, NULL),                                                  -- fora da janela de 7 dias
    (@c_oficina, @t_das,  @comp2, CURDATE() - INTERVAL 31 DAY, 'concluida',    CURDATE() - INTERVAL 29 DAY,  598.30, @contador, 'Pago com 2 dias de atraso.'),  -- concluída com atraso

    -- Bela Forma (MEI)
    (@c_belaforma, @t_dasmei, @comp1, CURDATE() - INTERVAL 12 DAY, 'pendente',  NULL,   75.90, @contador, NULL),                                                  -- ATRASADA (documento rejeitado)
    (@c_belaforma, @t_dasmei, @comp2, CURDATE() - INTERVAL 42 DAY, 'concluida', CURDATE() - INTERVAL 45 DAY, 75.90, @contador, NULL),                            -- concluída no prazo

    -- TechNova (Lucro Presumido)
    (@c_technova, @t_reinf, @comp1,   CURDATE() + INTERVAL 6 DAY,  'em_andamento', NULL,    NULL, @admin, NULL),                                                 -- vence em 6 dias
    (@c_technova, @t_dctf,  @comp1,   CURDATE() - INTERVAL 3 DAY,  'concluida',    CURDATE() - INTERVAL 4 DAY, 3120.00, @admin, NULL),                           -- concluída no prazo
    (@c_technova, @t_fgts,  @comp1,   CURDATE() + INTERVAL 1 DAY,  'pendente',     NULL, 2480.75, @admin, NULL),                                                 -- vence amanhã
    (@c_technova, @t_ecf,   @ano_ant, CURDATE() + INTERVAL 45 DAY, 'pendente',     NULL,    NULL, @admin, NULL),                                                 -- longo prazo

    -- Distribuidora Vale do Parnaíba (Lucro Real)
    (@c_distrib, @t_icms, @comp1,   CURDATE() + INTERVAL 3 DAY,  'pendente',     NULL,    NULL, @contador, NULL),                                                -- vence em 3 dias (bloqueada por documento)
    (@c_distrib, @t_icms, @comp2,   CURDATE() - INTERVAL 28 DAY, 'concluida',    CURDATE() - INTERVAL 26 DAY,    NULL, @contador, 'Entregue com atraso.'),     -- concluída com atraso
    (@c_distrib, @t_ecd,  @ano_ant, CURDATE() + INTERVAL 25 DAY, 'pendente',     NULL,    NULL, @contador, NULL),                                                -- longo prazo
    (@c_distrib, @t_dctf, @comp1,   CURDATE() - INTERVAL 1 DAY,  'em_andamento', NULL, 5400.00, @contador, NULL),                                                -- ATRASADA (1 dia)

    -- Construtora Horizonte: tudo em dia
    (@c_construtora, @t_dctf,  @comp1, CURDATE() - INTERVAL 6 DAY,  'concluida', CURDATE() - INTERVAL 8 DAY, 4210.90, @contador, NULL),                          -- concluída no prazo
    (@c_construtora, @t_fgts,  @comp1, CURDATE() - INTERVAL 4 DAY,  'concluida', CURDATE() - INTERVAL 5 DAY, 3890.00, @contador, NULL),                          -- concluída no prazo
    (@c_construtora, @t_reinf, @comp1, CURDATE() + INTERVAL 14 DAY, 'pendente',  NULL, NULL, @contador, NULL),                                                   -- fora da janela

    -- Clínica Vida Plena
    (@c_clinica, @t_das,   @comp1,   CURDATE() + INTERVAL 3 DAY,    'pendente',  NULL, 2875.60, @admin, NULL),                                                   -- vence em 3 dias (doc. aguardando análise)
    (@c_clinica, @t_defis, @ano_ant, CURDATE() - INTERVAL 150 DAY,  'concluida', CURDATE() - INTERVAL 152 DAY, NULL, @admin, NULL),                              -- concluída no prazo
    (@c_clinica, @t_fgts,  @comp1,   CURDATE() + INTERVAL 5 DAY,    'cancelada', NULL, NULL, @admin, 'Sem funcionários no período.'),                            -- cancelada: ignorada pelo Radar

    -- Livraria Página Nova (MEI)
    (@c_livraria, @t_dasmei, @comp1, CURDATE() + INTERVAL 7 DAY,  'pendente',  NULL, 75.90, @admin, NULL),                                                       -- vence em 7 dias (limite da janela)
    (@c_livraria, @t_dasmei, @comp2, CURDATE() - INTERVAL 24 DAY, 'concluida', CURDATE() - INTERVAL 25 DAY, 75.90, @admin, NULL),                                -- concluída no prazo

    -- Agência Pixel Criativo
    (@c_agencia, @t_das,  @comp1, CURDATE() - INTERVAL 2 DAY,  'pendente',  NULL, 1340.20, @contador, NULL),                                                     -- ATRASADA
    (@c_agencia, @t_das,  @comp2, CURDATE() - INTERVAL 33 DAY, 'concluida', CURDATE() - INTERVAL 33 DAY, 1290.00, @contador, NULL),                              -- concluída no dia do vencimento
    (@c_agencia, @t_fgts, @comp1, CURDATE() + INTERVAL 12 DAY, 'pendente',  NULL,  610.00, @contador, NULL),                                                     -- fora da janela

    -- Restaurante Sabor da Terra (cliente inativo)
    (@c_restaurante, @t_das, @comp3, CURDATE() - INTERVAL 60 DAY, 'concluida', CURDATE() - INTERVAL 61 DAY, 980.00, @admin, 'Última apuração antes do encerramento.'),

    -- Pessoas físicas
    (@c_joao,  @t_dirpf, @ano_ant, CURDATE() - INTERVAL 170 DAY, 'concluida', CURDATE() - INTERVAL 175 DAY, NULL, @admin,    NULL),                              -- concluída no prazo
    (@c_maria, @t_dirpf, @ano_ant, CURDATE() - INTERVAL 170 DAY, 'concluida', CURDATE() - INTERVAL 168 DAY, NULL, @contador, 'Entregue com 2 dias de atraso.'),  -- concluída com atraso

    -- Bela Forma: declaração anual do MEI
    (@c_belaforma, (SELECT id FROM tipos_obrigacao WHERE nome = 'DASN-SIMEI'), @ano_ant,
     CURDATE() - INTERVAL 160 DAY, 'concluida', CURDATE() - INTERVAL 163 DAY, NULL, @contador, NULL);                                                            -- concluída no prazo

-- IDs das obrigações que recebem documentos vinculados
SET @o_padaria_das   = (SELECT id FROM obrigacoes WHERE cliente_id = @c_padaria   AND tipo_obrigacao_id = @t_das    AND competencia = @comp1);
SET @o_padaria_fgts  = (SELECT id FROM obrigacoes WHERE cliente_id = @c_padaria   AND tipo_obrigacao_id = @t_fgts   AND competencia = @comp1);
SET @o_oficina_das   = (SELECT id FROM obrigacoes WHERE cliente_id = @c_oficina   AND tipo_obrigacao_id = @t_das    AND competencia = @comp1);
SET @o_bela_dasmei   = (SELECT id FROM obrigacoes WHERE cliente_id = @c_belaforma AND tipo_obrigacao_id = @t_dasmei AND competencia = @comp1);
SET @o_tech_reinf    = (SELECT id FROM obrigacoes WHERE cliente_id = @c_technova  AND tipo_obrigacao_id = @t_reinf  AND competencia = @comp1);
SET @o_dist_icms     = (SELECT id FROM obrigacoes WHERE cliente_id = @c_distrib   AND tipo_obrigacao_id = @t_icms   AND competencia = @comp1);
SET @o_dist_ecd      = (SELECT id FROM obrigacoes WHERE cliente_id = @c_distrib   AND tipo_obrigacao_id = @t_ecd    AND competencia = @ano_ant);
SET @o_constr_fgts   = (SELECT id FROM obrigacoes WHERE cliente_id = @c_construtora AND tipo_obrigacao_id = @t_fgts AND competencia = @comp1);
SET @o_clinica_das   = (SELECT id FROM obrigacoes WHERE cliente_id = @c_clinica   AND tipo_obrigacao_id = @t_das    AND competencia = @comp1);
SET @o_livraria_mei2 = (SELECT id FROM obrigacoes WHERE cliente_id = @c_livraria  AND tipo_obrigacao_id = @t_dasmei AND competencia = @comp2);
SET @o_joao_dirpf    = (SELECT id FROM obrigacoes WHERE cliente_id = @c_joao      AND tipo_obrigacao_id = @t_dirpf  AND competencia = @ano_ant);

-- ---------------------------------------------------------------------
-- 6. Documentos (15 registros)
--    Regras do CHECK:
--      solicitado -> sem arquivo, sem recebido_em, sem analisado_em
--      recebido   -> com arquivo e recebido_em, sem analisado_em
--      aprovado   -> com arquivo, recebido_em e analisado_em
--      rejeitado  -> idem aprovado + motivo_rejeicao
--    O documento vinculado a uma obrigação pertence ao MESMO cliente
--    (FK composta obrigacao_id + cliente_id).
--    Canal 'sistema' não é usado: reservado ao portal da versão 2.
-- ---------------------------------------------------------------------
INSERT INTO documentos
    (cliente_id, obrigacao_id, titulo, descricao, status, prazo_envio, canal_recebimento,
     arquivo_original, arquivo_armazenado, mime_type, tamanho_bytes,
     registrado_por, recebido_em, analisado_por, analisado_em, motivo_rejeicao)
VALUES
    -- NÃO ENVIADOS (solicitado)
    (@c_padaria, @o_padaria_das, 'Extrato bancário do mês', 'Extrato de todas as contas da empresa.',
     'solicitado', CURDATE() - INTERVAL 3 DAY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),               -- prazo vencido + obrigação atrasada (bloqueio)
    (@c_padaria, @o_padaria_fgts, 'Folha de pagamento', 'Relatório da folha e eventos do mês.',
     'solicitado', CURDATE() + INTERVAL 1 DAY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),               -- obrigação vence hoje (bloqueio)
    (@c_distrib, @o_dist_icms, 'Arquivos XML das notas fiscais', 'XML de todas as notas de entrada e saída.',
     'solicitado', CURDATE() + INTERVAL 2 DAY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),               -- obrigação vence em 3 dias (bloqueio)
    (@c_distrib, @o_dist_ecd, 'Livro de inventário', 'Posição de estoque em 31/12.',
     'solicitado', CURDATE() + INTERVAL 5 DAY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),               -- dentro do prazo
    (@c_agencia, NULL, 'Extrato bancário do mês', NULL,
     'solicitado', CURDATE() - INTERVAL 6 DAY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),               -- prazo vencido
    (@c_maria, NULL, 'Informe de rendimentos bancários', 'Informe do banco para a próxima declaração.',
     'solicitado', CURDATE() + INTERVAL 10 DAY, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),              -- dentro do prazo

    -- AGUARDANDO ANÁLISE (recebido)
    (@c_oficina, @o_oficina_das, 'Notas fiscais de entrada', NULL,
     'recebido', CURDATE() - INTERVAL 7 DAY, 'email', 'notas_entrada.pdf', '5f9e338cb009b534a21477917b8b68c7.pdf',
     'application/pdf', 482133, @contador, NOW() - INTERVAL 5 DAY, NULL, NULL, NULL),                                     -- parado há 5 dias
    (@c_clinica, @o_clinica_das, 'Relatório de faturamento mensal', NULL,
     'recebido', CURDATE() - INTERVAL 5 DAY, 'whatsapp', 'faturamento.xlsx', '5ffbf5d9059b8f8a93a1d986084d93eb.xlsx',
     'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 38211, @admin, NOW() - INTERVAL 4 DAY, NULL, NULL, NULL), -- parado há 4 dias
    (@c_technova, @o_tech_reinf, 'Relatório de retenções na fonte', NULL,
     'recebido', CURDATE() + INTERVAL 2 DAY, 'email', 'retencoes.pdf', 'fe85ca14d51cc42c10fa51547d81b9a6.pdf',
     'application/pdf', 215870, @admin, NOW() - INTERVAL 1 DAY, NULL, NULL, NULL),                                        -- recebido ontem

    -- REJEITADOS (voltam a ser pendência do cliente)
    (@c_belaforma, @o_bela_dasmei, 'Notas fiscais de serviço', NULL,
     'rejeitado', CURDATE() - INTERVAL 10 DAY, 'whatsapp', 'foto_notas.jpg', 'afea19a1fa71803fb92dbf3b9ea88f9d.jpg',
     'image/jpeg', 1048576, @contador, NOW() - INTERVAL 8 DAY, @contador, NOW() - INTERVAL 7 DAY,
     'Imagem ilegível. Enviar as notas em PDF.'),
    (@c_agencia, NULL, 'Comprovante de endereço', 'Atualização cadastral.',
     'rejeitado', NULL, 'email', 'comprovante.pdf', '643ef4cf8e56f71b43194e53254574e8.pdf',
     'application/pdf', 95321, @contador, NOW() - INTERVAL 3 DAY, @contador, NOW() - INTERVAL 2 DAY,
     'Comprovante emitido há mais de 90 dias.'),

    -- APROVADOS
    (@c_technova, NULL, 'Contrato social atualizado', '7ª alteração contratual.',
     'aprovado', NULL, 'presencial', 'contrato_social.pdf', '5fad2b6dbbb73d45872cd956653b89f9.pdf',
     'application/pdf', 1730050, @admin, NOW() - INTERVAL 20 DAY, @admin, NOW() - INTERVAL 19 DAY, NULL),
    (@c_construtora, @o_constr_fgts, 'Guia do FGTS paga', NULL,
     'aprovado', CURDATE() - INTERVAL 7 DAY, 'email', 'guia_fgts.pdf', 'a3798657068059019ad4ce626321ffdb.pdf',
     'application/pdf', 120400, @contador, NOW() - INTERVAL 6 DAY, @contador, NOW() - INTERVAL 5 DAY, NULL),
    (@c_livraria, @o_livraria_mei2, 'Comprovante de pagamento do DAS-MEI', NULL,
     'aprovado', NULL, 'whatsapp', 'comprovante_das.pdf', '6413f8c256f3739d1b40d0209703281e.pdf',
     'application/pdf', 64000, @admin, NOW() - INTERVAL 25 DAY, @admin, NOW() - INTERVAL 24 DAY, NULL),
    (@c_joao, @o_joao_dirpf, 'Informe de rendimentos', 'Informe do empregador.',
     'aprovado', NULL, 'email', 'informe_rendimentos.pdf', '04baa8d75d21f196006e0ab1fcfa8f42.pdf',
     'application/pdf', 88450, @admin, NOW() - INTERVAL 180 DAY, @admin, NOW() - INTERVAL 178 DAY, NULL);

COMMIT;

-- ---------------------------------------------------------------------
-- Resumo esperado (com a janela padrão de 7 dias do Radar):
--   Obrigações atrasadas ........ 4  (Padaria DAS, Bela Forma DAS-MEI,
--                                     Distribuidora DCTFWeb, Agência DAS)
--   Vencem hoje ................. 1  (Padaria FGTS)
--   Vencem em 1 a 7 dias ........ 7
--   Documentos não enviados ..... 8  (6 solicitados + 2 rejeitados;
--                                     3 com prazo vencido)
--   Aguardando análise .......... 3  (2 parados há mais de 3 dias)
--   Bloqueios ................... 4  (obrigação atrasada ou vencendo em até
--                                     7 dias com documento vinculado ainda não
--                                     recebido: solicitado ou rejeitado)
--   Clientes: 12 (11 ativos, 1 inativo) | Obrigações: 32 | Documentos: 15
-- ---------------------------------------------------------------------
