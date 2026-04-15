# Arquitetura da Aplicação — Lite Barber SaaS

> Stack: Vue 3 + Vite (Frontend) | Laravel 11+ (Backend) | MySQL 8 (Database)

---

## 1. Frontend — Vue 3 SPA

### 1.1 Estrutura de Pastas

```
frontend/
├── public/
│   └── favicon.ico
├── src/
│   ├── assets/              # Imagens, fontes, ícones estáticos
│   ├── components/          # Componentes reutilizáveis
│   │   ├── common/
│   │   │   ├── AppButton.vue
│   │   │   ├── AppInput.vue
│   │   │   ├── AppModal.vue
│   │   │   ├── AppBadge.vue
│   │   │   └── LoadingSpinner.vue
│   │   ├── booking/
│   │   │   ├── ServiceCard.vue
│   │   │   ├── BarberCard.vue
│   │   │   ├── TimeSlotGrid.vue
│   │   │   ├── DatePicker.vue
│   │   │   └── BookingConfirmation.vue
│   │   ├── waitlist/
│   │   │   ├── WaitlistCard.vue
│   │   │   └── QueuePosition.vue
│   │   └── admin/
│   │       ├── AppointmentRow.vue
│   │       ├── FinancialSummary.vue
│   │       └── WaitlistManagerRow.vue
│   ├── composables/         # Lógica reutilizável (Composition API)
│   │   ├── useAuth.js       # Login, logout, token management
│   │   ├── useShop.js       # Dados da barbearia atual
│   │   ├── useAvailability.js # Horários disponíveis
│   │   └── useWaitlist.js   # Estado da fila
│   ├── router/
│   │   └── index.js         # Definição de rotas + navigation guards
│   ├── stores/              # Pinia stores (estado global)
│   │   ├── auth.js          # Usuário autenticado, token
│   │   ├── shop.js          # Dados do tenant atual
│   │   └── booking.js       # Fluxo de agendamento em andamento
│   ├── services/            # Camada de comunicação com a API
│   │   ├── api.js           # Axios instance configurada (baseURL, interceptors)
│   │   ├── authService.js
│   │   ├── shopService.js
│   │   ├── bookingService.js
│   │   └── waitlistService.js
│   ├── views/               # Páginas (roteadas)
│   │   ├── public/
│   │   │   ├── ShopLanding.vue  # Página pública da barbearia
│   │   │   ├── BookingFlow.vue  # Fluxo de agendamento (multi-step)
│   │   │   └── WaitlistJoin.vue # Entrada na fila digital
│   │   └── admin/
│   │       ├── Login.vue
│   │       ├── Dashboard.vue
│   │       ├── AgendaView.vue
│   │       ├── WaitlistView.vue
│   │       ├── ServicesView.vue
│   │       ├── BarbersView.vue
│   │       └── SettingsView.vue
│   ├── App.vue
│   └── main.js
├── .env
├── .env.example
├── vite.config.js
└── package.json
```

### 1.2 Gerenciamento de Estado (Pinia)

**`stores/auth.js`**
```javascript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/services/authService'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token') || null)
  const user = ref(null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(credentials) {
    const { data } = await authService.login(credentials)
    token.value = data.token
    user.value = data.user
    localStorage.setItem('auth_token', data.token)
  }

  async function logout() {
    await authService.logout()
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
  }

  return { token, user, isAuthenticated, login, logout }
})
```

**`stores/booking.js`** — Estado do fluxo de agendamento (multi-step)
```javascript
export const useBookingStore = defineStore('booking', () => {
  const selectedService = ref(null)
  const selectedBarber = ref(null)
  const selectedDate = ref(null)
  const selectedSlot = ref(null)
  const clientInfo = ref({ name: '', phone: '' })

  function reset() {
    selectedService.value = null
    selectedBarber.value = null
    selectedDate.value = null
    selectedSlot.value = null
    clientInfo.value = { name: '', phone: '' }
  }

  return { selectedService, selectedBarber, selectedDate, selectedSlot, clientInfo, reset }
})
```

### 1.3 Comunicação com a API (Axios)

**`services/api.js`**
```javascript
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
})

// Injeta o token automaticamente em todo request autenticado
api.interceptors.request.use(config => {
  const authStore = useAuthStore()
  if (authStore.token) {
    config.headers.Authorization = `Bearer ${authStore.token}`
  }
  return config
})

// Trata erro 401 globalmente (token expirado)
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      const authStore = useAuthStore()
      authStore.logout()
      router.push('/admin/login')
    }
    return Promise.reject(error)
  }
)

export default api
```

