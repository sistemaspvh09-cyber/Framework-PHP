# AGENTS

Este arquivo orienta agentes automaticos a contribuir com seguranca e
consistencia neste repositorio. Ele resume comandos e convencoes observadas
no codigo atual.

Escopo e estrutura
- Projeto PHP baseado no Adianti Framework (ver `lib/adianti` e `app/*`).
- Entradas principais: `index.php` (web), `engine.php` (aplicacao), `cmd.php` (CLI).
- Configuracoes em `app/config/*.php` e `app/config/*.ini`.
- Controladores em `app/control/**`.
- Modelos em `app/model/**` (Active Record Adianti).
- Servicos em `app/service/**` (CLI, REST, jobs, auth, system).
- Recursos HTML/CSS em `app/resources/**` e templates em `app/templates/**`.
- Bases sqlite e scripts SQL em `app/database/**`.
- Consulte sempre que necessario o arquivo `contexto.md` para informacoes do projeto.

Regras de agentes (Cursor/Copilot)
- Nao existem regras em `.cursor/rules/` ou `.cursorrules`.
- Nao existe `.github/copilot-instructions.md`.

Requisitos e runtime
- PHP minimo: 8.2 (ver `init.php`).
- Composer esta presente apenas com dependencias (sem scripts definidos).
- Nao ha configuracao de testes/lint no nivel do projeto.

Comandos de build, lint e testes
Observacao: este repositorio nao define comandos oficiais de build/lint/test.
Evite inventar scripts. Use o que o projeto expor explicitamente.

Instalacao de dependencias
- `composer install`

Build
- Nao ha pipeline de build definida./

Lint
- Nao ha lint configurado no projeto.

Testes
- Nao ha testes do projeto. Existem testes apenas em `vendor/**`.

Testes individuais (quando existirem)
- Nao ha estrutura de testes do app para executar individualmente.
- Se forem adicionados no futuro, documente o comando aqui.

Execucao CLI (servicos Adianti)
- Uso: `php cmd.php "class=Classe&method=metodo&param=valor"`
- Exemplo: `php cmd.php "class=SystemScheduleService&method=run"`

Execucao web
- A entrada web usa `index.php` e carrega templates conforme login/public view.

Convencoes de codigo observadas

Formato e estilo
- Chaves em nova linha (estilo Allman).
- Indentacao com 4 espacos.
- Espacos ao redor de operadores binarios.
- Separacao por linhas em branco entre blocos logicos.
- Docblocks no topo das classes e metodos importantes.

Imports e namespaces
- Classes do app nao usam namespace.
- `use` no topo do arquivo quando necessario (ex.: `ApplicationAuthenticationService`).
- Evite alias desnecessario.

Nomenclatura
- Classes em PascalCase (ex.: `SystemNotification`, `LoginForm`).
- Metodos em camelCase ou `get_*`/`set_*` quando esperado pelo framework.
- Constantes em UPPER_SNAKE com valores fixos (ex.: `TABLENAME`, `PRIMARYKEY`).
- Variaveis locais em camelCase; arrays por convencao `[ 'chave' => 'valor' ]`.

Tipos e retornos
- Nao ha tipagem estrita declarada.
- Metodos publicos geralmente nao declaram tipo de retorno.
- Ao adicionar tipos, mantenha a compatibilidade com o padrao do arquivo atual.

Tratamento de erros e transacoes
- Use `TTransaction::open()` e `TTransaction::close()` ao acessar banco.
- Em excecoes, use `TTransaction::rollback()` quando apropriado.
- Em servicos CLI, capture `Exception` e `Error` separadamente se preciso.
- Para mensagens de erro em UI, use `new TMessage('error', $e->getMessage())`.

