<template>
  <div class="app-layout">
    <Sidebar />
    <main class="main-content">
      <div class="page-header flex-between">
        <div>
          <h1>Mes Feuilles de Notes</h1>
          <p>Gérez vos feuilles par classe, matière et trimestre</p>
        </div>
        <button class="btn btn-primary" @click="showCreateModal = true">+ Nouvelle feuille</button>
      </div>
      
      <!-- Filtres -->
      <div class="filters-bar">
        <input type="text" v-model="searchQuery" class="form-control" placeholder="Rechercher par classe, matière..." style="max-width: 300px" />
        <select v-model="filterTrimestre" class="form-control" style="max-width: 150px">
          <option value="">Tous les trimestres</option>
          <option value="1">Trimestre 1</option>
          <option value="2">Trimestre 2</option>
          <option value="3">Trimestre 3</option>
        </select>
      </div>
      
      <div v-if="loading" class="loading-spinner"><div class="spinner"></div></div>
      
      <div v-else-if="filteredFeuilles.length === 0" class="card text-center" style="padding: 3rem;">
        <p class="text-muted">Aucune feuille de notes trouvée.</p>
      </div>
      
      <div v-else class="feuilles-grid">
        <div v-for="feuille in filteredFeuilles" :key="feuille.id" class="card feuille-card">
          <div class="feuille-card-header">
            <div class="feuille-classe-badge">{{ feuille.classe }}</div>
            <div class="feuille-actions">
              <button class="btn btn-sm btn-outline" @click="$router.push(`/feuilles/${feuille.id}`)">Ouvrir</button>
              <button class="btn btn-sm btn-outline" @click="duplicateFeuille(feuille)" title="Dupliquer la feuille">📋</button>
              <button class="btn btn-sm btn-outline" @click="editFeuille(feuille)">✏️</button>
              <button class="btn btn-sm btn-danger" @click="confirmDelete(feuille)">🗑️</button>
            </div>
          </div>
          <div class="feuille-card-body">
            <div class="feuille-info-row">
              <span class="info-icon">📖</span>
              <span>{{ feuille.matiere }}</span>
            </div>
            <div class="feuille-info-row">
              <span class="info-icon">📅</span>
              <span>Trimestre {{ feuille.trimestre }} - {{ feuille.annee_scolaire }}</span>
            </div>
            <div class="feuille-info-row">
              <span class="info-icon">👨‍🎓</span>
              <span>{{ feuille.nombre_eleves || 0 }} élèves</span>
            </div>
            <div class="feuille-info-row">
              <span class="info-icon">📊</span>
              <span>{{ feuille.nombre_evaluations || 0 }} évaluations</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Modal Création -->
      <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
        <div class="modal-content">
          <div class="modal-header">
            <h2>Nouvelle feuille de notes</h2>
            <button class="btn btn-sm btn-outline" @click="showCreateModal = false">✕</button>
          </div>
          <form @submit.prevent="createFeuille">
            <div class="form-group">
              <label class="form-label">Classe</label>
              <input type="text" v-model="createForm.classe" class="form-control" placeholder="ex: 4ème Mathématiques" required />
            </div>
            <div class="form-group">
              <label class="form-label">Matière</label>
              <input type="text" v-model="createForm.matiere" class="form-control" placeholder="ex: Mathématiques" required />
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Trimestre</label>
                <select v-model="createForm.trimestre" class="form-control" required>
                  <option value="">Sélectionner</option>
                  <option value="1">Trimestre 1</option>
                  <option value="2">Trimestre 2</option>
                  <option value="3">Trimestre 3</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Année scolaire</label>
                <input type="text" v-model="createForm.annee_scolaire" class="form-control" placeholder="2025-2026" required />
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showCreateModal = false">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="creating">
                {{ creating ? 'Création...' : 'Créer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Modal Modification -->
      <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
        <div class="modal-content">
          <div class="modal-header">
            <h2>Modifier la feuille</h2>
            <button class="btn btn-sm btn-outline" @click="showEditModal = false">✕</button>
          </div>
          <form @submit.prevent="updateFeuille">
            <div class="form-group">
              <label class="form-label">Classe</label>
              <input type="text" v-model="editForm.classe" class="form-control" required />
            </div>
            <div class="form-group">
              <label class="form-label">Matière</label>
              <input type="text" v-model="editForm.matiere" class="form-control" required />
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Trimestre</label>
                <select v-model="editForm.trimestre" class="form-control" required>
                  <option value="1">Trimestre 1</option>
                  <option value="2">Trimestre 2</option>
                  <option value="3">Trimestre 3</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Année scolaire</label>
                <input type="text" v-model="editForm.annee_scolaire" class="form-control" required />
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showEditModal = false">Annuler</button>
              <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Modal Suppression -->
      <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
        <div class="modal-content">
          <div class="modal-header">
            <h2>Confirmer la suppression</h2>
            <button class="btn btn-sm btn-outline" @click="showDeleteConfirm = false">✕</button>
          </div>
          <p>Êtes-vous sûr de vouloir supprimer la feuille <strong>{{ deleteTarget?.classe }} - {{ deleteTarget?.matiere }}</strong> ?</p>
          <p class="text-danger mt-1">Cette action est irréversible. Toutes les données (élèves, notes) seront supprimées.</p>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showDeleteConfirm = false">Annuler</button>
            <button class="btn btn-danger" @click="deleteFeuille">Supprimer</button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '@/api/client'
import Sidebar from '@/components/Sidebar.vue'
import { useAnneeScolaireStore } from '@/stores/anneeScolaire'

const anneeScolaireStore = useAnneeScolaireStore()
const loading = ref(true)
const feuilles = ref([])
const searchQuery = ref('')
const filterTrimestre = ref('')
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDeleteConfirm = ref(false)
const creating = ref(false)
const deleteTarget = ref(null)
const editTarget = ref(null)

const createForm = ref({ classe: '', matiere: '', trimestre: '', annee_scolaire: anneeScolaireStore.anneeScolaire || '2025-2026' })
const editForm = ref({ classe: '', matiere: '', trimestre: '', annee_scolaire: '' })

const filteredFeuilles = computed(() => {
  let list = feuilles.value
  if (anneeScolaireStore.anneeScolaire) {
    list = list.filter(f => f.annee_scolaire === anneeScolaireStore.anneeScolaire)
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(f => f.classe.toLowerCase().includes(q) || f.matiere.toLowerCase().includes(q))
  }
  if (filterTrimestre.value) {
    list = list.filter(f => String(f.trimestre) === filterTrimestre.value)
  }
  return list
})

async function loadFeuilles() {
  try {
    const res = await apiClient.get('/feuilles')
    const data = res.data.data || {}
    feuilles.value = data.feuilles || []
    anneeScolaireStore.initFromFeuilles(feuilles.value)
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function createFeuille() {
  creating.value = true
  try {
    await apiClient.post('/feuilles', createForm.value)
    showCreateModal.value = false
    createForm.value = { classe: '', matiere: '', trimestre: '', annee_scolaire: anneeScolaireStore.anneeScolaire || '2025-2026' }
    await loadFeuilles()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur')
  } finally {
    creating.value = false
  }
}

function editFeuille(feuille) {
  editTarget.value = feuille
  editForm.value = {
    classe: feuille.classe,
    matiere: feuille.matiere,
    trimestre: String(feuille.trimestre),
    annee_scolaire: feuille.annee_scolaire
  }
  showEditModal.value = true
}

async function updateFeuille() {
  try {
    await apiClient.put(`/feuilles/${editTarget.value.id}`, editForm.value)
    showEditModal.value = false
    await loadFeuilles()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur')
  }
}

async function duplicateFeuille(feuille) {
  if (!confirm(`Dupliquer la feuille "${feuille.classe} - ${feuille.matiere}" ?\n\nUne copie sera créée avec les mêmes élèves et évaluations, sans notes.`)) return
  
  try {
    const res = await apiClient.post(`/feuilles/${feuille.id}/duplicate`)
    await loadFeuilles()
    alert(res.data.message || 'Feuille dupliquée avec succès !')
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur lors de la duplication')
  }
}

function confirmDelete(feuille) {
  deleteTarget.value = feuille
  showDeleteConfirm.value = true
}

async function deleteFeuille() {
  try {
    await apiClient.delete(`/feuilles/${deleteTarget.value.id}`)
    showDeleteConfirm.value = false
    await loadFeuilles()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur')
  }
}

onMounted(loadFeuilles)
</script>

<style scoped>
.filters-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.feuilles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1rem;
}

.feuille-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.feuille-classe-badge {
  background: var(--primary-light);
  color: var(--primary-color);
  padding: 0.375rem 0.75rem;
  border-radius: var(--radius-md);
  font-weight: 600;
  font-size: var(--font-size-sm);
}

.feuille-actions {
  display: flex;
  gap: 0.375rem;
}

.feuille-card-body {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.feuille-info-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--font-size-sm);
  color: var(--text-secondary);
}

.info-icon {
  font-size: 1rem;
}
</style>