### 1.4 Roteamento (Vue Router)

```javascript
// router/index.js
const routes = [
  // Rotas públicas
  { path: '/s/:slug',          name: 'ShopLanding',  component: ShopLanding },
  { path: '/s/:slug/agendar',  name: 'BookingFlow',  component: BookingFlow },
  { path: '/s/:slug/fila',     name: 'WaitlistJoin', component: WaitlistJoin },

  // Rotas admin (requerem autenticação)
  { path: '/admin/login',      name: 'Login',        component: Login },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '',        name: 'Dashboard',    component: Dashboard },
      { path: 'agenda',  name: 'Agenda',       component: AgendaView },
      { path: 'fila',    name: 'Waitlist',     component: WaitlistView },
      { path: 'servicos',name: 'Services',     component: ServicesView },
      { path: 'barbeiros',name: 'Barbers',     component: BarbersView },
      { path: 'config',  name: 'Settings',     component: SettingsView },
    ]
  }
]

// Navigation guard
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'Login' })
  } else {
    next()
  }
})
```

---

## 2. Backend — Laravel REST API

### 2.1 Estrutura de Pastas

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── AuthController.php
│   │   │   ├── Admin/
│   │   │   │   ├── AppointmentController.php
│   │   │   │   ├── BarberController.php
│   │   │   │   ├── ServiceController.php
│   │   │   │   ├── ShopController.php
│   │   │   │   └── WaitlistController.php
│   │   │   └── Public/
│   │   │       ├── ShopPublicController.php
│   │   │       ├── BookingController.php
│   │   │       └── WaitlistPublicController.php
│   │   ├── Middleware/
│   │   │   ├── SetTenantContext.php   # Injeta shop_id do usuário autenticado
│   │   │   └── EnsureShopIsActive.php # Bloqueia tenant suspenso
│   │   └── Requests/
│   │       ├── Auth/
│   │       │   └── LoginRequest.php
│   │       ├── Admin/
│   │       │   ├── StoreServiceRequest.php
│   │       │   ├── UpdateServiceRequest.php
│   │       │   ├── StoreBarberRequest.php
│   │       │   └── UpdateAppointmentStatusRequest.php
│   │       └── Public/
│   │           ├── StoreAppointmentRequest.php
│   │           └── JoinWaitlistRequest.php
│   ├── Models/
│   │   ├── Shop.php
│   │   ├── User.php
│   │   ├── Barber.php
│   │   ├── Service.php
│   │   ├── Appointment.php
│   │   └── Waitlist.php
│   └── Services/
│       ├── AppointmentService.php   # Regras de agendamento e conflito
│       ├── AvailabilityService.php  # Cálculo de slots disponíveis
│       ├── WaitlistService.php      # Gerenciamento de fila e posições
│       └── ReportService.php        # Relatórios financeiros
├── routes/
│   └── api.php
├── database/
│   ├── migrations/
│   └── seeders/
├── config/
│   └── sanctum.php
└── .env
```

### 2.2 Rotas da API (`routes/api.php`)

```php
<?php
// Rotas PÚBLICAS (sem autenticação)
Route::prefix('shops/{slug}')->group(function () {
    Route::get('/',             [ShopPublicController::class, 'show']);
    Route::get('/services',     [ShopPublicController::class, 'services']);
    Route::get('/barbers',      [ShopPublicController::class, 'barbers']);
    Route::get('/availability', [ShopPublicController::class, 'availability']); // ?date=2026-04-15&barber_id=1
    Route::post('/appointments',[BookingController::class, 'store']);     // throttle:30,1
    Route::post('/waitlist',    [WaitlistPublicController::class, 'join']); // throttle:10,1
});

