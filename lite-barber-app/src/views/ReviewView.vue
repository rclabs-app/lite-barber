<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import Swal from 'sweetalert2'

const route = useRoute()
const router = useRouter()

const token = computed(() => route.params.token)
const slug = computed(() => route.params.slug)

const loading = ref(true)
const submitting = ref(false)
const appointment = ref(null)
const alreadyReviewed = ref(false)
const existingReview = ref(null)
const successSubmitted = ref(false)

const rating = ref(5)
const hoverRating = ref(0)
const comment = ref('')
const errorMessage = ref('')

const fetchAppointment = async () => {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get(`/api/reviews/${token.value}`)
        appointment.value = data.appointment
        alreadyReviewed.value = data.already_reviewed
        if (data.already_reviewed && data.appointment?.review) {
            existingReview.value = data.appointment.review
        }
        if (appointment.value?.shop?.theme_color) {
            document.documentElement.style.setProperty('--gold', appointment.value.shop.theme_color)
            document.documentElement.style.setProperty('--accent', appointment.value.shop.theme_color)
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Não foi possível carregar os dados deste atendimento.'
    } finally {
        loading.value = false
    }
}

const submitReview = async () => {
    if (rating.value < 1 || rating.value > 5) {
        Swal.fire({ icon: 'warning', title: 'Nota necessária', text: 'Selecione uma nota de 1 a 5 estrelas.' })
        return
    }

    submitting.value = true
    try {
        await axios.post(`/api/reviews/${token.value}`, {
            rating: rating.value,
            comment: comment.value
        })
        successSubmitted.value = true
        Swal.fire({
            icon: 'success',
            title: 'Obrigado pelo seu feedback!',
            text: 'Sua avaliação ajuda a mantermos a excelência do nosso atendimento.',
            timer: 3000,
            showConfirmButton: false
        })
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Erro ao enviar',
            text: err.response?.data?.message || 'Não foi possível registrar sua avaliação.'
        })
    } finally {
        submitting.value = false
    }
}

const goShop = () => router.push({ name: 'shop', params: { slug: slug.value } })

onMounted(() => {
    fetchAppointment()
})
</script>

<template>
<div class="review-page">
    <div v-if="loading" class="state-card">
        <i class="fas fa-circle-notch fa-spin"></i>
        <p>Carregando dados da sua experiência...</p>
    </div>

    <div v-else-if="errorMessage" class="state-card error">
        <i class="fas fa-exclamation-triangle"></i>
        <h2>Aviso</h2>
        <p>{{ errorMessage }}</p>
        <button @click="goShop" class="btn-primary-luxury">VOLTAR À PÁGINA INICIAL</button>
    </div>

    <div v-else-if="alreadyReviewed || successSubmitted" class="review-card success-card">
        <div class="luxury-badge"><i class="fas fa-award"></i></div>
        <h1>MUITO OBRIGADO!</h1>
        <p class="subtitle">Sua avaliação para <strong>{{ appointment?.shop?.name }}</strong> foi registrada com sucesso.</p>

        <div class="rating-display">
            <span v-for="star in 5" :key="star" class="star" :class="{ filled: star <= (existingReview?.rating || rating) }">★</span>
        </div>

        <p v-if="existingReview?.comment" class="review-comment-echo">
            "{{ existingReview.comment }}"
        </p>

        <button @click="goShop" class="btn-primary-luxury" style="margin-top: 30px;">
            IR PARA A BARBEARIA
        </button>
    </div>

    <div v-else class="review-card">
        <div class="shop-badge">
            <span class="shop-name">{{ appointment?.shop?.name || 'Lite Barber' }}</span>
        </div>

        <h1>AVALIE SUA EXPERIÊNCIA</h1>
        <p class="subtitle">Olá, <strong>{{ appointment?.client_name }}</strong>! Conte-nos como foi seu atendimento com <strong>{{ appointment?.professional?.name }}</strong>.</p>

        <div class="service-pill">
            <i class="fas fa-cut"></i>
            <span>{{ appointment?.service?.name }}</span>
        </div>

        <div class="stars-selector">
            <span
                v-for="star in 5"
                :key="star"
                class="star-btn"
                :class="{ active: star <= (hoverRating || rating) }"
                @mouseenter="hoverRating = star"
                @mouseleave="hoverRating = 0"
                @click="rating = star"
            >
                ★
            </span>
        </div>
        <div class="rating-label">
            <span v-if="(hoverRating || rating) === 5">⭐️ Excelente, impecável!</span>
            <span v-else-if="(hoverRating || rating) === 4">⭐️ Muito bom!</span>
            <span v-else-if="(hoverRating || rating) === 3">⭐️ Bom, na média.</span>
            <span v-else-if="(hoverRating || rating) === 2">⭐️ Regular, pode melhorar.</span>
            <span v-else>⭐️ Não gostei.</span>
        </div>

        <div class="input-group">
            <label for="review-comment">DEIXE UM COMENTÁRIO (OPCIONAL)</label>
            <textarea
                id="review-comment"
                v-model="comment"
                rows="4"
                placeholder="Conte o que mais gostou no corte, pontualidade, ambiente..."
                maxlength="1000"
            ></textarea>
        </div>

        <button @click="submitReview" :disabled="submitting" class="btn-primary-luxury" style="width: 100%; margin-top: 15px;">
            <i v-if="submitting" class="fas fa-circle-notch fa-spin"></i>
            <span v-else>ENVIAR AVALIAÇÃO</span>
        </button>

        <button @click="goShop" class="btn-outline-luxury" style="width: 100%; margin-top: 10px;">
            PULAR / VOLTAR
        </button>
    </div>
