# ContaFácil

Sistema web para organizar a relação entre um escritório de contabilidade e seus clientes: cadastro de clientes, controle de obrigações e prazos, solicitação e análise de documentos, e um painel que mostra o que precisa de atenção.

Projeto final da disciplina de Programação Web II, do Colégio Técnico de Teresina (CTT/UFPI).

## Objetivo

Escritórios contábeis costumam acompanhar prazos em planilhas e cobrar documentos por e-mail ou WhatsApp, sem uma visão única do que está atrasado. O ContaFácil centraliza essas informações e aponta automaticamente as pendências de cada cliente.

Esta versão é usada pela equipe do escritório (perfis **administrador** e **contador**). O portal para os próprios clientes está reservado para uma versão futura (veja [Limitações e próximos passos](#limitações-e-próximos-passos)).

## Funcionalidades

### Autenticação e acesso
- Login com e-mail e senha (senhas armazenadas com `password_hash`).
- Mensagem genérica em caso de falha, sem revelar se o e-mail existe.
- Sessão encerrada após 30 minutos sem atividade.
- Perfis com permissões diferentes: só o **admin** pode excluir registros. O perfil `cliente` existe no banco, mas é recusado no login nesta versão.

### Clientes
- Cadastro de pessoa física (CPF) e pessoa jurídica (CNPJ), com validação dos dígitos verificadores e bloqueio de CPF/CNPJ duplicado.
- Regime tributário (MEI, Simples Nacional, Lucro Presumido, Lucro Real ou Pessoa Física), responsável no escritório e situação (ativo/inativo).
- Pesquisa por razão social, nome fantasia ou CPF/CNPJ, filtros (tipo, regime, responsável, situação), ordenação e paginação.
- Exclusão bloqueada quando o cliente tem obrigações ou documentos vinculados (a orientação é marcá-lo como inativo).

### Contato pelo WhatsApp (assistido)
Na ficha do cliente, o contador pode abrir uma conversa no WhatsApp por um link `wa.me`, com uma mensagem já preenchida:
- **Modelos:** contato geral, documentos pendentes (solicitados ou rejeitados, aguardando o cliente), obrigações com vencimento passado (em tom neutro) e próximos vencimentos. As pendências seguem as mesmas regras do Radar: documentos em análise e obrigações concluídas ou canceladas não entram.
- **Mensagem editável:** o texto pode ser alterado na própria ficha antes de abrir o WhatsApp, e o envio é sempre feito pelo contador no WhatsApp.
- **Telefone:** precisa ter DDD válido; celulares com o nono dígito. Telefones fixos geram o link com o aviso de que podem não ter WhatsApp. Com telefone ausente ou inválido, a ficha mostra um aviso e o link para editar o cadastro. Clientes inativos não exibem o botão.
- **Privacidade:** as mensagens não incluem CPF/CNPJ, valores, observações, motivo de rejeição nem links do sistema.
- **O sistema não envia mensagens automaticamente, não registra histórico e não usa WAHA nem outra API.** O recurso não altera o banco de dados e não substitui o fluxo de trabalho existente: o recebimento de documentos continua sendo registrado no módulo Documentos.

### Obrigações
- Cadastro por cliente, a partir de um catálogo de 11 tipos (DAS, DAS-MEI, DCTFWeb, FGTS Digital, EFD-Reinf, EFD ICMS/IPI, DASN-SIMEI, DEFIS, ECD, ECF e DIRPF), com competência, vencimento, valor e responsável.
- Situações: pendente, em andamento, concluída e cancelada. Ações para concluir e cancelar.
- Indicação automática do prazo: atrasada, vence hoje, vence nos próximos 7 dias.
- Bloqueio de obrigação duplicada (mesmo cliente, tipo e competência).
- Pesquisa por cliente (nome ou CPF/CNPJ), tipo de obrigação ou observações; filtros (prazo, situação, cliente, tipo, responsável, competência, período de vencimento), ordenação e paginação.

### Documentos
- Solicitação de documentos ao cliente, opcionalmente ligada a uma obrigação, com prazo de envio.
- Registro do recebimento com canal (e-mail, WhatsApp ou presencial), data e arquivo.
- Envio de arquivos PDF, JPG, PNG, XML ou XLSX de até 5 MB. O servidor confere o tipo real do conteúdo, não apenas a extensão.
- Análise: aprovação ou rejeição com motivo obrigatório. Um documento rejeitado pode ser reenviado.
- Visualização e download apenas para usuários logados. Os arquivos ficam fora do alcance direto da web.
- Pesquisa por título, descrição, nome do arquivo ou cliente; filtros por situação, canal, cliente, obrigação e tipo de pendência; paginação.

### Radar Contábil (funcionalidade inovadora)
O Radar analisa obrigações e documentos e classifica cada cliente por nível de risco, sem pesos ou pontuações ocultas:

| Pendência | Gravidade |
|---|---|
| Obrigação em aberto com vencimento anterior a hoje | Alta |
| Documento não enviado com prazo de envio vencido | Alta |
| Obrigação que vence hoje ou nos próximos 7 dias | Média |
| Documento aguardando análise há mais de 3 dias | Média |
| Documento não enviado dentro do prazo ou sem prazo | Baixa |
| Documento aguardando análise há até 3 dias | Baixa |

O risco do cliente é a maior gravidade entre as suas pendências (alto, médio ou baixo). A tela mostra os clientes do mais crítico para o menos crítico, com filtros por cliente, responsável, nível de risco e tipo de pendência, e links diretos para cada registro. A regra também é explicada na própria tela.

### Painel (Dashboard)
- Indicadores de obrigações (em aberto, atrasadas, vencem hoje, próximos 7 dias, concluídas), clientes (cadastrados, ativos, inativos) e documentos (aguardando recebimento, aguardando análise, aprovados, rejeitados). Cada indicador abre a lista correspondente já filtrada.
- Resumo do Radar e os clientes que mais precisam de atenção.
- Listas de próximos vencimentos e documentos pendentes.
- Atalhos para as ações mais usadas.

### Interface e segurança
- Layout responsivo (celular, tablet e computador) e tema claro/escuro automático, conforme a preferência do sistema.
- Validação no servidor de todos os formulários de cadastro, edição e análise, com mensagem de erro junto a cada campo.
- Token CSRF obrigatório em todo envio de dados (POST), saída escapada contra XSS e consultas com prepared statements (PDO).

## Tecnologias

- PHP 8.1+ (orientado a objetos, sem framework)
- MySQL 8 com PDO
- HTML5, CSS3 e JavaScript puro (sem bibliotecas)
- Arquitetura MVC própria, com ponto de entrada único (`index.php`)
- Git e GitHub

## Requisitos

| Item | Versão / detalhe |
|---|---|
| PHP | **8.1 ou superior** (o código usa recursos dessa versão) |
| Extensões PHP | `pdo_mysql`, `fileinfo`, `mbstring`, `simplexml` e `zip` (esta última é usada para validar arquivos XLSX) |
| Banco de dados | **MySQL 8.0.16 ou superior** (necessário para as restrições `CHECK` do schema) |
| Servidor web | Apache com `AllowOverride All` no diretório do projeto e, de preferência, `mod_rewrite` ativo |

Para conferir as extensões instaladas, use `php -m`.

> **Importante:** sem `AllowOverride All`, o Apache ignora os arquivos `.htaccess` do projeto. Nesse caso, o `.env`, os scripts SQL e os documentos enviados ficam acessíveis pela web.

O desenvolvimento e os testes foram feitos com PHP 8.3 (como módulo do Apache), MySQL 8.0 e Apache 2.4 no Linux. O funcionamento com MariaDB (por exemplo, no XAMPP) **não foi testado**.

## Instalação

### 1. Obter o código

Clone o repositório dentro da pasta servida pelo Apache (por exemplo, `/var/www/html` no Linux ou `htdocs` no XAMPP):

```bash
git clone https://github.com/kristhian-dev/Conta-Facil.git conta_facil
cd conta_facil
```

Os comandos dos próximos passos devem ser executados **dentro da pasta do projeto**.

O sistema funciona em qualquer subpasta: os links são montados a partir do caminho em que o `index.php` está.

### 2. Criar o banco de dados

Importe os dois scripts, **nesta ordem**, pelo phpMyAdmin ou pelo terminal:

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

Troque `root` pelo usuário do seu MySQL. Ele precisa ter permissão para criar bancos de dados. No phpMyAdmin, não é preciso selecionar um banco antes: o `schema.sql` cria e seleciona o banco `contafacil`.

- `schema.sql` cria o banco `contafacil`, as 5 tabelas e o catálogo de tipos de obrigação.
- `seed.sql` insere os dados de demonstração: 2 usuários, 12 clientes, 32 obrigações e 15 documentos, com datas calculadas a partir do dia em que é executado. Por isso o Radar sempre mostra situações variadas (atrasadas, vencendo hoje, próximas etc.).

> **Atenção:** o `schema.sql` **apaga e recria** as tabelas (`DROP TABLE`). Não o execute em um banco com dados que precisem ser mantidos.
>
> O `seed.sql` pode ser executado mais de uma vez para restaurar os dados de demonstração:
> - atualiza os 2 usuários e os 12 clientes de demonstração, sem duplicá-los, e **redefine as senhas** desses usuários;
> - **apaga todas as obrigações e documentos dos clientes de demonstração**, inclusive os que você criou para eles durante os testes, e os recria com datas atualizadas. Arquivos já enviados para esses documentos continuam em `storage/uploads/`, mas deixam de estar ligados a algum registro;
> - não altera clientes que não sejam de demonstração, nem as obrigações e documentos deles.

O nome do banco `contafacil` está fixo nos dois scripts. Se quiser outro nome, ajuste os scripts e o `DB_NAME` do `.env`.

### 3. Configurar o arquivo `.env`

Copie o modelo e ajuste os valores do seu ambiente:

```bash
cp .env.example .env
```

Se já existir um `.env`, esse comando o substitui. Nesse caso, edite o arquivo existente em vez de copiar.

| Variável | Padrão | Descrição |
|---|---|---|
| `APP_DEBUG` | `false` | `true` mostra detalhes técnicos dos erros. Use apenas em desenvolvimento. |
| `APP_TIMEZONE` | `America/Fortaleza` | Fuso horário usado nas datas e nos prazos. |
| `DB_HOST` | `127.0.0.1` | Endereço do MySQL. |
| `DB_PORT` | `3306` | Porta do MySQL. |
| `DB_NAME` | `contafacil` | Nome do banco criado pelo `schema.sql`. |
| `DB_USER` | `root` | Usuário do MySQL. |
| `DB_PASS` | *(vazio)* | Senha do usuário do MySQL. |

O `.env` contém credenciais e **não é versionado** (está no `.gitignore`). O acesso a ele pela web é bloqueado pelo `.htaccess`.

### 4. Permissão da pasta de documentos

Os arquivos enviados são gravados em `storage/uploads/`. O usuário do Apache precisa de permissão de escrita nessa pasta. No Linux (Debian/Ubuntu/Mint, em que o Apache roda como `www-data`), uma forma de fazer isso:

```bash
sudo chgrp www-data storage/uploads
sudo chmod 2775 storage/uploads
```

Sem essa permissão, o sistema funciona, mas o registro de recebimento informa que não foi possível salvar o arquivo.

### 5. Limite de upload do PHP

O limite da aplicação é de 5 MB por arquivo. O `.htaccess` ajusta `upload_max_filesize` (6M) e `post_max_size` (8M), mas isso **só funciona quando o PHP roda como módulo do Apache** (`mod_php`, caso comum no XAMPP e no Ubuntu). Com PHP-FPM, configure esses valores no `php.ini`. Se o PHP tiver um limite menor, ele prevalece.

## Como acessar

Com o Apache e o MySQL em execução, abra no navegador o endereço da pasta do projeto, por exemplo:

```
http://localhost/conta_facil/
```

Você será levado à tela de login.

### Credenciais de demonstração

Criadas pelo `seed.sql` (dados fictícios, apenas para avaliação):

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | `admin@contafacil.test` | `Admin@123` |
| Contador | `contador@contafacil.test` | `Contador@123` |

O administrador tem todas as permissões, inclusive excluir registros. O contador faz todo o resto, mas não exclui.

## Estrutura de pastas

```
conta_facil/
├── index.php              # Ponto de entrada único (rotas: index.php?r=modulo/acao)
├── .htaccess              # Bloqueia acesso direto às pastas internas e arquivos ocultos
├── .env.example           # Modelo de configuração do ambiente
├── app/
│   ├── bootstrap.php      # Inicialização (configuração, autoload, sessão)
│   ├── Core/              # Núcleo do MVC: Router, Controller, Model, View, Database,
│   │                      # Auth, Session, Csrf, Validator, Paginator, Upload, helpers
│   ├── Controllers/       # Auth, Dashboard, Radar, Cliente, Obrigacao, Documento
│   ├── Models/            # Usuario, Cliente, Obrigacao, TipoObrigacao, Documento
│   ├── Services/          # RadarService, DashboardService e MensagemWhatsapp (textos do contato)
│   └── Views/             # Telas por módulo, layouts e componentes
├── config/
│   ├── config.php         # Configuração central (lê o .env)
│   └── routes.php         # Rotas permitidas e perfis com acesso a cada uma
├── database/
│   ├── schema.sql         # Estrutura do banco
│   └── seed.sql           # Dados de demonstração
├── public/
│   ├── css/app.css        # Estilos (temas claro e escuro)
│   ├── js/app.js          # Máscaras, confirmações e validações complementares
│   └── img/favicon.svg
└── storage/
    └── uploads/           # Arquivos enviados (inacessível diretamente pela web)
```

Valores ajustáveis em `config/config.php`: janela de "próximas do vencimento" (7 dias), tempo para considerar uma análise parada (3 dias), tempo de inatividade da sessão (30 minutos) e tamanho máximo de arquivo (5 MB).

## Roteiro rápido de teste

Um roteiro curto para conhecer os módulos com os dados de demonstração:

1. **Login:** entre como contador. Teste também uma senha errada e veja a mensagem genérica.
2. **Painel:** confira os indicadores e clique em um deles (por exemplo, "Atrasadas") para abrir a lista filtrada.
3. **Radar Contábil:** veja os clientes por nível de risco, filtre por "Alto risco" e abra a pendência de um cliente.
4. **Clientes:** cadastre um cliente PJ ou PF. Tente um CPF/CNPJ inválido ou já cadastrado para ver a validação. Use a pesquisa e os filtros.
5. **Obrigações:** cadastre uma obrigação para o novo cliente com vencimento nos próximos dias, veja a etiqueta de prazo e depois marque-a como concluída.
6. **Documentos:** solicite um documento ao cliente, registre o recebimento com um arquivo PDF ou imagem, abra o arquivo e aprove-o, ou rejeite-o com um motivo e registre um novo envio.
7. **Permissões:** entre como contador e veja que o botão "Excluir" não aparece. Entre como administrador para excluir os registros de teste. A exclusão é bloqueada enquanto houver vínculos: exclua primeiro os documentos, depois as obrigações e por fim o cliente.
8. **WhatsApp:** cadastre um cliente de teste com **o seu próprio celular**, solicite um documento a ele e abra a ficha. Escolha o modelo "Documentos pendentes", edite o texto e clique em "Abrir no WhatsApp". Exclua o cliente de teste ao final.

> Os documentos de demonstração **não têm arquivo físico** (o seed cria apenas os registros). Nas fichas dos que já constam como recebidos, aparece o aviso de que o arquivo não está disponível, sem botão de download. Para testar visualização e download, registre um recebimento com um arquivo seu.

> **Não abra o WhatsApp com os telefones dos clientes de demonstração.** Os números do `seed.sql` são fictícios e podem pertencer a pessoas reais. Para demonstrar o recurso, use um número seu em um cliente de teste.

## Limitações e próximos passos

**Limitações conhecidas desta versão**
- Não há tela de gerenciamento de usuários. Os usuários de acesso são criados pelo `seed.sql`.
- Não há recuperação de senha nem envio automático de notificações (e-mail ou WhatsApp). O contato pelo WhatsApp é apenas assistido: abre a conversa com o texto pronto, sem envio pelo sistema, sem histórico e sem receber respostas. Os canais de recebimento de documentos são apenas registrados.
- Os documentos de demonstração não possuem arquivos físicos.
- O funcionamento com MariaDB/XAMPP não foi testado.

**Planejado para versão futura**
- **Portal do cliente:** acesso do próprio cliente para acompanhar suas pendências e enviar documentos pelo sistema. A estrutura já está preparada: perfil `cliente` vinculado a um cliente na tabela de usuários, canal de recebimento "Sistema" e restrição das consultas aos dados do próprio cliente. Na versão atual, porém, esse perfil é recusado no login e o canal "Sistema" não pode ser usado.

## Situação do projeto

Funcionalidades principais implementadas. Etapas em andamento para a entrega final:
- **Teste com usuário real: pendente.**
- Melhorias a partir do feedback desse teste: pendentes.

O contato assistido pelo WhatsApp foi uma decisão da equipe, implementada e testada por ela. **Não resultou de feedback de usuário real.**

## Equipe

- Kristhian Sousa
- Yasmin

## Disciplina

Programação Web II — Projeto Final
Colégio Técnico de Teresina (CTT/UFPI)
