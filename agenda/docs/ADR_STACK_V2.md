# Architectural Decision Records — Lite Barber SaaS v2

> ADRs substituem completamente os registros anteriores (baseados em protótipo HTML/JS).
> Contexto: migração para um SaaS real de produção.

---

## ADR-001: Vue 3 como Framework Frontend (SPA)

- **Status**: Aceito
- **Data**: Abril/2026

### Contexto
O protótipo anterior foi construído em HTML/JS Vanilla com SPAs manuais via classes CSS. Funciona para demonstração, mas não é sustentável para um produto real: ausência de reatividade declarativa, estado global frágil, sem roteamento real, e impossível de escalar com novos contribuidores.

### Decisão
Adotar **Vue 3** (Composition API) com **Vite** como bundler para o frontend SPA.

### Justificativa
- Vue 3 tem curva de aprendizado menor que React e menor boilerplate que Angular
- Composition API permite organização lógica por feature (não por lifecycle hook)
- Ecossistema maduro: Vue Router, Pinia, VueUse
- Vite oferece HMR instantâneo e build otimizado para produção
- Excelente integração com TypeScript (opcional, mas disponível)
- Amplamente usado no mercado brasileiro — facilita contratação futura

### Consequências
- Necessário `npm run build` para gerar o bundle de produção
- Requer CORS configurado no backend para aceitar o domínio do frontend
- O frontend é um projeto separado (deploy independente)

---

## ADR-002: Laravel como API Backend Central

- **Status**: Aceito
- **Data**: Abril/2026

### Contexto
O projeto precisa de um backend real com banco de dados persistente, regras de negócio complexas (agendamento, conflitos de horário, multi-tenancy), autenticação segura e capacidade de escalar. Soluções BaaS (Backend as a Service) foram descartadas por falta de controle sobre a lógica de negócio.

### Decisão
Adotar **Laravel** (versão estável mais recente, atualmente Laravel 11+) como backend exclusivo da aplicação, expondo uma **API RESTful** consumida pelo Vue SPA.

### Justificativa
- Laravel é o framework PHP mais maduro e com melhor DX (Developer Experience)
- Eloquent ORM reduz drasticamente a complexidade de queries relacionais
- Ecossistema nativo: Sanctum (auth), Queue (jobs), Events (WebSockets futuros), Horizon
- PHP 8.3 tem performance comparável a Go/Python em workloads de API típicas
- Altamente documentado, com grande comunidade brasileira
- Permite migrar para microsserviços futuramente sem reescrever o domínio

### Consequências
- Requer PHP 8.2+ e Composer no servidor
- A API é separada do frontend — comunicação via JSON/HTTP
- Todos os endpoints devem retornar responses padronizadas (`data`, `message`, `errors`)

---

## ADR-003: MySQL como Banco de Dados Relacional

- **Status**: Aceito
- **Data**: Abril/2026

### Contexto
O sistema possui dados altamente relacionais: shops têm barbers, barbers têm appointments, appointments têm services. A integridade referencial é crítica para evitar agendamentos em horários já ocupados e garantir consistência dos relatórios financeiros.

### Decisão
Usar **MySQL 8.x** como único banco de dados primário.

### Justificativa
- SQL relacional é a escolha correta para dados com relacionamentos complexos e integridade necessária
- MySQL 8 suporta window functions, CTEs, JSON columns e full-text search
- Amplamente disponível em todos os provedores de hosting brasileiros (HostGator, Locaweb, DigitalOcean)
- Custo operacional baixo — versão managed acessível em qualquer provedor
- Eloquent + MySQL tem integração nativa e otimizada no Laravel
- Equipe tem familiaridade com SQL (menor fricção que NoSQL para este domínio)

### Consequências
- Requer planejamento cuidadoso de índices para queries de disponibilidade (hot path)
- Migrations devem ser versionadas e testadas antes do deploy em produção
- Backup automatizado é obrigatório (recomenda-se dump diário para S3/R2)

---

## ADR-004: Estratégia de Multi-Tenancy via Coluna `shop_id`

- **Status**: Aceito
- **Data**: Abril/2026

### Contexto
O Lite Barber é um SaaS: múltiplas barbearias independentes compartilham a mesma infraestrutura. Os dados de uma barbearia nunca podem ser visíveis ou modificáveis por outra. É preciso definir como implementar esse isolamento.

### Decisão
Usar **Single Database / Shared Schema** com coluna `shop_id` em todas as tabelas de negócio. Um **Global Scope** no Eloquent injeta automaticamente o filtro pelo `shop_id` do tenant autenticado.

