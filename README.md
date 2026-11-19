
![This is a screenshot](/image/screen.png)

## VOTAÇÃO — Documentação Técnica

## 1. Visão Geral

VOTAÇÃO é uma aplicação web PHP para gestão e contabilização de votos, com autenticação de utilizadores, gestão de candidatos, votação em tempo real, resultados gráficos e exportação/importação de dados em JSON.

| Característica Detalhe |   |
| --- | --- |
| Linguagem | PHP 8+ (declare(strict_types=1)) |
| Base de dados | MySQL 8+ / MariaDB 10.6+ (PDO, prepared statements reais) |
| Frontend | Bootstrap 5.3, Bootstrap Icons, Chart.js 4, jQuery 3.7 |
| Segurança | CSP com nonce, CSRF, Argon2id/bcrypt, anti-força-bruta, sessões endurecidas |
| Idioma | Português (PT) |

## 2. Estrutura de Ficheiros

| config.php | → Configuração, ligação PDO, cabeçalhos de segurança, sessão |
| --- | --- |
| segura |   |
| auth.php | → Autenticação, CSRF, escape XSS, políticas de password, |
| bloqueios |   |
| layout.php | → Cabeçalho/rodapé partilhados (páginas de autenticação) |
| login.php | → Formulário e processamento de início de sessão |
| register.php → Registo de novas contas (com honeypot) |   |
| logout.php → Terminação de sessão (apenas POST + CSRF) |   |
| index.php | → Dashboard principal (votar, candidatos, resultados) |
| api.php → API JSON (listar, votar, definir, criar, remover, zerar, importar, |   |
| exportar) |   |
| users.php | → Gestão de utilizadores (apenas administradores) |
| database.sql → Esquema da base de dados + view de resultados |   |

## 3. Configuração (config.php)

Constantes principais


| Constante | Valor | Descrição |
| --- | --- | --- |
| APP_ENV | development | Em production exige HTTPS e cookie Secure |
| DB_HOST / DB_PORT | 127.0.0.1 / 3306 | Servidor MySQL |
| DB_NAME | votacao | Nome da base de dados |
| DB_USER / DB_PASS | root / (vazio) | Credenciais (usar utilizador mínimo em produção) |
| MAX_TENTATIVAS | 5 | Falhas consecutivas antes de bloquear |
| BLOQUEIO_MINUTOS | 15 | Duração do bloqueio |
| SESSAO_INATIVIDADE | 1800 | 30 min sem atividade → termina sessão |
| SESSAO_MAXIMA | 28800 | 8 h desde login → termina sessão |
| REGISTO_ABERTO | true | false → só o admin cria contas |

## Funções

- db(): PDO - Singleton PDO com ERRMODE_EXCEPTION, FETCH_ASSOC e EMULATE_PREPARES=false.

- is_https(): bool - Deteta HTTPS sem confiar em X-Forwarded-Proto.

- csp_nonce(): string ‑ Nonce CSP único por pedido.

- send_security_headers(): void - CSP, X-Content-Type-Options, X- Frame-Options, Referrer-Policy, Permissions-Policy, HSTS (se HTTPS).

- start_secure_session(): void - Sessão com strict_mode, use_only_cookies, cookie HttpOnly/SameSite=Strict/Secure, validação de inatividade, duração máxima e User-Agent.

- destroy_session(): void ‑ Limpa sessão e cookie.

## 4. Autenticação e Segurança (auth.php)

## 4.1 Escape XSS

php

e(?string \$s): string // htmlspecialchars com ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5


## 4.2 CSRF

| Função | Descrição |
| --- | --- |
| csrf_token() | Gera/retorna token (64 hex chars) em $_SESSION['csrf'] |
| csrf_field() | Campo hidden csrf |
| csrf_valid($t) | Comparação com hash_equals |
| csrf_require() | Exige token em $_POST['csrf'] ou cabeçalho X-CSRF-Token |

## 4.3 Mensagens Flash

- flash_set(string \$tipo, string \$msg)

- flash_get(): ?array ‐ lê e apaga.

## 4.4 Política de Palavras-passe

