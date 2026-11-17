# Registro de Sessão — 24/Jul/2026

> **Projeto:** Elite Agenda (Lite-Barber)  
> **Sessão:** 24/Jul/2026, ~00:37 às 03:02 (horário local)  
> **Conversation ID:** `ae7a55b3-702b-41e1-9c9d-c1968b1234bf`

---

## 1. Alterações de Código Realizadas

### 1.1 Correção de Flash/Scroll na Navegação

**Problema:** Ao trocar de página, a tela dava uma "piscada" e uma rolagem para baixo/para cima.

**Causa raiz:**
- Animação `fadeInUp` no `.view.active` disparava a cada montagem de componente
- Vue Router não resetava o scroll para o topo

**Arquivos modificados:**

#### `src/router/index.js`
- Adicionado `scrollBehavior()` ao `createRouter` com `{ top: 0, behavior: 'instant' }`

#### `src/App.vue`
- Envolveu os `<RouterView>` com `<Transition name="page-fade" mode="out-in">`
- Adicionou CSS de fade suave (0.18s) para entrada/saída de páginas
- Usa `v-slot="{ Component }"` + `<component :is="Component" :key="route.path" />`

#### `src/style.css`
- Removida a animação `fadeInUp 0.8s` do `.view.active`
- Mantido apenas `display: flex; flex-direction: column;`

---

### 1.2 Toggle de Exibir/Ocultar Senha

**Arquivos modificados:**

#### `src/views/LoginView.vue`
- Adicionado `ref showPassword`
- Input de senha com `:type="showPassword ? 'text' : 'password'"`
- Botão com ícone `fa-eye` / `fa-eye-slash`

#### `src/views/AdminDashboardView.vue`
- Adicionado `ref showProfilePassword`
- Campo "Nova Senha" na edição de perfil com o mesmo toggle
- Botão posicionado absolutamente dentro do input (`position: absolute; right: 12px`)

---

### 1.3 Redesign do Formulário de Login

**Arquivo:** `src/views/LoginView.vue` (reescrito completamente)

**Mudanças:**
- Inputs com caixa unificada (borda em todos os lados, fundo `#0d0d0d`)
- Ícones integrados como "prefixo" com divisor vertical (`border-right`)
- Foco com destaque: borda e ícone mudam para `var(--accent)` (dourado)
- Botão ENTRAR full-width com chevron
- Neutralização do autofill do browser (`-webkit-box-shadow: inset`)
- Labels com estilo `0.65rem`, `font-weight: 800`, `letter-spacing: 2px`
- CSS scoped com classes: `.login-form`, `.login-field`, `.login-input-box`, `.login-input-icon`, `.login-eye-btn`, `.login-submit-btn`

---

### 1.4 Campo Descrição nos Serviços

**Arquivos modificados:**

#### `src/views/AdminDashboardView.vue`
- `newService` ref: adicionado campo `description: ''`
- Funções `addService`, `editService`, `cancelEdit`: incluem `description`
- Template: adicionado `<textarea>` para descrição no formulário de serviços

#### `src/views/BookingView.vue`
- Card de serviço: adicionado `<p v-if="s.description" class="service-desc">` abaixo do nome
- Exibição condicional (só aparece se tiver descrição)

#### `src/style.css`
- Nova classe `.service-desc`: `font-size: 0.8rem`, `font-weight: 400`, `color: #aaa`
- Estado selecionado: `.service-card input:checked+.card-content .service-desc` → `color: #333`

---

## 2. Planejamento Estratégico

### 2.1 Planos Lite vs Pro (Decisões Finais)

| Recurso | **Lite** | **Pro** |
|---|:---:|:---:|
| Profissionais | Até 2 | Até 8 |
| Serviços | Até 5 | Até 20 |
| Agendamentos | Ilimitado | Ilimitado |
| Link personalizado | ✅ | ✅ |
| Página inicial | Básica (logo+agendar+mapa+redes) | Landing Page semi-padronizada |
| WhatsApp: Confirmação | ✅ | ✅ |
| WhatsApp: Cancelamento | ✅ | ✅ |
| WhatsApp: Lembrete | ✅ | ✅ |
| Temas | Black / White | Black / White / Nude / Wine |
| Google Maps | ✅ | ✅ |
| Ícones sociais | ✅ | ✅ |
| Relatórios | Básico (diário) | Detalhado (financeiro) |
| Fila de espera | ❌ | ✅ |
| LP do estabelecimento | ❌ | ✅ |
| Cadastro fidelidade | ❌ | ✅ |
| Código promocional | ❌ | ✅ |
| Multi-unidade | ❌ | ✅ |

