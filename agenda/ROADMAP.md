# Roadmap de Implementação — Lite Barber SaaS

> Do protótipo local ao SaaS de produção.
> Stack: Vue 3 + Vite | Laravel 11+ | MySQL 8 | Redis (Fase 2)

---

## Comparativo: Protótipo vs. Produção

| Aspecto              | Protótipo Atual                   | Produção (Nova Stack)                      |
|----------------------|-----------------------------------|--------------------------------------------|
| **Dados**           | `localStorage` (por navegador)    | MySQL centralizado (persistente e real)    |
| **Multi-usuário**   | Impossível — dados locais          | Multi-tenant nativo via `shop_id`          |
| **Multi-dispositivo**| Não — dados presos no browser    | Sim — API centralizada                     |
| **Autenticação**    | Código fixo `1234`                | Laravel Sanctum com token Bearer           |
| **Estado**          | JS global com variáveis           | Pinia stores + API                         |
| **Segurança**       | Zero — tudo exposto no JS         | Autenticação, autorização, rate limiting   |
| **Escalabilidade**  | 1 barbearia, 1 navegador          | N barbearias, N usuários simultâneos       |
| **Deploy**          | Abrir `index.html`                | CI/CD, Nginx, HTTPS, VPS                  |
| **Backup**          | Nenhum (localStorage apagável)    | MySQL dumps automáticos diários            |
| **Confiabilidade**  | Qualquer erro apaga dados         | Transações SQL, soft deletes               |

---

## Fase 1 — MVP Funcional (Semanas 1-6)

**Objetivo**: Sistema funcional end-to-end para 1 barbearia real, pronto para validar com clientes reais.

### Backend (Laravel)

- [ ] **Setup inicial**
  - [ ] `laravel new lite-barber-api`
  - [ ] Configurar `.env` com conexão MySQL
  - [ ] Instalar e configurar Laravel Sanctum
  - [ ] Configurar CORS (`config/cors.php`)

- [ ] **Database**
  - [ ] Migration: `shops`
  - [ ] Migration: `users` (com `role` e `shop_id`)
  - [ ] Migration: `barbers`
  - [ ] Migration: `services`
  - [ ] Migration: `appointments`
  - [ ] Migration: `waitlist`
  - [ ] Seeders de desenvolvimento (shop + admin + serviços demo)

- [ ] **Autenticação**
  - [ ] `POST /api/register` — cadastro de barbearia + dono
  - [ ] `POST /api/auth/login` — retorna token Sanctum
  - [ ] `POST /api/auth/logout` — revoga token
  - [ ] `GET /api/auth/me` — retorna usuário autenticado
  - [ ] Middleware `SetTenantContext`
  - [ ] Rate limiting nas rotas de login

- [ ] **API Pública** (sem autenticação)
  - [ ] `GET /api/shops/{slug}` — dados públicos da barbearia
  - [ ] `GET /api/shops/{slug}/services`
  - [ ] `GET /api/shops/{slug}/barbers`
  - [ ] `GET /api/shops/{slug}/availability?date=&barber_id=`
  - [ ] `POST /api/shops/{slug}/appointments` — criar agendamento
  - [ ] `POST /api/shops/{slug}/waitlist` — entrar na fila

- [ ] **API Admin** (autenticação obrigatória)
  - [ ] `GET /api/admin/appointments?date=` — agenda do dia
  - [ ] `PATCH /api/admin/appointments/{id}/status`
  - [ ] `DELETE /api/admin/appointments/{id}`
  - [ ] `GET /api/admin/waitlist` — fila ao vivo
  - [ ] `PATCH /api/admin/waitlist/{id}/call`
  - [ ] `PATCH /api/admin/waitlist/{id}/start`
  - [ ] `PATCH /api/admin/waitlist/{id}/complete`
  - [ ] `PATCH /api/admin/waitlist/{id}/no-show`
  - [ ] CRUD `/api/admin/services`
  - [ ] CRUD `/api/admin/barbers`
  - [ ] `GET/PATCH /api/admin/shop` — configurações da barbearia
  - [ ] `GET /api/admin/reports/daily`

- [ ] **Services (Business Logic)**
  - [ ] `AvailabilityService` — geração de slots disponíveis
  - [ ] `AppointmentService` — validação e criação de agendamentos
  - [ ] `WaitlistService` — gestão de posições na fila
  - [ ] `ReportService` — relatório financeiro diário

### Frontend (Vue 3)

- [ ] **Setup inicial**
  - [ ] `npm create vite@latest lite-barber-app -- --template vue`
  - [ ] Instalar: `vue-router`, `pinia`, `axios`
  - [ ] Configurar `api.js` (axios instance + interceptors)
  - [ ] Configurar variáveis de ambiente (`.env`)

- [ ] **Auth Store + Guard**
  - [ ] `stores/auth.js` (login, logout, token persistência)
  - [ ] Navigation guard no router

