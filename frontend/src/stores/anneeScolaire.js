import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAnneeScolaireStore = defineStore('anneeScolaire', () => {
  const anneeScolaire = ref(localStorage.getItem('annee_scolaire') || '')
  const trimestre = ref(localStorage.getItem('trimestre') || '')
  const availableAnnees = ref([])

  const hasSelection = computed(() => !!anneeScolaire.value)

  // Options combinées année + trimestre
  const combinedOptions = computed(() => {
    const options = []
    for (const year of availableAnnees.value) {
      for (let t = 1; t <= 3; t++) {
        options.push({
          value: `${year}|${t}`,
          label: `${year} - Trimestre ${t}`,
          annee: year,
          trimestre: t
        })
      }
    }
    return options
  })

  // Valeur combinée actuelle
  const combinedValue = computed(() => {
    if (anneeScolaire.value && trimestre.value) {
      return `${anneeScolaire.value}|${trimestre.value}`
    }
    return ''
  })

  function setCombinedSelection(value) {
    if (value) {
      const [annee, trimestreVal] = value.split('|')
      anneeScolaire.value = annee
      trimestre.value = trimestreVal
      localStorage.setItem('annee_scolaire', annee)
      localStorage.setItem('trimestre', trimestreVal)
    }
  }

  function setAnneeScolaire(annee) {
    anneeScolaire.value = annee
    localStorage.setItem('annee_scolaire', annee)
  }

  function setTrimestre(t) {
    trimestre.value = t
    localStorage.setItem('trimestre', t)
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

    // Si aucun trimestre n'est sélectionné, prendre le 1er
    if (!trimestre.value) {
      setTrimestre('1')
    }
  }

  return {
    anneeScolaire,
    trimestre,
    availableAnnees,
    hasSelection,
    combinedOptions,
    combinedValue,
    setCombinedSelection,
    setAnneeScolaire,
    setTrimestre,
    setAvailableAnnees,
    initFromFeuilles
  }
})
