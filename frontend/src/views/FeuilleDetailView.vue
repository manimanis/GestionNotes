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
        
        <!-- Validation Bar -->
        <div v-if="hasChanges" class="validation-bar">
          <span class="validation-info">📝 Modifications non sauvegardées</span>
          <div class="validation-actions">
            <button class="btn btn-sm btn-secondary" @click="cancelChanges" :disabled="saving">Annuler</button>
            <button class="btn btn-sm btn-success" @click="validateChanges" :disabled="saving">
              {{ saving ? '⏳ Sauvegarde...' : '✅ Valider' }}
            </button>
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
                  <div class="eleve-name clickable" @click="openEditEleve(eleve)" title="Cliquer pour modifier">
                    <strong>{{ eleve.nom }}</strong> {{ eleve.prenom }}
                  </div>
                </td>
                <td v-for="e in evaluations" :key="e.id" class="note-cell">
                  <input
                    type="number"
                    class="inline-edit"
                    :value="getNoteValue(eleve.id, e.id)"
                    @change="saveNote(eleve.id, e.id, $event.target.value)"
                    min="0"
                    :max="e.bareme"
                    step="0.01"
                  />
                </td>
                <td v-for="ep in epreuves" :key="ep.id" class="note-cell calculated">
                  {{ formatNote(getEpreuveNote(eleve, ep.id)) }}
                </td>
                <td class="moyenne-cell" :class="getMoyenneClass(eleve.moyenne)">
                  <strong>{{ eleve.moyenne !== null && eleve.moyenne !== undefined ? eleve.moyenne.toFixed(2) : '-' }}</strong>
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
        
        <!-- Modal Import Données -->
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
            </div>
            <div class="form-group">
              <label class="form-label">Données copiées depuis Excel</label>
              <textarea
                v-model="importDataText"
                class="form-control"
                rows="8"
                placeholder="Collez ici les données depuis Excel (séparées par des tabulations)"
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
              <div v-if="importColMapping.nom !== '' && importColMapping.prenom === ''" class="mapping-row">
                <label class="form-label" style="min-width: 120px;">Séparateur :</label>
                <select v-model="importNameSeparator" class="form-control" style="max-width: 250px;">
                  <option value=" ">Espace</option>
                  <option value=",">Virgule (,)</option>
                  <option value="-">Tiret (-)</option>
                </select>
              </div>
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

            <div v-if="importDataPreview.length > 0" class="import-preview">
              <p><strong>Aperçu ({{ importDataPreview.length }} élève(s) détecté(s)) :</strong></p>
              <table class="data-table" style="font-size: 0.75rem;">
                <thead>
                  <tr>
                    <th>N°</th>
                    <th>Identifiant</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th v-for="e in evaluations" :key="e.id">{{ e.nom }}</th>
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

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showImportDataModal = false">Fermer</button>
              <button type="button" class="btn btn-primary" @click="importData" :disabled="importDataPreview.length === 0">
                Importer
              </button>
            </div>
          </div>
        </div>
        
        <!-- Modal Édition Élève -->
        <div v-if="showEditEleveModal" class="modal-overlay" @click.self="showEditEleveModal = false">
          <div class="modal-content">
            <div class="modal-header">
              <h2>Modifier l'élève</h2>
              <button class="btn btn-sm btn-outline" @click="showEditEleveModal = false">✕</button>
            </div>
            <form @submit.prevent="updateEleve">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Identifiant (16 chiffres)</label>
                  <input type="text" v-model="editEleveForm.identifiant" class="form-control" required />
                </div>
                <div class="form-group">
                  <label class="form-label">N° ordre</label>
                  <input type="number" v-model="editEleveForm.numero_ordre" class="form-control" required />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Nom</label>
                  <input type="text" v-model="editEleveForm.nom" class="form-control" required />
                </div>
                <div class="form-group">
                  <label class="form-label">Prénom</label>
                  <input type="text" v-model="editEleveForm.prenom" class="form-control" required />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Nom tuteur</label>
                  <input type="text" v-model="editEleveForm.nom_tuteur" class="form-control" />
                </div>
                <div class="form-group">
                  <label class="form-label">Prénom tuteur</label>
                  <input type="text" v-model="editEleveForm.prenom_tuteur" class="form-control" />
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-danger" @click="deleteEleveFromModal">🗑️ Supprimer</button>
                <div class="flex gap-1">
                  <button type="button" class="btn btn-secondary" @click="showEditEleveModal = false">Annuler</button>
                  <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
              </div>
            </form>
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
                  Aucune évaluation sélectionnée — une évaluation sera créée automatiquement
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
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/api/client'
import Sidebar from '@/components/Sidebar.vue'

