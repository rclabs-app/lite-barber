# PRODUCAO.md — Lite Barber SaaS

> Documento de produção oficial. Reflete a arquitetura real para desenvolvimento e deploy.
> Stack: **Vue 3 + Vite | Laravel | MySQL**

---

## 1. Visão Geral do Produto

**Lite Barber** é um SaaS de agendamento e fila digital voltado para barbearias de bairro. O objetivo é oferecer uma plataforma simples, rápida e acessível que permita a qualquer barbearia ter sua própria agenda online e sistema de fila sem precisar de conhecimento técnico.

### Modelo de Negócio
- **Multi-tenant**: Cada barbearia é um tenant isolado (`shop_id`)
- **SaaS por assinatura**: Planos futuros (Starter, Pro, Premium)
- **Usuários finais**: Donos de barbearia (admins) + Clientes (end-users)

### Diferenciais
- Zero fricção para o cliente agendar via link público
- Fila digital em tempo real sem app instalado
- Painel admin simples o suficiente para qualquer barbeiro operar
- Backend 100% controlado — sem dependência de BaaS externo

---

## 2. Requisitos

### 2.1 Requisitos Funcionais

| ID   | Requisito                                                                 | Prioridade |
|------|---------------------------------------------------------------------------|------------|
| RF01 | Cadastro de barbearia (shop) com nome, endereço, horários                 | Alta       |
| RF02 | Cadastro e login de usuário dono (admin)                                  | Alta       |
| RF03 | Gestão de serviços (nome, preço, duração)                                 | Alta       |
| RF04 | Gestão de barbeiros (nome, foto, especialidades)                          | Alta       |
| RF05 | Agendamento de horário pelo cliente (sem cadastro necessário no MVP)      | Alta       |
| RF06 | Visualização e gestão da agenda pelo admin                                | Alta       |
| RF07 | Entrada na fila digital pelo cliente                                      | Alta       |
| RF08 | Painel da fila para o admin (chamada, avanço, conclusão)                  | Alta       |
| RF09 | Cancelamento de agendamento (pelo cliente e pelo admin)                   | Alta       |
| RF10 | Configuração de dias e horários de funcionamento                          | Média      |
| RF11 | Notificação por WhatsApp (link direto, sem API no MVP)                    | Média      |
| RF12 | Relatório financeiro diário (agendamentos × preço dos serviços)           | Média      |
| RF13 | Cadastro de múltiplos barbeiros por barbearia                             | Média      |
| RF14 | Sistema de planos/assinaturas                                             | Baixa      |

### 2.2 Requisitos Não Funcionais

| ID    | Requisito                                                                 |
|-------|---------------------------------------------------------------------------|
| RNF01 | API responde em < 300ms para operações com dados simples                  |
| RNF02 | Autenticação stateless via token (Sanctum)                                |
| RNF03 | Rate limiting nas rotas públicas (proteção contra abuso)                  |
| RNF04 | Isolamento total de dados entre tenants via `shop_id`                     |
| RNF05 | Frontend desacoplado do backend — comunicação exclusiva via API REST      |
| RNF06 | Responsividade mobile-first (operado principalmente por smartphones)      |
| RNF07 | Escalável horizontalmente (stateless backend)                             |
| RNF08 | Variáveis de ambiente para todas as configurações sensíveis               |
| RNF09 | HTTPS obrigatório em produção                                             |
| RNF10 | Logs de auditoria para ações críticas (cancel, delete)                    |

---

## 3. Arquitetura do Sistema

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENTE (Browser)                    │
│                Vue 3 SPA (Vite Build)                   │
│          Vue Router | Pinia | Axios                     │
└──────────────────────────┬──────────────────────────────┘
                           │  HTTPS / JSON (REST API)
                           │
┌──────────────────────────▼──────────────────────────────┐
│                   BACKEND (Laravel)                     │
│              API RESTful — Laravel 11+                  │
│   Routes → Middleware → Controller → Service → Model    │
│                  Laravel Sanctum (Auth)                 │
│              Laravel Queues (Jobs futuros)              │
└──────────────┬───────────────────────┬──────────────────┘
               │                       │
