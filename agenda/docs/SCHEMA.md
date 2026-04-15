# Schema do Banco de Dados — Lite Barber SaaS (MySQL)

> Versão: 2.0 | Stack: MySQL 8.x + Laravel Migrations

---

## Diagrama de Relacionamentos (ERD)

```
shops ──────< users
  │
  ├──────< barbers
  │
  ├──────< services
  │
  ├──────< appointments >──── barbers
  │              │
  │              └──────── services
  │
  └──────< waitlist >──────── barbers
```

---

## Tabelas

### `shops` — Barbearias (Tenants)

```sql
CREATE TABLE shops (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)    NOT NULL,
    slug            VARCHAR(100)    NOT NULL UNIQUE,  -- URL pública ex: minha-barbearia
    owner_id        BIGINT UNSIGNED NOT NULL,          -- FK → users.id
    phone           VARCHAR(20)     NULL,
    address         VARCHAR(255)    NULL,
    city            VARCHAR(100)    NULL,
    state           CHAR(2)         NULL,
    logo_url        VARCHAR(500)    NULL,
    description     TEXT            NULL,
    -- Configurações de funcionamento
    opening_time    TIME            NOT NULL DEFAULT '08:00:00',
    closing_time    TIME            NOT NULL DEFAULT '19:00:00',
    slot_duration   TINYINT UNSIGNED NOT NULL DEFAULT 30, -- minutos por slot
    working_days    JSON            NOT NULL,             -- ex: [1,2,3,4,5] (seg-sex)
    -- Plano/Assinatura (preparado para futuro)
    plan            ENUM('free','starter','pro','premium') NOT NULL DEFAULT 'free',
    plan_expires_at TIMESTAMP       NULL,
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    -- Auditoria
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,   -- soft delete

    INDEX idx_shops_slug (slug),
    INDEX idx_shops_owner (owner_id)
);
```

**Notas:**
- `slug` é o identificador público da barbearia (usado na URL pública: `app.litebarber.com.br/s/minha-barbearia`)
- `working_days` usa JSON para flexibilidade (dias da semana: 0=Dom, 6=Sáb)
- `plan_expires_at` prepara a tabela para billing futuro sem migration quebrada

---

### `users` — Usuários (Donos e Funcionários)

```sql
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id         BIGINT UNSIGNED NULL,              -- NULL = usuário sem barbearia ainda
    name            VARCHAR(150)    NOT NULL,
    email           VARCHAR(191)    NOT NULL UNIQUE,
    email_verified_at TIMESTAMP     NULL,
    password        VARCHAR(255)    NOT NULL,           -- bcrypt
    phone           VARCHAR(20)     NULL,
    role            ENUM('owner','barber','manager') NOT NULL DEFAULT 'owner',
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    -- Tokens Sanctum ficam na tabela personal_access_tokens (nativa do Sanctum)
    remember_token  VARCHAR(100)    NULL,
    -- Auditoria
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,

    INDEX idx_users_shop (shop_id),
    INDEX idx_users_email (email),
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL
);
```

**Notas:**
- `role` define o nível de acesso dentro da barbearia
- `shop_id` NULL é possível durante o cadastro (antes de criar a barbearia)
- A tabela `personal_access_tokens` é criada automaticamente pelo Sanctum

---

### `barbers` — Barbeiros

