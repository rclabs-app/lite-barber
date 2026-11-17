<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { Toast } from '../utils/swal'

const router = useRouter()
const email = ref('admin@eliteagenda.com')
const password = ref('')
const showPassword = ref(false)

const goBack = () => router.push('/')

const handleLogin = async () => {
    try {
        const payload = { email: email.value, password: password.value }
        const { data } = await axios.post('/api/auth/login', payload)
        localStorage.setItem('sanctum_token', data.token)
        axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
        router.push('/admin')
    } catch(err) {
        Toast.fire({ icon: 'error', title: 'Credenciais Inválidas' })
    }
}
</script>

<template>
<section class="view active">
    <nav class="luxury-nav">
        <button @click="goBack" class="btn-back-luxury"><i class="fas fa-long-arrow-alt-left"></i></button>
        <div class="nav-title">SEGURANÇA <span>Identificação</span></div>
    </nav>

    <div class="login-container">
        <p class="login-hint">Identifique-se para acessar o painel de controle.</p>

        <form @submit.prevent="handleLogin" class="login-form">
            <!-- Campo E-mail -->
            <div class="login-field">
                <label class="login-label">E-MAIL</label>
                <div class="login-input-box">
                    <span class="login-input-icon">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input
                        v-model="email"
                        type="email"
                        class="login-input"
                        placeholder="seu@email.com"
                        autocomplete="email"
                        required
                    >
                </div>
            </div>

            <!-- Campo Senha -->
            <div class="login-field">
                <label class="login-label">SENHA</label>
                <div class="login-input-box">
                    <span class="login-input-icon">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input
                        v-model="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="login-input"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        type="button"
                        class="login-eye-btn"
                        @click="showPassword = !showPassword"
                        :title="showPassword ? 'Ocultar senha' : 'Exibir senha'"
                    >
                        <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="login-submit-btn">
                <span>ENTRAR</span>
                <i class="fas fa-chevron-right"></i>
            </button>
        </form>
    </div>
</section>
</template>

<style scoped>
.login-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.login-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.login-label {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 2px;
    color: #555;
}

.login-input-box {
    display: flex;
    align-items: center;
    background: #0d0d0d;
    border: 1px solid #2a2a2a;
    border-radius: 6px;
    transition: border-color 0.2s ease;
    overflow: hidden;
}

.login-input-box:focus-within {
    border-color: var(--accent);
}

.login-input-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    min-width: 46px;
    color: #444;
    font-size: 0.85rem;
    border-right: 1px solid #2a2a2a;
    padding: 14px 0;
    transition: color 0.2s;
}

.login-input-box:focus-within .login-input-icon {
    color: var(--accent);
    border-right-color: var(--accent);
}

.login-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: #fff;
    font-family: inherit;
    font-size: 0.9rem;
    padding: 14px 16px;
    width: 100%;
}

.login-input::placeholder {
    color: #3a3a3a;
}

/* Neutraliza autofill do browser */
.login-input:-webkit-autofill,
.login-input:-webkit-autofill:hover,
.login-input:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0px 1000px #0d0d0d inset;
    -webkit-text-fill-color: #fff;
    transition: background-color 5000s ease-in-out 0s;
}

.login-eye-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    min-width: 46px;
    background: none;
    border: none;
    border-left: 1px solid #2a2a2a;
    color: #444;
    cursor: pointer;
    font-size: 0.85rem;
    padding: 14px 0;
    transition: color 0.2s;
}

.login-eye-btn:hover {
    color: var(--accent);
}

.login-submit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    background: var(--accent);
    color: #000;
    border: none;
    border-radius: 6px;
    padding: 16px;
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 2px;
    cursor: pointer;
    transition: filter 0.2s, transform 0.2s;
    margin-top: 8px;
}

.login-submit-btn:hover {
    filter: brightness(0.9);
    transform: translateY(-1px);
}
</style>
