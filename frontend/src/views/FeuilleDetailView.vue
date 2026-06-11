<template>
  <div class="app-layout">
    <Sidebar />
    <main class="main-content">
      <div v-if="loading" class="loading-spinner"><div class="spinner"></div></div>
      
      <template v-else-if="feuille">
        <!-- Header -->
        <div class="page-header flex-between">
          <div>
            <h1>{{ feuille.classe }} - {{ feuille.matiere }}</h1>
            <p>Trimestre {{ feuille.trimestre }} • {{ feuille.annee_scolaire }}</p>
          </div>
          <div class="flex gap-1">
            <button class="btn btn-sm btn-info" @click="$router.push(`/feuilles/${feuilleId}/stats`)">📊 Statistiques</button>
            <button class="btn btn-sm btn-secondary" @click="exportCsv">📥 CSV</button>
            <button class="btn btn-sm btn-secondary" @click="exportJson">📥 JSON</button>
            <button class="btn btn-sm btn-primary" @click="duplicateFeuille">📋 Dupliquer</button>
          </div>
        </div>
        
        <!-- Configuration Bar -->
        <div class="config-bar">
          <button class="btn btn-sm btn-primary" @click="showAddEvalModal = true">+ Évaluation</button>
          <button class="btn btn-sm btn-primary" @click="showAddEpreuveModal = true">+ Épreuve</button>
          <button class="btn btn-sm btn-primary" @click="showAddEleveModal = true">+ Élève</button>
          <button class="btn btn-sm btn-secondary" @click="openImportDataModal">📥 Importer données</button>
        </div>
        
        <!-- Data Table -->
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th class="sticky-col sortable-col" style="min-width: 40px" @click="toggleSort('numero_ordre')">
                  N° {{ sortKey === 'numero_ordre' ? (sortDirection === 'asc' ? '▲' : '▼') : '' }}
                </th>
                <th class="sticky-col sortable-col" style="min-width: 180px" @click="toggleSort('nom')">
                  Nom & Prénom {{ sortKey === 'nom' ? (sortDirection === 'asc' ? '▲' : '▼') : '' }}
                </th>
                <th v-for="e in evaluations" :key="e.id" class="eval-col sortable-col" style="min-width: 80px" @click="toggleSort('eval_' + e.id)">
                  <div class="col-header">
                    <span>{{ e.nom }} {{ sortKey === 'eval_' + e.id ? (sortDirection === 'asc' ? '▲' : '▼') : '' }}</span>
                    <span class="col-meta">/{{ e.bareme }} • c:{{ e.coefficient }}</span>
                    <button class="btn-del-col" @click.stop="deleteEvaluation(e.id)">×</button>
                  </div>
                </th>
                <th v-for="ep in epreuves" :key="ep.id" class="epreuve-col sortable-col" style="min-width: 100px" @click="toggleSort('ep_' + ep.id)">
                  <div class="col-header">
                    <span>{{ ep.nom }} {{ sortKey === 'ep_' + ep.id ? (sortDirection === 'asc' ? '▲' : '▼') : '' }}</span>
                    <span class="col-meta">c:{{ ep.coefficient }}</span>
                    <button class="btn-del-col" @click.stop="deleteEpreuve(ep.id)">×</button>
                  </div>
                </th>
                <th class="sortable-col" style="min-width: 80px" @click="toggleSort('moyenne')">
                  Moyenne {{ sortKey === 'moyenne' ? (sortDirection === 'asc' ? '▲' : '▼') : '' }}
                </th>
                <th class="sortable-col" style="min-width: 60px" @click="toggleSort('rang')">
                  Rang {{ sortKey === 'rang' ? (sortDirection === 'asc' ? '▲' : '▼') : '' }}
                </th>
                <th class="sortable-col" style="min-width: 120px" @click="toggleSort('observation')">
                  Observation {{ sortKey === 'observation' ? (sortDirection === 'asc' ? '▲' : '▼') : '' }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="eleve in sortedEleves" :key="eleve.id">
                <td class="sticky-col">{{ eleve.numero_ordre }}</td>
                <td class="sticky-col">
                  <div class="eleve-name">
                    <strong>{{ eleve.nom }}</strong> {{ eleve.prenom }}
                  </div>
                </td>
                <td v-for="e in evaluations" :key="e.id" class="note-cell">
                  <input
                    type="number"
                    class="inline-edit"
                    :value="formatNote(eleve.notes_evaluations[e.id])"
                    @change="saveNote(eleve.id, e.id, $event.target.value)"
                    min="0"
                    :max="e.bareme"
                    step="0.01"
                  />
                </td>
                <td v-for="ep in epreuves" :key="ep.id" class="note-cell calculated">
                  {{ formatNote(eleve.notes_epreuves[ep.id]) }}
                </td>
                <td class="moyenne-cell" :class="getMoyenneClass(eleve.moyenne)">
                  <strong>{{ eleve.moyenne !== null ? eleve.moyenne.toFixed(2) : '-' }}</strong>
                </td>
                <td class="rang-cell">
                  <span class="badge" :class="getRangBadgeClass(eleve.rang)">{{ eleve.rang }}</span>
                </td>
                <td>
                  <span class="badge" :class="getObservationClass(eleve.moyenne)">{{ eleve.observation }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Modal Import Données (Élèves + Notes) -->
        <div v-if="showImportDataModal" class="modal-overlay" @click.self="showImportDataModal = false">
          <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
              <h2>Importer des élèves et notes</h2>
              <button class="btn btn-sm btn-outline" @click="showImportDataModal = false">✕</button>
            </div>
            <div class="import-instructions">
              <p><strong>Instructions :</strong></p>
              <ol>
                <li>Ouvrez votre fichier Excel</li>
                <li>Sélectionnez les colonnes et copiez les données (Ctrl+C)</li>
                <li>Collez-les dans le champ ci-dessous (Ctrl+V)</li>
                <li>Associez chaque colonne au champ correspondant</li>
                <li>Vérifiez l'aperçu puis cliquez sur "Importer"</li>
              </ol>
              <p style="margin-top: 0.5rem; color: var(--text-muted); font-size: 0.8rem;">
                Les colonnes peuvent être dans n'importe quel ordre. Nom et prénom peuvent être dans la même colonne.
                <br>Les notes vides ou "-" sont ignorées. Les élèves existants (même identifiant) sont mis à jour.
              </p>
            </div>
            <div class="form-group">
              <label class="form-label">Données copiées depuis Excel</label>
              <textarea
                v-model="importDataText"
                class="form-control"
                rows="8"
                placeholder="Collez ici les données depuis Excel (séparées par des tabulations)&#10;&#10;Les colonnes peuvent être dans n'importe quel ordre.&#10;Nom et prénom peuvent être dans la même colonne."
                style="font-family: monospace; font-size: 0.85rem;"
              ></textarea>
            </div>

            <!-- Column Mapping -->
            <div v-if="importDataColumns.length > 0" class="import-mapping">
              <p><strong>Associez les colonnes :</strong></p>
              <div class="mapping-row">
                <label class="form-label" style="min-width: 120px;">Identifiant :</label>
                <select v-model="importColMapping.identifiant" class="form-control" style="max-width: 250px;">
                  <option value="">-- Sélectionner --</option>
                  <option v-for="(col, i) in importDataColumns" :key="i" :value="i">Colonne {{ i + 1 }} : {{ col }}</option>
                </select>
              </div>
              <div class="mapping-row">
                <label class="form-label" style="min-width: 120px;">N° ordre :</label>
                <select v-model="importColMapping.numero_ordre" class="form-control" style="max-width: 250px;">
                  <option value="">-- Sélectionner --</option>
                  <option v-for="(col, i) in importDataColumns" :key="i" :value="i">Colonne {{ i + 1 }} : {{ col }}</option>
                </select>
              </div>
              <div class="mapping-row">
                <label class="form-label" style="min-width: 120px;">Nom :</label>
                <select v-model="importColMapping.nom" class="form-control" style="max-width: 250px;">
                  <option value="">-- Sélectionner --</option>
                  <option v-for="(col, i) in importDataColumns" :key="i" :value="i">Colonne {{ i + 1 }} : {{ col }}</option>
                </select>
              </div>
              <div class="mapping-row">
                <label class="form-label" style="min-width: 120px;">Prénom :</label>
                <select v-model="importColMapping.prenom" class="form-control" style="max-width: 250px;">
                  <option value="">-- Même colonne que le nom --</option>
                  <option value="__last__">Dernière partie après le séparateur</option>
                  <option v-for="(col, i) in importDataColumns" :key="i" :value="i">Colonne {{ i + 1 }} : {{ col }}</option>
                </select>
              </div>
              <div class="mapping-row" v-if="importColMapping.nom !== '' && importColMapping.prenom === ''">
                <label class="form-label" style="min-width: 120px;">Séparateur :</label>
                <select v-model="importNameSeparator" class="form-control" style="max-width: 250px;">
                  <option value=" ">Espace</option>
                  <option value=",">Virgule (,)</option>
                  <option value="-">Tiret (-)</option>
                </select>
                <span class="text-muted" style="font-size: 0.75rem; margin-left: 0.5rem;">Séparateur entre nom et prénom dans la même colonne</span>
              </div>
              <!-- Notes mapping -->
              <div v-if="evaluations.length > 0" style="margin-top: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
                <p><strong>Notes des évaluations :</strong></p>
                <div class="mapping-row" v-for="(ev, idx) in evaluations" :key="ev.id">
                  <label class="form-label" style="min-width: 120px;">{{ ev.nom }} :</label>
                  <select v-model="importColMapping['eval_' + idx]" class="form-control" style="max-width: 250px;">
                    <option value="">-- Aucune colonne --</option>
                    <option v-for="(col, ci) in importDataColumns" :key="ci" :value="ci">Colonne {{ ci + 1 }} : {{ col }}</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Aperçu des données -->
            <div v-if="importDataPreview.length > 0" class="import-preview">
              <p><strong>Aperçu ({{ importDataPreview.length }} élève(s) détecté(s)) :</strong></p>
              <table class="data-table" style="font-size: 0.75rem;">
                <thead>
                  <tr>
                    <th>N°</th>
                    <th>Identifiant</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th v-for="(e, i) in evaluations" :key="e.id">{{ e.nom }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in importDataPreview.slice(0, 10)" :key="i">
                    <td>{{ row.numero_ordre }}</td>
                    <td>{{ row.identifiant }}</td>
                    <td>{{ row.nom }}</td>
                    <td>{{ row.prenom }}</td>
                    <td v-for="(note, ni) in row.notes" :key="ni">{{ note !== null && note !== undefined ? note : '-' }}</td>
                  </tr>
                  <tr v-if="importDataPreview.length > 10">
                    <td :colspan="4 + evaluations.length" class="text-muted">... et {{ importDataPreview.length - 10 }} autre(s)</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="importDataResult" class="import-result" :class="(importDataResult.imported > 0 || importDataResult.updated > 0) ? 'import-success' : 'import-error'">
              <p v-if="importDataResult.imported > 0">✅ {{ importDataResult.imported }} élève(s) importé(s) avec succès.</p>
              <p v-if="importDataResult.updated > 0">🔄 {{ importDataResult.updated }} élève(s) mis à jour.</p>
              <p v-if="importDataResult.errors && importDataResult.errors.length > 0" class="text-danger">
                ⚠️ {{ importDataResult.errors.length }} erreur(s) :
                <ul>
                  <li v-for="(err, i) in importDataResult.errors" :key="i">{{ err }}</li>
                </ul>
              </p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showImportDataModal = false">Fermer</button>
              <button type="button" class="btn btn-primary" @click="importData" :disabled="importingData || importDataPreview.length === 0">
                {{ importingData ? 'Importation...' : 'Importer' }}
              </button>
            </div>
          </div>
        </div>
        
        <!-- Modal Ajout Élève -->
        <div v-if="showAddEleveModal" class="modal-overlay" @click.self="showAddEleveModal = false">
          <div class="modal-content">
            <div class="modal-header">
              <h2>Ajouter un élève</h2>
              <button class="btn btn-sm btn-outline" @click="showAddEleveModal = false">✕</button>
            </div>
            <form @submit.prevent="addEleve">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Identifiant (16 chiffres)</label>
                  <input type="text" v-model="eleveForm.identifiant" class="form-control" required />
                </div>
                <div class="form-group">
                  <label class="form-label">N° ordre</label>
                  <input type="number" v-model="eleveForm.numero_ordre" class="form-control" required />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Nom</label>
                  <input type="text" v-model="eleveForm.nom" class="form-control" required />
                </div>
                <div class="form-group">
                  <label class="form-label">Prénom</label>
                  <input type="text" v-model="eleveForm.prenom" class="form-control" required />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Nom tuteur</label>
                  <input type="text" v-model="eleveForm.nom_tuteur" class="form-control" />
                </div>
                <div class="form-group">
                  <label class="form-label">Prénom tuteur</label>
                  <input type="text" v-model="eleveForm.prenom_tuteur" class="form-control" />
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" @click="showAddEleveModal = false">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Modal Ajout Évaluation -->
        <div v-if="showAddEvalModal" class="modal-overlay" @click.self="showAddEvalModal = false">
          <div class="modal-content">
            <div class="modal-header">
              <h2>Ajouter une évaluation</h2>
              <button class="btn btn-sm btn-outline" @click="showAddEvalModal = false">✕</button>
            </div>
            <form @submit.prevent="addEvaluation">
              <div class="form-group">
                <label class="form-label">Nom</label>
                <input type="text" v-model="evalForm.nom" class="form-control" placeholder="ex: Devoir 1" required />
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Barème</label>
                  <input type="number" v-model="evalForm.bareme" class="form-control" placeholder="20" />
                </div>
                <div class="form-group">
                  <label class="form-label">Coefficient</label>
                  <input type="number" v-model="evalForm.coefficient" class="form-control" step="0.01" placeholder="1" />
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Date (optionnelle)</label>
                <input type="date" v-model="evalForm.date_evaluation" class="form-control" />
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" @click="showAddEvalModal = false">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Modal Ajout Épreuve -->
        <div v-if="showAddEpreuveModal" class="modal-overlay" @click.self="showAddEpreuveModal = false">
          <div class="modal-content">
            <div class="modal-header">
              <h2>Ajouter une épreuve</h2>
              <button class="btn btn-sm btn-outline" @click="showAddEpreuveModal = false">✕</button>
            </div>
            <form @submit.prevent="addEpreuve">
              <div class="form-group">
                <label class="form-label">Nom</label>
                <input type="text" v-model="epreuveForm.nom" class="form-control" placeholder="ex: DC, DS" required />
              </div>
              <div class="form-group">
                <label class="form-label">Coefficient</label>
                <input type="number" v-model="epreuveForm.coefficient" class="form-control" step="0.01" placeholder="1" />
              </div>
              <div class="form-group">
                <label class="form-label">Formule</label>
                  <p class="form-help">Sélectionnez les évaluations et leurs coefficients :</p>
                <div v-for="e in evaluations" :key="e.id" class="formule-item">
                  <label class="checkbox-label">
                    <input type="checkbox" :value="e.id" v-model="selectedEvalsForFormule" />
                    {{ e.nom }}
                  </label>
                  <input
                    v-if="selectedEvalsForFormule.includes(e.id)"
                    type="number"
                    v-model="formuleCoefs[e.id]"
                    class="inline-edit"
                    step="0.1"
                    min="0"
                    max="1"
                    placeholder="Coef"
                  />
                </div>
                <div v-if="selectedEvalsForFormule.length === 0" class="text-muted" style="font-size: 0.8rem; margin-top: 0.5rem;">
                  Aucune évaluation sélectionnée
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" @click="showAddEpreuveModal = false">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
              </div>
            </form>
          </div>
        </div>
      </template>
      
      <div v-else class="card text-center" style="padding: 3rem;">
        <p class="text-muted">Feuille non trouvée.</p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/api/client'
import Sidebar from '@/components/Sidebar.vue'

const route = useRoute()
const feuilleId = computed(() => route.params.id)

const loading = ref(true)
const feuille = ref(null)
const eleves = ref([])
const evaluations = ref([])
const epreuves = ref([])

// Tri
const sortKey = ref('')
const sortDirection = ref('asc')

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDirection.value = 'asc'
  }
}

function getSortValue(eleve, key) {
  if (key === 'numero_ordre') return eleve.numero_ordre || 0
  if (key === 'nom') return (eleve.nom || '').toLowerCase() + ' ' + (eleve.prenom || '').toLowerCase()
  if (key === 'moyenne') return eleve.moyenne ?? -1
  if (key === 'rang') {
    if (eleve.rang === '-' || eleve.rang === 0) return 9999
    return eleve.rang
  }
  if (key === 'observation') return (eleve.observation || '').toLowerCase()
  if (key.startsWith('ep_')) {
    const epId = key.substring(3)
    return eleve.notes_epreuves?.[epId] ?? -1
  }
  if (key.startsWith('eval_')) {
    const evalId = key.substring(5)
    return eleve.notes_evaluations?.[evalId] ?? -1
  }
  return ''
}

const sortedEleves = computed(() => {
  if (!sortKey.value) return eleves.value
  const list = [...eleves.value]
  const dir = sortDirection.value === 'asc' ? 1 : -1
  list.sort((a, b) => {
    const va = getSortValue(a, sortKey.value)
    const vb = getSortValue(b, sortKey.value)
    if (typeof va === 'string') return va.localeCompare(vb, 'fr') * dir
    return (va - vb) * dir
  })
  return list
})

// Modals
const showAddEleveModal = ref(false)
const showAddEvalModal = ref(false)
const showAddEpreuveModal = ref(false)
const showImportDataModal = ref(false)

// Import données
const importDataText = ref('')
const importingData = ref(false)
const importDataResult = ref(null)
const importColMapping = ref({ identifiant: '', numero_ordre: '', nom: '', prenom: '' })
const importNameSeparator = ref(' ')

// Forms
const eleveForm = ref({ identifiant: '', numero_ordre: 1, nom: '', prenom: '', nom_tuteur: '', prenom_tuteur: '' })
const evalForm = ref({ nom: '', bareme: 20, coefficient: 1, date_evaluation: '' })
const epreuveForm = ref({ nom: '', coefficient: 1 })
const selectedEvalsForFormule = ref([])
const formuleCoefs = ref({})

// Import données - Colonnes détectées
const importDataColumns = computed(() => {
  if (!importDataText.value.trim()) return []
  const firstLine = importDataText.value.trim().split('\n')[0]
  const cols = firstLine.split('\t')
  return cols.map(c => c.trim()).filter(c => c.length > 0)
})

// Import données - Aperçu avec mapping flexible
const importDataPreview = computed(() => {
  if (!importDataText.value.trim()) return []
  const lines = importDataText.value.trim().split('\n')
  const rows = []
  const mapping = importColMapping.value

  for (const line of lines) {
    const cols = line.split('\t')
    if (cols.length < 2) continue

    const identifiant = mapping.identifiant !== '' ? (cols[mapping.identifiant] || '').trim() : ''
    const numeroOrdre = mapping.numero_ordre !== '' ? parseInt(cols[mapping.numero_ordre]) : 0
    const nomRaw = mapping.nom !== '' ? (cols[mapping.nom] || '').trim() : ''
    const prenomRaw = mapping.prenom !== '' ? (cols[mapping.prenom] || '').trim() : ''

    let nom = nomRaw
    let prenom = prenomRaw

    // Gérer la fusion nom/prénom dans la même colonne
    if (mapping.prenom === '' && nomRaw) {
      const sep = importNameSeparator.value
      const parts = nomRaw.split(sep)
      if (parts.length >= 2) {
        nom = parts[0].trim()
        prenom = parts.slice(1).join(sep).trim()
      }
    } else if (mapping.prenom === '__last__') {
      const sep = importNameSeparator.value
      const parts = nomRaw.split(sep)
      if (parts.length >= 2) {
        nom = parts[0].trim()
        prenom = parts.slice(1).join(sep).trim()
      }
    }

    if (!identifiant && !nom) continue

    // Récupérer les notes des évaluations
    const notes = []
    for (let i = 0; i < evaluations.value.length; i++) {
      const colIndex = mapping['eval_' + i]
      if (colIndex !== undefined && colIndex !== '' && cols[colIndex] !== undefined) {
        const val = cols[colIndex].trim()
        if (val === '' || val === '-' || val === 'null') {
          notes.push(null)
        } else {
          const num = parseFloat(val.replace(',', '.'))
          notes.push(isNaN(num) ? null : num)
        }
      } else {
        notes.push(null)
      }
    }

    rows.push({
      numero_ordre: numeroOrdre || (rows.length + 1),
      identifiant: identifiant,
      nom: nom,
      prenom: prenom,
      notes: notes
    })
  }
  return rows
})

function openImportDataModal() {
  importDataText.value = ''
  importDataResult.value = null
  importColMapping.value = { identifiant: '', numero_ordre: '', nom: '', prenom: '' }
  importNameSeparator.value = ' '
  // Reset eval mapping
  for (let i = 0; i < 20; i++) {
    importColMapping.value['eval_' + i] = ''
  }
  showImportDataModal.value = true
}

async function importData() {
  if (importDataPreview.value.length === 0) return
  importingData.value = true
  importDataResult.value = null
  try {
    const res = await apiClient.post(`/feuilles/${feuilleId.value}/import-data`, {
      rows: importDataPreview.value
    })
    importDataResult.value = res.data.data
    if (res.data.data.imported > 0 || res.data.data.updated > 0) {
      await loadFeuille()
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur lors de l\'importation')
  } finally {
    importingData.value = false
  }
}

function formatNote(note) {
  if (note === null || note === undefined || note === '') return ''
  return note.toFixed(2)
}

function getMoyenneClass(moyenne) {
  if (moyenne === null) return ''
  if (moyenne >= 16) return 'moyenne-excellent'
  if (moyenne >= 14) return 'moyenne-tb'
  if (moyenne >= 12) return 'moyenne-bien'
  if (moyenne >= 10) return 'moyenne-passable'
  return 'moyenne-insuffisant'
}

function getRangBadgeClass(rang) {
  if (rang === '-' || rang === 0) return 'badge-secondary'
  if (rang <= 3) return 'badge-success'
  return 'badge-primary'
}

function getObservationClass(moyenne) {
  if (moyenne >= 18) return 'badge-success'
  if (moyenne >= 14) return 'badge-primary'
  if (moyenne >= 12) return 'badge-info'
  if (moyenne >= 10) return 'badge-warning'
  return 'badge-danger'
}

async function loadFeuille() {
  loading.value = true
  try {
    const res = await apiClient.get(`/feuilles/${feuilleId.value}`)
    const data = res.data.data
    feuille.value = data.feuille
    eleves.value = data.eleves || []
    evaluations.value = data.evaluations || []
    epreuves.value = data.epreuves || []
  } catch (e) {
    console.error('Erreur chargement feuille:', e)
  } finally {
    loading.value = false
  }
}

async function saveNote(eleveId, evalId, value) {
  const note = value === '' ? 'null' : parseFloat(value)
  try {
    await apiClient.post('/notes-evaluations', {
      eleve_id: eleveId,
      evaluation_id: evalId,
      note: note
    })
    await loadFeuille()
  } catch (e) {
    console.error('Erreur enregistrement note:', e)
    alert(e.response?.data?.message || 'Erreur lors de l\'enregistrement')
  }
}

async function addEleve() {
  try {
    await apiClient.post('/eleves', {
      ...eleveForm.value,
      feuille_id: feuilleId.value
    })
    showAddEleveModal.value = false
    eleveForm.value = { identifiant: '', numero_ordre: 1, nom: '', prenom: '', nom_tuteur: '', prenom_tuteur: '' }
    await loadFeuille()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur')
  }
}

async function addEvaluation() {
  try {
    await apiClient.post('/evaluations', {
      ...evalForm.value,
      bareme: parseFloat(evalForm.value.bareme) || 20,
      coefficient: parseFloat(evalForm.value.coefficient) || 1,
      feuille_id: feuilleId.value
    })
    showAddEvalModal.value = false
    evalForm.value = { nom: '', bareme: 20, coefficient: 1, date_evaluation: '' }
    await loadFeuille()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur')
  }
}

async function addEpreuve() {
  try {
    const formuleItems = selectedEvalsForFormule.value.map(evalId => ({
      eval: evalId,
      coef: parseFloat(formuleCoefs.value[evalId]) || 0.5
    }))
    
    await apiClient.post('/epreuves', {
      feuille_id: feuilleId.value,
      nom: epreuveForm.value.nom,
      coefficient: parseFloat(epreuveForm.value.coefficient) || 1,
      formule: JSON.stringify(formuleItems)
    })
    showAddEpreuveModal.value = false
    epreuveForm.value = { nom: '', coefficient: 1 }
    selectedEvalsForFormule.value = []
    formuleCoefs.value = {}
    await loadFeuille()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur')
  }
}

async function deleteEvaluation(id) {
  if (!confirm('Supprimer cette évaluation ?')) return
  try {
    await apiClient.delete(`/evaluations/${id}`)
    await loadFeuille()
  } catch (e) {
    alert('Erreur lors de la suppression')
  }
}

async function deleteEpreuve(id) {
  if (!confirm('Supprimer cette épreuve ?')) return
  try {
    await apiClient.delete(`/epreuves/${id}`)
    await loadFeuille()
  } catch (e) {
    alert('Erreur lors de la suppression')
  }
}

async function exportCsv() {
  window.open(`/api/feuilles/${feuilleId.value}/export/csv`, '_blank')
}

async function exportJson() {
  window.open(`/api/feuilles/${feuilleId.value}/export/json`, '_blank')
}

async function duplicateFeuille() {
  if (!confirm(`Dupliquer cette feuille ?\n\nUne copie sera créée avec les mêmes élèves et évaluations, sans notes.`)) return
  
  try {
    const res = await apiClient.post(`/feuilles/${feuilleId.value}/duplicate`)
    alert(res.data.message || 'Feuille dupliquée avec succès !')
    if (res.data.data && res.data.data.id) {
      window.location.href = `/GestionNotes/feuilles/${res.data.data.id}`
    } else {
      window.location.href = `/GestionNotes/feuilles`
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur lors de la duplication')
  }
}

onMounted(loadFeuille)
</script>

<style scoped>
.config-bar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
}

.table-container {
  max-height: calc(100vh - 280px);
  overflow: auto;
}

.data-table {
  font-size: 0.8rem;
}

.data-table th {
  position: sticky;
  top: 0;
  z-index: 20;
}

.sticky-col {
  position: sticky;
  left: 0;
  z-index: 15;
  background: var(--bg-primary);
}

th.sticky-col {
  z-index: 25;
  background: var(--bg-tertiary);
}

.col-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.125rem;
  position: relative;
}