### 2.2 Pricing

| Mercado | Lite | Pro |
|---|---|---|
| **Brasil** | R$ 19,90/mês | R$ 49,90/mês |
| **EUA** | $ 4,99/mês | $ 14,99/mês |
| **Portugal** | € 4,99/mês | € 14,99/mês |

### 2.3 Redução de Custos — Evolution API

- WhatsApp Business API oficial: R$ 0,25-0,50/mensagem → **caro**
- Evolution API (self-hosted): R$ 0/mensagem → **grátis**
- Custo total por cliente/mês cai de R$13-18 para **~R$1**
- Margem do Lite R$19,90: de R$4,90 → **R$18,90 (95%)**
- Toda infra roda numa VPS de R$40/mês (Hetzner/Contabo)

### 2.4 Fluxos de WhatsApp Definidos

1. **Confirmação pós-agendamento**: Link com prazo. Não confirmou → vaga liberada
2. **Cancelamento pós-confirmação**: Link para cancelar com antecedência mínima
3. **Lembrete automático**: X horas antes (configurável pelo admin)
4. **Fila inteligente (Pro)**: Loop rotativo — não confirmou → final da fila → próximo

### 2.5 Internacionalização

- Mercados-alvo pós-validação: EUA e Portugal
- Nicho nos EUA: barbershops de imigrantes (brasileiros, latinos, africanos)
- Requisitos técnicos: i18n (vue-i18n), moeda dinâmica, timezone, Stripe
- Projeção 180 clientes (BR+US+PT): ~R$5.590/mês lucro líquido

### 2.6 Temas por Segmento

| Tema | Segmento | Plano |
|---|---|---|
| Black | Barbearias | Lite + Pro |
| White | Barbearias | Lite + Pro |
| Nude | Studios beleza/estética | Pro |
| Wine | Studios beleza/estética | Pro |

### 2.7 Itens Futuros Confirmados

- **Chatbot + Tickets (SAC)**: Viável. Chatbot de regras fixas + CRUD de tickets. ~2-3 semanas
- **Agentes IA monitoramento**: Viável em camadas. PHPUnit → Sentry → CRON + LLM
- **Testes automatizados**: PHPUnit (backend) + Vitest/Playwright (frontend)
- **Landing Page de vendas**: Pertence à empresa RCLabs. Cadastro de novos estabelecimentos

---

## 3. Backlog Pendente (Próximos Passos)

| Prioridade | Item | Descrição |
|---|---|---|
| 🔴 Alta | Google Maps + Ícones sociais | Iframe do Maps + ícones Instagram/WhatsApp na página inicial |
| 🔴 Alta | Limites de plano (backend) | Middleware Laravel para enforcar limites Lite (2 prof, 5 serviços) |
| 🔴 Alta | Slug dinâmico | Trocar `/elite-agenda` hardcoded por slug personalizado |
| 🔴 Alta | LP de vendas da empresa | Página de captação + RegisterView.vue |
| 🟡 Média | Evolution API (WhatsApp) | Setup Docker + integração com Laravel |
| 🟡 Média | Testes (PHPUnit) | Cobertura dos endpoints críticos |
| 🟡 Média | i18n | Vue-i18n para pt-BR, en-US, pt-PT |
| 🟢 Baixa | Chatbot + Tickets | Sistema de suporte self-service |
| 🟢 Baixa | Agente IA | Análise diária de logs |

---

## 4. Arquivos Modificados (Referência Rápida)

| Arquivo | Modificação |
|---|---|
| `src/router/index.js` | scrollBehavior adicionado |
| `src/App.vue` | Vue Transition + fade CSS |
| `src/style.css` | Remoção fadeInUp + classes .service-desc |
| `src/views/LoginView.vue` | Redesign completo do form |
| `src/views/AdminDashboardView.vue` | Toggle senha perfil + campo descrição serviços |
| `src/views/BookingView.vue` | Exibição da descrição nos cards |

---

## 5. Documento de Planejamento

O plano estratégico detalhado (última versão) está salvo em:
`artifacts/plano_lite_pro.md`

Contém: comparativo Lite vs Pro, análise de custos com Evolution API, pricing internacional, projeções de receita, requisitos técnicos de i18n, stack de infraestrutura, e roadmap com Gantt.
