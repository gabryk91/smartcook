<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { api } from './api/client'
import type { Recipe } from './types'

const props = defineProps<{ token: string }>()
const recipe = ref<Recipe | null>(null)
const password = ref('')
const error = ref('')
const loading = ref(false)

const load = async () => {
	loading.value = true
	error.value = ''
	try {
		recipe.value = (await api.publicRecipe(props.token, password.value)).recipe
	} catch (e: unknown) {
		const candidate = e as { response?: { data?: { error?: string } } }
		error.value = candidate.response?.data?.error || t('smartcook', 'Could not load the shared recipe')
	} finally {
		loading.value = false
	}
}
onMounted(load)
</script>

<template>
	<main class="public-page">
		<div class="public-brand"><img src="../img/app.svg" alt=""><strong>SmartCook</strong></div>
		<form v-if="!recipe && error" class="password-card" @submit.prevent="load">
			<h1>{{ t('smartcook', 'Shared recipe') }}</h1>
			<p>{{ error }}</p>
			<label>{{ t('smartcook', 'Password') }}<input v-model="password" type="password" autocomplete="current-password"></label>
			<button class="primary" type="submit" :disabled="loading">{{ t('smartcook', 'Open recipe') }}</button>
		</form>
		<article v-else-if="recipe" class="public-recipe">
			<img v-if="recipe.imagePath && /^https?:/.test(recipe.imagePath)" class="hero" :src="recipe.imagePath" alt="">
			<p class="eyebrow">{{ recipe.cuisine }}<span v-if="recipe.course"> · {{ recipe.course }}</span></p>
			<h1>{{ recipe.title }}</h1>
			<p class="lead">{{ recipe.description }}</p>
			<div class="metrics">
				<div><strong>{{ recipe.servings }}</strong><span>{{ t('smartcook', 'Servings') }}</span></div>
				<div><strong>{{ recipe.prepTime }} min</strong><span>{{ t('smartcook', 'Preparation') }}</span></div>
				<div><strong>{{ recipe.cookTime }} min</strong><span>{{ t('smartcook', 'Cooking') }}</span></div>
				<div><strong>{{ recipe.totalTime }} min</strong><span>{{ t('smartcook', 'Total') }}</span></div>
			</div>
			<div class="public-grid">
				<section><h2>{{ t('smartcook', 'Ingredients') }}</h2><ul><li v-for="(item, index) in recipe.ingredients" :key="index"><b>{{ item.quantity }} {{ item.unit }}</b> {{ item.name }} <small>{{ item.notes }}</small></li></ul></section>
				<section><h2>{{ t('smartcook', 'Method') }}</h2><ol><li v-for="(step, index) in recipe.steps" :key="index">{{ step.text }}</li></ol></section>
			</div>
		</article>
		<p v-else>{{ t('smartcook', 'Loading…') }}</p>
	</main>
</template>
