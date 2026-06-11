<template>
  <div class="app-layout">
    <Sidebar />
    <main class="main-content">
      <div v-if="loading" class="loading-spinner"><div class="spinner"></div></div>
      
      <template v-else-if="feuilleStats">
        <div class="page-header flex-between">
          <div>
            <h1>📊 Statistiques - {{ feuilleStats.feuille.classe }}</h1>
            <p>{{ feuilleStats.feuille.matiere }} • Trimestre {{ feuilleStats.feuille.trimestre }}</p>
          </div>
          <button class="btn btn-sm btn-outline" @click="$router.push(`/feuilles/${feuilleId}`)">← Retour</button>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-value" :class="getStatClass(feuilleStats.stats.moyenne_classe)">{{ feuilleStats.stats.moyenne_classe.toFixed(2) }}</div>
            <div class="stat-label">Moyenne de classe</div>
          </div>
          <div class="stat-card">
            <div class="stat-value text-success">{{ feuilleStats.stats.max.toFixed(2) }}</div>
            <div class="stat-label">Meilleure moyenne</div>
          </div>
          <div class="stat-card">
            <div class="stat-value text-danger">{{ feuilleStats.stats.min.toFixed(2) }}</div>
            <div class="stat-label">Plus faible moyenne</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ feuilleStats.stats.effectif }}</div>
            <div class="stat-label">Effectif</div>
          </div>
        </div>
        
        <div class="charts-grid">
          <!-- Distribution des notes -->
          <div class="card">
            <div class="card-header">
              <h2>Répartition des notes</h2>
            </div>
            <div style="height: 300px;">
              <canvas ref="distributionChart"></canvas>
            </div>
          </div>
          
          <!-- Top 5 élèves -->
          <div class="card">
            <div class="card-header">
              <h2>Top 5 élèves</h2>
            </div>
            <div style="height: 300px;">
              <canvas ref="top5Chart"></canvas>
            </div>
          </div>
          
          <!-- Moyennes par évaluation -->
          <div class="card">
            <div class="card-header">
              <h2>Moyennes par évaluation</h2>
            </div>
            <div style="height: 300px;">
              <canvas ref="evalMoyennesChart"></canvas>
            </div>
          </div>
          
          <!-- Détail des stats -->
          <div class="card">
            <div class="card-header">
              <h2>Détails</h2>
            </div>
            <div class="stats-details">
              <div class="detail-row">
                <span class="detail-label">Effectif total</span>
                <span class="detail-value">{{ feuilleStats.stats.effectif }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Notes renseignées</span>
                <span class="detail-value">{{ feuilleStats.stats.notes_renseignees }}</span>
              </div>
              <div class="detail-row" v-for="(count, idx) in feuilleStats.stats.distribution" :key="idx">
                <span class="detail-label">{{ ['0-7', '7-10', '10-13', '13-16', '16-20'][idx] }}</span>
                <span class="detail-value">{{ count }} élève(s)</span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/api/client'
import Sidebar from '@/components/Sidebar.vue'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

const route = useRoute()
const feuilleId = ref(route.params.id)
const loading = ref(true)
const feuilleStats = ref(null)

const distributionChart = ref(null)
const top5Chart = ref(null)
const evalMoyennesChart = ref(null)
let charts = []

function getStatClass(val) {
  if (val >= 14) return 'text-success'
  if (val >= 10) return 'text-warning'
  return 'text-danger'
}

function renderCharts() {
  // Nettoyer les anciens charts
  charts.forEach(c => c.destroy())
  charts = []

  if (!feuilleStats.value) return

  const stats = feuilleStats.value.stats
  const labels = ['0-7', '7-10', '10-13', '13-16', '16-20']

  // 1. Distribution des notes
  if (distributionChart.value) {
    const ctx = distributionChart.value.getContext('2d')
    const chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Nombre d\'élèves',
          data: stats.distribution,
          backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#10b981'],
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 }
          }
        }
      }
    })
    charts.push(chart)
  }

  // 2. Top 5
  if (top5Chart.value && stats.top5.length > 0) {
    const ctx = top5Chart.value.getContext('2d')
    const chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: stats.top5.map(e => `${e.prenom} ${e.nom}`),
        datasets: [{
          label: 'Moyenne',
          data: stats.top5.map(e => e.moyenne),
          backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444'],
          borderRadius: 6,
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: {
            beginAtZero: true,
            max: 20
          }
        }
      }
    })
    charts.push(chart)
  }

  // 3. Moyennes par évaluation
  if (evalMoyennesChart.value && feuilleStats.value.moyennes_evaluations.length > 0) {
    const ctx = evalMoyennesChart.value.getContext('2d')
    const chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: feuilleStats.value.moyennes_evaluations.map(e => e.nom),
        datasets: [
          {
            label: 'Moyenne',
            data: feuilleStats.value.moyennes_evaluations.map(e => e.moyenne),
            backgroundColor: '#4f46e5',
            borderRadius: 6,
          },
          {
            label: 'Barème',
            data: feuilleStats.value.moyennes_evaluations.map(e => e.bareme),
            backgroundColor: '#94a3b8',
            borderRadius: 6,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true }
        }
      }
    })
    charts.push(chart)
  }
}

onMounted(async () => {
  try {
    const res = await apiClient.get(`/feuilles/${feuilleId.value}/stats`)
    feuilleStats.value = res.data.data
  } catch (e) {
    console.error('Erreur chargement stats:', e)
  } finally {
    loading.value = false
    await nextTick()
    renderCharts()
  }
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
  text-align: center;
  box-shadow: var(--shadow-sm);
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: var(--font-size-sm);
  color: var(--text-muted);
}

.charts-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.stats-details {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  color: var(--text-muted);
  font-size: var(--font-size-sm);
}

.detail-value {
  font-weight: 600;
  color: var(--text-primary);
  font-size: var(--font-size-sm);
}

@media (max-width: 768px) {
  .stats-grid, .charts-grid {
    grid-template-columns: 1fr;
  }
}
</style>