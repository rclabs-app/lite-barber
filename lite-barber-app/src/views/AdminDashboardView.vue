<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from '../services/api'
import { LuxurySwal, Toast } from '../utils/swal'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const activeTab = ref('dashboard')
const sidebarOpen = ref(false)
const appointments = ref([])
const appointmentsPagination = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
const waitlist = ref([])
const waitlistPagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const waitlistAlert = ref(null)
const newWaitlistEntry = ref({ client_name: '', client_phone: '', desired_date: '', desired_time: '', professional_id: '', service_id: '', notes: '' })
const services = ref([])
const newService = ref({ name: '', price: '', duration_minutes: 30, description: '' })
const editingServiceId = ref(null)
const professionals = ref([])
const newProfessional = ref({ name: '', phone: '', specialties: '' })
const editingProfessionalId = ref(null)
const shopConfig = ref({ opening_time: '08:00', closing_time: '19:00', working_days: [1,2,3,4,5], unavailable_dates: [] })
const shopName = ref('Meu Estabelecimento')
const userName = ref('Admin')
const currentCalendarMonth = ref(new Date())
const todayRevenue = ref(0)
const _d = new Date();
const today = _d.getFullYear() + '-' + String(_d.getMonth() + 1).padStart(2, '0') + '-' + String(_d.getDate()).padStart(2, '0');
const agendaFilterDate = ref(today)
const isAgendaLoading = ref(false)
const isEditingProfile = ref(false)
const profileForm = ref({ name: '', email: '', password: '' })
const showProfilePassword = ref(false)

const statusMap = { scheduled:'Agendado', confirmed:'Confirmado', completed:'Finalizado', cancelled:'Cancelado', no_show:'Não Compareceu', in_progress:'Em Atendimento', waiting:'Aguardando', 'no-show':'Não Compareceu' }
const statusColor = { scheduled:'#3b82f6', confirmed:'#10b981', completed:'#D4AF37', cancelled:'#ef4444', no_show:'#f97316', in_progress:'#8b5cf6' }

const navItems = [
  { key: 'dashboard', icon: 'fas fa-home', label: 'Dashboard' },
  { key: 'agenda', icon: 'fas fa-calendar-alt', label: 'Agenda' },
  { key: 'fila', icon: 'fas fa-hourglass-half', label: 'Lista de Espera', hidden: true },
  { key: 'services', icon: 'fas fa-cut', label: 'Serviços' },
  { key: 'especialistas', icon: 'fas fa-user-tie', label: 'Especialistas' },
  { key: 'relatorios', icon: 'fas fa-chart-bar', label: 'Relatórios', locked: true },
  { key: 'config', icon: 'fas fa-cog', label: 'Configurações' },
  { key: 'perfil', icon: 'fas fa-user', label: 'Meu Perfil', hidden: true },
]


const dashboardAgendaPage = ref(1)
const dashboardAgendaPerPage = 5

const todayAppointments = computed(() =>
  appointments.value.filter(a => a.scheduled_at && a.scheduled_at.startsWith(today))
    .sort((a,b) => a.scheduled_at.localeCompare(b.scheduled_at))
)
const dashboardPaginatedAppointments = computed(() => {
  const start = (dashboardAgendaPage.value - 1) * dashboardAgendaPerPage
  return todayAppointments.value.slice(start, start + dashboardAgendaPerPage)
})
const dashboardAgendaTotalPages = computed(() => {
  return Math.ceil(todayAppointments.value.length / dashboardAgendaPerPage) || 1
})
const todayCompleted = computed(() => todayAppointments.value.filter(a => a.status === 'completed'))
const todayNoShow = computed(() => todayAppointments.value.filter(a => a.status === 'no_show'))
const monthAppointments = computed(() => {
  const m = new Date().toISOString().slice(0,7)
  return appointments.value.filter(a => a.scheduled_at && a.scheduled_at.startsWith(m))
})

const ticketMedio = computed(() => {
  const comp = todayCompleted.value
  if (!comp.length) return 0
  const total = comp.reduce((s,a) => s + parseFloat(a.price_snapshot || 0), 0)
  return total / comp.length
})
const noShowRate = computed(() => {
  if (!todayAppointments.value.length) return 0
  return Math.round((todayNoShow.value.length / todayAppointments.value.length) * 100)
})
const professionalRanking = computed(() => {
  const map = {}
  appointments.value.forEach(a => {
    if (!a.professional) return
    const n = a.professional.name
    if (!map[n]) map[n] = { name: n, atendimentos: 0, faturamento: 0 }
    if (a.status === 'completed') {
      map[n].atendimentos++
      map[n].faturamento += parseFloat(a.price_snapshot || 0)
    }
  })
  return Object.values(map).sort((a,b) => b.atendimentos - a.atendimentos)
})

const calendarDays = computed(() => {
  const year = currentCalendarMonth.value.getFullYear()
  const month = currentCalendarMonth.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const days = []
  for (let i = 0; i < firstDay.getDay(); i++) days.push(null)
  for (let i = 1; i <= lastDay.getDate(); i++) days.push(new Date(year, month, i))
  return days
})

const nextMonth = () => { currentCalendarMonth.value = new Date(currentCalendarMonth.value.getFullYear(), currentCalendarMonth.value.getMonth() + 1, 1) }
const prevMonth = () => { currentCalendarMonth.value = new Date(currentCalendarMonth.value.getFullYear(), currentCalendarMonth.value.getMonth() - 1, 1) }
const formatDate = (date) => { if (!date) return ''; const y = date.getFullYear(), m = String(date.getMonth()+1).padStart(2,'0'), d = String(date.getDate()).padStart(2,'0'); return `${y}-${m}-${d}` }
const toggleUnavailableDate = (date) => { const s = formatDate(date); const i = shopConfig.value.unavailable_dates.indexOf(s); i === -1 ? shopConfig.value.unavailable_dates.push(s) : shopConfig.value.unavailable_dates.splice(i, 1) }
const isDateUnavailable = (date) => { if (!date) return false; return shopConfig.value.unavailable_dates.includes(formatDate(date)) }

const groupedAppointments = computed(() => {
  const groups = {}
  appointments.value.forEach(app => {
    if (!app.scheduled_at) return
    const date = app.scheduled_at.split(/[\sT]/)[0]
    if (!groups[date]) groups[date] = []
    groups[date].push(app)
  })
  // ordena cada grupo por horário
  Object.keys(groups).forEach(d => {
    groups[d].sort((a, b) => a.scheduled_at.localeCompare(b.scheduled_at))
  })
  return Object.keys(groups).sort().reduce((obj, key) => { obj[key] = groups[key]; return obj }, {})
})

const formatEndTime = (scheduledAt, durationMinutes) => {
  if (!scheduledAt || !durationMinutes) return null
  const start = new Date(scheduledAt)
  const end = new Date(start.getTime() + durationMinutes * 60000)
  return end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

const whatsappLink = (phone) => {
  if (!phone) return null
  return `https://wa.me/55${phone.replace(/\D/g, '')}`
}

const formatDateLong = (dateStr) => {
  if (!dateStr) return 'DATA INVÁLIDA'
  const parts = dateStr.split('-')
  if (parts.length < 3) return 'DATA INVÁLIDA'
  const date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]))
  if (isNaN(date.getTime())) return 'DATA INVÁLIDA'
  return date.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).toUpperCase()
}

const calculateRevenue = () => {
  todayRevenue.value = appointments.value
    .filter(a => a.status === 'completed' && a.scheduled_at && a.scheduled_at.startsWith(today))
    .reduce((sum, a) => sum + parseFloat(a.price_snapshot || 0), 0)
}

const fetchAgenda = async (page = 1) => {
  isAgendaLoading.value = true
  try {
    const params = { page, per_page: appointmentsPagination.value.per_page, date: agendaFilterDate.value }

    const { data } = await axios.get('/api/admin/appointments', { params })
    if (data.data) { appointments.value = data.data; appointmentsPagination.value = { current_page: data.current_page, last_page: data.last_page, per_page: data.per_page, total: data.total } }
    else appointments.value = data
  } catch(err) { console.error(err) } finally { calculateRevenue(); isAgendaLoading.value = false }
}

const fetchWaitlist = async (page = 1) => {
  try {
    const { data } = await axios.get('/api/admin/waitlist', { params: { page, per_page: waitlistPagination.value.per_page } })
    if (data.data) { waitlist.value = data.data; waitlistPagination.value = { current_page: data.current_page, last_page: data.last_page, per_page: data.per_page, total: data.total } }
    else waitlist.value = data
  } catch(err) { console.error(err) }
}

