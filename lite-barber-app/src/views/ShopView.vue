<script setup>
import { ref, onMounted } from 'vue'
import axios from '../services/api'
import { useRouter } from 'vue-router'

const router = useRouter()
const shop = ref(null)
const loading = ref(true)

onMounted(async () => {
    try {
        const response = await axios.get('/api/shops/elite-agenda');
        shop.value = response.data;
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
    } catch(err) {
        console.error(err);
    } finally {
        loading.value = false;
    }
})

const goBooking = () => router.push('/booking')
const goQueue = () => router.push('/queue')
const goAdmin = () => router.push('/login')
</script>

<template>
<section class="view active" v-if="!loading && shop">
    <header class="home-hero">
                <div class="hero-logo-container">
                    <img v-if="shop.logo_url" :src="shop.logo_url" alt="Logo" class="main-logo" style="border-radius: 10px; object-fit: cover;">
                    <img v-else :src="'https://ui-avatars.com/api/?name=' + shop.name.split(' ').join('+') + '&background=0D0D0D&color=D4AF37&size=120'" alt="Logo" class="main-logo" style="border-radius: 10px;">
                </div>
                <div class="hero-text">
                    <span class="eyebrow">DESDE 2024</span>
                    <!-- Integramos o nome do shop oriundo da API do Laravel! -->
                    <h1>{{ shop.name }}</h1>
                    <p>Redefinindo o conceito de atendimento com um toque de modernidade e precisão.</p>
                </div>
            </header>
            
            <div class="hero-stats-luxury">
                <div class="l-stat">
                    <span class="val">4.9</span>
                    <span class="lab">Avaliação</span>
                </div>
                <div class="l-stat">
                    <span class="val">10y</span>
                    <span class="lab">Experiência</span>
                </div>
                <div class="l-stat">
                    <span class="val">2k+</span>
                    <span class="lab">Clientes</span>
                </div>
            </div>

            <div class="home-actions-luxury">
                <button @click="goBooking" class="btn-primary-luxury">
                    <span>AGENDAR AGORA</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button v-if="false" @click="goQueue" class="btn-outline-luxury">
                    <span>FILA DIGITAL</span>
                </button>
                <button @click="goAdmin" class="btn-outline-luxury" style="display:flex; justify-content:center; align-items:center; gap:10px;">
                    <i class="fas fa-fingerprint"></i>
                    <span>LOGIN</span>
                </button>
            </div>
        </section>
</template>
