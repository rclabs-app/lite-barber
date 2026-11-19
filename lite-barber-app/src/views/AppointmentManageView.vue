<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../services/api'
import { LuxurySwal, Toast } from '../utils/swal'
import { usePublicShop } from '../composables/usePublicShop'

const route = useRoute()
const router = useRouter()
const { slug, loadShop } = usePublicShop()

const appointment = ref(null)
const loading = ref(true)
const error = ref(null)
const cancelReason = ref('')
const cancelling = ref(false)

const statusLabel = {
  scheduled: 'Agendado',
  confirmed: 'Confirmado',
  in_progress: 'Em Atendimento',
  completed: 'Concluído',
  cancelled: 'Cancelado',
  no_show: 'Não Compareceu',
}

const canCancel = computed(() =>
  appointment.value && !['completed', 'cancelled', 'no_show'].includes(appointment.value.status)
)

const formattedSchedule = computed(() => {
  if (!appointment.value?.scheduled_at) return ''
  const dt = new Date(appointment.value.scheduled_at)
  return dt.toLocaleDateString('pt-BR') + ' às ' + dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
})

const fetchAppointment = async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await axios.get(`/api/appointments/${encodeURIComponent(route.params.token)}`)
    appointment.value = data.appointment
  } catch (err) {
    error.value = err.response?.data?.message || 'Agendamento não encontrado.'
  } finally {
    loading.value = false
  }
}

const handleCancel = async () => {
  const confirm = await LuxurySwal.fire({
    title: 'Cancelar seu agendamento?',
    text: 'O horário será liberado para outros clientes.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, cancelar',
    cancelButtonText: 'Manter horário',
  })

  if (!confirm.isConfirmed) return

  cancelling.value = true
  try {
    const { data } = await axios.post(`/api/appointments/${encodeURIComponent(route.params.token)}/cancel`, {
      cancel_reason: cancelReason.value || 'Cancelado pelo cliente via link público',
    })
    appointment.value = data.appointment
    Toast.fire({ icon: 'success', title: 'Agendamento cancelado com sucesso!' })
  } catch (err) {
    Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Não foi possível cancelar' })
  } finally {
    cancelling.value = false
  }
}

const goBack = () => router.push({ name: 'shop', params: { slug: slug.value || appointment.value?.shop?.slug } })

onMounted(async () => {
  if (slug.value) {
    await loadShop()
  }
  await fetchAppointment()
})
</script>

<template>
  <section class="view active">
    <nav class="luxury-nav">
      <button @click="goBack" class="btn-back-luxury"><i class="fas fa-long-arrow-alt-left"></i></button>
      <div class="nav-title">MEU <span>Agendamento</span></div>
    </nav>

    <div v-if="loading" style="text-align: center; padding: 60px 20px; color: #888;">
      Carregando dados do agendamento...
    </div>

    <div v-else-if="error" style="text-align: center; padding: 60px 20px;">
      <p style="color: #ef4444; margin-bottom: 20px;">{{ error }}</p>
      <button @click="goBack" class="btn-primary-luxury" style="max-width: 240px; margin: 0 auto;">VOLTAR AO INÍCIO</button>
    </div>

    <div v-else-if="appointment" style="max-width: 420px; margin: 30px auto 0; width: 100%; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 26px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 18px;">
        <span style="font-size: 0.75rem; color: #888; letter-spacing: 1.5px; text-transform: uppercase;">STATUS</span>
        <span style="font-weight: 700; color: var(--accent);">{{ statusLabel[appointment.status] || appointment.status }}</span>
      </div>

      <div style="display:flex; flex-direction:column; gap:12px; margin-bottom: 22px; font-size: 0.92rem;">
        <div><strong style="color:#888;">Cliente:</strong> {{ appointment.client_name }}</div>
        <div><strong style="color:#888;">Data e Hora:</strong> {{ formattedSchedule }}</div>
        <div v-if="appointment.service"><strong style="color:#888;">Serviço:</strong> {{ appointment.service.name }}</div>
        <div v-if="appointment.professional"><strong style="color:#888;">Especialista:</strong> {{ appointment.professional.name }}</div>
        <div v-if="appointment.price_snapshot"><strong style="color:#888;">Valor:</strong> R$ {{ Number(appointment.price_snapshot).toFixed(2) }}</div>
      </div>

      <div v-if="canCancel" style="border-top: 1px solid rgba(255,255,255,0.08); padding-top: 18px;">
        <div class="input-luxury-wrapper" style="margin-bottom: 12px;">
          <input v-model="cancelReason" type="text" placeholder="Motivo do cancelamento (opcional)">
        </div>
        <button @click="handleCancel" :disabled="cancelling" class="btn-outline-luxury" style="width:100%; border-color:#ef4444; color:#ef4444;">
          <span>{{ cancelling ? 'CANCELANDO...' : 'CANCELAR AGENDAMENTO' }}</span>
        </button>
      </div>

      <button @click="goBack" class="btn-primary-luxury" style="margin-top: 14px; width: 100%;">
        <span>VOLTAR À BARBEARIA</span>
      </button>
    </div>
  </section>
</template>