const addToWaitlist = async () => {
  try { await axios.post('/api/admin/waitlist', newWaitlistEntry.value); newWaitlistEntry.value = { client_name: '', client_phone: '', desired_date: '', desired_time: '', professional_id: '', service_id: '', notes: '' }; fetchWaitlist(); Toast.fire({ icon: 'success', title: 'Adicionado à lista de espera' }) }
  catch(err) { Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Erro ao adicionar' }) }
}

const notifyWaitlistClient = async (id) => {
  try { await axios.patch(`/api/admin/waitlist/${id}/notify`); fetchWaitlist(); Toast.fire({ icon: 'success', title: 'Marcado como notificado' }) }
  catch(err) { Toast.fire({ icon: 'error', title: 'Erro ao notificar' }) }
}

const removeFromWaitlist = async (id) => {
  const r = await LuxurySwal.fire({ title: 'Remover da lista?', text: 'O cliente será removido.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sim, remover', cancelButtonText: 'Cancelar' })
  if (!r.isConfirmed) return
  try { await axios.delete(`/api/admin/waitlist/${id}`); fetchWaitlist(); Toast.fire({ icon: 'success', title: 'Removido' }) }
  catch(err) { Toast.fire({ icon: 'error', title: 'Erro ao remover' }) }
}

const updateStatus = async (id, status) => {
  if (status === 'cancelled') {
    const r = await LuxurySwal.fire({ title: 'Cancelar agendamento?', text: 'Esta ação não pode ser desfeita.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sim, cancelar', cancelButtonText: 'Manter' })
    if (!r.isConfirmed) return
  }
  try {
    const { data } = await axios.patch(`/api/admin/appointments/${id}/status`, { status })
    fetchAgenda(appointmentsPagination.value.current_page)
    Toast.fire({ icon: 'success', title: `Status: ${statusMap[status]}` })
    if (status === 'cancelled' && data.waiting_clients?.length > 0) {
      waitlistAlert.value = data.waiting_clients
      LuxurySwal.fire({ title: `⚠️ ${data.waiting_clients.length} cliente(s) aguardam este horário!`, html: data.waiting_clients.map(c => `<strong>${c.client_name}</strong> — <a href="https://wa.me/55${c.client_phone.replace(/\D/g,'')}" target="_blank" style="color:#25D366">${c.client_phone}</a>`).join('<br>'), icon: 'info', confirmButtonText: 'Ver Lista de Espera', showCancelButton: true, cancelButtonText: 'Fechar' }).then(r => { if (r.isConfirmed) { activeTab.value = 'fila'; sidebarOpen.value = false } })
    }
  } catch(err) { Toast.fire({ icon: 'error', title: 'Erro ao atualizar status' }) }
}

const fetchProfessionals = async () => { try { const { data } = await axios.get('/api/admin/professionals'); professionals.value = data } catch(err) { console.error(err) } }
const addProfessional = async () => {
  try {
    const payload = { ...newProfessional.value, specialties: newProfessional.value.specialties ? newProfessional.value.specialties.split(',').map(s => s.trim()) : [] }
    editingProfessionalId.value ? await axios.put(`/api/admin/professionals/${editingProfessionalId.value}`, payload) : await axios.post('/api/admin/professionals', payload)
    newProfessional.value = { name: '', phone: '', specialties: '' }; editingProfessionalId.value = null; fetchProfessionals()
    Toast.fire({ icon: 'success', title: 'Especialista salvo' })
  } catch(err) { Toast.fire({ icon: 'error', title: 'Erro ao salvar especialista' }) }
}
const editProfessional = (b) => { editingProfessionalId.value = b.id; newProfessional.value = { name: b.name, phone: b.phone || '', specialties: b.specialties ? b.specialties.join(', ') : '' } }
const cancelEditProfessional = () => { editingProfessionalId.value = null; newProfessional.value = { name: '', phone: '', specialties: '' } }
const deleteProfessional = async (id) => {
  const r = await LuxurySwal.fire({ title: 'Remover especialista?', text: 'Esta ação não pode ser revertida!', icon: 'warning', showCancelButton: true })
  if (!r.isConfirmed) return
  try { await axios.delete(`/api/admin/professionals/${id}`); fetchProfessionals(); Toast.fire({ icon: 'success', title: 'Especialista removido' }) }
  catch(err) { Toast.fire({ icon: 'error', title: 'Erro ao excluir' }) }
}

const setThemeMode = (mode) => {
    shopConfig.value.theme_mode = mode;
    if (mode === 'dark') {
        shopConfig.value.theme_color = '#D4AF37';
    } else if (mode === 'light-nude') {
        shopConfig.value.theme_color = '#dca392';
    } else if (mode === 'light-wine') {
        shopConfig.value.theme_color = '#800020';
    }
    document.documentElement.style.setProperty('--accent', shopConfig.value.theme_color);
    document.documentElement.style.setProperty('--gold', shopConfig.value.theme_color);
    applyTheme(mode);
}

const applyTheme = (theme) => {
    if (theme && theme.includes('light')) {
        document.body.classList.add('light-theme');
        document.documentElement.classList.add('light-theme');
        if (theme === 'light-wine') {
            document.body.classList.add('theme-wine');
            document.documentElement.classList.add('theme-wine');
            document.body.classList.remove('theme-nude');
            document.documentElement.classList.remove('theme-nude');
        } else {
            document.body.classList.add('theme-nude');
            document.documentElement.classList.add('theme-nude');
            document.body.classList.remove('theme-wine');
            document.documentElement.classList.remove('theme-wine');
        }
    } else {
        document.body.classList.remove('light-theme', 'theme-nude', 'theme-wine');
        document.documentElement.classList.remove('light-theme', 'theme-nude', 'theme-wine');
    }
}

const fetchShopConfig = async () => {
  try {
    const { data } = await axios.get('/api/admin/shop')
    shopConfig.value = { ...data, working_days: Array.isArray(data.working_days) ? data.working_days : JSON.parse(data.working_days || '[]'), unavailable_dates: Array.isArray(data.unavailable_dates) ? data.unavailable_dates : JSON.parse(data.unavailable_dates || '[]'), theme_color: data.theme_color || '#D4AF37', theme_mode: data.theme_mode || 'dark' }
    document.documentElement.style.setProperty('--gold', shopConfig.value.theme_color);
    document.documentElement.style.setProperty('--accent', shopConfig.value.theme_color);
    applyTheme(shopConfig.value.theme_mode);
    if (data.name) shopName.value = data.name
  } catch(err) { console.error(err) }
}
const updateShopConfig = async () => {
  try { 
      await axios.put('/api/admin/shop', shopConfig.value); 
      document.documentElement.style.setProperty('--gold', shopConfig.value.theme_color);
      document.documentElement.style.setProperty('--accent', shopConfig.value.theme_color);
      applyTheme(shopConfig.value.theme_mode);
      Toast.fire({ icon: 'success', title: 'Configurações atualizadas!' }) 
  }
  catch(err) { Toast.fire({ icon: 'error', title: 'Erro ao atualizar' }) }
}

const uploadLogo = async (event) => {
  const file = event.target.files[0];
  if (!file) return;
  const formData = new FormData();
  formData.append('logo', file);
  // Need to append shop_id since it might be required by AdminShopController, or backend determines it via tenant. Wait, AdminShopController uses $request->get('shop_id') which is likely injected by tenant middleware, but wait, the update endpoint uses put('/api/admin/shop', shopConfig.value), and tenant middleware handles shop_id. But just in case, AdminShopController show() uses $request->get('shop_id'). 
  // Let's just post. The tenant middleware adds shop_id to request.
  try {
    const { data } = await axios.post('/api/admin/shop/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    shopConfig.value.logo_url = data.logo_url;
    Toast.fire({ icon: 'success', title: 'Logo atualizada!' });
  } catch (err) {
    Toast.fire({ icon: 'error', title: 'Erro ao enviar logo' });
  }
}

const fetchServices = async () => { try { const { data } = await axios.get('/api/admin/services'); services.value = data } catch(err) { console.error(err) } }
const addService = async () => {
  try {
    editingServiceId.value ? await axios.put(`/api/admin/services/${editingServiceId.value}`, newService.value) : await axios.post('/api/admin/services', newService.value)
    newService.value = { name: '', price: '', duration_minutes: 30, description: '' }; editingServiceId.value = null; fetchServices()
    Toast.fire({ icon: 'success', title: 'Serviço salvo' })
  } catch(err) { LuxurySwal.fire({ icon: 'error', title: 'Erro ao salvar serviço', text: err.response?.data?.message || err.message }) }
}
const editService = (s) => { editingServiceId.value = s.id; newService.value = { name: s.name, price: s.price, duration_minutes: s.duration_minutes, description: s.description || '' }; window.scrollTo({ top: 0, behavior: 'smooth' }) }
const cancelEdit = () => { editingServiceId.value = null; newService.value = { name: '', price: '', duration_minutes: 30, description: '' } }
const deleteService = async (id) => {
  const r = await LuxurySwal.fire({ title: 'Excluir serviço?', text: 'Deseja remover este serviço?', icon: 'warning', showCancelButton: true })
  if (!r.isConfirmed) return
  try { await axios.delete(`/api/admin/services/${id}`); fetchServices(); Toast.fire({ icon: 'success', title: 'Serviço excluído' }) }
  catch(err) { Toast.fire({ icon: 'error', title: 'Erro ao excluir' }) }
}
const editProfile = () => {
  profileForm.value.name = userName.value;
  navigate('perfil');
}

const updateProfileSubmit = async () => {
  if (!profileForm.value.name) {
    Toast.fire({ icon: 'warning', title: 'O nome é obrigatório' })
    return
  }
  
  try {
    const payload = { name: profileForm.value.name }
    if (profileForm.value.email) payload.email = profileForm.value.email
    if (profileForm.value.password) payload.password = profileForm.value.password
    
    await axios.put('/api/auth/profile', payload)
    
    userName.value = profileForm.value.name
    localStorage.setItem('user_name', profileForm.value.name)
    
    Toast.fire({ icon: 'success', title: 'Perfil atualizado!' })
    profileForm.value.password = '' // clear password field
    isEditingProfile.value = false; // back to view mode
  } catch(err) {
    Toast.fire({ icon: 'error', title: 'Erro ao atualizar perfil' })
  }
}
const fetchUserProfile = async () => {
  try {
    const { data } = await axios.get('/api/auth/me')
    profileForm.value.name = data.name
    profileForm.value.email = data.email
    userName.value = data.name
    localStorage.setItem('user_name', data.name)
  } catch(err) {
    console.error('Erro ao buscar perfil')
  }
}

const logout = async () => {
  await auth.logout()
  await router.replace('/')
}

const navigate = (key) => { if (key === 'relatorios') return; activeTab.value = key; sidebarOpen.value = false }

const greetingText = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Bom dia'
  if (h < 18) return 'Boa tarde'
  return 'Boa noite'
})