```sql
CREATE TABLE barbers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id         BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL,              -- NULL = barbeiro sem acesso ao sistema
    name            VARCHAR(150)    NOT NULL,
    photo_url       VARCHAR(500)    NULL,
    bio             TEXT            NULL,
    specialties     JSON            NULL,               -- ex: ["corte", "barba", "degradê"]
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    -- Auditoria
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,

    INDEX idx_barbers_shop (shop_id),
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

---

### `services` — Serviços Oferecidos

```sql
CREATE TABLE services (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id         BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(100)    NOT NULL,
    description     VARCHAR(255)    NULL,
    price           DECIMAL(8,2)    NOT NULL,           -- em reais
    duration_minutes TINYINT UNSIGNED NOT NULL DEFAULT 30,
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    display_order   TINYINT UNSIGNED NOT NULL DEFAULT 0, -- ordem de exibição
    -- Auditoria
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,

    INDEX idx_services_shop (shop_id),
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
);
```

---

### `appointments` — Agendamentos

```sql
CREATE TABLE appointments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id         BIGINT UNSIGNED NOT NULL,
    barber_id       BIGINT UNSIGNED NOT NULL,
    service_id      BIGINT UNSIGNED NOT NULL,
    -- Dados do cliente (sem necessidade de cadastro no MVP)
    client_name     VARCHAR(150)    NOT NULL,
    client_phone    VARCHAR(20)     NOT NULL,
    -- Horário
    scheduled_at    DATETIME        NOT NULL,            -- data + hora do agendamento
    ends_at         DATETIME        NOT NULL,            -- calculado: scheduled_at + service.duration
    -- Status do ciclo de vida
    status          ENUM(
                        'scheduled',   -- agendado, aguardando confirmação
                        'confirmed',   -- confirmado pelo barbeiro/admin
                        'in_progress', -- cliente chegou, atendimento iniciado
                        'completed',   -- serviço finalizado
                        'cancelled',   -- cancelado (pelo cliente ou admin)
                        'no_show'      -- cliente não compareceu
                    ) NOT NULL DEFAULT 'scheduled',
    -- Rastreabilidade
    cancelled_by    ENUM('client','admin') NULL,
    cancel_reason   VARCHAR(255)    NULL,
    notes           TEXT            NULL,                -- observações do cliente
    -- Valores snapshot (preservar histórico se o serviço mudar de preço)
    price_snapshot  DECIMAL(8,2)    NOT NULL,
    -- Auditoria
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,

    -- Índices críticos para performance
    INDEX idx_apt_shop_date (shop_id, scheduled_at),
    INDEX idx_apt_barber_date (barber_id, scheduled_at),
    INDEX idx_apt_status (shop_id, status),
    -- Constraint de não-conflito de horário (por barbeiro)
    UNIQUE KEY uk_barber_slot (barber_id, scheduled_at),

    FOREIGN KEY (shop_id)    REFERENCES shops(id)    ON DELETE CASCADE,
    FOREIGN KEY (barber_id)  REFERENCES barbers(id)  ON DELETE RESTRICT,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT
);
```

**Notas:**
- `price_snapshot` evita que relatórios históricos se corrompam se o preço do serviço mudar
- `UNIQUE KEY uk_barber_slot` previne double-booking no nível do banco (última linha de defesa)
- `ends_at` é calculado na camada de serviço antes da inserção

---

### `waitlist` — Fila Digital

```sql
CREATE TABLE waitlist (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id         BIGINT UNSIGNED NOT NULL,
    barber_id       BIGINT UNSIGNED NULL,               -- NULL = qualquer barbeiro disponível
    -- Dados do cliente
    client_name     VARCHAR(150)    NOT NULL,
    client_phone    VARCHAR(20)     NOT NULL,
    service_id      BIGINT UNSIGNED NULL,               -- serviço desejado (opcional)
    -- Posição e tempo
    position        SMALLINT UNSIGNED NOT NULL,          -- posição na fila (1 = próximo)
    estimated_wait  SMALLINT UNSIGNED NULL,              -- minutos estimados (calculado)
    -- Status
    status          ENUM(
                        'waiting',     -- na fila, aguardando
                        'called',      -- foi chamado (notificação enviada)
                        'in_service',  -- sendo atendido
                        'completed',   -- atendimento finalizado
                        'cancelled',   -- saiu da fila
                        'no_show'      -- foi chamado mas não apareceu
                    ) NOT NULL DEFAULT 'waiting',
    -- Rastreabilidade
    called_at       TIMESTAMP       NULL,               -- momento em que foi chamado
    started_at      TIMESTAMP       NULL,               -- início do atendimento
    completed_at    TIMESTAMP       NULL,               -- fim do atendimento
    notes           TEXT            NULL,
    -- Auditoria
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_wl_shop_status (shop_id, status),
    INDEX idx_wl_shop_position (shop_id, position),
    FOREIGN KEY (shop_id)    REFERENCES shops(id)    ON DELETE CASCADE,
    FOREIGN KEY (barber_id)  REFERENCES barbers(id)  ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);
```

**Notas:**
- `position` é gerenciado pela camada de serviço (reordenação ao cancelar/chamar)
- `estimated_wait` é calculado dinamicamente: `position × slot_duration` da shop
- Timestamps individuais (`called_at`, `started_at`, `completed_at`) permitem métricas de tempo de espera real

---

## Migrations Laravel (Ordem de Criação)

```
2026_04_01_000001_create_shops_table.php
2026_04_01_000002_create_users_table.php           ← após shops (FK shop_id)
2026_04_01_000003_create_barbers_table.php
2026_04_01_000004_create_services_table.php
2026_04_01_000005_create_appointments_table.php
2026_04_01_000006_create_waitlist_table.php
2026_04_01_000007_create_personal_access_tokens_table.php  ← Sanctum (via vendor:publish)
```

---

## Índices de Performance Críticos

| Tabela        | Índice                            | Por quê                                                      |
|---------------|-----------------------------------|--------------------------------------------------------------|
| appointments  | `(shop_id, scheduled_at)`        | Busca de agenda diária/semanal — hot path da aplicação       |
| appointments  | `(barber_id, scheduled_at)`      | Verificação de disponibilidade de barbeiro                   |
| waitlist      | `(shop_id, status)`              | Listagem da fila ativa — hot path                             |
| services      | `(shop_id)`                      | Listagem pública de serviços por barbearia                   |
| users         | `(email)`                        | Login por email                                              |
| shops         | `(slug)`                         | Busca da barbearia pela URL pública                          |

---

*Última atualização: Abril 2026 — Schema v2.0*
