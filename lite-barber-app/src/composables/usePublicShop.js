import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'

const defaultSlug = import.meta.env.VITE_DEFAULT_SHOP_SLUG || ''

export function usePublicShop() {
  const route = useRoute()
  const router = useRouter()
  const shop = ref(null)
  const loading = ref(true)
  const error = ref(null)

  const slug = computed(() => route.params.slug || defaultSlug)

  const applyTheme = (currentShop) => {
    if (currentShop.theme_color) {
      document.documentElement.style.setProperty('--gold', currentShop.theme_color)
      document.documentElement.style.setProperty('--accent', currentShop.theme_color)
    }

    const theme = currentShop.theme_mode
    const lightTheme = theme && theme.includes('light')

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

  const loadShop = async () => {
    if (!slug.value) {
      loading.value = false
      error.value = 'Configure VITE_DEFAULT_SHOP_SLUG ou acesse uma URL com /slug.'
      return null
    }

    loading.value = true
    error.value = null

    try {
      const { data } = await api.get(`/api/shops/${encodeURIComponent(slug.value)}`)
      shop.value = data
      applyTheme(data)
      return data
    } catch (err) {
      if (err.response?.status === 404) {
        await router.replace({ name: 'shop-not-found', params: { slug: slug.value } })
      } else {
        error.value = err.response?.data?.message || 'Não foi possível carregar a barbearia.'
      }
      return null
    } finally {
      loading.value = false
    }
  }

  return { shop, loading, error, slug, loadShop }
}