</div>
</template>

<style scoped>
.review-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 20px;
    background: radial-gradient(circle at top, rgba(212, 175, 55, 0.08) 0%, rgba(10, 10, 10, 0.98) 100%), #0a0a0a;
}

.review-card, .state-card {
    background: rgba(18, 18, 18, 0.95);
    border: 1px solid rgba(212, 175, 55, 0.25);
    border-radius: 16px;
    padding: 40px 30px;
    max-width: 500px;
    width: 100%;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
}

.shop-badge {
    display: inline-block;
    padding: 6px 16px;
    background: rgba(212, 175, 55, 0.12);
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 20px;
    margin-bottom: 20px;
}

.shop-name {
    color: var(--gold, #d4af37);
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
}

h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    color: #fff;
    margin-bottom: 10px;
    letter-spacing: 1px;
}

.subtitle {
    color: #a0a0a0;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 20px;
}

.service-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 25px;
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    font-size: 0.9rem;
    margin-bottom: 25px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.service-pill i {
    color: var(--gold, #d4af37);
}

.stars-selector {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 10px;
}

.star-btn {
    font-size: 2.8rem;
    cursor: pointer;
    color: #3a3a3a;
    transition: transform 0.2s ease, color 0.2s ease;
    user-select: none;
}

.star-btn.active {
    color: #f5b301;
    transform: scale(1.15);
    text-shadow: 0 0 12px rgba(245, 179, 1, 0.5);
}

.rating-label {
    min-height: 24px;
    color: var(--gold, #d4af37);
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 25px;
}

.input-group {
    text-align: left;
    margin-bottom: 20px;
}

.input-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 1px;
    color: #888;
    margin-bottom: 8px;
}

.input-group textarea {
    width: 100%;
    background: #111;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    padding: 12px 14px;
    color: #fff;
    font-family: inherit;
    font-size: 0.95rem;
    resize: vertical;
    transition: border-color 0.3s;
}

.input-group textarea:focus {
    outline: none;
    border-color: var(--gold, #d4af37);
}

.luxury-badge {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: rgba(212, 175, 55, 0.15);
    border: 2px solid var(--gold, #d4af37);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: var(--gold, #d4af37);
}

.rating-display .star {
    font-size: 2.2rem;
    color: #333;
}

.rating-display .star.filled {
    color: #f5b301;
}

.review-comment-echo {
    font-style: italic;
    color: #ccc;
    margin-top: 15px;
    background: rgba(255, 255, 255, 0.03);
    padding: 15px;
    border-radius: 8px;
}

.state-card i {
    font-size: 2.5rem;
    color: var(--gold, #d4af37);
    margin-bottom: 15px;
}
</style>
