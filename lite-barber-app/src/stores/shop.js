import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '../services/api'

export const useShopStore = defineStore('shop', () => {
  const shop = ref(null)
  const currentSlug = ref('')
  const loading = ref(false)
  const error = ref(null)

  const applyTheme = (currentShop) => {
    if (!currentShop) return

    if (currentShop.theme_color) {
      document.documentElement.style.setProperty('--gold', currentShop.theme_color)
      document.documentElement.style.setProperty('--accent', currentShop.theme_color)
    }

    const theme = currentShop.theme_mode
    const lightTheme = Boolean(theme && theme.includes('light'))

    document.body.classList.toggle('light-theme', lightTheme)
    document.documentElement.classList.toggle('light-theme', lightTheme)

    if (!lightTheme) {
      document.body.classList.remove('theme-nude', 'theme-wine')
      document.documentElement.classList.remove('theme-nude', 'theme-wine')
      return
    }

    const isWine = theme === 'light-wine'
    document.body.classList.toggle('theme-wine', isWine)
    document.documentElement.classList.toggle('theme-wine', isWine)
    document.body.classList.toggle('theme-nude', !isWine)
    document.documentElement.classList.toggle('theme-nude', !isWine)
  }

  const fetchShopBySlug = async (slug, { force = false } = {}) => {
    if (!slug) {
      loading.value = false
      error.value = 'Configure VITE_DEFAULT_SHOP_SLUG ou acesse uma URL com /slug.'
      return null
    }

    if (!force && shop.value && currentSlug.value === slug) {
      applyTheme(shop.value)
      return shop.value
    }

    loading.value = true
    error.value = null

    try {
      const { data } = await api.get(`/api/shops/${encodeURIComponent(slug)}`)
      shop.value = data
      currentSlug.value = slug
      applyTheme(data)
      return data
    } catch (err) {
      shop.value = null
      currentSlug.value = ''
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    shop,
    currentSlug,
    loading,
    error,
    applyTheme,
    fetchShopBySlug,
  }
})