Padroes Adianti (framework)
- Models herdam de `TRecord` e declaram `TABLENAME`, `PRIMARYKEY`, `IDPOLICY`.
- Atributos do model sao definidos via `parent::addAttribute()` no construtor.
- Controladores herdam de `TPage`.
- Permissoes e acesso sao verificados via `SystemPermission`.
- Traducao de strings via `_t('Texto')`.
- Certifique-se do arquivo translations.json ter todos as traduçoes necessarias
Configuracoes
- `app/config/application.php` concentra opcoes gerais, login, template e hooks.
- Conexoes SQLite em `app/config/*.php` (permission/log/etc).
- Evite hardcode de credenciais; siga os exemplos existentes.

Arquitetura por camadas
- Controladores coordenam UI e fluxo.
- Servicos encapsulam logica de jobs, REST, auth, system e CLI.
- Models representam tabelas do banco com Active Record.

Boas praticas ao editar
- Respeite o estilo do arquivo onde esta editando.
- Mantenha padroes de transacao consistentes.
- Use `_t()` para textos exibidos ao usuario.
- Evite alterar `vendor/**`.
- Evite criar novas dependencias sem necessidade clara.

Recursos e templates
- HTML de recursos fica em `app/resources/**`.
- Templates principais em `app/templates/adminbs5/**`.
- Na web, o layout depende do estado de login e do tema configurado.

Paginas customizadas (HTML + JS + TPage)
- Controllers de paginas customizadas ficam em `app/control/**` e devem conter `Custom` no nome (ex.: `AgendarServicoCustomForm`).
- Recursos HTML ficam em `app/resources/<dominio-do-projeto>/**` conforme o projeto (ex.: `app/resources/barbearia/agendar_servico_custom_form.html`).
- O controller deve usar `THtmlRenderer` e `enableSection('main', [...])` para injetar textos e labels.
- O HTML usa secao `<!--[main]--> <!--[/main]-->` e pode incluir CSS/JS locais para a pagina.
- JS deve consumir metodos `onGet*`/`onConfirm` via `__adianti_ajax_exec` e tratar retorno JSON.
- Padronize respostas JSON com `jsonResponse`, `Content-Type: application/json` e erro com `error`/`message`.
- Valide entradas no controller, normalize data/hora com helpers (`normalizeDate`, `normalizeTime`).
- Separe regra de negocio em `app/service/**` quando houver logica reutilizavel.
- Consulte exemplo `contexto.md` AgendamentoAdminCustomForm|AgendamentoService|agendamento_admin_custom_form

Cadastro de programas e menu (rotina obrigatoria)
- Verifique o banco de permissao configurado em `app/config/permission.php` (padrao: `app/database/permission.db`).
- Antes de usar um novo controller no app, confirme se ele existe em `system_program`.
- Se nao existir, faca a inclusao no `app/database/permission.db` seguindo o padrao de `app/database/permission.sql`.
- Atualize tambem os scripts `app/database/permission.sql` e/ou `app/database/permission-update.sql` com o novo programa.
- Ajuste `menu.xml` para incluir o item de menu correspondente, respeitando a hierarquia atual.

Seguranca e sessao
- Sessao e permissao sao tratadas por `ApplicationAuthenticationService`.
- Tokens JWT usam seed configurado em `app/config/application.php`.
- Quando validar unidades e idioma, siga o padrao de `setUnit` e `setLang`.

Observacoes sobre banco
- O projeto usa SQLite por padrao (`app/database/*.db`).
- Scripts SQL de atualizacao estao em `app/database/*-update.sql`.

Se voce adicionar testes
- Documente o comando para rodar todos e um teste unico.
- Inclua caminho e framework de teste usado.

Se voce adicionar lint/format
- Documente o comando e a configuracao (ex.: phpcs, php-cs-fixer).
- Garanta que o comando funcione no root do repositorio.

Checklist rapido para agentes
- Entender o fluxo atual e o ponto de entrada.
- Manter compatibilidade com Adianti e PHP 8.2.
- Respeitar padroes de transacao e traducao.
- Atualizar este AGENTS.md se novas regras forem criadas.
