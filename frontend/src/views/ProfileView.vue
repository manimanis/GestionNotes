<template>
  <div class="app-layout">
    <Sidebar />
    <main class="main-content">
      <div class="page-header">
        <h1>Mon Profil</h1>
        <p>Gérez vos informations personnelles</p>
      </div>
      
      <div class="profile-grid">
        <div class="card profile-card">
          <div class="profile-header">
            <div class="profile-avatar-large">{{ initials }}</div>
            <div>
              <h2>{{ authStore.userName }}</h2>
              <p class="text-muted">{{ authStore.user?.email }}</p>
            </div>
          </div>
          
          <div class="profile-info">
            <div class="info-row">
              <span class="info-label">Identifiant</span>
              <span class="info-value">{{ authStore.user?.identifiant }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Date de naissance</span>
              <span class="info-value">{{ authStore.user?.date_naissance }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Lycée</span>
              <span class="info-value">{{ authStore.user?.lycee }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Téléphone</span>
              <span class="info-value">{{ authStore.user?.telephone }}</span>
            </div>
          </div>
        </div>
        
        <div class="card">
          <div class="card-header">
            <h2>Modifier le profil</h2>
          </div>
          
          <div v-if="successMsg" class="alert alert-success">{{ successMsg }}</div>
          <div v-if="authStore.error" class="alert alert-error">{{ authStore.error }}</div>
          
          <form @submit.prevent="handleUpdate">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Nom</label>
                <input type="text" v-model="form.nom" class="form-control" required />
              </div>
              <div class="form-group">
                <label class="form-label">Prénom</label>
                <input type="text" v-model="form.prenom" class="form-control" required />
              </div>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Lycée</label>
                <input type="text" v-model="form.lycee" class="form-control" required />
              </div>
              <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input type="tel" v-model="form.telephone" class="form-control" required />
              </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </form>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import Sidebar from '@/components/Sidebar.vue'

const authStore = useAuthStore()
const successMsg = ref('')
const form = reactive({
  nom: '',
  prenom: '',
  lycee: '',
  telephone: ''
})

const initials = computed(() => {
  if (!authStore.user) return '?'
  return `${authStore.user.prenom[0]}${authStore.user.nom[0]}`.toUpperCase()
})

onMounted(() => {
  if (authStore.user) {
    form.nom = authStore.user.nom || ''
    form.prenom = authStore.user.prenom || ''
    form.lycee = authStore.user.lycee || ''
    form.telephone = authStore.user.telephone || ''
  }
})

async function handleUpdate() {
  successMsg.value = ''
  const ok = await authStore.updateProfile({ ...form })
  if (ok) {
    successMsg.value = 'Profil mis à jour avec succès.'
    setTimeout(() => successMsg.value = '', 3000)
  }
}
</script>

<style scoped>
.profile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.profile-header {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.profile-avatar-large {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--primary-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--font-size-2xl);
  font-weight: 700;
}

.profile-info {
  display: grid;
  gap: 1rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}

.info-row:last-child {
  border-bottom: none;
}

.info-label {
  color: var(--text-muted);
  font-size: var(--font-size-sm);
}

.info-value {
  font-weight: 500;
  color: var(--text-primary);
  font-size: var(--font-size-sm);
}

.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
  padding: 0.75rem 1rem;
  border-radius: var(--radius-md);
  margin-bottom: 1rem;
  font-size: var(--font-size-sm);
}

@media (max-width: 768px) {
  .profile-grid {
    grid-template-columns: 1fr;
  }
}
</style>