- [ ] **Telas Públicas**
  - [ ] `ShopLanding.vue` — página da barbearia (serviços, barbeiros, botões CTA)
  - [ ] `BookingFlow.vue` — wizard de 4 steps (serviço → barbeiro → horário → dados)
  - [ ] `WaitlistJoin.vue` — formulário de entrada na fila
  - [ ] Componentes: `ServiceCard`, `BarberCard`, `TimeSlotGrid`, `DatePicker`

- [ ] **Painel Admin**
  - [ ] `Login.vue`
  - [ ] `Dashboard.vue` — métricas do dia (agendamentos, fatura, fila)
  - [ ] `AgendaView.vue` — lista de agendamentos do dia + ações de status
  - [ ] `WaitlistView.vue` — fila ao vivo com ações
  - [ ] `ServicesView.vue` — CRUD de serviços
  - [ ] `BarbersView.vue` — CRUD de barbeiros
  - [ ] `SettingsView.vue` — configurações da barbearia

---

## Fase 2 — Melhorias e Estabilização (Semanas 7-12)

**Objetivo**: Polir o produto, corrigir os pontos fracos do MVP e entregar uma experiência profissional.

- [ ] **Atualização em tempo real da fila**
  - [ ] Polling automático a cada 30 segundos no Vue (simples, sem WebSocket)
  - [ ] Ou: implementar Laravel Reverb (WebSockets) para push em tempo real

- [ ] **Cancelamento pelo cliente**
  - [ ] Token único por agendamento (UUID)
  - [ ] `GET/POST /api/appointments/{token}/cancel`
  - [ ] Tela de cancelamento pública

- [ ] **Notificações WhatsApp**
  - [ ] Botão "Notificar no WhatsApp" no painel (link `wa.me/` com mensagem pré-preenchida)
  - [ ] Fase futura: integração com Evolution API ou Z-API para envio automático

- [ ] **Redis + Queue**
  - [ ] Instalar Redis no servidor
  - [ ] Mudar `QUEUE_CONNECTION=redis`
  - [ ] Instalar Laravel Horizon
  - [ ] Mover envio de notificações para Jobs

- [ ] **Gestão avançada de agenda**
  - [ ] Bloqueio de datas (folgas, feriados) — tabela `blocked_slots`
  - [ ] Visualização semanal da agenda no admin
  - [ ] Filtro por barbeiro na agenda

- [ ] **UX Polish**
  - [ ] Loading states em todas as operações
  - [ ] Toasts de sucesso/erro (Vue Toastification ou similar)
  - [ ] Confirmação modal para ações destrutivas (cancelar, deletar)
  - [ ] PWA básico (manifest + service worker) para instalação no celular

- [ ] **Segurança**
  - [ ] HTTPS + redirect HTTP → HTTPS no Nginx
  - [ ] Validação de `shop_id` em todos os endpoints admin (Policy)
  - [ ] Auditoria de ações críticas (log em tabela `audit_logs`)

---

## Fase 3 — SaaS Completo (Semanas 13+)

**Objetivo**: Transformar em produto comercializável com múltiplas barbearias, planos e cobrança.

- [ ] **Onboarding Multi-tenant**
  - [ ] Página de cadastro pública (`/cadastro`)
  - [ ] Onboarding wizard pós-cadastro (configurar shop em 5 passos)
  - [ ] Página de landing do produto (`/`)

- [ ] **Sistema de Planos e Assinaturas**
  - [ ] Tabela `plans` e `subscriptions`
  - [ ] Integração com Pagar.me ou Mercado Pago (pagamento recorrente)
  - [ ] Enforcement de limites por plano (ex: plano free = máx 2 barbeiros)
  - [ ] Dashboard de billing para o admin

- [ ] **Relatórios Avançados**
  - [ ] Histórico de agendamentos (filtros por período, barbeiro, serviço)
  - [ ] Gráficos de faturamento (Chart.js ou Vue-chartjs)
  - [ ] Export CSV de agendamentos

- [ ] **App Mobile (Progressive Web App)**
  - [ ] Manifest completo + ícones
  - [ ] Notificações push (Web Push API)
  - [ ] Modo offline para visualização da agenda

- [ ] **Painel Super Admin** (interno RCLabs)
  - [ ] Lista de todas as barbearias
  - [ ] Métricas de uso por tenant
  - [ ] Gerenciamento de planos e suspensão

- [ ] **Integrações**
  - [ ] Google Calendar sync (agendamentos → Google Calendar do barbeiro)
  - [ ] WhatsApp Business API automático (Evolution API)
  - [ ] Instagram link automático na página pública

---

## Estimativa de Esforço

| Fase   | Duração Estimada | Resultado                             |
|--------|-----------------|---------------------------------------|
| Fase 1 | 6 semanas       | MVP funcional para 1ª barbearia real  |
| Fase 2 | 6 semanas       | Produto estável e polido              |
| Fase 3 | 8+ semanas      | SaaS comercial com billing            |

---

*Última atualização: Abril 2026*
