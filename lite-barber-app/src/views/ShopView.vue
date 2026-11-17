<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePublicShop } from '../composables/usePublicShop'

const router = useRouter()
const { shop, loading, slug, loadShop } = usePublicShop()

onMounted(loadShop)

const goBooking = () => router.push({ name: 'booking', params: { slug: slug.value } })
const goQueue = () => router.push({ name: 'queue', params: { slug: slug.value } })
const goAdmin = () => router.push({ name: 'login' })
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
