<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Toast } from '../utils/swal'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const form = ref({
  shop_name: '',
  slug: '',
  admin_name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const loading = ref(false)

// Function to auto-generate slug based on shop name
const updateSlug = () => {
  if (!form.value.shop_name) {
    form.value.slug = ''
    return
  }
  form.value.slug = form.value.shop_name
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

const register = async () => {
  if (!form.value.shop_name || !form.value.slug || !form.value.admin_name || !form.value.email || !form.value.password || !form.value.password_confirmation) {
    Toast.fire({ icon: 'warning', title: 'Preencha todos os campos.' })
    return
  }

  if (form.value.password !== form.value.password_confirmation) {
    Toast.fire({ icon: 'error', title: 'As senhas não coincidem.' })
    return
  }

  loading.value = true
  try {
    await auth.register(form.value)

    Toast.fire({ icon: 'success', title: 'Estabelecimento criado com sucesso!' })
    await router.replace('/admin')
  } catch (error) {
    console.error(error)
    const msg = error.normalized?.message || 'Erro ao registrar estabelecimento.'
    Toast.fire({ icon: 'error', title: msg })
  } finally {
    loading.value = false
  }
}

const goLogin = () => {
  router.push('/login')
}
</script>

<template>
  <div class="view active">
    <!-- Hero / Logo -->
    <div class="hero-logo-container" style="text-align: center;">
      <h1 style="font-size: 2rem;">LITE<span>AGENDA</span></h1>
      <p style="color: var(--text-dim); font-size: 0.9rem; letter-spacing: 2px;">CRIE SUA CONTA</p>
    </div>

    <!-- Register Form -->
    <div class="login-container">
      <form @submit.prevent="register">
        <div class="luxury-field-group" style="--delay: 0.1s; margin-bottom: 30px;">
          <h4 class="group-title">DADOS DO ESTABELECIMENTO</h4>
          
          <div class="input-luxury-wrapper">
            <i class="fas fa-store"></i>
            <input 
              v-model="form.shop_name" 
              @input="updateSlug"
              type="text" 
              placeholder="Nome do Estabelecimento" 
              required
            >
          </div>

          <div class="input-luxury-wrapper">
            <i class="fas fa-link"></i>
            <input 
              v-model="form.slug" 
              type="text" 
              placeholder="Link personalizado" 
              required
            >
          </div>
          <small style="color: #666; font-size: 0.75rem; display: block; margin-top: -15px; margin-bottom: 20px;">
            Seu link será: liteagenda.com/<strong>{{ form.slug || 'seu-estabelecimento' }}</strong>
          </small>
        </div>

        <div class="luxury-field-group" style="--delay: 0.2s;">
          <h4 class="group-title">DADOS DO ADMINISTRADOR</h4>
          
          <div class="input-luxury-wrapper">
            <i class="far fa-user"></i>
            <input 
              v-model="form.admin_name" 
              type="text" 
              placeholder="Seu Nome Completo" 
              required
            >
          </div>

          <div class="input-luxury-wrapper">
            <i class="far fa-envelope"></i>
            <input 
              v-model="form.email" 
              type="email" 
              placeholder="E-mail de Acesso" 
              required
            >
          </div>

          <div class="input-luxury-wrapper">
            <i class="fas fa-key"></i>
            <input 
              v-model="form.password" 
              type="password" 
              placeholder="Senha de Acesso" 
              required
            >
          </div>

          <div class="input-luxury-wrapper" style="margin-top: 15px;">
            <i class="fas fa-check-double"></i>
            <input 
              v-model="form.password_confirmation" 
              type="password" 
              placeholder="Confirmar Senha" 
              required
            >
          </div>
        </div>

        <div class="luxury-field-group" style="--delay: 0.3s;">
          <button type="submit" class="btn-confirm-luxury" :disabled="loading">
            <span v-if="loading">CRIANDO...</span>
            <span v-else>CRIAR ESTABELECIMENTO <i class="fas fa-arrow-right"></i></span>
          </button>
        </div>
      </form>

      <div style="text-align: center; margin-top: 30px; animation: fadeInUp 0.8s 0.4s both;">
        <p style="color: var(--text-dim); font-size: 0.85rem; margin-bottom: 10px;">Já possui uma conta?</p>
        <button @click="goLogin" class="btn-outline-luxury" style="width: 100%;">FAZER LOGIN</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.btn-confirm-luxury:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
