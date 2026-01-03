import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json'
  }
})

// Request interceptor для добавления токена
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor для обработки ошибок
api.interceptors.response.use(
  (response) => {
    return response.data
  },
  (error) => {
    console.error('❌ API ERROR:', {
      url: error.config?.url,
      status: error.response?.status,
      data: error.response?.data
    })
    
    if (error.response?.status === 401) {
      // Избегаем редиректа если мы уже на странице логина или делаем запрос авторизации
      const isLoginPage = window.location.pathname === '/login'
      const isAuthRequest = error.config?.url?.includes('/auth/')
      
      console.log('🔒 401 Error:', { isLoginPage, isAuthRequest })
      
      if (!isLoginPage && !isAuthRequest) {
        console.log('⛔ Clearing auth and redirecting to /login')
        localStorage.removeItem('token')
        localStorage.removeItem('admin')
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api