const route = useRoute()
const feuilleId = computed(() => route.params.id)

// ============================================
// ÉTAT RÉACTIF
// ============================================
const loading = ref(true)
const saving = ref(false)
const feuille = ref(null)
const eleves = ref([])
const evaluations = ref([])
const epreuves = ref([])
const notesEval = ref({}) // { "eleveId:evalId": noteValue }
const hasChanges = ref(false)

// Tri
const sortKey = ref('')
const sortDirection = ref('asc')

// Modals
const showAddEleveModal = ref(false)
const showAddEvalModal = ref(false)
const showAddEpreuveModal = ref(false)
const showImportDataModal = ref(false)
const showEditEleveModal = ref(false)
const editEleveForm = ref({ id: '', identifiant: '', numero_ordre: 1, nom: '', prenom: '', nom_tuteur: '', prenom_tuteur: '' })

// Import
const importDataText = ref('')
const importColMapping = ref({ identifiant: '', numero_ordre: '', nom: '', prenom: '' })
const importNameSeparator = ref(' ')

// Forms
const eleveForm = ref({ identifiant: '', numero_ordre: 1, nom: '', prenom: '', nom_tuteur: '', prenom_tuteur: '' })
const evalForm = ref({ nom: '', bareme: 20, coefficient: 1, date_evaluation: '' })
const epreuveForm = ref({ nom: '', coefficient: 1 })
const selectedEvalsForFormule = ref([])
const formuleCoefs = ref({})

// ============================================
// FONCTIONS DE CALCUL EN MÉMOIRE
// ============================================

function generateUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0
    const v = c === 'x' ? r : (r & 0x3 | 0x8)
    return v.toString(16)
  })
}

function calculateEpreuveNote(formule, studentNotesEval, evalsData) {
  let config
  try {
    config = JSON.parse(formule)
  } catch { return null }
  
  if (!config || !Array.isArray(config) || config.length === 0) return null

  const evalIndex = {}
  evalsData.forEach(e => { evalIndex[e.id] = e })

  let totalNote = 0, totalCoef = 0

  for (const item of config) {
    const evalId = item.eval
    const coef = parseFloat(item.coef) || 1
    const noteVal = studentNotesEval[evalId]

    if (!evalId || noteVal === null || noteVal === undefined) continue

    let note = parseFloat(noteVal)
    const bareme = evalIndex[evalId] ? parseFloat(evalIndex[evalId].bareme) : 20

    if (bareme > 0 && bareme !== 20) {
      note = (note / bareme) * 20
    }

    totalNote += note * coef
    totalCoef += coef
  }

  if (totalCoef === 0) return null
  return Math.round((totalNote / totalCoef) * 100) / 100
}

function calculateWeightedAverage(notesEpreuvesData, epreuvesData) {
  let totalNote = 0, totalCoef = 0

  for (const ep of epreuvesData) {
    const coef = parseFloat(ep.coefficient)
    const noteVal = notesEpreuvesData[ep.id]

    if (noteVal !== null && noteVal !== undefined) {
      totalNote += parseFloat(noteVal) * coef
      totalCoef += coef
    }
  }

  if (totalCoef === 0) return null
  return Math.round((totalNote / totalCoef) * 100) / 100
}

function generateObservation(moyenne) {
  if (moyenne === null || moyenne === undefined) return 'Notes insuffisantes'
  if (moyenne >= 16) return 'Excellent'
  if (moyenne >= 14) return 'Très bien'
  if (moyenne >= 12) return 'Bien'
  if (moyenne >= 10) return 'Passable'
  return 'Insuffisant'
}

