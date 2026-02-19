import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { pinia } from './stores'

const app = createApp(App)

app.use(pinia)
app.use(router)

const authStore = useAuthStore(pinia)

authStore.initialize().finally(() => {
  app.mount('#app')
})