| Função | Descrição |
| --- | --- |
| pw_algo() | Argon2id se disponível, senão bcrypt |
| pw_opts() | bcrypt cost 12 / Argon2id memory_cost=65536, time_cost=4, threads=1 |
| pw_hash() | Gera hash |
| pw_validate() | Mín. 12 chars, maiúsculas+minúsculas+número+símbolo, não contém parte local do email, limite de bytes (72 bcrypt / 128 Argon2) |

## 4.5 Utilizador Atual

| Função | Descrição |
| --- | --- |
| current_user(bool $refresh=false) | Valida $_SESSION['uid'], conta ativa e sessao_versao |
| require_login() | Redireciona para login.php se não autenticado |
| require_admin() | 403 se não for admin |

## 4.6 Anti-força-bruta

- client_ip() ‑ Usa apenas REMOTE_ADDR (não falsificável), convertido com inet_pton.

- register_failure(?int \$userId, string \$ip) - Incrementa contadores em bloqueios_ip e utilizadores; bloqueia ao atingir MAX_TENTATIVAS. Usa hora do MySQL.

- attempt_login(string \$email, string \$senha): bool

- o Verifica bloqueio por IP e por conta.


- o Executa password_verify sempre (mesmo se o utilizador não existir, para evitar enumeração por tempo).

- o Em falha → register_failure.

- o Em sucesso → rehash se necessário, limpa contadores, regenera ID de sessão, cria \$_SESSION com uid, v, inicio, atividade, ua.

## 4.7 Criação de Utilizadores

- create_user(nome, email, senha, ?papel) - Se \$papel=null, o primeiro utilizador torna-se admin. Devolve false em email duplicado (código SQL 23000).

- clean_email() — Valida tamanho d190 e FILTER_VALIDATE_EMAIL.

- clean_nome() ‐ 2 a 80 caracteres, normaliza espaços.

## 5. Base de Dados (database.sql)

## Tabelas

## utilizadores

| Coluna | Tipo | Notas |
| --- | --- | --- |
| id | INT UNSIGNED PK AI |   |
| nome | VARCHAR(80) |   |
| email | VARCHAR(190) UNIQUE |   |
| senha_hash | VARCHAR(255) | Argon2id ou bcrypt |
| papel | ENUM('admin','utilizador') default utilizador |   |
| ativo | TINYINT(1) | default 1 |
| tentativas_falhadas | TINYINT UNSIGNED | default 0 |
| bloqueado_ate | DATETIME NULL |   |
| sessao_versao | INT UNSIGNED | incrementar invalida sessões |
| ultimo_login | DATETIME NULL |   |
| criado_em | TIMESTAMP |   |

## bloqueios_ip


| Coluna | Tipo |
| --- | --- |
| ip | VARBINARY(16) PK |
| tentativas | SMALLINT UNSIGNED |
| bloqueado_ate | DATETIME NULL |
| atualizado_em | TIMESTAMP ON UPDATE |

## candidatos

| Coluna | Tipo |
| --- | --- |
| id | INT UNSIGNED PK AI |
| nome | VARCHAR(60) |
| funcao | VARCHAR(60) |
| foto | MEDIUMTEXT (data URL base64) |
| votos | INT UNSIGNED default 0 |
| criado_em | TIMESTAMP |

## log_votos

| Coluna | Tipo |
| --- | --- |
| id | BIGINT UNSIGNED PK AI |
| candidato_id | INT UNSIGNED FK → candidatos (CASCADE) |
| utilizador_id | INT UNSIGNED NULL FK → utilizadores (SET NULL) |
| delta | INT |
| tipo | ENUM('voto','ajuste','zerar','importacao') |
| ip | VARBINARY(16) NULL |
| criado_em | DATETIME(3) |

## View

sql


vw_resultado → id, nome, funcao, votos, percentagem (ordenado por votos DESC)

## Utilizador MySQL dedicado (comentado no SQL)

```
sql
CREATE USER 'votacao_app'@'localhost' IDENTIFIED BY '...';
GRANT SELECT, INSERT, UPDATE, DELETE ON votacao.* TO 'votacao_app'@'localhost';
```

## 6. Páginas

## 6.1 login.php

- Redireciona para index.php se já autenticado.

- POST com csrf_require(); valida tamanhos (email d190, senha d1024).

- Em sucesso  index.php; em falha  usleep(1509350ms) + mensagem genérica MSG_LOGIN_FALHOU.

- Mostra link para register.php apenas se REGISTO_ABERTO.

## 6.2 register.php

