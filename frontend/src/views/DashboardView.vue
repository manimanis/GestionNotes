<template>
  <div class="app-layout">
    <Sidebar />
    <main class="main-content">
      <div class="page-header">
        <h1>Tableau de bord</h1>
        <p>Bienvenue, {{ authStore.userName }}</p>
      </div>
      
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">📝</div>
          <div class="stat-info">
            <div class="stat-value">{{ stats.totalFeuilles }}</div>
            <div class="stat-label">Feuilles de notes</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">👨‍🎓</div>
          <div class="stat-info">
            <div class="stat-value">{{ stats.totalEleves }}</div>
            <div class="stat-label">Total élèves</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🏫</div>
          <div class="stat-info">
            <div class="stat-value">{{ stats.totalClasses }}</div>
            <div class="stat-label">Classes</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🏫</div>
          <div class="stat-info">
            <div class="stat-value">{{ stats.anneeScolaire }}</div>
            <div class="stat-label">Année en cours</div>
          </div>
        </div>
      </div>
      
      <div class="dashboard-grid">
        <div class="card">
          <div class="card-header">
            <h2>Dernières feuilles</h2>
            <router-link to="/feuilles" class="btn btn-sm btn-outline">Voir tout</router-link>
          </div>
          <div v-if="loading" class="loading-spinner"><div class="spinner"></div></div>
          <div v-else-if="recentFeuilles.length === 0" class="empty-state">
            <p>Aucune feuille de notes pour le moment.</p>
            <router-link to="/feuilles" class="btn btn-primary mt-2">Créer une feuille</router-link>
          </div>
          <div v-else class="feuille-list">
            <div v-for="feuille in recentFeuilles" :key="feuille.id" class="feuille-item" @click="$router.push(`/feuilles/${feuille.id}`)">
              <div class="feuille-info">
                <div class="feuille-classe">{{ feuille.classe }}</div>
                <div class="feuille-meta">{{ feuille.matiere }} • Trimestre {{ feuille.trimestre }} • {{ feuille.annee_scolaire }}</div>
              </div>
              <div class="feuille-count">
                <span class="badge badge-primary">{{ feuille.nombre_eleves || 0 }} élèves</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="card">
          <div class="card-header">
            <h2>Actions rapides</h2>
          </div>
          <div class="quick-actions">
            <router-link to="/feuilles" class="action-item">
              <span class="action-icon">📝</span>
              <span>Nouvelle feuille</span>
            </router-link>
            <router-link to="/profile" class="action-item">
              <span class="action-icon">👤</span>
              <span>Mon profil</span>
            </router-link>
            <div class="action-item" @click="toggleDarkMode">
              <span class="action-icon">{{ themeStore.isDarkMode ? '☀️' : '🌙' }}</span>
              <span>{{ themeStore.isDarkMode ? 'Mode clair' : 'Mode sombre' }}</span>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { useAnneeScolaireStore } from '@/stores/anneeScolaire'
import apiClient from '@/api/client'
import Sidebar from '@/components/Sidebar.vue'

const authStore = useAuthStore()
const themeStore = useThemeStore()
const anneeScolaireStore = useAnneeScolaireStore()
const loading = ref(true)
const recentFeuilles = ref([])
const stats = ref({
  totalFeuilles: 0,
  totalEleves: 0,
  totalClasses: 0,
  anneeScolaire: '-'
})

function toggleDarkMode() {
  themeStore.toggleDarkMode()
}

const allFeuilles = ref([])

async function loadDashboard() {
  loading.value = true
  try {
    // Charger toutes les feuilles pour découvrir les années disponibles
    const response = await apiClient.get('/feuilles')
    const data = response.data.data || {}
    allFeuilles.value = data.feuilles || []
    
    // Initialiser les années disponibles (toutes les années, pas filtrées)
    anneeScolaireStore.initFromFeuilles(allFeuilles.value)
    
    // Filtrer par année sélectionnée
    applyFilter()
  } catch (e) {
    console.error('Erreur chargement dashboard:', e)
  } finally {
    loading.value = false
  }
}

function applyFilter() {
  const annee = anneeScolaireStore.anneeScolaire
  const trim = anneeScolaireStore.trimestre
  let filtered = allFeuilles.value
  if (annee) {
    filtered = filtered.filter(f => f.annee_scolaire === annee)
  }
  if (trim) {
    filtered = filtered.filter(f => String(f.trimestre) === String(trim))
  }
  
  recentFeuilles.value = filtered
  stats.value.totalFeuilles = filtered.length
  stats.value.totalEleves = filtered.reduce((sum, f) => sum + (f.nombre_eleves || 0), 0)
  stats.value.totalClasses = new Set(filtered.map(f => f.classe).filter(Boolean)).size
  
  if (filtered.length > 0) {
    stats.value.anneeScolaire = annee || filtered[0].annee_scolaire
  } else {
    stats.value.anneeScolaire = annee || '-'
  }
}

onMounted(loadDashboard)

// Recharger quand l'année scolaire ou le trimestre change
watch(() => [anneeScolaireStore.anneeScolaire, anneeScolaireStore.trimestre], () => {
  applyFilter()
})
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: var(--shadow-sm);
}

.stat-icon {
  font-size: 2rem;
}

.stat-value {
  font-size: var(--font-size-2xl);
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.2;
}

.stat-label {
  font-size: var(--font-size-xs);
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 500;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.5rem;
}

.feuille-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.feuille-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.875rem 1rem;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: var(--transition);
  border: 1px solid transparent;
}

.feuille-item:hover {
  background: var(--bg-tertiary);
  border-color: var(--border-color);
}

.feuille-classe {
  font-weight: 600;
  font-size: var(--font-size-sm);
  color: var(--text-primary);
}

.feuille-meta {
  font-size: var(--font-size-xs);
  color: var(--text-muted);
  margin-top: 0.125rem;
}

.empty-state {
  text-align: center;
  padding: 2rem;
  color: var(--text-muted);
}

.quick-actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.action-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  border-radius: var(--radius-md);
  cursor: pointer;
  text-decoration: none;
  color: var(--text-primary);
  transition: var(--transition);
}

.action-item:hover {
  background: var(--bg-tertiary);
}

.action-icon {
  font-size: 1.25rem;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}
</style>