function recalculateAll() {
  // Recalculate all grades for all students
  for (const eleve of eleves.value) {
    // Build notesEval for this student
    const studentNotes = {}
    for (const ev of evaluations.value) {
      const key = eleve.id + ':' + ev.id
      studentNotes[ev.id] = notesEval.value[key] !== undefined ? notesEval.value[key] : null
    }

    // Calculate épreuve notes
    const epreuveNotes = {}
    for (const ep of epreuves.value) {
      epreuveNotes[ep.id] = calculateEpreuveNote(ep.formule, studentNotes, evaluations.value)
    }
    eleve.notes_epreuves = epreuveNotes

    // Calculate average
    eleve.moyenne = calculateWeightedAverage(epreuveNotes, epreuves.value)

    // Generate observation
    eleve.observation = generateObservation(eleve.moyenne)
  }

  // Calculate ranks
  calculateRanks()
}

function calculateRanks() {
  const sorted = [...eleves.value].filter(e => e.moyenne !== null && e.moyenne !== undefined)
    .sort((a, b) => b.moyenne - a.moyenne)

  const allSorted = [...eleves.value]

  let currentRang = 1, previousMoyenne = null, skipCount = 0

  for (const eleve of sorted) {
    const moyenne = eleve.moyenne
    if (previousMoyenne !== null && moyenne < previousMoyenne) {
      currentRang += skipCount
      skipCount = 1
    } else if (previousMoyenne !== null && moyenne === previousMoyenne) {
      skipCount++
    } else {
      skipCount = 1
    }
    eleve.rang = currentRang
    previousMoyenne = moyenne
  }

  // Set '-' for students without moyenne
  for (const eleve of allSorted) {
    if (eleve.moyenne === null || eleve.moyenne === undefined) {
      eleve.rang = '-'
    }
  }
}

// ============================================
// CHARGEMENT DES DONNÉES
// ============================================

async function loadFeuille() {
  loading.value = true
  try {
    const res = await apiClient.get(`/feuilles/${feuilleId.value}`)
    const data = res.data.data
    feuille.value = data.feuille
    eleves.value = data.eleves || []
    evaluations.value = data.evaluations || []
    epreuves.value = data.epreuves || []

    // Flatten notes_evaluations
    const ne = {}
    for (const eleve of eleves.value) {
      if (eleve.notes_evaluations) {
        for (const [evalId, note] of Object.entries(eleve.notes_evaluations)) {
          ne[eleve.id + ':' + evalId] = note
        }
        
      }
    }
    notesEval.value = ne

    hasChanges.value = false
  } catch (e) {
    console.error('Erreur chargement feuille:', e)
  } finally {
    loading.value = false
  }
}

// ============================================
// VALIDATION / ANNULATION
// ============================================

async function validateChanges() {
  saving.value = true
  try {
    await apiClient.post(`/feuilles/${feuilleId.value}/validate`, {
      eleves: eleves.value.map(e => ({
        id: e.id,
        identifiant: e.identifiant,
        numero_ordre: e.numero_ordre,
        nom: e.nom,
        prenom: e.prenom,
        nom_tuteur: e.nom_tuteur || null,
        prenom_tuteur: e.prenom_tuteur || null
      })),
      evaluations: evaluations.value.map(e => ({
        id: e.id,
        nom: e.nom,
        date_evaluation: e.date_evaluation || null,
        bareme: e.bareme,
        coefficient: e.coefficient,
        ordre: e.ordre || 0
      })),
      epreuves: epreuves.value.map(e => ({
        id: e.id,
        nom: e.nom,
        formule: e.formule,
        coefficient: e.coefficient,
        ordre: e.ordre || 0
      })),
      notes_evaluations: notesEval.value
    })
    await loadFeuille()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur lors de la sauvegarde')
  } finally {
    saving.value = false
  }
}

function cancelChanges() {
  if (!confirm('Annuler toutes les modifications non sauvegardées ?')) return
  loadFeuille()
}

// ============================================
// OPÉRATIONS CRUD EN MÉMOIRE
// ============================================

