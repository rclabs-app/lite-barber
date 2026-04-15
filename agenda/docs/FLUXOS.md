# Fluxos de Negócio — Lite Barber SaaS

> Descrição técnica e passo a passo de cada fluxo principal do sistema.

---

## Fluxo 1: Cadastro e Onboarding da Barbearia

**Atores**: Dono da barbearia
**Resultado esperado**: Barbearia criada, dono autenticado, pronto para configurar

```
┌──────┐     ┌──────────┐     ┌──────────────────────┐     ┌─────────┐
│ Dono │────▶│ Frontend │────▶│  POST /api/register  │────▶│  MySQL  │
└──────┘     └──────────┘     └──────────────────────┘     └─────────┘
                                         │
                                         ▼
                              Cria user (role=owner)
                              Cria shop (slug auto-gerado)
                              Vincula user.shop_id = shop.id
                              Retorna token Sanctum
```

**Passo a Passo:**

1. Dono acessa `/cadastro` no frontend
2. Preenche: nome, email, senha, nome da barbearia
3. Frontend envia `POST /api/register`:
   ```json
   {
     "name": "João Silva",
     "email": "joao@barbearia.com",
     "password": "...",
     "shop_name": "Barbearia do João"
   }
   ```
4. Backend (em transação SQL):
   - Cria `User` com `role = 'owner'`
   - Cria `Shop` com `slug = str_slug('Barbearia do João')` → `barbearia-do-joao`
   - Vincula `user.shop_id = shop.id`
5. Backend retorna token Sanctum + dados do usuário + dados da shop
6. Frontend armazena token, redireciona para `/admin/config` (configuração inicial)
7. Admin configura: horários de funcionamento, dias, primeiro serviço, primeiro barbeiro

---

## Fluxo 2: Login do Dono

**Atores**: Dono/Admin
**Resultado esperado**: Token válido, redirecionado ao dashboard

```
1. POST /api/auth/login → { email, password }
   └── Laravel valida credenciais com Hash::check
   └── Gera token: $user->createToken('admin-app')->plainTextToken
   └── Responde: { data: { token, user } }

2. Frontend:
   └── Armazena token no localStorage
   └── Seta store auth.token + auth.user
   └── Redireciona → /admin/dashboard
```

**Tratamento de erros:**
- Credenciais inválidas → 401 + `message: "Credenciais inválidas"`
- Rate limit excedido → 429 + `message: "Muitas tentativas. Tente em 1 minuto"`

---

## Fluxo 3: Agendamento pelo Cliente (Público)

**Atores**: Cliente final (sem login)
**Resultado esperado**: Agendamento criado, cliente recebe confirmação

```
Cliente acessa: app.litebarber.com.br/s/barbearia-do-joao
        │
        ▼
┌─────────────────────┐
│  Step 1: Serviço    │  GET /api/shops/{slug}/services
│  Seleciona o corte  │
└────────┬────────────┘
         ▼
┌─────────────────────┐
│  Step 2: Barbeiro   │  GET /api/shops/{slug}/barbers
│  Escolhe quem       │
└────────┬────────────┘
         ▼
┌─────────────────────┐
│  Step 3: Data/Hora  │  GET /api/shops/{slug}/availability
│  Seleciona slot     │     ?date=2026-04-20&barber_id=3
└────────┬────────────┘
         ▼
┌─────────────────────┐
│  Step 4: Dados      │  Client-side apenas
│  Nome + Telefone    │
└────────┬────────────┘
         ▼
┌─────────────────────┐
│  Step 5: Confirmação│  POST /api/shops/{slug}/appointments
│  Resumo + Agendar   │
└────────┬────────────┘
         ▼
   Agendamento criado → status: 'scheduled'
   Frontend exibe: "Agendado para 20/04 às 14:30. Tchau!"
```

**Regras de negócio no `AppointmentService`:**
1. Verifica se `scheduled_at` está no futuro
2. Verifica se o slot está disponível (sem conflito para o barbeiro)
3. Verifica se o dia é um dia de trabalho da shop
4. Verifica se o horário está dentro do horário de funcionamento
5. Captura `price_snapshot` do serviço (imutável após criação)
6. Calcula `ends_at = scheduled_at + service.duration_minutes`

---

## Fluxo 4: Entrada na Fila Digital (Sem Agendamento)

**Atores**: Cliente que chegou na barbearia sem agendamento
**Resultado esperado**: Cliente entra na fila e vê sua posição

