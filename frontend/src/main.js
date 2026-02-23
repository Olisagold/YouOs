import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { useCommandCenterStore } from './stores/commandCenter'
import { pinia } from './stores'

const app = createApp(App)

app.use(pinia)
app.use(router)

const authStore = useAuthStore(pinia)
const commandCenterStore = useCommandCenterStore(pinia)

authStore
  .initialize()
  .then(() => {
    if (authStore.isAuthenticated) {
      commandCenterStore.prefetchStatus().catch(() => {})
      return
    }

    commandCenterStore.clearStatus()
  })
  .finally(() => {
    app.mount('#app')
  })