function saveNote(eleveId, evalId, value) {
  const note = value === '' ? null : parseFloat(value)
  const key = eleveId + ':' + evalId
  notesEval.value[key] = note
  hasChanges.value = true
  recalculateAll()
}

function addEleve() {
  const newId = generateUUID()
  const maxOrdre = eleves.value.reduce((max, e) => Math.max(max, e.numero_ordre || 0), 0)
  eleves.value.push({
    id: newId,
    feuille_id: feuilleId.value,
    identifiant: eleveForm.value.identifiant,
    numero_ordre: parseInt(eleveForm.value.numero_ordre) || (maxOrdre + 1),
    nom: eleveForm.value.nom,
    prenom: eleveForm.value.prenom,
    nom_tuteur: eleveForm.value.nom_tuteur || null,
    prenom_tuteur: eleveForm.value.prenom_tuteur || null,
    notes_evaluations: {},
    notes_epreuves: {},
    moyenne: null,
    observation: 'Notes insuffisantes',
    rang: '-'
  })
  showAddEleveModal.value = false
  eleveForm.value = { identifiant: '', numero_ordre: 1, nom: '', prenom: '', nom_tuteur: '', prenom_tuteur: '' }
  hasChanges.value = true
  recalculateAll()
}

function updateEleve() {
  const idx = eleves.value.findIndex(e => e.id === editEleveForm.value.id)
  if (idx !== -1) {
    eleves.value[idx].identifiant = editEleveForm.value.identifiant
    eleves.value[idx].numero_ordre = parseInt(editEleveForm.value.numero_ordre)
    eleves.value[idx].nom = editEleveForm.value.nom
    eleves.value[idx].prenom = editEleveForm.value.prenom
    eleves.value[idx].nom_tuteur = editEleveForm.value.nom_tuteur || null
    eleves.value[idx].prenom_tuteur = editEleveForm.value.prenom_tuteur || null
    hasChanges.value = true
  }
  showEditEleveModal.value = false
}

function deleteEleveFromModal() {
  if (!confirm(`Supprimer l'élève ${editEleveForm.value.nom} ${editEleveForm.value.prenom} ?\n\nCette action sera appliquée lors de la validation.`)) return
  eleves.value = eleves.value.filter(e => e.id !== editEleveForm.value.id)
  showEditEleveModal.value = false
  hasChanges.value = true
  recalculateAll()
}

function addEvaluation() {
  const newId = generateUUID()
  const maxOrdre = evaluations.value.reduce((max, e) => Math.max(max, e.ordre || 0), 0)
  evaluations.value.push({
    id: newId,
    feuille_id: feuilleId.value,
    nom: evalForm.value.nom,
    date_evaluation: evalForm.value.date_evaluation || null,
    bareme: parseFloat(evalForm.value.bareme) || 20,
    coefficient: parseFloat(evalForm.value.coefficient) || 1,
    ordre: maxOrdre + 1
  })
  showAddEvalModal.value = false
  evalForm.value = { nom: '', bareme: 20, coefficient: 1, date_evaluation: '' }
  hasChanges.value = true
  recalculateAll()
}

function addEpreuve() {
  const newId = generateUUID()
  const maxOrdre = epreuves.value.reduce((max, e) => Math.max(max, e.ordre || 0), 0)

  let formuleItems = selectedEvalsForFormule.value.map(evalId => ({
    eval: evalId,
    coef: parseFloat(formuleCoefs.value[evalId]) || 0.5
  }))

  // Si aucune évaluation sélectionnée, créer automatiquement une évaluation
  if (formuleItems.length === 0) {
    const evalId = generateUUID()
    const evalMaxOrdre = evaluations.value.reduce((max, e) => Math.max(max, e.ordre || 0), 0)
    evaluations.value.push({
      id: evalId,
      feuille_id: feuilleId.value,
      nom: epreuveForm.value.nom,
      date_evaluation: null,
      bareme: 20,
      coefficient: parseFloat(epreuveForm.value.coefficient) || 1,
      ordre: evalMaxOrdre + 1
    })
    formuleItems = [{ eval: evalId, coef: 1 }]
  }

  epreuves.value.push({
    id: newId,
    feuille_id: feuilleId.value,
    nom: epreuveForm.value.nom,
    formule: JSON.stringify(formuleItems),
    coefficient: parseFloat(epreuveForm.value.coefficient) || 1,
    ordre: maxOrdre + 1
  })

  showAddEpreuveModal.value = false
  epreuveForm.value = { nom: '', coefficient: 1 }
  selectedEvalsForFormule.value = []
  formuleCoefs.value = {}
  hasChanges.value = true
  recalculateAll()
}

