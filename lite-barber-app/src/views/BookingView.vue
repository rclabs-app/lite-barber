<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from '../services/api'
import { usePublicShop } from '../composables/usePublicShop'
import { LuxurySwal, Toast } from '../utils/swal'

const router = useRouter()
const { shop, slug, loadShop } = usePublicShop()

// Refs do Flow
const services = ref([])
const professionals = ref([])
const availableSlots = ref([]) 
// const shop = ref(null)

// Model do Form
const name = ref('')
const phone = ref('')
const notes = ref('')
const selectedServiceId = ref(null)
const selectedProfessionalId = ref(null)
const today = new Date()
const selectedDate = ref(null)
const selectedTime = ref(null)
const loadingSlots = ref(false)
const submitting = ref(false)

const currentCalendarMonth = ref(new Date())

const formatDate = (date) => {
    if (!date) return ''
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

const calendarDays = computed(() => {
    const year = currentCalendarMonth.value.getFullYear()
    const month = currentCalendarMonth.value.getMonth()
    const firstDay = new Date(year, month, 1)
    const lastDay = new Date(year, month + 1, 0)
    
    const days = []
    for (let i = 0; i < firstDay.getDay(); i++) {
        days.push(null)
    }
    for (let i = 1; i <= lastDay.getDate(); i++) {
        days.push(new Date(year, month, i))
    }
    return days
})

const isDateDisabled = (date) => {
    if (!date || !shop.value) return true
    
    const todayNoTime = new Date()
    todayNoTime.setHours(0,0,0,0)
    if (date < todayNoTime) return true

    const dateStr = formatDate(date)
    const dayOfWeek = date.getDay()
    
    const workingDays = Array.isArray(shop.value.working_days) ? shop.value.working_days : JSON.parse(shop.value.working_days || '[]')
    if (!workingDays.includes(dayOfWeek)) return true
    
    const unavailableDates = Array.isArray(shop.value.unavailable_dates) ? shop.value.unavailable_dates : JSON.parse(shop.value.unavailable_dates || '[]')
    if (unavailableDates.includes(dateStr)) return true
    
    return false
}

const selectDate = (date) => {
    if (!date || isDateDisabled(date)) return
    selectedDate.value = formatDate(date)
    selectedTime.value = null // Reset time when date changes
    fetchAvailableSlots()
}

const fetchAvailableSlots = async () => {
    console.log('Fetching slots for:', selectedDate.value, 'Professional:', selectedProfessionalId.value)
    
    if (!selectedDate.value || !shop.value || !selectedProfessionalId.value) {
        availableSlots.value = []
        return
    }
    
    loadingSlots.value = true
    
    try {
        const { data: busySlots } = await axios.get(`/api/shops/${encodeURIComponent(slug.value)}/appointments/busy`, {
            params: { 
                date: selectedDate.value,
                professional_id: selectedProfessionalId.value
            }
        })

        console.log('Busy slots from API:', busySlots)

        const allSlots = generateSlots(
            shop.value.opening_time || '08:00', 
            shop.value.closing_time || '19:00',
            shop.value.slot_duration || 30,
            shop.value.lunch_start,
            shop.value.lunch_end
        )

        console.log('All potential slots:', allSlots)

        const durationMinutes = selectedService.value?.duration_minutes || shop.value.slot_duration || 30

        // Filtrar horários ocupados
        availableSlots.value = allSlots.filter(slotTime => {
            const [h, m] = slotTime.split(':')
            const [y, mon, d] = selectedDate.value.split('-')
            const slotStart = new Date(y, mon - 1, d, h, m, 0)
            const slotEnd = new Date(slotStart.getTime() + durationMinutes * 60 * 1000)

            if (slotStart <= new Date()) return false

            if (shop.value.closing_time) {
                const [closeH, closeM] = shop.value.closing_time.substring(0, 5).split(':').map(Number)
                const closing = new Date(y, mon - 1, d, closeH, closeM, 0)
                if (slotEnd > closing) return false
            }

            if (shop.value.lunch_start && shop.value.lunch_end) {
                const [lunchStartH, lunchStartM] = shop.value.lunch_start.substring(0, 5).split(':').map(Number)
                const [lunchEndH, lunchEndM] = shop.value.lunch_end.substring(0, 5).split(':').map(Number)
                const lunchStart = new Date(y, mon - 1, d, lunchStartH, lunchStartM, 0)
                const lunchEnd = new Date(y, mon - 1, d, lunchEndH, lunchEndM, 0)
                if (slotStart < lunchEnd && slotEnd > lunchStart) return false
            }

            return !busySlots.some(busy => {
                const bStart = new Date(busy.scheduled_at.replace(' ', 'T'))
                const bEnd = new Date(busy.ends_at.replace(' ', 'T'))
                return slotStart < bEnd && slotEnd > bStart
            })
        })

        console.log('Final available slots:', availableSlots.value)

    } catch (err) {
        console.error('Erro ao buscar horários ocupados:', err)
        Toast.fire({
            icon: 'error',
            title: 'Erro ao carregar horários',
            text: err.normalized?.message || 'Tente novamente em alguns instantes.'
        })
    } finally {
        loadingSlots.value = false
    }
}

watch([selectedProfessionalId, selectedServiceId], () => {
    if (selectedDate.value) {
        selectedTime.value = null
        fetchAvailableSlots()
    }
})

const nextMonth = () => {
    currentCalendarMonth.value = new Date(currentCalendarMonth.value.getFullYear(), currentCalendarMonth.value.getMonth() + 1, 1)
}

const prevMonth = () => {
    currentCalendarMonth.value = new Date(currentCalendarMonth.value.getFullYear(), currentCalendarMonth.value.getMonth() - 1, 1)
}

const generateSlots = (open, close, interval = 30, lunchStart = null, lunchEnd = null) => {
    if (!open || !close) return []
    
    let slots = []
    const [openH, openM] = open.split(':').map(Number)
    const [closeH, closeM] = close.split(':').map(Number)
    
    let current = new Date(2024, 0, 1, openH, openM, 0)
    const endTime = new Date(2024, 0, 1, closeH, closeM, 0)
    
    let lStart = null
    let lEnd = null
    
    if (lunchStart && lunchEnd) {
        const [lsH, lsM] = lunchStart.split(':').map(Number)
        const [leH, leM] = lunchEnd.split(':').map(Number)
        lStart = new Date(2024, 0, 1, lsH, lsM, 0)
        lEnd = new Date(2024, 0, 1, leH, leM, 0)
    }

    while (current < endTime) {
        const isLunch = lStart && lEnd && current >= lStart && current < lEnd
        if (!isLunch) {
            slots.push(current.toTimeString().substring(0, 5))
        }
        current.setMinutes(current.getMinutes() + interval)
    }
    return slots
}

const isDateAvailable = computed(() => {
    if (!shop.value || !selectedDate.value) return true
    
    // Obter dia da semana (0-6)
    const dateParts = selectedDate.value.split('-')
    const date = new Date(dateParts[0], dateParts[1] - 1, dateParts[2])
    const dayOfWeek = date.getDay()
    
    // Verificar dias da semana permitidos
    const workingDays = Array.isArray(shop.value.working_days) ? shop.value.working_days : JSON.parse(shop.value.working_days || '[]')
    if (!workingDays.includes(dayOfWeek)) return false
    
    // Verificar datas indisponíveis específicas
    const unavailableDates = Array.isArray(shop.value.unavailable_dates) ? shop.value.unavailable_dates : JSON.parse(shop.value.unavailable_dates || '[]')
    if (unavailableDates.includes(selectedDate.value)) return false
    
    return true
})

const applyPhoneMask = (e) => {
    let v = e.target.value.replace(/\D/g, '').substring(0, 11)
    let formatted = ''
    if (v.length > 0) formatted += '(' + v.substring(0, 2)
    if (v.length > 2) formatted += ') ' + v.substring(2, 7)
    if (v.length > 7) formatted += '-' + v.substring(7, 11)
    phone.value = formatted
}

onMounted(async () => {
    await loadShop()
    try {
        const [sData, bData] = await Promise.all([
            axios.get(`/api/shops/${encodeURIComponent(slug.value)}/services`),
            axios.get(`/api/shops/${encodeURIComponent(slug.value)}/professionals`)
        ])
        services.value = sData.data
        professionals.value = bData.data
        if (shop.value.theme_color) {
            document.documentElement.style.setProperty('--gold', shop.value.theme_color);
            document.documentElement.style.setProperty('--accent', shop.value.theme_color);
        }
        const theme = shop.value.theme_mode;
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

        // Auto-select professional if only one exists
        if (professionals.value.length === 1) {
            selectedProfessionalId.value = professionals.value[0].id
        }
        
        // No automatic slot generation on mount anymore
        // We wait for date selection
    } catch(err) {
        console.error('Erro ao carregar setup do agendamento:', err)
    }
})

const goBack = () => router.push({ name: 'shop', params: { slug: slug.value } })

const confirmBooking = async () => {
    if (!selectedServiceId.value || !selectedProfessionalId.value || !selectedTime.value) {
        Toast.fire({
            icon: 'warning',
            title: 'Campos incompletos',
            text: 'Por favor, selecione o Serviço, o Especialista e o Horário.'
        })
        return;
    }

    if (submitting.value) return
    submitting.value = true

    try {
        const { data } = await axios.post(`/api/shops/${encodeURIComponent(slug.value)}/appointments`, {
            service_id: selectedServiceId.value,
            professional_id: selectedProfessionalId.value,
            customer_name: name.value,
            customer_phone: phone.value,
            scheduled_at: `${selectedDate.value} ${selectedTime.value}:00`,
            notes: notes.value
        })
        
        router.push({
            name: 'success',
            params: { slug: slug.value },
            query: {
                type: 'appointment',
                name: name.value,
                date: selectedDate.value,
                time: selectedTime.value,
                token: data.appointment?.cancel_token || undefined
            }
        })
    } catch (err) {
        LuxurySwal.fire({
            icon: 'error',
            title: 'Falha ao agendar',
            text: err.normalized?.message || err.response?.data?.message || err.message
        })
    } finally {
        submitting.value = false
    }
}

const selectedService = computed(() =>
    services.value.find((service) => service.id === selectedServiceId.value) || null
)

const selectedProfessional = computed(() =>
    professionals.value.find((professional) => professional.id === selectedProfessionalId.value) || null
)
</script>

<template>
<section class="view active">
    <nav class="luxury-nav">
                <button @click="goBack" class="btn-back-luxury"><i class="fas fa-long-arrow-alt-left"></i></button>
                <div class="nav-title">MEMBROS <span>Agendamento</span></div>
            </nav>

            <form @submit.prevent="confirmBooking" class="luxury-form">
                <div class="scroll-wrapper">

                    <div class="luxury-field-group">
                        <h3 class="group-title">DADOS PESSOAIS</h3>
                        <div class="input-luxury-wrapper">
                            <i class="far fa-user"></i>
                            <input v-model="name" type="text" required placeholder="Seu nome completo">
                        </div>
                        <div class="input-luxury-wrapper">
                            <i class="fab fa-whatsapp"></i>
                            <input v-model="phone" type="tel" required placeholder="WhatsApp (00) 00000-0000" @input="applyPhoneMask">
                        </div>
                    </div>

                    <div class="luxury-field-group">
                        <h3 class="group-title">ESPECIALISTA</h3>
                        <div class="luxury-service-grid" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));">
                            <label v-for="b in professionals" :key="b.id" class="service-card">
                                <input type="radio" :value="b.id" v-model="selectedProfessionalId">
                                <div class="card-content" style="padding: 20px;">
                                    <strong style="font-size: 0.9rem;">{{ b.name.toUpperCase() }}</strong>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="luxury-field-group">
                        <h3 class="group-title">SERVIÇOS EXCLUSIVOS</h3>
                        <div class="luxury-service-grid">
                            <label v-for="s in services" :key="s.id" class="service-card">
                                <input type="radio" :value="s.id" v-model="selectedServiceId">
                                <div class="card-content">
                                    <strong>{{ s.name.toUpperCase() }}</strong>
                                    <p v-if="s.description" class="service-desc">{{ s.description }}</p>
                                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-top: 8px; font-size: 0.75rem; color: #888;">
                                        <span><i class="far fa-clock" style="margin-right: 4px;"></i>{{ s.duration_minutes }} MIN</span>
                                        <span style="color: #D4AF37; font-weight: 600;">R$ {{ Math.floor(s.price) }}</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="luxury-field-group">
                        <h3 class="group-title">DISPONIBILIDADE</h3>
                        
                        <div class="calendar-luxury-booking">
                            <div class="calendar-header-lux">
                                <button @click="prevMonth" type="button" class="cal-nav-btn"><i class="fas fa-chevron-left"></i></button>
                                <span class="cal-month-title">{{ currentCalendarMonth.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }).toUpperCase() }}</span>
                                <button @click="nextMonth" type="button" class="cal-nav-btn"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            <div class="calendar-grid-lux">
                                <div v-for="d in ['D','S','T','Q','Q','S','S']" :key="d" class="cal-day-header">{{ d }}</div>
                                <div v-for="(date, idx) in calendarDays" :key="idx" 
                                    class="cal-day-cell" 
                                    :class="{ 
                                        'padding': !date, 
                                        'disabled': isDateDisabled(date), 
                                        'selected': selectedDate === formatDate(date) 
                                    }"
                                    @click="selectDate(date)"
                                >
                                    {{ date ? date.getDate() : '' }}
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedDate && !isDateAvailable" class="unavailable-notice-luxury" style="margin-top: 20px;">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Data Indisponível</p>
                            <span>O local não abrirá nesta data.</span>
                        </div>
                        
                        <div v-else-if="selectedDate" style="margin-top: 20px;">
                            <div v-if="!selectedProfessionalId" class="select-date-prompt-luxury" style="text-align: center; padding: 20px; border: 1px dashed #D4AF37; color: #D4AF37; border-radius: 8px;">
                                <i class="fas fa-user-tie" style="margin-bottom: 10px;"></i>
                                <p style="font-size: 0.9rem;">Por favor, selecione um <strong>Especialista</strong> acima para ver os horários.</p>
                            </div>
                            <div v-else-if="loadingSlots" class="loading-slots-luxury" style="text-align: center; padding: 20px;">
                                <i class="fas fa-circle-notch fa-spin" style="color: #D4AF37; font-size: 1.5rem;"></i>
                                <p style="font-size: 0.8rem; color: #888; margin-top: 10px;">Buscando horários disponíveis...</p>
                            </div>
                            <div v-else-if="availableSlots.length > 0" class="luxury-time-grid">
                                <label v-for="slot in availableSlots" :key="slot" class="time-slot" :class="{ selected: selectedTime === slot }">
                                    <input type="radio" :value="slot" v-model="selectedTime" style="display:none">
                                    {{ slot }}
                                </label>
                            </div>
                            <div v-else class="select-date-prompt-luxury" style="text-align: center; padding: 20px; border: 1px dashed #666; color: #888; border-radius: 8px;">
                                <i class="fas fa-clock" style="margin-bottom: 10px;"></i>
                                <p style="font-size: 0.9rem;">Nenhum horário disponível para esta data com este profissional.</p>
                            </div>
                        </div>

                        <div v-else class="select-date-prompt-luxury" style="margin-top: 20px; text-align: center; padding: 30px; border: 1px dashed #333; color: #666; border-radius: 8px;">
                            <i class="far fa-calendar-alt" style="margin-bottom: 10px; font-size: 1.2rem;"></i>
                            <p style="font-size: 0.9rem;">Selecione uma data no calendário para ver os horários disponíveis.</p>
                        </div>
                    </div>

                    <div class="luxury-field-group">
                        <h3 class="group-title">NOTAS ADICIONAIS</h3>
                        <div class="input-luxury-wrapper" style="border: 1px solid #333; padding: 10px; border-radius: 4px;">
                            <textarea v-model="notes" placeholder="Alguma preferência especial?" style="background: transparent; border: none; color: #fff; width: 100%; min-height: 80px; resize: none; font-family: inherit; font-size: 1rem; outline: none;"></textarea>
                        </div>
                    </div>

                </div>

                <div class="luxury-sticky-footer">
                    <button type="submit" class="btn-confirm-luxury" :disabled="submitting">
                        <span v-if="submitting">RESERVANDO...</span>
                        <span v-else>
                            RESERVAR HORÁRIO <i class="fas fa-arrow-right"></i>
                        </span>
                    </button>
                </div>
            </form>
        </section>
</template>
