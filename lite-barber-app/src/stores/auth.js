import { defineStore } from 'pinia'
import api from '../services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('sanctum_token') || '',
    user: null,
    shop: null,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token),
  },

  actions: {
    setSession(token, user, shop = null) {
      this.token = token
      this.user = user
      this.shop = shop
      localStorage.setItem('sanctum_token', token)
      localStorage.setItem('user_name', user?.name || '')
    },

    async login(email, password) {
      const { data } = await api.post('/api/auth/login', { email, password })
      this.setSession(data.token, data.user)
      return data
    },

    async register(payload) {
      const { data } = await api.post('/api/auth/register', payload)
      this.setSession(data.token, data.user, data.shop)
      if (data.shop) localStorage.setItem('shop_name', data.shop.name)
      return data
    },

    async restoreSession() {
      if (!this.token) return false

      try {
        const { data } = await api.get('/api/auth/me')
        this.user = data
        localStorage.setItem('user_name', data.name || '')
        return true
      } catch (error) {
        this.clearSession()
        return false
      }
    },

    async logout() {
      if (this.token) {
        try {
          await api.post('/api/auth/logout')
        } catch (error) {
          // O token local é removido mesmo se o servidor estiver indisponível.
        }
      }
      this.clearSession()
    },

    clearSession() {
      this.token = ''
      this.user = null
      this.shop = null
      localStorage.removeItem('sanctum_token')
      localStorage.removeItem('user_name')
      localStorage.removeItem('shop_name')
    },
  },
})