```
1. Cliente acessa: app.litebarber.com.br/s/{slug}/fila
   └── Tela simples: nome, telefone, serviço desejado (opcional)

2. POST /api/shops/{slug}/waitlist
   └── Body: { client_name, client_phone, service_id (opcional), barber_id (opcional) }

3. WaitlistService::join():
   └── Conta quantas entradas com status='waiting' existem para o shop
   └── Define position = count + 1
   └── Calcula estimated_wait = position × shop.slot_duration
   └── Cria entrada na waitlist

4. Resposta ao cliente:
   {
     "data": {
       "position": 3,
       "estimated_wait": 45,
       "message": "Você é o 3º na fila. Espera estimada: 45 minutos."
     }
   }

5. Cliente vê a tela de acompanhamento com polling a cada 30s
   └── GET /api/shops/{slug}/waitlist/{id}/position (rota pública)
```

---

## Fluxo 5: Gestão da Fila pelo Admin

**Atores**: Admin/Barbeiro no painel
**Resultado esperado**: Fila avançada com rastreabilidade completa

```
Painel Admin → /admin/fila

┌─────────────────────────────────────────┐
│  FILA AO VIVO                           │
│  ┌─────────────────────────────────┐   │
│  │ #1 João Santos   │ Corte + Barba │   │
│  │ Status: WAITING  │ [Chamar]      │   │
│  └─────────────────────────────────┘   │
│  ┌─────────────────────────────────┐   │
│  │ #2 Pedro Alves   │ Degradê       │   │
│  │ Status: WAITING  │              │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
```

**Ações disponíveis:**

| Ação          | Endpoint                              | Transição de Status         |
|---------------|---------------------------------------|-----------------------------|
| Chamar        | `PATCH /admin/waitlist/{id}/call`     | `waiting` → `called`        |
| Iniciar       | `PATCH /admin/waitlist/{id}/start`    | `called` → `in_service`     |
| Concluir      | `PATCH /admin/waitlist/{id}/complete` | `in_service` → `completed`  |
| Não compareceu| `PATCH /admin/waitlist/{id}/no-show`  | `called` → `no_show`        |

**Após cada ação:** `WaitlistService::recalculatePositions()` reordena os `waiting` restantes.

---

## Fluxo 6: Atualização de Status do Agendamento

**Atores**: Admin
**Resultado esperado**: Status correto refletido no painel e histórico

```
Estados válidos e transições:

  scheduled → confirmed → in_progress → completed
      │                                     
      └──────────────────────────────────→ cancelled
                                        → no_show

Endpoint: PATCH /api/admin/appointments/{id}/status
Body: { "status": "confirmed" | "in_progress" | "completed" | "cancelled" | "no_show" }

Regras:
- completed → não pode ser alterado (imutável)
- cancelled → não pode ser alterado (imutável)
- Admin sempre pode cancelar qualquer status anterior a 'completed'
```

---

## Fluxo 7: Cancelamento de Agendamento

**Atores**: Cliente (via link) ou Admin (painel)

**Cancelamento pelo Admin (painel):**
```
1. Admin clica em "Cancelar" no agendamento
2. Modal pede motivo (opcional)
3. PATCH /api/admin/appointments/{id}/status
   Body: { "status": "cancelled", "cancel_reason": "Cliente solicitou", "cancelled_by": "admin" }
4. Status atualizado, agendamento some da visualização ativa
```

**Cancelamento pelo Cliente (futuro - Fase 2):**
```
1. Cliente recebe link único no cadastro do agendamento
2. GET /api/appointments/{token}/cancel → página de confirmação
3. POST /api/appointments/{token}/cancel → cancela com cancelled_by='client'
4. Slot volta a ficar disponível automaticamente
```

---

## Fluxo 8: Relatório Financeiro Diário (Admin)

**Atores**: Admin
**Resultado esperado**: Faturamento previsto do dia

```
GET /api/admin/reports/daily?date=2026-04-15

ReportService::daily($shopId, $date):
└── Busca appointments do dia com status IN ('confirmed', 'in_progress', 'completed')
└── Soma price_snapshot de cada um
└── Agrupa por barbeiro
└── Retorna totais

Response:
{
  "data": {
    "date": "2026-04-15",
    "total_appointments": 12,
    "total_revenue": 870.00,
    "by_barber": [
      { "barber": "Carlos", "count": 7, "revenue": 490.00 },
      { "barber": "Marcos", "count": 5, "revenue": 380.00 }
    ]
  }
}
```

---

*Última atualização: Abril 2026*