.col-meta {
  font-size: 0.65rem;
  color: var(--text-muted);
  font-weight: 400;
  text-transform: none;
  letter-spacing: normal;
}

.btn-del-col {
  position: absolute;
  top: -6px;
  right: -6px;
  background: var(--danger-color);
  color: white;
  border: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 0.7rem;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s;
}

th:hover .btn-del-col {
  opacity: 1;
}

.eleve-name {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.note-cell {
  text-align: right;
}

.note-cell.calculated {
  font-weight: 600;
  color: var(--primary-color);
}

.moyenne-cell {
  text-align: center;
  font-weight: 700;
}

.moyenne-excellent { color: #059669; }
.moyenne-tb { color: #2563eb; }
.moyenne-bien { color: #7c3aed; }
.moyenne-passable { color: #d97706; }
.moyenne-insuffisant { color: var(--danger-color); }

.rang-cell {
  text-align: center;
}

.sortable-col {
  cursor: pointer;
  user-select: none;
  transition: background 0.15s;
}

.sortable-col:hover {
  background: var(--bg-tertiary);
}

.form-help {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin-bottom: 0.5rem;
}

.formule-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.375rem 0;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--font-size-sm);
  cursor: pointer;
}

.import-instructions {
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: var(--bg-secondary);
  border-radius: var(--radius-md);
  font-size: var(--font-size-sm);
}

.import-instructions ol {
  margin: 0.5rem 0;
  padding-left: 1.25rem;
}

.import-preview {
  margin: 1rem 0;
  max-height: 200px;
  overflow: auto;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 0.5rem;
}

.import-result {
  margin: 1rem 0;
  padding: 0.75rem;
  border-radius: var(--radius-md);
}

.import-result ul {
  margin: 0.25rem 0 0 0;
  padding-left: 1.25rem;
  font-size: 0.8rem;
}

.import-success {
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
}

.import-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
}

.import-mapping {
  margin: 1rem 0;
  padding: 0.75rem;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
}

.mapping-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
  .table-container {
    max-height: none;
  }
}
</style>