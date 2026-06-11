<template>
  <aside class="sidebar" :class="{ collapsed: isCollapsed }">
    <div class="sidebar-header">
      <div class="logo">
        <span class="logo-icon">📚</span>
        <span v-if="!isCollapsed" class="logo-text">Gestion Notes</span>
      </div>
      <button class="toggle-btn" @click="toggleSidebar">
        <span v-if="isCollapsed">➡️</span>
        <span v-else>⬅️</span>
      </button>
    </div>
    
    <nav class="sidebar-nav">
      <router-link to="/" class="nav-item" active-class="active" exact>
        <span class="nav-icon">📊</span>
        <span v-if="!isCollapsed" class="nav-label">Tableau de bord</span>
      </router-link>
      
      <router-link to="/feuilles" class="nav-item" active-class="active">
        <span class="nav-icon">📝</span>
        <span v-if="!isCollapsed" class="nav-label">Mes feuilles</span>
      </router-link>
      
      <router-link to="/profile" class="nav-item" active-class="active">
        <span class="nav-icon">👤</span>
        <span v-if="!isCollapsed" class="nav-label">Mon profil</span>
      </router-link>
    </nav>
    
    <div class="sidebar-year-selector" v-if="!isCollapsed">
      <label class="year-label">📅 Année scolaire</label>
      <select
        class="year-select"
        :value="anneeScolaireStore.anneeScolaire"
        @change="anneeScolaireStore.setAnneeScolaire($event.target.value)"
      >
        <option value="" disabled>Choisir...</option>
        <option v-for="year in anneeScolaireStore.availableAnnees" :key="year" :value="year">
          {{ year }}
        </option>
      </select>
    </div>

    <div class="sidebar-footer">
      <div class="user-info" v-if="authStore.user">
        <div class="user-avatar">{{ initials }}</div>
        <div v-if="!isCollapsed" class="user-details">
          <div class="user-name">{{ authStore.userName }}</div>
          <div class="user-role">Enseignant</div>
        </div>
      </div>
      <button class="logout-btn" @click="handleLogout" :title="'Déconnexion'">
        <span>🚪</span>
        <span v-if="!isCollapsed">Déconnexion</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAnneeScolaireStore } from '@/stores/anneeScolaire'

const authStore = useAuthStore()
const anneeScolaireStore = useAnneeScolaireStore()
const router = useRouter()
const isCollapsed = ref(false)

const initials = computed(() => {
  if (!authStore.user) return '?'
  return `${authStore.user.prenom[0]}${authStore.user.nom[0]}`.toUpperCase()
})

function toggleSidebar() {
  isCollapsed.value = !isCollapsed.value
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.sidebar {
  width: var(--sidebar-width);
  height: 100vh;
  position: fixed;
  left: 0;
  top: 0;
  background: var(--bg-secondary);
  border-right: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  transition: width 0.3s ease;
  z-index: 100;
}

.sidebar.collapsed {
  width: 72px;
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem;
  border-bottom: 1px solid var(--border-color);
}

.logo {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.logo-icon {
  font-size: 1.5rem;
}

.logo-text {
  font-size: var(--font-size-lg);
  font-weight: 700;
  color: var(--primary-color);
  white-space: nowrap;
}

.toggle-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem;
  font-size: 1rem;
  color: var(--text-muted);
  transition: var(--transition);
}

.toggle-btn:hover {
  color: var(--text-primary);
}

.sidebar-nav {
  flex: 1;
  padding: 1rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border-radius: var(--radius-md);
  color: var(--text-secondary);
  text-decoration: none;
  transition: var(--transition);
  white-space: nowrap;
}

.nav-item:hover {
  background: var(--bg-tertiary);
  color: var(--text-primary);
}

.nav-item.active {
  background: var(--primary-light);
  color: var(--primary-color);
  font-weight: 500;
}

.nav-icon {
  font-size: 1.25rem;
  flex-shrink: 0;
}

.nav-label {
  font-size: var(--font-size-sm);
}

.sidebar-year-selector {
  padding: 0.75rem;
  border-top: 1px solid var(--border-color);
}

.year-label {
  display: block;
  font-size: var(--font-size-xs);
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 500;
  margin-bottom: 0.375rem;
}

.year-select {
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  background: var(--bg-primary);
  color: var(--text-primary);
  font-size: var(--font-size-sm);
  font-family: var(--font-family);
  cursor: pointer;
  transition: var(--transition);
}

.year-select:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 2px var(--primary-light);
}

.sidebar-footer {
  padding: 1rem 0.75rem;
  border-top: 1px solid var(--border-color);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--primary-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: var(--font-size-sm);
  flex-shrink: 0;
}

.user-details {
  overflow: hidden;
}

.user-name {
  font-size: var(--font-size-sm);
  font-weight: 500;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: var(--font-size-xs);
  color: var(--text-muted);
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.625rem 1rem;
  background: none;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  color: var(--text-secondary);
  cursor: pointer;
  font-size: var(--font-size-sm);
  font-family: var(--font-family);
  transition: var(--transition);
}

.logout-btn:hover {
  background: #fee2e2;
  color: var(--danger-color);
  border-color: var(--danger-color);
}

.dark-mode .logout-btn:hover {
  background: #7f1d1d;
}
</style>