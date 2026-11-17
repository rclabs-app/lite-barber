import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '',
  headers: {
    'Accept': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('sanctum_token')

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('sanctum_token')
    }

    const responseData = error.response?.data

    error.normalized = {
      status: error.response?.status || 0,
      message: responseData?.message || 'Não foi possível concluir a operação.',
      errors: responseData?.errors || {},
    }

    return Promise.reject(error)
  },
)

export default api
