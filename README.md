# Workshop CRM

CRM multi-tenant MVP para pequenas e medias empresas, com pipeline Kanban de vendas e integracao com WhatsApp via EvolutionAPI v2.

**Stack:** Laravel 12 | Livewire 4 | Tailwind CSS 4 | PostgreSQL 18 | Pest 4

**Funcionalidades:** Registro de tenant, RBAC (Business Owner / Salesperson), gestao de leads e deals no Kanban, notas, atribuicao de responsaveis, notificacoes por email, dashboard de vendas e conversas via WhatsApp.

---

## Rodando o projeto

### Pre-requisitos

- [Docker](https://www.docker.com/) instalado
- [Claude Code](https://docs.anthropic.com/en/docs/claude-code) instalado (para o fluxo de desenvolvimento com IA)

### Setup

```bash
# Clone o repositorio
git clone <url-do-repo>
cd workshop-crm

# Copie o .env
cp .env.example .env

# Suba os containers com Sail
./vendor/bin/sail up -d

# Instale dependencias
./vendor/bin/sail composer install
./vendor/bin/sail npm install

# Gere a key e rode as migrations
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# Build dos assets
./vendor/bin/sail npm run build
```

### Comandos do dia-a-dia

```bash
# Rodar testes
./vendor/bin/sail artisan test --compact

# Formatar codigo PHP
./vendor/bin/sail bin pint --dirty

# Subir/parar containers
./vendor/bin/sail up -d
./vendor/bin/sail stop
```

---

## Fluxo de prompts para gerar o projeto

Na pasta `prompts/` existem 4 prompts que foram usados em sequencia para planejar o projeto inteiro antes de codar:

1. **`criar-descricao-do-projeto.md`** — Gera a descricao geral do projeto (`docs/project-description.md`)
2. **`user-stories.md`** — Gera as historias de usuario (`docs/user-stories.md`)
3. **`database-structure.md`** — Gera o schema do banco de dados (`docs/database-schema.md`)
4. **`project-phases.md`** — Gera as fases de implementacao (`docs/project-phases.md`)

O fluxo e sequencial: cada prompt usa a saida do anterior como contexto. Os documentos gerados ficam em `docs/`.

---

## MCP Servers utilizados

### Laravel Boost (via Sail)

MCP server do ecossistema Laravel com acesso a schema do banco, Artisan, Tinker, logs, busca de documentacao e mais.

```bash
# Remover config antiga (se existir)
claude mcp remove laravel-boost -s local

# Adicionar com Sail
claude mcp add -s local -t stdio laravel-boost ./vendor/bin/sail php artisan boost:mcp
```

### Serena

MCP server para navegacao semantica de codigo — overview de simbolos, busca de referencias, edicao simbolica.

```bash
claude mcp add serena -- uvx --from git+https://github.com/oraios/serena serena start-mcp-ntext claude-code --project "$(pwd)"
```

### Context7

MCP server para busca de documentacao atualizada de qualquer biblioteca, com exemplos de codigo.

```bash
claude mcp add --scope user --transport http context7 https://mcp.context7.com/mcp \
  --header "CONTEXT7_API_KEY: <sua-api-key>"
```

---

## Ralph Wiggum Plugin

O [Ralph Wiggum](https://github.com/frankbria/ralph-claude-code) e um plugin para o Claude Code que transforma ele em um agente autonomo capaz de trabalhar em tarefas por horas sem intervencao humana.

O nome vem do personagem Ralph Wiggum dos Simpsons — a filosofia e de iteracao persistente ate completar a tarefa.

### Como funciona

Voce inicia um loop iterativo com `/ralph-loop` passando seu prompt. O plugin intercepta saidas de sessao via stop hook e re-alimenta o prompt automaticamente, preservando todas as modificacoes de arquivo e historico git entre iteracoes. Cada iteracao ve o codebase modificado das tentativas anteriores.

### Uso basico

```bash
# Iniciar o loop
/ralph-loop "implemente a feature X" --max-iterations 10

# Cancelar a qualquer momento
/cancel-ralph
```

Na aula, tambem foi apresentado o script `ralph.sh` na raiz do projeto — um orquestrador bash que le `docs/project-phases.md`, quebra em fases individuais e alimenta cada uma ao OpenAI Codex CLI para implementacao automatica sequencial, com retry automatico em caso de falha nos testes.