### Justificativa
- Solução mais simples de implementar e operar no estágio atual do produto
- Suporta facilmente até centenas de tenants com volume de dados típico de barbearias de bairro
- Migrations únicas — não há necessidade de gerenciar schemas separados por cliente
- Facilita análises agregadas e relatórios SaaS (métricas de plataforma)
- Estratégia pode ser migrada para schemas separados futuramente sem reescrever o domínio

### Consequências
- **Risco crítico**: Esquecer o filtro `shop_id` em alguma query expõe dados de outros tenants. Mitigado com Global Scopes automáticos nos Models.
- Índices compostos (`shop_id` + campo buscado) são obrigatórios para performance
- Crescimento explosivo de um único tenant pode afetar outros (mitigado com rate limiting e monitoramento)

---

## ADR-005: Arquitetura Desacoplada Frontend/Backend

- **Status**: Aceito
- **Data**: Abril/2026

### Contexto
A abordagem anterior era um único arquivo HTML. Para produção, precisamos de um sistema que permita deploys independentes, times separados trabalhando em paralelo, e a possibilidade de um app mobile consumir a mesma API no futuro.

### Decisão
Frontend (Vue SPA) e Backend (Laravel API) são **projetos completamente separados**, com repositórios (ou subpastas) independentes, deployados em domínios distintos, comunicando-se exclusivamente via HTTP/JSON.

| Componente | Domínio (exemplo)            |
|------------|------------------------------|
| Frontend   | `app.litebarber.com.br`      |
| Backend    | `api.litebarber.com.br`      |

### Justificativa
- Permite deploy do frontend via CDN (Vercel/Netlify) com cache otimizado automaticamente
- Backend pode ser escalado horizontalmente (múltiplas instâncias) sem afetar o frontend
- A mesma API pode ser consumida por um app mobile futuro sem mudanças
- Separação clara de responsabilidades: UI/UX vs. Regras de negócio

### Consequências
- CORS deve ser configurado corretamente no Laravel para aceitar requests do domínio do frontend
- Requer gestão de variáveis de ambiente em dois ambientes distintos
- Logs e monitoramento precisam cobrir ambos os projetos

---

## ADR-006: Autenticação com Laravel Sanctum (Token Bearer)

- **Status**: Aceito
- **Data**: Abril/2026

### Contexto
O sistema precisa autenticar donos de barbearia no painel admin. A autenticação precisa ser segura, stateless (para escalar sem sessão no servidor) e compatível com uma SPA desacoplada.

### Decisão
Usar **Laravel Sanctum** com tokens Bearer para autenticação do painel admin.

### Justificativa
- Sanctum é o padrão oficial do Laravel para autenticação de SPAs e APIs
- Tokens são stateless: o servidor não precisa manter sessão em banco ou memória
- Simples de implementar: `$user->createToken('auth_token')->plainTextToken`
- Suporta expiração de token configurável
- Sem overhead de JWT (não precisa de biblioteca externa)
- Permite revogar tokens individualmente (logout em dispositivos específicos)

### Fluxo
```
POST /api/auth/login → { token: "..." }
GET  /api/admin/*    → Header: Authorization: Bearer {token}
POST /api/auth/logout → Revoga token atual
```

### Consequências
- Token armazenado no `localStorage` do frontend (aceitável para admin SPA)
- Logout deve chamar a API para revogar o token (não apenas limpar o storage)
- Tokens não expiram automaticamente sem configuração do `expiration` no Sanctum

---

## ADR-007: Estratégia de Fila (Gradual: Síncrono → Redis)

- **Status**: Aceito
- **Data**: Abril/2026

### Contexto
No MVP, o volume de operações assíncronas é baixo: sem envio de e-mails automáticos, sem push notifications. Mas no futuro, o sistema precisará de jobs para: notificações WhatsApp (via API), relatórios periódicos, lembretes de agendamento.

### Decisão
**Fase MVP**: Processamento síncrono — sem sistema de filas, operações no request.

**Fase 2**: Laravel Queues com **Redis** como driver, monitorado via **Laravel Horizon**.

### Justificativa
- Não complicar o MVP com infraestrutura desnecessária
- A API do Laravel Queues é idêntica entre os drivers (`database`, `redis`) — troca é transparente
- Redis é performático, battle-tested, e disponível na maioria dos provedores
- Horizon oferece dashboard visual de monitoramento dos workers (essencial em produção)

### Consequências
- Mudar de driver síncrono para Redis no futuro requer apenas: instalar Redis + trocar `QUEUE_CONNECTION` no `.env` e rodar `php artisan horizon`
- Jobs devem ser **idempotentes** (re-execução segura em caso de falha)

---

*Última atualização: Abril 2026 — Stack v2.0 (Laravel + Vue 3 + MySQL)*
