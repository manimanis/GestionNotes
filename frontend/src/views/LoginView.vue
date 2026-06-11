<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-header">
        <div class="auth-logo">📚</div>
        <h1>Gestion de Notes</h1>
        <p>Connectez-vous à votre espace enseignant</p>
      </div>
      
      <form @submit.prevent="handleLogin" class="auth-form">
        <div v-if="authStore.error" class="alert alert-error">{{ authStore.error }}</div>
        
        <div class="form-group">
          <label class="form-label">Email</label>
          <input 
            type="email" 
            v-model="email" 
            class="form-control" 
            placeholder="votre@email.com"
            required
          />
        </div>
        
        <div class="form-group">
          <label class="form-label">Mot de passe</label>
          <input 
            type="password" 
            v-model="password" 
            class="form-control" 
            placeholder="Votre mot de passe"
            required
          />
        </div>
        
        <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="authStore.loading">
          {{ authStore.loading ? 'Connexion...' : 'Se connecter' }}
        </button>
      </form>
      
      <div class="auth-footer">
        <p>Pas encore de compte ? <router-link to="/register">S'inscrire</router-link></p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const email = ref('')
const password = ref('')

async function handleLogin() {
  const success = await authStore.login(email.value, password.value)
  if (success) {
    router.push('/')
  }
}
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-primary);
  padding: 1rem;
}

.auth-card {
  width: 100%;
  max-width: 420px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-xl);
  padding: 2.5rem;
  box-shadow: var(--shadow-lg);
}

.auth-header {
  text-align: center;
  margin-bottom: 2rem;
}

.auth-logo {
  font-size: 3rem;
  margin-bottom: 0.75rem;
}

.auth-header h1 {
  font-size: var(--font-size-2xl);
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.auth-header p {
  color: var(--text-secondary);
  font-size: var(--font-size-sm);
}

.alert {
  padding: 0.75rem 1rem;
  border-radius: var(--radius-md);
  margin-bottom: 1rem;
  font-size: var(--font-size-sm);
}

.alert-error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.dark-mode .alert-error {
  background: #7f1d1d;
  color: #fca5a5;
  border-color: #991b1b;
}

.auth-footer {
  text-align: center;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border-color);
  font-size: var(--font-size-sm);
  color: var(--text-secondary);
}

.auth-footer a {
  font-weight: 500;
}
</style>