┌──────────────▼──────┐   ┌────────────▼─────────────────┐
│   MySQL (Principal) │   │   Redis (Fila / Cache)       │
│   Dados relacionais │   │   Opcional no MVP            │
└─────────────────────┘   └──────────────────────────────┘
```

### Princípios Arquiteturais
1. **Separação total**: Frontend e backend são projetos independentes, deployados separadamente
2. **API-first**: O backend é puro REST. A UI é apenas um consumidor da API
3. **Service Layer**: Business logic fica em classes `Service`, não nos Controllers
4. **Multi-tenancy implícito**: Todo request autenticado carrega o `shop_id` via Middleware
5. **Stateless**: Backend não mantém sessão — usa tokens Bearer (Sanctum)

---

## 4. Estratégia de Autenticação

### Tecnologia: Laravel Sanctum (SPA Tokens)

**Fluxo:**
1. Admin envia `POST /api/auth/login` com `email` + `password`
2. Backend valida com `Hash::check`, retorna **token Bearer** com validade configurável
3. Frontend armazena o token em `localStorage` (ou cookie HttpOnly para maior segurança)
4. Todos os requests subsequentes enviam `Authorization: Bearer {token}` no header
5. Middleware `auth:sanctum` valida o token em cada rota protegida

**Rotas públicas** (sem autenticação):
- `GET /api/shops/{slug}` — dados públicos da barbearia
- `GET /api/shops/{slug}/services` — listagem de serviços
- `GET /api/shops/{slug}/availability` — horários disponíveis
- `POST /api/shops/{slug}/appointments` — criar agendamento
- `POST /api/shops/{slug}/waitlist` — entrar na fila

**Rotas protegidas** (token obrigatório):
- Todo `/api/admin/*`

---

## 5. Estratégia de Multi-Tenancy

### Abordagem: Single Database / Shared Schema

Todos os tenants compartilham o mesmo banco de dados e as mesmas tabelas. O isolamento é garantido pela coluna `shop_id` presente em todas as tabelas de negócio.

**Por que essa abordagem?**
- Simples de implementar e manter no estágio inicial
- Adequada para centenas de tenants com volume de dados médio
- Suporta escalar para milhares de barbearias sem grandes mudanças
- Migrations únicas, sem gerenciamento de múltiplos schemas

**Garantia de isolamento:**
```php
// Middleware: SetTenantContext.php
// Toda query de negócio inclui automaticamente o shop_id do usuário autenticado

class TenantScopeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $shopId = $request->user()->shop_id;
        app()->instance('current_shop_id', $shopId);
        return $next($request);
    }
}
```

Todos os Models com `shop_id` implementam um **Global Scope** que injeta o filtro automaticamente, evitando vazamento de dados entre tenants.

---

## 6. Estratégia de Fila

### Fase MVP: Síncrono (sem fila)
- Processamento direto no request
- Adequado para notificações simples e operações leves

### Fase 2: Laravel Queues com Redis
- Jobs assíncronos para: envio de mensagens WhatsApp, relatórios, notificações push
- Redis como driver de fila (mais rápido que database)
- Horizon para monitoramento dos workers

---

## 7. Estratégia de Deploy

### Ambiente de Produção Recomendado

| Componente        | Tecnologia                            |
|-------------------|---------------------------------------|
| Backend (Laravel) | VPS Ubuntu + PHP 8.3 + Nginx + FPM   |
| Frontend (Vue)    | Vercel / Netlify / Nginx static       |
| Banco de Dados    | MySQL 8.x (managed ou self-hosted)    |
| Cache/Fila        | Redis 7.x                             |
| HTTPS             | Certbot (Let's Encrypt)               |
| Deploy            | GitHub Actions → SSH deploy           |

### Variáveis de Ambiente (Backend `.env`)
```env
APP_ENV=production
APP_KEY=base64:...
APP_URL=https://api.litebarber.com.br

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lite_barber
DB_USERNAME=barber_user
DB_PASSWORD=...

SANCTUM_STATEFUL_DOMAINS=app.litebarber.com.br

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Variáveis de Ambiente (Frontend `.env`)
```env
VITE_API_BASE_URL=https://api.litebarber.com.br
VITE_APP_NAME=Lite Barber
```

### Pipeline CI/CD (GitHub Actions — Exemplo)
```yaml
# .github/workflows/deploy.yml
on:
  push:
    branches: [main]

jobs:
  deploy-backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Deploy via SSH
        run: |
          ssh user@server "cd /var/www/api && git pull && composer install && php artisan migrate --force && php artisan config:cache"

  deploy-frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: npm ci && npm run build
      - name: Deploy to Vercel
        uses: amondnet/vercel-action@v25
```

---

## 8. Segurança

| Camada        | Medida                                                           |
|---------------|------------------------------------------------------------------|
| Autenticação  | Bearer Token (Sanctum) — expiração configurável                  |
| Autorização   | Policy/Gate por recurso                                          |
| Validação     | FormRequest em cada endpoint (server-side obrigatório)           |
| SQL Injection | Eloquent ORM + Query Builder com bindings                        |
| Rate Limiting | `throttle:60,1` nas rotas públicas, `throttle:10,1` em login     |
| CORS          | Configurado para aceitar somente domínios do frontend            |
| Secrets       | Somente via `.env` — nunca hardcoded no código                   |
| HTTPS         | Obrigatório — redirect HTTP → HTTPS via Nginx                    |
| Senha         | `Hash::make()` com bcrypt (padrão do Laravel)                    |
| XSS           | Vue escapa output por padrão; API retorna JSON puro              |

---

*Última atualização: Abril 2026 — Versão 2.0 (Nova Stack de Produção)*
