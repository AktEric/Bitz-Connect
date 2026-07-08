import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Attach Bearer token from localStorage on every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle 401 and 403 globally
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 403) {
      window.dispatchEvent(
        new CustomEvent('auth:forbidden', {
          detail: { message: error.response.data?.message ?? 'Acesso negado.' },
        })
      )
    }
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('hotel_session')
      window.dispatchEvent(new CustomEvent('auth:unauthenticated'))
    }
    return Promise.reject(error)
  }
)

export default api