- Fechado se REGISTO_ABERTO=false (exceto quando não existe nenhum utilizador — primeiro torna-se admin).

- Valida nome, email, confirmação de senha e política pw_validate.

- Honeypot campo website: se preenchido (bot), finge sucesso sem criar conta.

- Sucesso  flash genérica ("Se os dados forem válidos...") sem revelar se o email já existia.

## 6.3 logout.php

- Apenas POST + csrf_require()  destroy_session(), regenera ID, flash de sucesso, redireciona para login.php.

## 6.4 layout.php

Funções de layout para páginas de autenticação:

- html_head(\$titulo, \$bodyClass) – HTML head com Bootstrap, ícones, Google Fonts e style.css.

- auth_start(\$titulo, \$subtitulo) / auth_end() - cartão centrado de autenticação.

- alert_html(?array \$f) — renderiza alerta Bootstrap ([tipo, mensagem]).


## 6.5 index.php — Dashboard

Interface principal com sidebar, topbar e três vistas:

| Vista | Conteúdo |
| --- | --- |
| Votar | Grelha de cartões de candidatos com botão "Votar" (cada clique = 1 voto) e input de votos (editável apenas por admin) |
| Candidatos | Formulário de criação (nome, função, foto com recorte 300×300 para JPEG base64) + tabela de candidatos com remoção |
| Resultado final | Estatísticas (total, nº candidatos, líder, % líder), gráfico Chart.js (bar/pie/doughnut) e classificação |

## Funcionalidades JS:

- \$.ajaxSetup injeta X-CSRF-Token em todos os pedidos.

- api(action, data) – wrapper POST JSON com tratamento de 401 (redireciona para login).

- reload()  GET api.php?action=listar.

- Avatares SVG gerados localmente (inicial do nome) quando não há foto.

- Gráfico de barras com plugin personalizado que desenha nome, função e fotografia por baixo de cada barra, e valores/percentagens dentro das barras.

- Exportar  location.href='api.php?action=exportar'.

- Importar  modal com modos acrescentar / substituir.

- Elementos de admin ocultos/removidos se body[data-admin=0].

## 6.6 users.php — Gestão de Utilizadores (admin)

## Ações POST (com CSRF):

| Ação | Efeito |
| --- | --- |
| criar | Cria utilizador (create_user com papel explícito) |
| alternar | Ativa/desativa conta + incrementa sessao_versao |
| papel | Alterna entre admin/utilizador + incrementa sessao_versao |
| desbloquear | Zera tentativas e bloqueado_ate |
| apagar | DELETE do utilizador |
| senha | Redefine hash + zera bloqueios + incrementa sessao_versao |

## Regras:


- Administrador não pode executar ações sobre a própria conta (evita auto-bloqueio).

- Padrão Post/Redirect/Get.

- Modal para redefinir palavra-passe.

- Confirmação JS em ações destrutivas (data-confirm).

## 7. API (api.php)

## Formato

- Todas as respostas: Content-Type: application/json; charset=utf-8, Cache-Control: no-store.

- Erros: {"erro": "mensagem"} com código HTTP apropriado.

## Ações

| Ação |   | Método Autorização Body |   | Retorno |
| --- | --- | --- | --- | --- |
| listar | GET | autenticado — |   | {candidatos: [...]} |
| votar | POST | autenticado | {id} | {id, votos} |
| definir | POST | admin | {id, votos} | {id, votos} |
| criar | POST | admin | {name, role, photo} {id} (201) |   |
| remover | POST | admin | {id} | {ok: true} |
| zerar | POST | admin | — | {ok: true} |
| importar | POST | admin | {modo, candidatos} {importados} |   |
| exportar | GET | admin | — | Download JSON |

## Regras de roteamento

- POST exige Content-Type: application/json (senão 415).

- Ações sensíveis (definir, criar, remover, zerar, importar, exportar) exigem papel=admin (senão 403).

- POST exige X-CSRF-Token válido (senão 403).

- session_write_close() antes do match - liberta o bloqueio da sessão para cliques rápidos.

## Funções internas

| Função | Descrição |
| --- | --- |