function deleteEvaluation(id) {
  if (!confirm('Supprimer cette évaluation ? Cette action sera appliquée lors de la validation.')) return
  evaluations.value = evaluations.value.filter(e => e.id !== id)
  // Supprimer les notes associées
  for (const key of Object.keys(notesEval.value)) {
    if (key.endsWith(':' + id)) {
      delete notesEval.value[key]
    }
  }
  hasChanges.value = true
  recalculateAll()
}

function deleteEpreuve(id) {
  if (!confirm('Supprimer cette épreuve ? Cette action sera appliquée lors de la validation.')) return
  epreuves.value = epreuves.value.filter(e => e.id !== id)
  hasChanges.value = true
  recalculateAll()
}

// ============================================
// IMPORT DE DONNÉES EN MÉMOIRE
// ============================================

function openImportDataModal() {
  importDataText.value = ''
  importColMapping.value = { identifiant: '', numero_ordre: '', nom: '', prenom: '' }
  importNameSeparator.value = ' '
  showImportDataModal.value = true
}

const importDataColumns = computed(() => {
  if (!importDataText.value.trim()) return []
  const firstLine = importDataText.value.trim().split('\n')[0]
  return firstLine.split('\t').map(c => c.trim()).filter(c => c.length > 0)
})

const importDataPreview = computed(() => {
  if (!importDataText.value.trim()) return []
  const lines = importDataText.value.trim().split('\n')
  const rows = []
  const mapping = importColMapping.value

  for (const line of lines) {
    const cols = line.split('\t')
    if (cols.length < 2) continue

    const identifiant = formatIdentifiant(mapping.identifiant !== '' ? (cols[mapping.identifiant] || '').trim() : '')
    const numeroOrdre = mapping.numero_ordre !== '' ? parseInt(cols[mapping.numero_ordre]) : 0
    const nomRaw = mapping.nom !== '' ? (cols[mapping.nom] || '').trim() : ''
    const prenomRaw = mapping.prenom !== '' ? (cols[mapping.prenom] || '').trim() : ''

    let nom = nomRaw
    let prenom = prenomRaw

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

    rows.push({ numero_ordre: numeroOrdre || (rows.length + 1), identifiant, nom, prenom, notes })
  }
  return rows
})

function importData() {
  if (importDataPreview.value.length === 0) return

  let imported = 0, updated = 0

  for (const row of importDataPreview.value) {
    // Chercher un élève existant par identifiant
    const existingIdx = eleves.value.findIndex(e => e.identifiant === row.identifiant)

    if (existingIdx !== -1) {
      // Mettre à jour
      eleves.value[existingIdx].numero_ordre = row.numero_ordre
      eleves.value[existingIdx].nom = row.nom
      eleves.value[existingIdx].prenom = row.prenom
      updated++

      // Appliquer les notes
      for (let i = 0; i < evaluations.value.length; i++) {
        if (i < row.notes.length && row.notes[i] !== null) {
          const key = eleves.value[existingIdx].id + ':' + evaluations.value[i].id
          notesEval.value[key] = row.notes[i]
        }
      }
    } else {
      // Créer un nouvel élève
      const newId = generateUUID()
      const maxOrdre = eleves.value.reduce((max, e) => Math.max(max, e.numero_ordre || 0), 0)
      eleves.value.push({
        id: newId,
        feuille_id: feuilleId.value,
        identifiant: row.identifiant,
        numero_ordre: row.numero_ordre || (maxOrdre + 1),
        nom: row.nom,
        prenom: row.prenom,
        nom_tuteur: null,
        prenom_tuteur: null,
        notes_evaluations: {},
        notes_epreuves: {},
        moyenne: null,
        observation: 'Notes insuffisantes',
        rang: '-'
      })
      imported++

      // Appliquer les notes
      for (let i = 0; i < evaluations.value.length; i++) {
        if (i < row.notes.length && row.notes[i] !== null) {
          const key = newId + ':' + evaluations.value[i].id
          notesEval.value[key] = row.notes[i]
        }
      }
    }
  }

  showImportDataModal.value = false
  hasChanges.value = true
  recalculateAll()
  alert(`${imported} élève(s) importé(s), ${updated} mis à jour.`)
}