onMounted(() => {
  const u = localStorage.getItem('user_name')
  if (u) userName.value = u
  fetchUserProfile()
  fetchAgenda()
  fetchWaitlist()
  fetchServices()
  fetchProfessionals()
  fetchShopConfig()
})
</script>

<template>
<div class="admin-shell">
  <!-- Sidebar Overlay (mobile) -->
  <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

  <!-- Sidebar -->
  <aside :class="['admin-sidebar', { open: sidebarOpen }]">
    <div class="sidebar-logo">
      <img v-if="shopConfig.logo_url" :src="shopConfig.logo_url" alt="Logo do Estabelecimento" class="logo-image" />
      <div v-else class="logo-circle">LE</div>
      <div class="logo-info">
        <span class="logo-name">{{ shopName }}</span>
        <span class="logo-sub">DESDE 2024</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <button
        v-for="item in navItems.filter(i => !i.hidden)"
        :key="item.key"
        :class="['sidebar-nav-item', { active: activeTab === item.key, locked: item.locked }]"
        @click="navigate(item.key)"
      >
        <i :class="item.icon"></i>
        <span>{{ item.label }}</span>
        <i v-if="item.locked" class="fas fa-lock sidebar-lock-icon"></i>
      </button>
    </nav>

    <div class="sidebar-footer">
      <div class="plan-badge">Plano Bronze</div>
      <div class="sidebar-user" @click="editProfile" style="cursor: pointer;" title="Editar Perfil">
        <div class="user-avatar">{{ userName.charAt(0).toUpperCase() }}</div>
        <span class="user-name">{{ userName }}</span>
        <button @click.stop="logout" class="btn-logout" title="Sair"><i class="fas fa-sign-out-alt"></i></button>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="admin-main">
    <!-- Top Bar -->
    <header class="admin-topbar">
      <button class="hamburger" @click="sidebarOpen = !sidebarOpen"><i class="fas fa-bars"></i></button>
      <div class="topbar-title">
        <span class="topbar-section">{{ navItems.find(n => n.key === activeTab)?.label || 'Dashboard' }}</span>
      </div>
      <div class="topbar-right">
        <button @click="navigate('perfil')" class="btn-topbar-mobile" title="Meu Perfil"><i class="fas fa-user-circle"></i></button>
        <button @click="navigate('config')" class="btn-topbar-mobile" title="Configurações"><i class="fas fa-cog"></i></button>
        <button @click="logout" class="btn-topbar-mobile" title="Sair"><i class="fas fa-sign-out-alt"></i></button>
      </div>
    </header>

    <!-- Content Area -->
    <main class="admin-content">

      <!-- ===== DASHBOARD ===== -->
      <section v-if="activeTab === 'dashboard'">
        <div class="dash-greeting">{{ greetingText }}, {{ userName }} 👋</div>

        <div class="kpi-grid">
          <div class="kpi-card kpi-blue">
            <div class="kpi-label">AGENDAMENTOS HOJE</div>
            <div class="kpi-value">{{ todayAppointments.length }}</div>
          </div>
          <div class="kpi-card kpi-green">
            <div class="kpi-label">RECEITA HOJE</div>
            <div class="kpi-value">R$ {{ todayRevenue.toFixed(2) }}</div>
          </div>
          <div class="kpi-card kpi-teal">
            <div class="kpi-label">TICKET MÉDIO</div>
            <div class="kpi-value">R$ {{ ticketMedio.toFixed(2) }}</div>
          </div>
          <div class="kpi-card kpi-purple" style="cursor:pointer" @click="navigate('fila')">
            <div class="kpi-label">LISTA DE ESPERA</div>
            <div class="kpi-value">{{ waitlist.length }}</div>
          </div>
          <div class="kpi-card kpi-orange">
            <div class="kpi-label">NO-SHOW HOJE</div>
            <div class="kpi-value">{{ todayNoShow.length }} <span class="kpi-pct">({{ noShowRate }}%)</span></div>
          </div>
          <div class="kpi-card kpi-gold">
            <div class="kpi-label">AGENDADOS (MÊS)</div>
            <div class="kpi-value">{{ monthAppointments.length }}</div>
          </div>
        </div>

        <div class="dash-panels">
          <!-- Agenda de Hoje -->
          <div class="dash-panel">
            <div class="panel-header">
              <h3 class="panel-title">Agenda de Hoje</h3>
              <button class="btn-ver-todos" @click="navigate('agenda')">Ver todos</button>
            </div>
            <div v-if="todayAppointments.length === 0" class="empty-state">
              <i class="fas fa-calendar-times"></i>
              <p>Nenhum agendamento hoje.</p>
            </div>
            <div v-for="app in dashboardPaginatedAppointments" :key="app.id" class="today-appt-row">
              <span class="appt-time">{{ new Date(app.scheduled_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) }}</span>
              <div class="appt-info">
                <span class="appt-client">{{ app.client_name }}</span>
                <span class="appt-detail">{{ app.professional?.name }} · {{ app.service?.name }}</span>
              </div>
              <span class="appt-status-pill" :style="{ color: statusColor[app.status] || '#888', background: (statusColor[app.status] || '#555') + '15', borderColor: (statusColor[app.status] || '#555') + '40' }">
                <span class="status-dot" :style="{ background: statusColor[app.status] || '#888' }"></span>
                {{ statusMap[app.status] || app.status }}
              </span>
            </div>

            <div v-if="dashboardAgendaTotalPages > 1" class="dashboard-pagination">
              <button :disabled="dashboardAgendaPage === 1" @click="dashboardAgendaPage--" class="btn-dash-page">« Anterior</button>
              <span class="dash-page-info">{{ dashboardAgendaPage }} de {{ dashboardAgendaTotalPages }}</span>
              <button :disabled="dashboardAgendaPage === dashboardAgendaTotalPages" @click="dashboardAgendaPage++" class="btn-dash-page">Próxima »</button>
            </div>
          </div>

          <!-- Ranking Especialistas -->
          <div class="dash-panel">
            <div class="panel-header">
              <h3 class="panel-title">Ranking Especialistas</h3>
            </div>
            <div v-if="professionalRanking.length === 0" class="empty-state">
              <i class="fas fa-users"></i>
              <p>Sem dados de atendimento.</p>
            </div>
            <table v-else class="rank-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Especialista</th>
                  <th>Atend.</th>
                  <th>Faturamento</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(b, idx) in professionalRanking" :key="b.name">
                  <td class="rank-pos">{{ idx + 1 }}</td>
                  <td>{{ b.name }}</td>
                  <td class="rank-num">{{ b.atendimentos }}</td>
                  <td class="rank-rev">R$ {{ b.faturamento.toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ===== AGENDA ===== -->
      <section v-if="activeTab === 'agenda'">
        <div class="section-toolbar">
          <div>
            <h2 class="section-title">Compromissos Agendados</h2>
            <p class="section-subtitle">
              <template v-if="agendaFilterDate === today">Agendamentos de Hoje</template>
              <template v-else>Agendamentos de {{ new Date(agendaFilterDate + 'T00:00:00').toLocaleDateString('pt-BR') }}</template>
              · {{ appointmentsPagination.total }} no total
            </p>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">

            <!-- Filtro de Data -->
            <div class="date-filter-btn">
              <i class="fas fa-calendar-alt" style="color: var(--accent);"></i>
              <input type="date" v-model="agendaFilterDate" @change="fetchAgenda(1)" class="interval-date-input" style="margin-left: 8px;">
            </div>

            <!-- Por página -->
            <select v-model="appointmentsPagination.per_page" @change="fetchAgenda(1)" class="per-page-select">
              <option value="10">10</option><option value="20">20</option><option value="30">30</option><option value="50">50</option><option value="100">100</option>
            </select>
          </div>
        </div>

        <!-- Skeleton Loader -->
        <div v-if="isAgendaLoading" class="agenda-skeleton">
          <div class="skeleton-group-date"></div>
          <div v-for="i in 3" :key="i" class="skeleton-card">
            <div class="skeleton-time"></div>
            <div class="skeleton-info">
              <div class="skeleton-line skeleton-name"></div>
              <div class="skeleton-line skeleton-detail"></div>
            </div>
            <div class="skeleton-actions">
              <div class="skeleton-badge"></div>
              <div class="skeleton-btn"></div>
              <div class="skeleton-btn"></div>
            </div>
          </div>
        </div>

        <div v-else-if="appointments.length === 0" class="empty-state">
          <i class="fas fa-calendar-check" style="color: var(--accent); opacity: 0.4;"></i>
          <p>Nenhum agendamento a partir de hoje.</p>
          <button @click="selectedAgendaDate='';fetchAgenda(1)" class="btn-clear-filter" style="margin-top:12px;">Ver todos os registros</button>
        </div>

        <template v-else>
          <div v-for="(group, date) in groupedAppointments" :key="date" class="agenda-group">
            <div class="agenda-group-date">
              <span>{{ formatDateLong(date) }}</span>
              <span class="group-count">{{ group.length }} {{ group.length === 1 ? 'agendamento' : 'agendamentos' }}</span>
            </div>
            <div v-for="app in group" :key="app.id" :class="['agenda-appt-card', { 'appt-inactive': ['completed', 'cancelled', 'no_show'].includes(app.status) }]">
              <!-- Coluna 1: Cliente & Serviço -->
              <div class="agenda-appt-main">
                <div class="appt-time-block">
                  <span class="appt-time">{{ new Date(app.scheduled_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) }}</span>
                  <span class="appt-time-end" v-if="formatEndTime(app.scheduled_at, app.service?.duration_minutes)">→ {{ formatEndTime(app.scheduled_at, app.service?.duration_minutes) }}</span>
                </div>
                <div class="appt-info">
                  <div style="display:flex; align-items:center; gap:8px;">
                    <span class="appt-client">{{ app.client_name }}</span>
                    <a v-if="app.client_phone" :href="whatsappLink(app.client_phone)" target="_blank" class="client-wz-icon" title="Contatar via WhatsApp">
                      <i class="fab fa-square-whatsapp"></i>
                    </a>
                  </div>
                  <span class="appt-detail">{{ app.professional?.name }} · {{ app.service?.name }}</span>
                </div>
              </div>

              <!-- Coluna 2: Preço -->
              <div class="appt-col-price">
                <span class="appt-price" v-if="app.price_snapshot">R$ {{ parseFloat(app.price_snapshot).toFixed(2) }}</span>
              </div>

              <!-- Coluna 3: Status -->
              <div class="appt-col-status">
                <span class="appt-status-pill" :style="{ color: statusColor[app.status] || '#888', background: (statusColor[app.status] || '#555') + '15', borderColor: (statusColor[app.status] || '#555') + '40' }">
                  <span class="status-dot" :style="{ background: statusColor[app.status] || '#888' }"></span>
                  {{ statusMap[app.status] || app.status }}
                </span>
              </div>

              <!-- Coluna 4: Botão Confirmar -->
              <div class="appt-col-btn">
                <button 
                  :disabled="app.status !== 'scheduled'" 
                  @click="updateStatus(app.id,'confirmed')" 
                  class="btn-action btn-confirm"
                >
                  Confirmar
                </button>
              </div>

              <!-- Coluna 5: Botão Atendido -->
              <div class="appt-col-btn">
                <button 
                  :disabled="!['scheduled','confirmed'].includes(app.status)" 
                  @click="updateStatus(app.id,'completed')" 
                  class="btn-action btn-complete"
                >
                  Atendido
                </button>
              </div>

              <!-- Coluna 6: Botão Cancelar -->
              <div class="appt-col-btn">
                <button 
                  :disabled="['completed','cancelled','no_show'].includes(app.status)" 
                  @click="updateStatus(app.id,'cancelled')" 
                  class="btn-action btn-cancel"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>

          <div v-if="appointmentsPagination.last_page > 1" class="pagination">
            <button :disabled="appointmentsPagination.current_page === 1" @click="fetchAgenda(appointmentsPagination.current_page-1)" class="btn-page">« Anterior</button>
            <span class="page-info">Página {{ appointmentsPagination.current_page }} de {{ appointmentsPagination.last_page }}</span>
            <button :disabled="appointmentsPagination.current_page === appointmentsPagination.last_page" @click="fetchAgenda(appointmentsPagination.current_page+1)" class="btn-page">Próxima »</button>
          </div>
        </template>
      </section>

      <!-- ===== LISTA DE ESPERA ===== -->
      <section v-if="activeTab === 'fila'">
        <div class="section-toolbar">
          <h2 class="section-title">Lista de Espera por Horário</h2>
          <p class="section-subtitle">Clientes que querem ser avisados se um horário específico abrir.</p>
        </div>

        <form @submit.prevent="addToWaitlist" class="form-card">
          <div class="form-grid-2">
            <input v-model="newWaitlistEntry.client_name" type="text" placeholder="Nome do cliente *" required class="form-input">
            <input v-model="newWaitlistEntry.client_phone" type="text" placeholder="WhatsApp (ex: 11999998888) *" required class="form-input">
            <input v-model="newWaitlistEntry.desired_date" type="date" required class="form-input">
            <input v-model="newWaitlistEntry.desired_time" type="time" required class="form-input">
            <select v-model="newWaitlistEntry.professional_id" class="form-input">
              <option value="">Qualquer especialista</option>
              <option v-for="b in professionals" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <select v-model="newWaitlistEntry.service_id" class="form-input">
              <option value="">Qualquer serviço</option>
              <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div style="display:flex;gap:12px;margin-top:12px;">
            <input v-model="newWaitlistEntry.notes" type="text" placeholder="Observações (opcional)" class="form-input" style="flex:1">
            <button type="submit" class="btn-primary">+ ADICIONAR</button>
          </div>
        </form>

        <div v-if="waitlist.length === 0" class="empty-state"><i class="fas fa-clock"></i><p>Nenhum cliente na lista de espera.</p></div>

        <div v-for="entry in waitlist" :key="entry.id" class="waitlist-card">
          <div class="waitlist-info">
            <h4 class="waitlist-name"><i class="fas fa-user"></i> {{ entry.client_name }}</h4>
            <p class="waitlist-slot">
              <i class="fas fa-calendar-day"></i>
              {{ new Date(entry.desired_date + 'T00:00:00').toLocaleDateString('pt-BR') }} às {{ entry.desired_time?.slice(0,5) }}
              <span v-if="entry.professional"> · {{ entry.professional.name }}</span>
              <span v-if="entry.service"> · {{ entry.service.name }}</span>
            </p>
            <p class="waitlist-contact">
              <a :href="`https://wa.me/55${entry.client_phone.replace(/\D/g,'')}`" target="_blank" class="whatsapp-link">
                <i class="fab fa-whatsapp"></i> {{ entry.client_phone }}
              </a>
              &nbsp;—&nbsp;
              <span :class="entry.status==='notified' ? 'status-notified' : 'status-waiting'">
                {{ entry.status === 'notified' ? '✅ Notificado' : '⏳ Aguardando' }}
              </span>
            </p>
            <p v-if="entry.notes" class="waitlist-notes">{{ entry.notes }}</p>
          </div>
          <div class="waitlist-actions">
            <button v-if="entry.status === 'waiting'" @click="notifyWaitlistClient(entry.id)" class="btn-action btn-whatsapp"><i class="fab fa-whatsapp"></i> Notificar</button>
            <button @click="removeFromWaitlist(entry.id)" class="btn-action btn-cancel">Remover</button>
          </div>
        </div>

        <div v-if="waitlistPagination.last_page > 1" class="pagination">
          <button :disabled="waitlistPagination.current_page === 1" @click="fetchWaitlist(waitlistPagination.current_page-1)" class="btn-page">« Anterior</button>
          <span class="page-info">Página {{ waitlistPagination.current_page }} de {{ waitlistPagination.last_page }}</span>
          <button :disabled="waitlistPagination.current_page === waitlistPagination.last_page" @click="fetchWaitlist(waitlistPagination.current_page+1)" class="btn-page">Próxima »</button>
        </div>
      </section>

      <!-- ===== SERVIÇOS ===== -->
      <section v-if="activeTab === 'services'">
        <div class="section-toolbar">
          <h2 class="section-title">Menu de Serviços</h2>
          <p class="section-subtitle">Adicione ou remova os serviços oferecidos.</p>
        </div>
        <form @submit.prevent="addService" class="form-card">
          <input v-model="newService.name" type="text" placeholder="Nome do Serviço" required class="form-input" style="margin-bottom:12px;">
          <textarea v-model="newService.description" placeholder="Descrição do Serviço (Opcional)" class="form-input" style="margin-bottom:12px; resize:vertical; min-height:60px;"></textarea>
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <div class="input-prefix"><span>R$</span><input v-model="newService.price" type="number" step="0.01" placeholder="Preço" required class="form-input" style="width:120px;"></div>
            <div class="input-prefix"><i class="far fa-clock"></i><input v-model="newService.duration_minutes" type="number" placeholder="Min" required class="form-input" style="width:80px;"></div>
            <button type="submit" class="btn-primary">{{ editingServiceId ? 'SALVAR' : 'ADICIONAR' }}</button>
            <button v-if="editingServiceId" type="button" @click="cancelEdit" class="btn-secondary">CANCELAR</button>
          </div>
        </form>
        <div class="list-items">
          <div v-for="s in services" :key="s.id" class="list-item">
            <div class="list-item-info">
              <strong class="list-item-name">{{ s.name.toUpperCase() }}</strong>
              <span class="list-item-meta">R$ {{ Math.floor(s.price) }} · {{ s.duration_minutes }} min</span>
            </div>
            <div class="list-item-actions">
              <button @click="editService(s)" class="btn-link-gold">EDITAR</button>
              <button @click="deleteService(s.id)" class="btn-link-red">REMOVER</button>
            </div>
          </div>
          <div v-if="services.length === 0" class="empty-state"><i class="fas fa-cut"></i><p>Nenhum serviço cadastrado.</p></div>
        </div>
      </section>

      <!-- ===== ESPECIALISTAS ===== -->
      <section v-if="activeTab === 'especialistas'">
        <div class="section-toolbar">
          <h2 class="section-title">Especialistas</h2>
          <p class="section-subtitle">Gerencie quem atende em sua unidade.</p>
        </div>
        <form @submit.prevent="addProfessional" class="form-card">
          <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <input v-model="newProfessional.name" type="text" placeholder="Nome do Especialista" required class="form-input" style="flex:1;min-width:180px;">
            <input v-model="newProfessional.phone" type="text" placeholder="Celular (WhatsApp)" class="form-input" style="flex:1;min-width:150px;">
            <input v-model="newProfessional.specialties" type="text" placeholder="Especialidades (vírgula)" class="form-input" style="flex:2;min-width:200px;">
            <button type="submit" class="btn-primary">{{ editingProfessionalId ? 'SALVAR' : 'ADICIONAR' }}</button>
            <button v-if="editingProfessionalId" type="button" @click="cancelEditProfessional" class="btn-secondary">CANCELAR</button>
          </div>
        </form>
        <div class="list-items">
          <div v-for="b in professionals" :key="b.id" class="list-item">
            <div class="list-item-info">
              <strong class="list-item-name">{{ b.name.toUpperCase() }}</strong>
              <div class="list-item-meta">
                <span v-if="b.phone"><i class="fas fa-phone"></i> {{ b.phone }}</span>
                <span v-if="b.specialties?.length"><i class="fas fa-cut"></i> {{ b.specialties.join(', ') }}</span>
              </div>
            </div>
            <div class="list-item-actions">
              <button @click="editProfessional(b)" class="btn-link-gold">EDITAR</button>
              <button @click="deleteProfessional(b.id)" class="btn-link-red">REMOVER</button>
            </div>
          </div>
          <div v-if="professionals.length === 0" class="empty-state"><i class="fas fa-user-tie"></i><p>Nenhum especialista cadastrado.</p></div>
        </div>
      </section>

      <!-- ===== RELATÓRIOS (bloqueado) ===== -->
      <section v-if="activeTab === 'relatorios'" class="locked-section">
        <i class="fas fa-lock lock-icon"></i>
        <h2>Relatórios</h2>
        <p>Disponível nos planos Silver e Gold.</p>
      </section>

      <!-- ===== CONFIG ===== -->
      <section v-if="activeTab === 'config'">
        <div class="section-toolbar"><h2 class="section-title">Configurações</h2></div>
        <div class="form-card">
          <div class="config-section" style="margin-bottom: 24px;">
            <h4 class="config-label">DADOS DO ESTABELECIMENTO</h4>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="config-label" style="font-size: 0.7rem; color: #888; margin-bottom: 8px; display: block;">TEMA DO SISTEMA</label>
                <div class="theme-buttons-container" style="display: flex; gap: 10px;">
                  <button type="button" @click="setThemeMode('dark')" :class="['theme-select-btn', shopConfig.theme_mode === 'dark' ? 'active' : '']">Escuro (Barbearia)</button>
                  <button type="button" @click="setThemeMode('light-nude')" :class="['theme-select-btn', shopConfig.theme_mode === 'light-nude' ? 'active' : '']">Claro: Nude</button>
                  <button type="button" @click="setThemeMode('light-wine')" :class="['theme-select-btn', shopConfig.theme_mode === 'light-wine' ? 'active' : '']">Claro: Vinho</button>
                </div>
            </div>

            <div class="form-grid-2">
              <div class="form-group" style="flex: 1; margin-right: 12px;">
                <label class="config-label" style="font-size: 0.7rem; color: #888; margin-bottom: 4px; display: block;">NOME DO ESTABELECIMENTO</label>
                <input v-model="shopConfig.name" type="text" class="form-input">
              </div>
              <div class="form-group" style="flex: 1;">
                <label class="config-label" style="font-size: 0.7rem; color: #888; margin-bottom: 4px; display: block;">LOGOMARCA</label>
                <input type="file" @change="uploadLogo" accept="image/*" class="form-input" style="padding: 6px;">
              </div>
            </div>
          </div>
          <h4 class="config-label">JANELA DE ATENDIMENTO</h4>
          <div class="time-row">
            <div class="time-in-lux"><span>Início</span><input type="time" v-model="shopConfig.opening_time" class="form-input"></div>
            <div class="time-divider">ATÉ</div>
            <div class="time-in-lux"><span>Término</span><input type="time" v-model="shopConfig.closing_time" class="form-input"></div>
          </div>
          <h4 class="config-label" style="margin-top:24px;">HORÁRIO DE ALMOÇO</h4>
          <div class="time-row">
            <div class="time-in-lux"><span>Início</span><input type="time" v-model="shopConfig.lunch_start" class="form-input"></div>
            <div class="time-divider">ATÉ</div>
            <div class="time-in-lux"><span>Retorno</span><input type="time" v-model="shopConfig.lunch_end" class="form-input"></div>
          </div>
          <div style="display: flex; gap: 70px; margin-top: 24px; flex-wrap: wrap; align-items: flex-start;">
            <div style="flex: 1; min-width: 250px;">
              <h4 class="config-label">DIAS DE FUNCIONAMENTO</h4>
              <div class="days-selector">
                <label v-for="day in [0,1,2,3,4,5,6]" :key="day" class="day-chip">
                  <input type="checkbox" :value="day" v-model="shopConfig.working_days">
                  <span>{{ ['DOM','SEG','TER','QUA','QUI','SEX','SÁB'][day] }}</span>
                </label>
              </div>
            </div>
            <div style="flex: 1; min-width: 300px;">
              <h4 class="config-label">DATAS INDISPONÍVEIS</h4>
              <div class="calendar-luxury-admin" style="margin-top: 8px;">
                <div class="calendar-header-lux">
                  <button @click="prevMonth" type="button" class="cal-nav-btn"><i class="fas fa-chevron-left"></i></button>
                  <span class="cal-month-title">{{ currentCalendarMonth.toLocaleString('pt-BR',{month:'long',year:'numeric'}).toUpperCase() }}</span>
                  <button @click="nextMonth" type="button" class="cal-nav-btn"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="calendar-grid-lux">
                  <div v-for="d in ['D','S','T','Q','Q','S','S']" :key="d+'h'" class="cal-day-header">{{ d }}</div>
                  <div v-for="(date, idx) in calendarDays" :key="idx" class="cal-day-cell" :class="{ padding: !date, unavailable: isDateUnavailable(date) }" @click="date && toggleUnavailableDate(date)">{{ date ? date.getDate() : '' }}</div>
                </div>
              </div>
            </div>
          </div>
          <button @click="updateShopConfig" class="btn-primary" style="margin-top:30px;width:100%;">SALVAR CONFIGURAÇÕES</button>
        </div>
      </section>

      <!-- ===== MEU PERFIL ===== -->
      <section v-if="activeTab === 'perfil'">
        <div class="profile-social-header">
          <div class="profile-avatar-container">
            <img v-if="shopConfig.logo_url" :src="shopConfig.logo_url" alt="Avatar" class="profile-avatar" />
            <div v-else class="profile-avatar-placeholder"><i class="fas fa-user"></i></div>
          </div>
          <h2 class="profile-name">{{ profileForm.name || userName }}</h2>
          <p class="profile-email">{{ profileForm.email }}</p>
          <button v-if="!isEditingProfile" @click="isEditingProfile = true" class="btn-outline-gold profile-edit-btn">Editar Perfil</button>
        </div>

        <div v-if="isEditingProfile" class="form-card animate-fade-in" style="margin-top: 24px;">
          <div class="form-group">
            <h4 class="config-label">SEU NOME</h4>
            <input type="text" v-model="profileForm.name" class="form-input" placeholder="Ex: José Adeildo" />
          </div>
          <div class="form-group" style="margin-top: 24px;">
            <h4 class="config-label">E-MAIL DE ACESSO</h4>
            <input type="email" v-model="profileForm.email" class="form-input" placeholder="Seu E-mail" />
          </div>
          <div class="form-group" style="margin-top: 24px;">
            <h4 class="config-label">NOVA SENHA</h4>
          <div style="position: relative; display: flex; align-items: center;">
            <input :type="showProfilePassword ? 'text' : 'password'" v-model="profileForm.password" class="form-input" placeholder="Deixe em branco para manter a atual" style="padding-right: 40px;" />
            <button type="button" @click="showProfilePassword = !showProfilePassword" style="position:absolute; right:12px; background:none; border:none; cursor:pointer; color:#666; transition: color 0.2s; padding:0;" :title="showProfilePassword ? 'Ocultar senha' : 'Exibir senha'">
              <i :class="showProfilePassword ? 'fas fa-eye-slash' : 'fas fa-eye'" style="font-size: 0.9rem;"></i>
            </button>
          </div>
          </div>
          <div style="display: flex; gap: 12px; margin-top: 30px;">
            <button @click="updateProfileSubmit" class="btn-primary" style="flex:1;">SALVAR PERFIL</button>
            <button @click="isEditingProfile = false" class="btn-secondary" style="flex:1;">CANCELAR</button>
          </div>
        </div>
      </section>

    </main>
    <!-- Bottom Navigation (Mobile) -->
    <nav class="mobile-bottom-nav">
      <button 
        v-for="item in navItems.filter(i => !i.locked && !i.hidden).slice(0, 5)" 
        :key="item.key"
        :class="['bottom-nav-item', { active: activeTab === item.key }]"
        @click="navigate(item.key)"
      >
        <i :class="item.icon"></i>
        <span>{{ item.label.split(' ')[0] }}</span>
      </button>
    </nav>
  </div>
</div>
</template>

<style scoped>
/* ===== SHELL ===== */
.admin-shell { display: flex; min-height: 100vh; background: #080808; font-family: 'Inter', sans-serif; }

/* ===== SIDEBAR ===== */
.admin-sidebar {
  width: 240px; min-height: 100vh; background: #111;
  border-right: 1px solid #1e1e1e; display: flex; flex-direction: column;
  position: fixed; top: 0; left: 0; z-index: 200; transition: transform 0.3s ease;
}
.sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 190; }
.sidebar-logo { padding: 24px 20px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #1e1e1e; }
.logo-circle { width: 64px; height: 64px; border-radius: 8px; background: var(--accent); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; color: #fff; flex-shrink: 0; }
.logo-image { width: 80px; height: 80px; border-radius: 8px; object-fit: contain; flex-shrink: 0; background: transparent; }
.logo-info { display: flex; flex-direction: column; }
.logo-name { color: #fff; font-weight: 700; font-size: 0.9rem; line-height: 1.2; }
.logo-sub { color: #555; font-size: 0.65rem; letter-spacing: 2px; }
.sidebar-nav { flex: 1; padding: 16px 0; overflow-y: auto; }
.sidebar-nav-item {
  width: 100%; display: flex; align-items: center; gap: 12px;
  padding: 12px 20px; background: transparent; border: none; color: #888;
  font-size: 0.85rem; font-weight: 500; cursor: pointer; text-align: left;
  transition: all 0.2s; position: relative; letter-spacing: 0.5px;
}
.sidebar-nav-item:hover { background: rgba(212,175,55,0.07); color: #ccc; }
.sidebar-nav-item.active { background: rgba(212,175,55,0.12); color: #D4AF37; border-right: 3px solid #D4AF37; }
.sidebar-nav-item.locked { opacity: 0.4; cursor: not-allowed; }
.sidebar-nav-item i:first-child { width: 18px; text-align: center; font-size: 0.9rem; }
.sidebar-lock-icon { margin-left: auto; font-size: 0.7rem; }
.sidebar-footer { padding: 16px 20px; border-top: 1px solid #1e1e1e; }
.plan-badge { display: inline-block; background: linear-gradient(135deg, #D4AF37, #a07d1c); color: #000; font-size: 0.65rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; letter-spacing: 1px; margin-bottom: 14px; }
.sidebar-user { display: flex; align-items: center; gap: 10px; }
.user-avatar { width: 32px; height: 32px; border-radius: 50%; background: #222; display: flex; align-items: center; justify-content: center; color: #D4AF37; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; }
.user-name { color: #aaa; font-size: 0.8rem; flex: 1; }
.btn-logout { background: transparent; border: none; color: #555; cursor: pointer; font-size: 0.9rem; padding: 4px; transition: color 0.2s; }
.btn-logout:hover { color: #ef4444; }

/* ===== MAIN ===== */
.admin-main { flex: 1; margin-left: 240px; display: flex; flex-direction: column; min-height: 100vh; }
.admin-topbar { height: 56px; background: #111; border-bottom: 1px solid #1e1e1e; display: flex; align-items: center; padding: 0 24px; gap: 16px; position: sticky; top: 0; z-index: 100; }
.hamburger { display: none; background: transparent; border: none; color: #fff; font-size: 1.1rem; cursor: pointer; padding: 4px 8px; }
.topbar-title { flex: 1; }
.topbar-section { color: #fff; font-size: 1.15rem; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.btn-topbar-mobile { display: none; background: transparent; border: none; cursor: pointer; }
.admin-content { flex: 1; padding: 28px 32px; overflow-y: auto; padding-bottom: 80px; }

/* ===== BOTTOM NAV (Mobile) ===== */
.mobile-bottom-nav {
  display: none;
  position: fixed;
  bottom: 0; left: 0; right: 0;
  background: rgba(17, 17, 17, 0.95);
  backdrop-filter: blur(10px);
  border-top: 1px solid #2a2a2a;
  z-index: 500;
  padding: 8px 10px 16px;
  justify-content: space-around;
  align-items: center;
}
.bottom-nav-item {
  background: transparent; border: none; color: #666;
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  cursor: pointer; padding: 8px 10px; border-radius: 8px;
  transition: all 0.2s;
  width: 20%;
}
.bottom-nav-item i { font-size: 1.2rem; margin-bottom: 2px; }
.bottom-nav-item span { font-size: 0.6rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.bottom-nav-item.active { color: #D4AF37; }
.bottom-nav-item.active i { transform: translateY(-2px); }

/* ===== DASHBOARD ===== */
.dash-greeting { color: var(--text-main, #fff); font-size: 1.4rem; font-weight: 700; margin-bottom: 24px; }
.kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
.kpi-card { border-radius: 12px; padding: 20px 22px; transition: transform 0.2s, box-shadow 0.2s; }
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.4); }
.kpi-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #888; margin-bottom: 10px; }
.kpi-value { font-size: 1.8rem; font-weight: 800; color: #fff; line-height: 1; }
.kpi-pct { font-size: 1rem; font-weight: 600; color: #aaa; }
.kpi-blue { background: linear-gradient(135deg, #161b22, #0d1117); border: 1px solid #30363d; }
.kpi-green { background: linear-gradient(135deg, #112016, #09120c); border: 1px solid #24422e; }
.kpi-teal { background: linear-gradient(135deg, #261f0e, #141006); border: 1px solid #4a3d1b; }
.kpi-purple { background: linear-gradient(135deg, #1e1624, #100b14); border: 1px solid #3b2a47; }
.kpi-orange { background: linear-gradient(135deg, #241416, #140a0b); border: 1px solid #47272b; }
.kpi-gold { background: linear-gradient(135deg, #221812, #120c09); border: 1px solid #422f23; }
.dash-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.dash-panel { background: #111; border: 1px solid #1e1e1e; border-radius: 12px; padding: 20px; }
.panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.panel-title { color: #fff; font-size: 0.95rem; font-weight: 700; }
.btn-ver-todos { background: transparent; border: 1px solid #333; color: #888; font-size: 0.72rem; padding: 4px 12px; border-radius: 6px; cursor: pointer; transition: all 0.2s; }
.btn-ver-todos:hover { border-color: #D4AF37; color: #D4AF37; }
.today-appt-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #1a1a1a; }
.today-appt-row:last-child { border-bottom: none; }
.appt-time { color: #D4AF37; font-weight: 700; font-size: 0.85rem; min-width: 46px; }
.appt-info { flex: 1; }
.appt-client { display: block; color: #fff; font-size: 0.85rem; font-weight: 600; }
.appt-detail { display: block; color: #555; font-size: 0.75rem; margin-top: 2px; }
.appt-badge { display: inline-flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700; padding: 0 10px; height: 24px; box-sizing: border-box; border-radius: 4px; letter-spacing: 0.5px; white-space: nowrap; text-transform: uppercase; color: #fff; }
.rank-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.rank-table th { color: #555; font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 0 0 10px; text-align: left; border-bottom: 1px solid #1e1e1e; }
.rank-table td { padding: 10px 0; border-bottom: 1px solid #111; color: #ccc; }
.rank-table tr:last-child td { border-bottom: none; }
.rank-pos { color: #D4AF37; font-weight: 700; }
.rank-num { color: #fff; font-weight: 600; text-align: center; }
.rank-rev { color: #10b981; font-weight: 600; }

/* ===== SEÇÕES ===== */
.section-toolbar { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; }
.section-title { color: #fff; font-size: 1.2rem; font-weight: 700; margin-bottom: 4px; }
.section-subtitle { color: #666; font-size: 0.85rem; }
.date-filter-btn { position: relative; display: flex; align-items: center; gap: 8px; background: #111; border: 1px solid #333; padding: 8px 14px; border-radius: 8px; cursor: pointer; color: #fff; font-size: 0.85rem; font-weight: 600; }
.date-filter-btn i { color: var(--accent); }
.date-filter-group { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.interval-date-input { background: transparent; border: none; color: #fff; outline: none; font-family: inherit; font-size: 0.85rem; font-weight: 600; }
.interval-date-input::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
.btn-clear-filter { background: #1a1a1a; border: 1px solid #333; color: #aaa; padding: 7px 12px; border-radius: 8px; cursor: pointer; font-size: 0.8rem; transition: all 0.2s; }
.btn-clear-filter:hover { border-color: var(--accent); color: var(--accent); }
.per-page-select { background: #111; color: #fff; border: 1px solid #333; padding: 7px 10px; border-radius: 8px; outline: none; font-size: 0.85rem; }

/* Agenda */
.agenda-group { margin-bottom: 28px; }
.agenda-group-date { display: flex; justify-content: space-between; align-items: center; color: var(--accent); font-size: 0.7rem; font-weight: 800; letter-spacing: 2px; padding: 8px 12px; border-left: 3px solid var(--accent); margin-bottom: 12px; background: color-mix(in srgb, var(--accent) 5%, transparent); border-radius: 0 6px 6px 0; }
.group-count { font-size: 0.65rem; font-weight: 600; color: #555; letter-spacing: 1px; text-transform: uppercase; }
.agenda-appt-card { background: #111; border: 1px solid #1e1e1e; border-radius: 10px; padding: 12px 20px; margin-bottom: 10px; display: grid; grid-template-columns: 1fr 90px 130px 88px 88px 88px; align-items: center; gap: 10px; transition: all 0.25s ease; }
.agenda-appt-card:hover { border-color: #2a2a2a; }
.agenda-appt-card.appt-inactive { opacity: 0.35; background: #0a0a0a; border-color: #141414; }
.agenda-appt-card.appt-inactive:hover { opacity: 0.7; }
.agenda-appt-main { display: flex; align-items: center; gap: 16px; min-width: 0; }
.appt-time-block { display: flex; flex-direction: column; align-items: center; min-width: 52px; }
.appt-time-end { color: #444; font-size: 0.65rem; font-weight: 600; margin-top: 2px; }
.appt-col-price { display: flex; align-items: center; justify-content: flex-end; }
.appt-col-status { display: flex; align-items: center; justify-content: center; }
.appt-col-btn { display: flex; align-items: center; justify-content: center; }
.appt-price { color: #ffffff; font-size: 0.85rem; font-weight: 700; white-space: nowrap; }
.appt-status-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 0.62rem; font-weight: 700; padding: 3px 9px; border-radius: 20px; border: 1px solid transparent; letter-spacing: 0.5px; white-space: nowrap; text-transform: uppercase; }
.status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.client-wz-icon { color: #25D366; font-size: 1.1rem; text-decoration: none; transition: transform 0.2s; display: inline-flex; align-items: center; }
.client-wz-icon:hover { transform: scale(1.15); filter: brightness(1.2); }

/* Skeleton Loader */
@keyframes skeleton-pulse { 0%,100%{opacity:0.4} 50%{opacity:0.8} }
.agenda-skeleton { display: flex; flex-direction: column; gap: 12px; }
.skeleton-group-date { height: 36px; background: #1a1a1a; border-radius: 6px; width: 300px; animation: skeleton-pulse 1.5s ease infinite; margin-bottom: 4px; }
.skeleton-card { background: #111; border: 1px solid #1e1e1e; border-radius: 10px; padding: 16px 20px; display: flex; align-items: center; gap: 16px; }
.skeleton-time { width: 48px; height:  36px; background: #1e1e1e; border-radius: 6px; animation: skeleton-pulse 1.5s ease infinite; flex-shrink: 0; }
.skeleton-info { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.skeleton-line { background: #1e1e1e; border-radius: 4px; animation: skeleton-pulse 1.5s ease infinite; }
.skeleton-name { height: 14px; width: 60%; }
.skeleton-detail { height: 11px; width: 40%; }
.skeleton-actions { display: flex; gap: 8px; align-items: center; }
.skeleton-badge { width: 90px; height: 34px; background: #1e1e1e; border-radius: 6px; animation: skeleton-pulse 1.5s ease infinite; }
.skeleton-btn { width: 80px; height: 34px; background: #1e1e1e; border-radius: 6px; animation: skeleton-pulse 1.5s ease infinite; }
/* Botões de Ação */
.btn-action { display: inline-flex; align-items: center; justify-content: center; width: 100%; height: 28px; box-sizing: border-box; border: 1px solid transparent; border-radius: 5px; font-weight: 700; font-size: 0.6rem; cursor: pointer; transition: all 0.2s ease; letter-spacing: 0.8px; text-transform: uppercase; }
.btn-action:disabled { opacity: 0.12; cursor: not-allowed; filter: grayscale(1); box-shadow: none !important; transform: none !important; border-color: #1a1a1a !important; background: #111 !important; color: #333 !important; pointer-events: none; }
.btn-confirm { background: #10b981; color: #fff; box-shadow: 0 4px 10px rgba(16,185,129,0.2); }
.btn-confirm:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 6px 15px rgba(16,185,129,0.3); }
.btn-complete { background: var(--accent); color: #000; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
.btn-complete:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 6px 15px rgba(0,0,0,0.4); }
.btn-cancel { background: transparent; color: #888; border: 1px solid #333; }
.btn-cancel:hover { color: #ef4444; border-color: #ef4444; background: rgba(239, 68, 68, 0.05); }
.btn-whatsapp { background: #25D366; color: #fff; }
.btn-whatsapp:hover { filter: brightness(1.1); transform: translateY(-1px); }

/* Paginação */
.pagination { display: flex; justify-content: center; align-items: center; gap: 16px; margin-top: 28px; }
.btn-page { background: #111; color: #fff; border: 1px solid #333; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; }
.btn-page:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { color: #D4AF37; font-weight: 700; font-size: 0.85rem; }

/* Dashboard Pagination */
.dashboard-pagination { display: flex; justify-content: center; align-items: center; gap: 12px; margin-top: 16px; padding-top: 12px; border-top: 1px solid #1a1a1a; }
.btn-dash-page { background: #111; color: #888; border: 1px solid #333; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; transition: all 0.2s; }
.btn-dash-page:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.btn-dash-page:disabled { opacity: 0.3; cursor: not-allowed; }
.dash-page-info { color: #aaa; font-size: 0.75rem; font-weight: 600; }

/* Forms */
.form-card { background: #111; border: 1px solid #1e1e1e; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-input { background: #0d0d0d; border: 1px solid #2a2a2a; color: #fff; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; width: 100%; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
.form-input:focus { border-color: #D4AF37; }
.input-prefix { display: flex; align-items: center; gap: 8px; background: #0d0d0d; border: 1px solid #2a2a2a; border-radius: 8px; padding: 0 12px; color: #666; font-size: 0.85rem; }
.input-prefix .form-input { border: none; background: transparent; padding: 10px 4px; }
.btn-primary { background: #D4AF37; color: #000; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 800; font-size: 0.82rem; cursor: pointer; letter-spacing: 1px; transition: opacity 0.2s; white-space: nowrap; }
.btn-primary:hover { opacity: 0.9; }
.btn-secondary { background: #222; color: #aaa; border: 1px solid #333; padding: 10px 22px; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; white-space: nowrap; }

/* Lists */
.list-items { }
.list-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #1a1a1a; }
.list-item-name { color: #fff; font-size: 0.9rem; display: block; }
.list-item-meta { color: #666; font-size: 0.78rem; margin-top: 3px; display: flex; gap: 12px; }
.list-item-actions { display: flex; gap: 16px; }
.btn-link-gold { background: transparent; border: none; color: var(--accent); font-size: 0.72rem; font-weight: 800; letter-spacing: 1px; cursor: pointer; }
.btn-link-red { background: transparent; border: none; color: #ef4444; font-size: 0.72rem; font-weight: 800; letter-spacing: 1px; cursor: pointer; }

/* Waitlist */
.waitlist-card { background: #111; border: 1px solid #1e1e1e; border-radius: 10px; padding: 16px 20px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
.waitlist-name { color: #D4AF37; font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; }
.waitlist-slot { color: #888; font-size: 0.82rem; margin-bottom: 4px; }
.waitlist-contact { font-size: 0.8rem; color: #666; }
.waitlist-notes { color: #555; font-size: 0.78rem; font-style: italic; margin-top: 4px; }
.waitlist-actions { display: flex; gap: 8px; flex-shrink: 0; }
.whatsapp-link { color: #25D366; text-decoration: none; }
.status-notified { color: #D4AF37; }
.status-waiting { color: #666; }

/* Config */
.config-label { color: #888; font-size: 0.7rem; font-weight: 700; letter-spacing: 2px; margin-bottom: 12px; }
.time-row { display: flex; align-items: center; gap: 16px; }
.time-in-lux span { display: block; color: #666; font-size: 0.72rem; margin-bottom: 4px; }
.time-divider { color: #555; font-size: 0.7rem; font-weight: 700; letter-spacing: 2px; padding-top: 18px; }
.days-selector { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
.day-chip input { display: none; }
.day-chip span { display: block; padding: 6px 12px; border: 1px solid #333; color: #666; font-size: 0.72rem; font-weight: 700; border-radius: 6px; cursor: pointer; letter-spacing: 1px; transition: all 0.2s; }
.day-chip input:checked + span { background: color-mix(in srgb, var(--accent) 15%, transparent); border-color: var(--accent); color: var(--accent); }

/* Calendar */
.calendar-luxury-admin { border: 1px solid #1e1e1e; border-radius: 10px; overflow: hidden; margin-top: 8px; width: 100%; }
.calendar-header-lux { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #0d0d0d; }
.cal-month-title { color: var(--accent); font-size: 0.78rem; font-weight: 700; letter-spacing: 2px; }
.cal-nav-btn { background: transparent; border: none; color: #888; cursor: pointer; font-size: 0.85rem; }
.cal-nav-btn:hover { color: var(--accent); }
.calendar-grid-lux { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #1a1a1a; }
.cal-day-header { background: #0d0d0d; text-align: center; padding: 8px; color: #555; font-size: 0.65rem; font-weight: 700; }
.cal-day-cell { background: #111; text-align: center; padding: 10px 4px; color: #888; font-size: 0.82rem; cursor: pointer; transition: all 0.15s; }
.cal-day-cell:hover:not(.padding) { background: color-mix(in srgb, var(--accent) 10%, transparent); color: var(--accent); }
.cal-day-cell.unavailable { background: rgba(220,38,38,0.15); color: #ef4444; }
.cal-day-cell.padding { background: #0a0a0a; cursor: default; }

/* Empty state */
.empty-state { text-align: center; padding: 48px; color: #444; }
.empty-state i { font-size: 2rem; margin-bottom: 12px; display: block; }
.empty-state p { font-size: 0.9rem; }

/* Locked */
.locked-section { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; text-align: center; color: #444; }
.lock-icon { font-size: 3rem; margin-bottom: 16px; color: #333; }
.locked-section h2 { color: #555; font-size: 1.2rem; margin-bottom: 8px; }
.locked-section p { color: #D4AF37; font-size: 0.85rem; }

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
  .admin-sidebar { transform: translateX(-100%); }
  .admin-sidebar.open { transform: translateX(0); }
  .admin-main { margin-left: 0; }
  .hamburger { display: flex; }
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .dash-panels { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .admin-topbar { position: fixed; top: 0; left: 0; right: 0; z-index: 400; background: #111; }
  .admin-content { padding: 16px; padding-top: 72px; padding-bottom: 90px; }
  .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .kpi-value { font-size: 1.4rem; }
  .form-grid-2 { grid-template-columns: 1fr; }
  .agenda-appt-card { display: flex; flex-direction: column; align-items: stretch; gap: 12px; }
  .appt-col-price, .appt-col-status { justify-content: flex-start; }
  .section-toolbar { flex-direction: column; align-items: flex-start; gap: 12px; }
  .admin-sidebar { display: none; } /* Hide sidebar completely on very small screens, rely on bottom nav */
  .hamburger { display: none; }
  .mobile-bottom-nav { display: flex; }
  .btn-topbar-mobile { display: inline-block; color: #aaa; margin-left: 12px; font-size: 1.2rem; transition: color 0.2s; }
  .btn-topbar-mobile:hover { color: #fff; }
}
.profile-social-header { display: flex; flex-direction: column; align-items: center; background: #111; padding: 40px 20px; border-radius: 12px; border: 1px solid #1e1e1e; margin-bottom: 24px; text-align: center; }
.profile-avatar-container { width: 160px; height: 160px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; justify-content: center; }
.profile-avatar { width: 100%; height: 100%; border-radius: 8px; object-fit: contain; background: transparent; }
.profile-avatar-placeholder { width: 100%; height: 100%; border-radius: 8px; background: linear-gradient(135deg, #D4AF37, #a07d1c); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #000; }
.profile-name { font-size: 1.8rem; color: #fff; font-weight: 700; margin-bottom: 4px; }
.profile-email { color: #888; font-size: 0.9rem; margin-bottom: 24px; }
.profile-edit-btn { padding: 10px 30px; border-radius: 30px; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
.btn-outline-gold { border: 1px solid #D4AF37; color: #D4AF37; background: transparent; cursor: pointer; transition: 0.3s; }
.btn-outline-gold:hover { background: #D4AF37; color: #000; }
.animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
