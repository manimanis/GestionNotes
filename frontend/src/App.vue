<template>
  <div id="app-root" :class="{ 'dark-mode': isDarkMode }">
    <router-view v-if="!isLoading" />
    <div v-else class="loading-screen">
      <div class="spinner"></div>
      <p>Chargement...</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'

const authStore = useAuthStore()
const themeStore = useThemeStore()
const isLoading = ref(true)
const isDarkMode = ref(false)

onMounted(async () => {
  isDarkMode.value = themeStore.isDarkMode
  
  // Vérifier si un token existe
  const token = localStorage.getItem('token')
  if (token) {
    try {
      await authStore.fetchUser()
    } catch (e) {
      localStorage.removeItem('token')
    }
  }
  isLoading.value = false
})
</script>

<style scoped>
.loading-screen {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100vh;
  background: var(--bg-primary);
  color: var(--text-primary);
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid var(--border-color);
  border-top: 4px solid var(--primary-color);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>