| out(array $data, int $code=200) | Emite JSON e termina |
| --- | --- |
| body(): array | Lê e valida JSON do corpo (json_validate) |
| tx(callable) | Envolve em transação com rollback automático |
| fmt(array $r) | Mapeia linha DB → {id, name, role, photo, votes} |
| clean_text($v, $max=60) | Valida texto 1..max |
| clean_photo($v) | Valida data URL jpeg/png/webp base64 ≤700 KB |
| clean_votes($v) | Inteiro 0..2 000 000 000 |
| log_votes(PDO, $id, $delta, $tipo) | Insere em log_votos |
| listar() | SELECT ordenado por id |
| votar($b) | UPDATE votos = votos + 1 + log (transação) |
| definir($b) | SELECT FOR UPDATE + UPDATE + log de ajuste (transação) |
| criar($b) | INSERT candidato |
| remover($b) | DELETE candidato |
| zerar() | Log de -votos + UPDATE todos a 0 (transação) |
| exportar() | JSON pretty-print com Content-Disposition: attachment |
| importar($b) | Valida tudo antes de tocar na DB; modo acrescentar/substituir; máx. 500 itens; log de importação |

## Tratamento de erros

- ApiError  código HTTP + mensagem.

- Throwable genérico  error_log + 500 "Erro interno do servidor." (não vaza detalhes).

## 8. Modelo de Segurança (resumo)

| Vetor | Mitigação |
| --- | --- |
| XSS | e() em todas as saídas; CSP com nonce e sem unsafe-inline para scripts |
| CSRF | Token em sessão, hash_equals, campo em formulários e cabeçalho X-CSRF-Token em AJAX |


| SQL Injection | PDO com EMULATE_PREPARES=false e prepared statements reais |
| --- | --- |
| Força bruta | 5 tentativas / 15 min por IP e por conta; atraso aleatório no login |
| Enumeração de utilizadores | password_hash executado mesmo sem utilizador; mensagens genéricas no login e registo |
| Session Fixation | session_regenerate_id(true) após login |
| Session Hijacking | Validação de User-Agent (hash) + expiração por inatividade e duração máxima |
| Clickjacking | X-Frame-Options: DENY + frame-ancestors 'none' na CSP |
| MIME sniffing | X-Content-Type-Options: nosniff |
| HTTPS | Obrigatório em produção; HSTS quando HTTPS |
| Bots de registo | Honeypot website |
| Escalada de privilégios | Verificação de papel na API; admin não pode agir sobre si próprio |
| Auto-bloqueio do admin | Ações sobre a própria conta proibidas em users.php |
| Vazamento de erros | 500 genérico; detalhes só em error_log |
| Revogação de sessão | sessao_versao incrementada em alterações de papel/estado/password |

## 9. Fluxo de Votação

```
text
Utilizador autenticado
│
[index.php] → GET api.php?action=listar → renderiza grelha
│
├── Clique "Votar" → POST {action:votar, id} → UPDATE votos+1 + log
→ refresh UI
│
├── (Admin) altera input → POST {action:definir, id, votos} → log de
ajuste
│
```


└── (Admin) Resultado final → Chart.js (bar/pie/doughnut) + ranking

Cada voto e ajuste é auditado em log_votos com utilizador, IP, delta e timestamp (ms).

## 10. Instalação

## 1. Criar base de dados:

## bash

mysql -u root -p < database.sql

- 2. Configurar config.php (credenciais DB, APP_ENV, REGISTO_ABERTO).

- 3. Servir com PHP (desenvolvimento):

bash

php -S 127.0.0.1:8000

- 4. Primeiro acesso: abrir register.php — a primeira conta criada torna-se administrador.

## 5. Produção:

- o APP_ENV='production' (força HTTPS + cookie Secure).

- o Criar utilizador MySQL dedicado com privilégios mínimos.

- o Configurar servidor web (Apache/Nginx) para HTTPS e para passar REMOTE_ADDR corretamente atrás de proxy.

## 11. Notas de Manutenção

- Rehash automático: ao iniciar sessão, se o algoritmo/custo mudou, a password é re-hasheada.

- Invalidar sessões: incrementar sessao_versao do utilizador (feito automaticamente em alterações de papel, estado e password).

- Limpeza de bloqueios_ip: registos são apagados no login bem- sucedido; podem ser limpos periodicamente por cron.

- Limites: máx. 500 candidatos por importação; foto <700 KB em base64; votos <2 000 000 000.

- Log de votos: mantém histórico completo; considera arquivamento periódico em produção.