// Autenticação
Route::prefix('auth')->group(function () {
    Route::post('/login',  [AuthController::class, 'login']);   // throttle:10,1
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me',      [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Rotas ADMIN (requerem autenticação + contexto do tenant)
Route::middleware(['auth:sanctum', 'tenant'])->prefix('admin')->group(function () {

    // Agenda
    Route::get('/appointments',               [AppointmentController::class, 'index']);   // ?date=
    Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
    Route::delete('/appointments/{id}',       [AppointmentController::class, 'destroy']);

    // Fila
    Route::get('/waitlist',                   [WaitlistController::class, 'index']);
    Route::patch('/waitlist/{id}/call',       [WaitlistController::class, 'call']);
    Route::patch('/waitlist/{id}/start',      [WaitlistController::class, 'startService']);
    Route::patch('/waitlist/{id}/complete',   [WaitlistController::class, 'complete']);
    Route::patch('/waitlist/{id}/no-show',    [WaitlistController::class, 'noShow']);

    // Serviços
    Route::apiResource('services', ServiceController::class);

    // Barbeiros
    Route::apiResource('barbers', BarberController::class);

    // Configurações da Barbearia
    Route::get('/shop',    [ShopController::class, 'show']);
    Route::patch('/shop',  [ShopController::class, 'update']);

    // Relatórios
    Route::get('/reports/daily', [ReportController::class, 'daily']); // ?date=
});
```

### 2.3 Service Layer (Lógica de Negócio)

**`AppointmentService.php`**
```php
class AppointmentService
{
    public function __construct(
        private AvailabilityService $availability
    ) {}

    public function book(Shop $shop, array $data): Appointment
    {
        // 1. Valida se o slot ainda está disponível
        $available = $this->availability->isSlotAvailable(
            $shop,
            $data['barber_id'],
            $data['scheduled_at']
        );

        if (!$available) {
            throw new SlotUnavailableException('Horário já ocupado.');
        }

        // 2. Carrega o serviço para calcular end_at e snapshot de preço
        $service = Service::findOrFail($data['service_id']);

        // 3. Cria o agendamento com snapshot de preço
        return Appointment::create([
            'shop_id'        => $shop->id,
            'barber_id'      => $data['barber_id'],
            'service_id'     => $service->id,
            'client_name'    => $data['client_name'],
            'client_phone'   => $data['client_phone'],
            'scheduled_at'   => $data['scheduled_at'],
            'ends_at'        => Carbon::parse($data['scheduled_at'])->addMinutes($service->duration_minutes),
            'price_snapshot' => $service->price,
            'notes'          => $data['notes'] ?? null,
            'status'         => 'scheduled',
        ]);
    }
}
```

**`AvailabilityService.php`**
```php
class AvailabilityService
{
    public function getAvailableSlots(Shop $shop, int $barberId, Carbon $date): array
    {
        // 1. Verifica se a data é um dia de trabalho da shop
        if (!$this->isWorkingDay($shop, $date)) return [];

        // 2. Gera todos os slots do dia
        $slots = $this->generateSlots($shop, $date);

        // 3. Busca agendamentos existentes do barbeiro nesta data
        $booked = Appointment::where('barber_id', $barberId)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
            ->pluck('scheduled_at')
            ->map(fn($dt) => Carbon::parse($dt)->format('H:i'))
            ->toArray();

        // 4. Remove slots ocupados
        return array_filter($slots, fn($slot) => !in_array($slot, $booked));
    }

    private function generateSlots(Shop $shop, Carbon $date): array
    {
        $slots = [];
        $current = Carbon::parse($date->format('Y-m-d') . ' ' . $shop->opening_time);
        $closing = Carbon::parse($date->format('Y-m-d') . ' ' . $shop->closing_time);

        while ($current->lt($closing)) {
            $slots[] = $current->format('H:i');
            $current->addMinutes($shop->slot_duration);
        }

        return $slots;
    }
}
```

### 2.4 Middleware de Tenant

**`SetTenantContext.php`**
```php
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->shop_id) {
            return response()->json(['message' => 'Barbearia não configurada.'], 403);
        }

        // Disponibiliza o shop_id globalmente para o request
        app()->instance('shop_id', $user->shop_id);

        return $next($request);
    }
}
```

### 2.5 Padrão de Response da API

Todos os endpoints retornam respostas no formato padronizado:

```json
// Sucesso com dados
{
  "data": { ... },
  "message": "Agendamento criado com sucesso."
}

// Sucesso com lista
{
  "data": [ ... ],
  "meta": {
    "total": 25,
    "per_page": 15,
    "current_page": 1
  }
}

// Erro de validação (422)
{
  "message": "Os dados fornecidos são inválidos.",
  "errors": {
    "client_phone": ["O campo telefone é obrigatório."]
  }
}

// Erro de negócio (409)
{
  "message": "Horário já ocupado. Por favor escolha outro.",
  "error_code": "SLOT_UNAVAILABLE"
}
```

---

*Última atualização: Abril 2026*
