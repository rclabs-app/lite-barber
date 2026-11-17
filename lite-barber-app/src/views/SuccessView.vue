<script setup>
import { useRoute, useRouter } from 'vue-router'
import { computed } from 'vue'

const route = useRoute()
const router = useRouter()

const type = computed(() => route.query.type) // 'appointment' ou 'waitlist'
const name = computed(() => route.query.name)
const time = computed(() => route.query.time)
const professional = computed(() => route.query.professional)
const rawDate = computed(() => route.query.date)

const formattedDate = computed(() => {
    if (!rawDate.value) return ''
    const parts = rawDate.value.split('-')
    return `${parts[2]}/${parts[1]}/${parts[0]}`
})

const goHome = () => router.push({ name: 'shop', params: { slug: route.params.slug } })
</script>

<template>
<section class="view active" style="text-align: center; padding-top: 60px;">
            <div class="success-icon-wrapper">
                <div class="success-ring"></div>
                <i class="fas fa-check"></i>
            </div>

            <h1 class="success-title">SOLICITAÇÃO<span>Confirmada!</span></h1>
            
            <div class="success-card">
                <p class="welcome-msg">Olá, <strong>{{ name }}</strong>!</p>
                
                <div v-if="type === 'appointment'" class="details-box">
                    <p>Seu horário foi agendado com sucesso:</p>
                    <div class="booking-summary">
                        <div class="summary-item">
                            <i class="far fa-calendar-alt"></i>
                            <span>{{ formattedDate }}, {{ time }}</span>
                        </div>
                    </div>
                </div>

                <div v-else class="details-box">
                    <p>Você entrou na fila digital!</p>
                    <div class="queue-ticket">
                        <small>SUA POSIÇÃO ATUAL</small>
                        <div class="ticket-number">#{{ route.query.pos || '?' }}</div>
                    </div>
                    <p class="instruction">Fique atento ao painel do estabelecimento, chamaremos seu nome em breve.</p>
                </div>
            </div>

            <button @click="goHome" class="btn-primary-luxury" style="margin-top: 40px; max-width: 250px; margin-left: auto; margin-right: auto;">VOLTAR AO INÍCIO</button>
</section>
</template>

<style scoped>
.success-icon-wrapper {
    position: relative;
    width: 100px;
    height: 100px;
    margin: 0 auto 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.success-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border: 3px solid var(--primary);
    border-radius: 50%;
    animation: pulse-gold 2s infinite;
}
.success-icon-wrapper i {
    font-size: 3rem;
    color: var(--gold);
}
.success-title {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    letter-spacing: 2px;
    margin-bottom: 30px;
}
.success-title span {
    display: block;
    font-size: 1.2rem;
    color: var(--gold);
    font-family: 'Outfit', sans-serif;
}
.success-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.05);
    padding: 30px;
    border-radius: 15px;
    max-width: 350px;
    margin: 0 auto;
}
.welcome-msg {
    font-size: 1.1rem;
    margin-bottom: 20px;
}
.details-box p {
    color: #888;
    margin-bottom: 20px;
}
.booking-summary {
    display: flex;
    flex-direction: column;
    gap: 15px;
    align-items: flex-start;
}
.summary-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
}
.summary-item i {
    color: var(--gold);
    width: 20px;
}
.queue-ticket {
    background: var(--primary);
    color: #000;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.ticket-number {
    font-size: 2.5rem;
    font-weight: 900;
}
.instruction {
    font-size: 0.8rem !important;
}

@keyframes pulse-gold {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.5; }
    100% { transform: scale(1); opacity: 1; }
}
</style>
