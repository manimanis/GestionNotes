import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient from '@/api/client'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token') || null)
  const loading = ref(false)
  const error = ref(null)

  const isAuthenticated = computed(() => !!token.value)
  const userName = computed(() => user.value ? `${user.value.prenom} ${user.value.nom}` : '')
  const userId = computed(() => user.value ? user.value.id : null)

  async function login(email, mot_de_passe) {
    loading.value = true
    error.value = null
    try {
      const response = await apiClient.post('/login', { email, mot_de_passe })
      const data = response.data.data
      token.value = data.token
      user.value = data.user
      localStorage.setItem('token', data.token)
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur de connexion'
      return false
    } finally {
      loading.value = false
    }
  }

  async function register(userData) {
    loading.value = true
    error.value = null
    try {
      const response = await apiClient.post('/register', userData)
      const data = response.data.data
      token.value = data.token
      user.value = data.user
      localStorage.setItem('token', data.token)
      return true
    } catch (err) {
      error.value = err.response?.data?.message || "Erreur d'inscription"
      return false
    } finally {
      loading.value = false
    }
  }

  async function fetchUser() {
    if (!token.value) return false
    try {
      const response = await apiClient.get('/user')
      user.value = response.data.data
      return true
    } catch (err) {
      logout()
      return false
    }
  }

  async function updateProfile(data) {
    try {
      const response = await apiClient.put('/profile', data)
      user.value = response.data.data
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur de mise à jour'
      return false
    }
  }

  function logout() {
    user.value = null
    token.value = null
    localStorage.removeItem('token')
  }

  return {
    user, token, loading, error,
    isAuthenticated, userName, userId,
    login, register, fetchUser, updateProfile, logout
  }
})