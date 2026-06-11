<template>
  <div class="auth-page">
    <div class="auth-card auth-card-lg">
      <div class="auth-header">
        <div class="auth-logo">📚</div>
        <h1>Inscription</h1>
        <p>Créez votre compte enseignant</p>
      </div>
      
      <form @submit.prevent="handleRegister" class="auth-form">
        <div v-if="authStore.error" class="alert alert-error">{{ authStore.error }}</div>
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Identifiant (10 chiffres)</label>
            <input type="text" v-model="form.identifiant" class="form-control" placeholder="1234567890" maxlength="10" required />
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" v-model="form.email" class="form-control" placeholder="votre@email.com" required />
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nom</label>
            <input type="text" v-model="form.nom" class="form-control" placeholder="Ben Ali" required />
          </div>
          <div class="form-group">
            <label class="form-label">Prénom</label>
            <input type="text" v-model="form.prenom" class="form-control" placeholder="Mohamed" required />
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">Mot de passe</label>
          <input type="password" v-model="form.mot_de_passe" class="form-control" placeholder="Min 8 car, 1 maj, 1 chiffre" required />
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date de naissance</label>
            <input type="date" v-model="form.date_naissance" class="form-control" required />
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="tel" v-model="form.telephone" class="form-control" placeholder="+21698123456" required />
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">Lycée</label>
          <input type="text" v-model="form.lycee" class="form-control" placeholder="Lycée Pilote de Tunis" required />
        </div>
        
        <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="authStore.loading">
          {{ authStore.loading ? 'Inscription...' : 'Créer mon compte' }}
        </button>
      </form>
      
      <div class="auth-footer">
        <p>Déjà un compte ? <router-link to="/login">Se connecter</router-link></p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const form = reactive({
  identifiant: '',
  nom: '',
  prenom: '',
  email: '',
  mot_de_passe: '',
  date_naissance: '',
  lycee: '',
  telephone: ''
})

async function handleRegister() {
  const success = await authStore.register({ ...form })
  if (success) {
    router.push('/')
  }
}
</script>

<style scoped>
.auth-card-lg {
  max-width: 560px;
}
</style>