// ============================================
// TRI
// ============================================

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
    return notesEval.value[eleve.id + ':' + evalId] ?? -1
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

// ============================================
// AIDES D'AFFICHAGE
// ============================================

function getNoteValue(eleveId, evalId) {
  const key = eleveId + ':' + evalId
  const val = notesEval.value[key]
  if (val === null || val === undefined) return ''
  return val
}

function getEpreuveNote(eleve, epreuveId) {
  return eleve.notes_epreuves?.[epreuveId] ?? null
}

function formatNote(note) {
  if (note === null || note === undefined || note === '') return ''
  return parseFloat(note).toFixed(2)
}

function formatIdentifiant(identifiant) {
  if (!identifiant) return ''
  while (identifiant.length < 12) {
    identifiant = '0' + identifiant
  }
  return identifiant.toUpperCase()
}

function getMoyenneClass(moyenne) {
  if (moyenne === null || moyenne === undefined) return ''
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

function openEditEleve(eleve) {
  editEleveForm.value = {
    id: eleve.id,
    identifiant: eleve.identifiant || '',
    numero_ordre: eleve.numero_ordre || 1,
    nom: eleve.nom || '',
    prenom: eleve.prenom || '',
    nom_tuteur: eleve.nom_tuteur || '',
    prenom_tuteur: eleve.prenom_tuteur || ''
  }
  showEditEleveModal.value = true
}

// ============================================
// EXPORT (toujours via API)
// ============================================

async function exportCsv() {
  try {
    const response = await fetch(`/GestionNotes/api/feuilles/${feuilleId.value}/export/csv`, {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    if (!response.ok) throw new Error('Erreur lors de l\'export')
    const blob = await response.blob()
    const disposition = response.headers.get('Content-Disposition') || ''
    const match = disposition.match(/filename="?([^";\n]+)"?/)
    const filename = match ? match[1] : 'export.csv'
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    alert(e.message || 'Erreur lors de l\'export CSV')
  }
}

async function exportJson() {
  try {
    const response = await fetch(`/GestionNotes/api/feuilles/${feuilleId.value}/export/json`, {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    if (!response.ok) throw new Error('Erreur lors de l\'export')
    const blob = await response.blob()
    const disposition = response.headers.get('Content-Disposition') || ''
    const match = disposition.match(/filename="?([^";\n]+)"?/)
    const filename = match ? match[1] : 'export.json'
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    alert(e.message || 'Erreur lors de l\'export JSON')
  }
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

// ============================================
// INITIALISATION
// ============================================

onMounted(loadFeuille)
</script>

<style scoped>
.validation-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding: 0.75rem 1rem;
  background: #fef3cd;
  border: 1px solid #ffc107;
  border-radius: var(--radius-md);
}

.validation-info {
  font-weight: 600;
  color: #856404;
  font-size: 0.9rem;
}

.validation-actions {
  display: flex;
  gap: 0.5rem;
}

.btn-success {
  background: #28a745;
  color: white;
  border: 1px solid #28a745;
}

.btn-success:hover {
  background: #218838;
}

.btn-success:disabled {
  background: #6c757d;
  border-color: #6c757d;
}

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
  max-height: calc(100vh - 320px);
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

.eleve-name.clickable {
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-md);
  transition: background 0.15s, color 0.15s;
}

.eleve-name.clickable:hover {
  background: var(--primary-color);
  color: white;
}

.eleve-name.clickable:hover strong {
  color: white;
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
  font-size: 0.85rem;
}

.import-mapping {
  margin-bottom: 1rem;
}

.mapping-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.import-preview {
  margin-bottom: 1rem;
  max-height: 300px;
  overflow: auto;
}
</style>