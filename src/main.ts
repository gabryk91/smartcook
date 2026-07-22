import { createApp } from 'vue'
import App from './App.vue'
import PublicApp from './PublicApp.vue'
import './styles/app.scss'

const publicRoot = document.getElementById('smartcook-public')
if (publicRoot) {
	createApp(PublicApp, { token: publicRoot.dataset.token || '' }).mount(publicRoot)
} else {
	createApp(App).mount('#smartcook')
}
