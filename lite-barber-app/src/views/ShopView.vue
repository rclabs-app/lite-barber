<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePublicShop } from '../composables/usePublicShop'
import { t } from '../utils/i18n'

const router = useRouter()
const { shop, loading, slug, loadShop } = usePublicShop()

onMounted(() => {
  if (slug.value) {
    loadShop()
  }
})

const goBooking = () => router.push({ name: 'booking', params: { slug: slug.value } })
const goQueue = () => router.push({ name: 'queue', params: { slug: slug.value } })
const goAdmin = () => router.push({ name: 'login' })
const goRegister = () => router.push({ name: 'register' })

const whatsappUrl = computed(() => {
  const raw = shop.value?.whatsapp_phone || shop.value?.phone
  if (!raw) return null
  const digits = String(raw).replace(/\D/g, '')
  if (!digits) return null
  return digits.startsWith('55') ? `https://wa.me/${digits}` : `https://wa.me/55${digits}`
})

const instagramUrl = computed(() => {
  const raw = shop.value?.instagram_url?.trim()
  if (!raw) return null
  if (raw.startsWith('http://') || raw.startsWith('https://')) return raw
  const handle = raw.replace(/^@/, '')
  return `https://instagram.com/${handle}`
})

const mapsUrl = computed(() => {
  const raw = shop.value?.maps_url?.trim()
  if (raw) return raw
  if (shop.value?.address) {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(shop.value.address)}`
  }
  return null
})
</script>

<template>
<section class="view active" v-if="!slug">
    <header class="home-hero">
        <div class="hero-text">
            <span class="eyebrow">RCLABS · SAAS PARA BARBEARIAS &amp; ESTÚDIOS</span>
            <h1>LITE BARBER</h1>
            <p>Agendamento online, fila de espera digital e gestão financeira em uma experiência minimalista de alto padrão.</p>
        </div>
    </header>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; width:100%; max-width:560px; margin:20px auto;">
        <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px; text-align:left;">
            <span style="font-size:0.7rem; color:#888; letter-spacing:2px;">PLANO LITE</span>
            <h3 style="font-size:1.5rem; margin:6px 0; color:#fff;">R$ 19,90<small style="font-size:0.75rem; color:#888;">/mês</small></h3>
            <p style="font-size:0.8rem; color:#aaa; line-height:1.5;">Até 2 profissionais · Até 5 serviços · Link personalizado · Google Maps &amp; Redes Sociais</p>
        </div>
        <div style="background:rgba(212,175,55,0.06); border:1px solid var(--accent); border-radius:12px; padding:20px; text-align:left;">
            <span style="font-size:0.7rem; color:var(--accent); letter-spacing:2px; font-weight:700;">PLANO PRO</span>
            <h3 style="font-size:1.5rem; margin:6px 0; color:#fff;">R$ 49,90<small style="font-size:0.75rem; color:#888;">/mês</small></h3>
            <p style="font-size:0.8rem; color:#aaa; line-height:1.5;">Até 8 profissionais · Até 20 serviços · Fila Digital · Temas Exclusivos · Relatórios Financeiros</p>
        </div>
    </div>

    <div class="home-actions-luxury">
        <button @click="goRegister" class="btn-primary-luxury">
            <span>CRIAR CONTA DA BARBEARIA</span>
            <i class="fas fa-chevron-right"></i>
        </button>
        <button @click="goAdmin" class="btn-outline-luxury" style="display:flex; justify-content:center; align-items:center; gap:10px;">
            <i class="fas fa-fingerprint"></i>
            <span>ENTRAR NO PAINEL</span>
        </button>
    </div>
</section>

<section class="view active" v-else-if="!loading && shop">
    <header class="home-hero">
                <div class="hero-logo-container">
                    <img v-if="shop.logo_url" :src="shop.logo_url" alt="Logo" class="main-logo" style="border-radius: 10px; object-fit: cover;">
                    <img v-else :src="'https://ui-avatars.com/api/?name=' + shop.name.split(' ').join('+') + '&background=0D0D0D&color=D4AF37&size=120'" alt="Logo" class="main-logo" style="border-radius: 10px;">
                </div>
                <div class="hero-text">
                    <span class="eyebrow">{{ t(shop.locale, 'since') }}</span>
                    <h1>{{ shop.name }}</h1>
                    <p>{{ shop.description || 'Redefinindo o conceito de atendimento com um toque de modernidade e precisão.' }}</p>
                    <p v-if="shop.address" style="font-size: 0.82rem; color: #aaa; margin-top: 8px;">
                        <i class="fas fa-map-marker-alt" style="color: var(--accent); margin-right: 6px;"></i>{{ shop.address }}
                    </p>
                    <div v-if="whatsappUrl || instagramUrl || mapsUrl" style="display: flex; justify-content: center; gap: 16px; margin-top: 14px;">
                        <a v-if="whatsappUrl" :href="whatsappUrl" target="_blank" rel="noopener" title="WhatsApp" style="color: var(--accent); font-size: 1.25rem;">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a v-if="instagramUrl" :href="instagramUrl" target="_blank" rel="noopener" title="Instagram" style="color: var(--accent); font-size: 1.25rem;">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a v-if="mapsUrl" :href="mapsUrl" target="_blank" rel="noopener" title="Como chegar (Google Maps)" style="color: var(--accent); font-size: 1.25rem;">
                            <i class="fas fa-map-marked-alt"></i>
                        </a>
                    </div>
                </div>
            </header>
            
            <div class="hero-stats-luxury">
                <div class="l-stat">
                    <span class="val">4.9</span>
                    <span class="lab">{{ t(shop.locale, 'rating') }}</span>
                </div>
                <div class="l-stat">
                    <span class="val">10y</span>
                    <span class="lab">{{ t(shop.locale, 'experience') }}</span>
                </div>
                <div class="l-stat">
                    <span class="val">2k+</span>
                    <span class="lab">{{ t(shop.locale, 'clients') }}</span>
                </div>
            </div>

            <div class="home-actions-luxury">
                <button @click="goBooking" class="btn-primary-luxury">
                    <span>{{ t(shop.locale, 'bookNow') }}</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button @click="goQueue" class="btn-outline-luxury">
                    <span>{{ t(shop.locale, 'digitalQueue') }}</span>
                </button>
                <button @click="goAdmin" class="btn-outline-luxury" style="display:flex; justify-content:center; align-items:center; gap:10px;">
                    <i class="fas fa-fingerprint"></i>
                    <span>{{ t(shop.locale, 'login') }}</span>
                </button>
            </div>
        </section>
</template>
