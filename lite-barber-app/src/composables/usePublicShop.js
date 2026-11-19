import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useShopStore } from '../stores/shop'

const defaultSlug = import.meta.env.VITE_DEFAULT_SHOP_SLUG || ''

export function usePublicShop() {
  const route = useRoute()
  const router = useRouter()
  const shopStore = useShopStore()
  const { shop, loading, error } = storeToRefs(shopStore)

  const slug = computed(() => route.params.slug || defaultSlug)

  const loadShop = async ({ force = false } = {}) => {
    try {
      return await shopStore.fetchShopBySlug(slug.value, { force })
    } catch (err) {
      if (err.response?.status === 404) {
        await router.replace({ name: 'shop-not-found', params: { slug: slug.value } })
      } else {
        error.value = err.response?.data?.message || 'Não foi possível carregar a barbearia.'
      }
      return null
    }
  }

  return { shop, loading, error, slug, loadShop }
}
