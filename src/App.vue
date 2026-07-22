<script setup lang="ts">
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import DashboardView from './views/DashboardView.vue'
import ImportView from './views/ImportView.vue'
import PlannerView from './views/PlannerView.vue'
import RecipeEditorView from './views/RecipeEditorView.vue'
import RecipesView from './views/RecipesView.vue'
import SettingsView from './views/SettingsView.vue'
import ShoppingView from './views/ShoppingView.vue'
import { appState } from './stores/app'

interface RouteState { section: string; id?: number }
const parseRoute = (): RouteState => {
	const parts = (location.hash.replace(/^#\/?/, '') || 'dashboard').split('/')
	if (parts[0] === 'recipes' && parts[1]) return { section: 'editor', id: Number(parts[1]) || undefined }
	if (parts[0] === 'new') return { section: 'editor' }
	return { section: parts[0] || 'dashboard' }
}
const route = ref<RouteState>(parseRoute())
const listener = () => { route.value = parseRoute() }
onMounted(() => window.addEventListener('hashchange', listener))
onBeforeUnmount(() => window.removeEventListener('hashchange', listener))

const title = computed(() => ({
	dashboard: t('smartcook', 'Dashboard'),
	recipes: t('smartcook', 'Recipes'),
	editor: route.value.id ? t('smartcook', 'Edit recipe') : t('smartcook', 'New recipe'),
	import: t('smartcook', 'Import'),
	planner: t('smartcook', 'Meal planner'),
	shopping: t('smartcook', 'Shopping lists'),
	settings: t('smartcook', 'Settings'),
}[route.value.section] || 'SmartCook'))

const navItems = computed(() => [
	{ id: 'dashboard', label: t('smartcook', 'Dashboard') },
	{ id: 'recipes', label: t('smartcook', 'Recipes') },
	{ id: 'import', label: t('smartcook', 'Import') },
	{ id: 'planner', label: t('smartcook', 'Meal planner') },
	{ id: 'shopping', label: t('smartcook', 'Shopping lists') },
	{ id: 'settings', label: t('smartcook', 'Settings') },
])

const navigate = (section: string) => { location.hash = `#/${section}` }
</script>

<template>
	<NcContent app-name="smartcook" class="smartcook-shell">
		<aside class="smartcook-sidebar" aria-label="SmartCook">
			<div class="brand">
				<img src="../img/app.svg" alt="">
				<div><strong>SmartCook</strong><span>{{ t('smartcook', 'Recipe intelligence') }}</span></div>
			</div>
			<nav>
				<button v-for="item in navItems" :key="item.id" :class="{ active: route.section === item.id || (item.id === 'recipes' && route.section === 'editor') }" @click="navigate(item.id)">
					{{ item.label }}
				</button>
			</nav>
			<button class="primary full" @click="navigate('new')">＋ {{ t('smartcook', 'New recipe') }}</button>
		</aside>
		<NcAppContent class="smartcook-content">
			<header class="page-header">
				<div><h1>{{ title }}</h1><p>{{ t('smartcook', 'Self-hosted recipes, structured and searchable') }}</p></div>
				<div v-if="appState.busy" class="busy" aria-live="polite">{{ t('smartcook', 'Working…') }}</div>
			</header>
			<div v-if="appState.error" class="notice error" role="alert">{{ appState.error }} <button @click="appState.error = ''">×</button></div>
			<div v-if="appState.message" class="notice success" role="status">{{ appState.message }}</div>
			<DashboardView v-if="route.section === 'dashboard'" />
			<RecipesView v-else-if="route.section === 'recipes'" />
			<RecipeEditorView v-else-if="route.section === 'editor'" :recipe-id="route.id" />
			<ImportView v-else-if="route.section === 'import'" />
			<PlannerView v-else-if="route.section === 'planner'" />
			<ShoppingView v-else-if="route.section === 'shopping'" />
			<SettingsView v-else-if="route.section === 'settings'" />
			<DashboardView v-else />
		</NcAppContent>
	</NcContent>
</template>
