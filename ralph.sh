#!/bin/bash
#
# ralph_wiggum.sh 🤡
# ==================
# "Me fail English? That's unpossible!" - Ralph Wiggum
#
# Orquestrador que lê docs/project-phases.md, quebra em fases,
# e alimenta cada uma ao OpenAI Codex CLI para implementação automática.
#
# Uso:
#   chmod +x ralph_wiggum.sh
#   ./ralph_wiggum.sh [caminho-do-arquivo]
#
# Pré-requisitos:
#   - OpenAI Codex CLI instalado (npm install -g @openai/codex)
#   - OPENAI_API_KEY ou login via `codex login`
#   - Estar na raiz do projeto Laravel (dentro de um repo git)
#

set -euo pipefail

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Configuração
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INPUT_FILE="${1:-docs/project-phases.md}"
PHASES_DIR=".phases"
LOG_DIR=".phases/logs"
PROMPT_DIR=".phases/prompts"
MANIFEST="$PHASES_DIR/manifest.txt"
PROGRESS_FILE="$PHASES_DIR/.progress"
MAX_RETRIES=2

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Funções auxiliares
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
log() { echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1"; }
success() { echo -e "${GREEN}[$(date '+%H:%M:%S')] ✅ $1${NC}"; }
warn() { echo -e "${YELLOW}[$(date '+%H:%M:%S')] ⚠️  $1${NC}"; }
fail() { echo -e "${RED}[$(date '+%H:%M:%S')] ❌ $1${NC}"; }
ralph() { echo -e "${PURPLE}[$(date '+%H:%M:%S')] 🤡 $1${NC}"; }

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Pré-checks
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
preflight_checks() {
  echo ""
  ralph "Ralph Wiggum reporting for duty! 'I'm helping!'"
  echo ""

  # Verifica codex
  if ! command -v codex &> /dev/null; then
    fail "codex CLI não encontrado. Instale com: npm install -g @openai/codex"
    exit 1
  fi

  # Verifica arquivo de fases
  if [ ! -f "$INPUT_FILE" ]; then
    fail "Arquivo não encontrado: $INPUT_FILE"
    exit 1
  fi

  # Verifica se é um projeto Laravel
  if [ ! -f "artisan" ]; then
    warn "Não parece ser a raiz de um projeto Laravel (artisan não encontrado)"
    read -p "Continuar mesmo assim? (y/N) " -n 1 -r
    echo
    [[ $REPLY =~ ^[Yy]$ ]] || exit 1
  fi

  # Verifica git (obrigatório para codex exec)
  if ! git rev-parse --is-inside-work-tree &> /dev/null 2>&1; then
    fail "codex exec requer um repositório git. Rode 'git init && git add -A && git commit -m init' primeiro."
    exit 1
  fi

  success "Pré-checks OK"
}

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Split: quebra o arquivo em fases
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
split_phases() {
  log "Quebrando $INPUT_FILE em fases..."

  rm -rf "$PHASES_DIR"
  mkdir -p "$PHASES_DIR" "$LOG_DIR" "$PROMPT_DIR"

  > "$MANIFEST"

  local current_file=""
  local phase_count=0

  while IFS= read -r line || [ -n "$line" ]; do
    if [[ "$line" =~ ^##[[:space:]]+(Phase[[:space:]]+[0-9]+[^#]*) ]]; then
      phase_count=$((phase_count + 1))

      local raw_title="${BASH_REMATCH[1]}"
      raw_title="$(echo "$raw_title" | sed 's/[[:space:]]*$//')"

      local slug
      slug=$(echo "$raw_title" \
        | tr '[:upper:]' '[:lower:]' \
        | sed 's/phase[[:space:]]*/phase-/' \
        | sed 's/[^a-z0-9-]/-/g' \
        | sed 's/--*/-/g' \
        | sed 's/-$//' \
        | sed 's/^-//')

      slug=$(echo "$slug" | sed -E 's/phase-([0-9])$/phase-0\1/' | sed -E 's/phase-([0-9])-/phase-0\1-/')

      current_file="$PHASES_DIR/${slug}.md"
      echo "$line" > "$current_file"
      echo "${slug}.md|${raw_title}" >> "$MANIFEST"

      continue
    fi

    if [ -n "$current_file" ]; then
      echo "$line" >> "$current_file"
    fi
  done < "$INPUT_FILE"

  success "$phase_count fases extraídas"
}

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Salva o prompt num arquivo temporário
# (evita problemas com stdin/tty do codex)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
build_prompt_file() {
  local phase_file="$1"
  local phase_title="$2"
  local prompt_file="$PROMPT_DIR/${phase_file%.md}.txt"

  cat > "$prompt_file" <<PROMPT
Você é um desenvolvedor Laravel sênior implementando um CRM multi-tenant.

## Stack do projeto
- Laravel 12, PHP 8.4
- Livewire 3 (full-page components)
- Pest PHP (testes)
- Tailwind CSS 4
- pgsql 18
- Interface em pt-BR

## Arquivos de referência importantes
- docs/project-phases.md — plano completo de fases
- docs/database-schema.md — schema do banco (se existir)
- docs/user-stories.md — user stories (se existir)
- docs/project-description.md — descrição geral (se existir)

## Sua tarefa agora
Implemente COMPLETAMENTE a fase descrita abaixo.

Para cada item:
1. Implemente o código completo (não deixe TODOs ou placeholders)
2. Crie os testes listados
3. Rode os testes com
4. Se um teste falhar, corrija o código e rode novamente
5. Só passe pro próximo item quando os testes passarem

## Regras obrigatórias
- Use BelongsToTenant trait em todos os models multi-tenant
- Factories devem criar todas as dependências (tenant, role, user, etc.)
- Nomes de classes, arquivos e métodos devem seguir EXATAMENTE o que está descrito
- Todos os textos de interface em pt-BR
- Não pule nenhum item marcado com [ ]
- Rode php artisan test ao final para garantir que TUDO passa

## Fase a implementar
$(cat "$PHASES_DIR/$phase_file")
PROMPT

  echo "$prompt_file"
}

build_retry_prompt_file() {
  local phase_file="$1"
  local test_output="$2"
  local prompt_file="$PROMPT_DIR/${phase_file%.md}-retry.txt"

  cat > "$prompt_file" <<PROMPT
Os testes falharam após a implementação anterior. Corrija os erros.

Saída dos testes:
\`\`\`
$test_output
\`\`\`

Corrija o código para que todos os testes passem. Rode os testes novamente após cada correção.
PROMPT

  echo "$prompt_file"
}

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Executa uma fase via Codex exec
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
run_phase() {
  local phase_file="$1"
  local phase_title="$2"
  local phase_num="$3"
  local total_phases="$4"
  local log_file="$LOG_DIR/${phase_file%.md}.log"

  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  ralph "[$phase_num/$total_phases] $phase_title"
  ralph "'Me fail English? That's unpossible!'"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

  local attempt=0
  local phase_success=false

  while [ $attempt -le $MAX_RETRIES ]; do
    attempt=$((attempt + 1))

    if [ $attempt -gt 1 ]; then
      warn "Tentativa $attempt/$((MAX_RETRIES + 1))..."
    fi

    log "Montando prompt e enviando para Codex exec..."

    local prompt_file
    if [ $attempt -eq 1 ]; then
      prompt_file=$(build_prompt_file "$phase_file" "$phase_title")
    fi
    # Para retries, prompt_file já foi setado no bloco else abaixo

    log "Prompt salvo em: $prompt_file"

    # ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    # CODEX EXEC — modo não-interativo
    #
    # --sandbox danger-full-access  → acesso total (rede, disco)
    # Prompt via stdin com "-"      → evita erro "stdin is not a terminal"
    # ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    if cat "$prompt_file" | codex exec \
      --sandbox danger-full-access \
      - \
      2>&1 | tee "$log_file"; then

      log "Codex finalizou. Rodando testes..."

      # Roda os testes
      if php artisan test --stop-on-failure 2>&1 | tee -a "$log_file"; then
        phase_success=true
        break
      else
        warn "Testes falharam após Codex"

        if [ $attempt -le $MAX_RETRIES ]; then
          local test_output
          test_output=$(php artisan test --stop-on-failure 2>&1 | tail -50)
          prompt_file=$(build_retry_prompt_file "$phase_file" "$test_output")
        fi
      fi
    else
      fail "Codex retornou erro"

      if [ $attempt -le $MAX_RETRIES ]; then
        local test_output
        test_output=$(tail -30 "$log_file" 2>/dev/null || echo "Sem output disponível")
        prompt_file=$(build_retry_prompt_file "$phase_file" "$test_output")
      fi
    fi
  done

  if $phase_success; then
    success "$phase_title — COMPLETA"

    # Commit no git
    if git rev-parse --is-inside-work-tree &> /dev/null 2>&1; then
      git add -A
      git commit -m "feat: $phase_title

Implementado automaticamente pelo Ralph Wiggum 🤡
$(date '+%Y-%m-%d %H:%M:%S')" --allow-empty
      log "Commit criado no git"
    fi

    # Marca progresso
    echo "$phase_file" >> "$PROGRESS_FILE"

    return 0
  else
    fail "$phase_title — FALHOU após $((MAX_RETRIES + 1)) tentativas"
    fail "Log disponível em: $log_file"
    return 1
  fi
}

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Verifica quais fases já foram completadas
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
is_phase_done() {
  local phase_file="$1"
  if [ -f "$PROGRESS_FILE" ]; then
    grep -qF "$phase_file" "$PROGRESS_FILE"
    return $?
  fi
  return 1
}

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Main
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
main() {
  preflight_checks
  split_phases

  local total_phases
  total_phases=$(wc -l < "$MANIFEST")

  echo ""
  log "📋 $total_phases fases para implementar"
  echo ""

  # Lista as fases
  local num=0
  while IFS="|" read -r file title; do
    num=$((num + 1))
    if is_phase_done "$file"; then
      echo -e "  ${GREEN}[$num] ✅ $title (já completada)${NC}"
    else
      echo -e "  ${YELLOW}[$num] ⏳ $title${NC}"
    fi
  done < "$MANIFEST"

  echo ""
  ralph "'I'm learnding!'"
  echo ""
  read -p "🚀 Iniciar implementação? (Y/n) " -n 1 -r
  echo
  [[ $REPLY =~ ^[Nn]$ ]] && exit 0

  # Executa cada fase
  local current=0
  local failed_phases=()
  local skipped_phases=()
  local completed_phases=()

  while IFS="|" read -r file title; do
    current=$((current + 1))

    # Pula fases já completadas
    if is_phase_done "$file"; then
      log "Pulando $title (já completada)"
      skipped_phases+=("$title")
      continue
    fi

    if run_phase "$file" "$title" "$current" "$total_phases"; then
      completed_phases+=("$title")
    else
      failed_phases+=("$title")

      echo ""
      warn "Fase falhou: $title"
      read -p "Continuar para a próxima fase? (Y/n) " -n 1 -r
      echo
      [[ $REPLY =~ ^[Nn]$ ]] && break
    fi

  done < "$MANIFEST"

  # ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  # Relatório final
  # ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  ralph "RELATÓRIO FINAL — 'I'm a unitard!'"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo ""

  if [ ${#completed_phases[@]} -gt 0 ]; then
    success "Completadas (${#completed_phases[@]}):"
    for phase in "${completed_phases[@]}"; do
      echo -e "    ${GREEN}✅ $phase${NC}"
    done
    echo ""
  fi

  if [ ${#skipped_phases[@]} -gt 0 ]; then
    log "Puladas (${#skipped_phases[@]}):"
    for phase in "${skipped_phases[@]}"; do
      echo -e "    ⏭️  $phase"
    done
    echo ""
  fi

  if [ ${#failed_phases[@]} -gt 0 ]; then
    fail "Falharam (${#failed_phases[@]}):"
    for phase in "${failed_phases[@]}"; do
      echo -e "    ${RED}❌ $phase${NC}"
    done
    echo ""
    fail "Verifique os logs em $LOG_DIR/"
  fi

  echo ""
  if [ ${#failed_phases[@]} -eq 0 ]; then
    ralph "'Super Nintendo Chalmers!' — Tudo completo! 🎉"
  else
    ralph "'My cat's breath smells like cat food.' — Algumas fases falharam 😢"
  fi
  echo ""
}

# Roda
main