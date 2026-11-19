<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from '../services/api'
import { Toast } from '../utils/swal'
import { usePublicShop } from '../composables/usePublicShop'

const router = useRouter()
const { slug, loadShop } = usePublicShop()

const goBack = () => router.push({ name: 'shop', params: { slug: slug.value } })

const queueCount = ref(0)
const names = ref([])
const name = ref('')
const phone = ref('')

const applyPhoneMask = (e) => {
    let v = e.target.value.replace(/\D/g, '').substring(0, 11)
    let formatted = ''
    if (v.length > 0) formatted += '(' + v.substring(0, 2)
    if (v.length > 2) formatted += ') ' + v.substring(2, 7)
    if (v.length > 7) formatted += '-' + v.substring(7, 11)
    phone.value = formatted
}

const fetchWaitlistStatus = async () => {
    try {
        const { data } = await axios.get(`/api/shops/${encodeURIComponent(slug.value)}/waitlist`)
        queueCount.value = data.waiting_count
        names.value = data.names

        if (data.theme_color) {
            document.documentElement.style.setProperty('--gold', data.theme_color)
            document.documentElement.style.setProperty('--accent', data.theme_color)
        }
    } catch(err) {
        console.error(err)
    }
}

const joinQueue = async () => {
    try {
        const { data } = await axios.post(`/api/shops/${encodeURIComponent(slug.value)}/waitlist`, {
            customer_name: name.value,
            customer_phone: phone.value
        })
        
        router.push({
            name: 'success',
            params: { slug: slug.value },
            query: {
                type: 'waitlist',
                name: name.value,
                pos: data.position
            }
        })
    } catch(err) {
        Toast.fire({
            icon: 'error',
            title: 'Erro ao entrar na fila'
        })
    }
}

let queueRefreshTimer = null

onMounted(async () => {
    await loadShop()
    fetchWaitlistStatus()
    // Atualiza status localmente a cada 10 segundos
    queueRefreshTimer = setInterval(fetchWaitlistStatus, 10000)
})

onUnmounted(() => {
    if (queueRefreshTimer) {
        clearInterval(queueRefreshTimer)
        queueRefreshTimer = null
    }
})
</script>

<template>
<section class="view active">
            <nav class="luxury-nav">
                <button @click="goBack" class="btn-back-luxury"><i class="fas fa-long-arrow-alt-left"></i></button>
                <div class="nav-title">FILA <span>Digital</span></div>
            </nav>

            <div id="queue-status" class="luxury-queue-card">
                <div class="luxury-pulse-ring">
                    <div class="ring-core">
                        <span>{{ queueCount }}</span>
                        <small>NA FILA</small>
                    </div>
                </div>
            </div>

            <div class="luxury-form" style="margin-top:20px;">
                <div class="info-banner" style="margin-bottom:20px;">
                    <i class="fas fa-bolt"></i>
                    <span>A fila digital é para quem quer cortar agora, por ordem de chegada.</span>
                </div>
                <form @submit.prevent="joinQueue">
                    <div class="input-luxury-wrapper">
                        <input v-model="name" type="text" required placeholder="Seu nome completo">
                    </div>
                    <div class="input-luxury-wrapper">
                        <input v-model="phone" type="tel" required placeholder="Seu WhatsApp (00) 00000-0000" @input="applyPhoneMask">
                    </div>
                    <button type="submit" class="btn-primary-luxury">SOLICITAR LUGAR</button>
                </form>
            </div>

            <div v-if="names.length > 0" class="waiting-names-list" style="margin-top: 30px; border: 1px solid #1a1a1a; padding: 15px; border-radius: 4px; background: rgba(0,0,0,0.2);">
                <small style="color: #555; display: block; margin-bottom: 10px; letter-spacing: 1px; font-weight: bold;">AGUARDANDO ATENDIMENTO:</small>
                <div v-for="(n, index) in names" :key="index" style="color: #ccc; font-size: 0.9rem; padding: 5px 0; border-bottom: 1px solid #111;">
                    {{ index + 1 }}. {{ n }}
                </div>
            </div>
</section>
</template>
