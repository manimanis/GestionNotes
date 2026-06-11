import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAnneeScolaireStore = defineStore('anneeScolaire', () => {
  const anneeScolaire = ref(localStorage.getItem('annee_scolaire') || '')
  const availableAnnees = ref([])

  const hasSelection = computed(() => !!anneeScolaire.value)

  function setAnneeScolaire(annee) {
    anneeScolaire.value = annee
    localStorage.setItem('annee_scolaire', annee)
  }

  function setAvailableAnnees(annees) {
    availableAnnees.value = annees
  }

  function initFromFeuilles(feuilles) {
    const years = [...new Set(feuilles.map(f => f.annee_scolaire).filter(Boolean))]
    years.sort().reverse()
    availableAnnees.value = years

    // Si aucune année n'est sélectionnée, prendre la plus récente
    if (!anneeScolaire.value && years.length > 0) {
      setAnneeScolaire(years[0])
    } else if (anneeScolaire.value && !years.includes(anneeScolaire.value)) {
      // Si l'année sélectionnée n'existe plus, prendre la plus récente
      if (years.length > 0) {
        setAnneeScolaire(years[0])
      }
    }
  }

  return {
    anneeScolaire,
    availableAnnees,
    hasSelection,
    setAnneeScolaire,
    setAvailableAnnees,
    initFromFeuilles
  }
})