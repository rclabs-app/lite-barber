<script setup>
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
</script>

<template>
  <section class="view active" style="text-align:center;padding-top:60px">
    <h1 style="font-size:1.5rem;margin-bottom:12px">BARBEARIA NÃO ENCONTRADA</h1>
    <p style="color:#888;margin-bottom:30px">
      Não encontramos uma barbearia ativa com o endereço <strong>{{ route.params.slug }}</strong>.
    </p>
    <button class="btn-primary-luxury" @click="router.push('/')">
      VOLTAR
    </button>
  </section>
</template>
