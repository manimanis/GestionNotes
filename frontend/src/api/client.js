import axios from 'axios'

// URL de base de l'API
// En production (Apache, port 80) : /GestionNotes/api
// En développement (Vite) : /GestionNotes/api (proxifié vers le backend)
const API_BASE_URL = '/GestionNotes/api'

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Intercepteur pour ajouter le token JWT
apiClient.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Intercepteur pour gérer les erreurs 401
apiClient.interceptors.response.use(
  response => response,
  error => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('token')
      window.location.href = '/GestionNotes/login'
    }
    return Promise.reject(error)
  }
)

export default apiClient