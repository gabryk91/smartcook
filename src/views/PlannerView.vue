<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { api } from '@/api/client'
import { notify, runBusy } from '@/stores/app'
import type { Meal, Recipe } from '@/types'

const startOfWeek = (date: Date) => {
	const copy = new Date(date)
	const day = (copy.getDay() + 6) % 7
	copy.setDate(copy.getDate() - day)
	copy.setHours(0, 0, 0, 0)
	return copy
}
const iso = (date: Date) => date.toISOString().slice(0, 10)
const weekStart = ref(startOfWeek(new Date()))
const recipes = ref<Recipe[]>([])
const meals = ref<Meal[]>([])
const mealLabel = (slot: string) => t('smartcook', ({ breakfast: 'Breakfast', lunch: 'Lunch', dinner: 'Dinner', snack: 'Snack' } as Record<string, string>)[slot] || 'Meal')
const recipeId = ref<number | null>(null)
const selectedDate = ref(iso(new Date()))
const slot = ref('dinner')
const servings = ref(2)
const days = computed(() => Array.from({ length: 7 }, (_, index) => {
	const date = new Date(weekStart.value)
	date.setDate(date.getDate() + index)
	return date
}))

const load = async () => {
	await runBusy(async () => {
		[recipes.value, meals.value] = await Promise.all([
			api.recipes({ sort: 'title', direction: 'ASC' }),
			api.planner(iso(days.value[0]!), iso(days.value[6]!)),
		])
		if (!recipeId.value && recipes.value[0]?.id) recipeId.value = recipes.value[0].id
	})
}
onMounted(load)

const moveWeek = (offset: number) => {
	const date = new Date(weekStart.value)
	date.setDate(date.getDate() + offset * 7)
	weekStart.value = date
	load()
}
const add = async () => {
	if (!recipeId.value) return
	await runBusy(async () => {
		await api.createMeal({ recipeId: recipeId.value, date: selectedDate.value, slot: slot.value, servings: servings.value })
		await load()
		notify(t('smartcook', 'Meal added'))
	})
}
const remove = async (id: number) => {
	await runBusy(async () => { await api.deleteMeal(id); await load() })
}
</script>

<template>
	<div class="view-stack">
		<section class="toolbar panel planner-toolbar"><button class="secondary" @click="moveWeek(-1)">←</button><div><p class="eyebrow">{{ t('smartcook', 'Week') }}</p><h2>{{ days[0]?.toLocaleDateString() }} – {{ days[6]?.toLocaleDateString() }}</h2></div><button class="secondary" @click="moveWeek(1)">→</button><button class="ghost" @click="weekStart = startOfWeek(new Date()); load()">{{ t('smartcook', 'Today') }}</button></section>
		<section class="planner-grid">
			<article v-for="day in days" :key="iso(day)" class="day-column panel" :class="{ today: iso(day) === iso(new Date()) }">
				<header><span>{{ day.toLocaleDateString(undefined, { weekday: 'short' }) }}</span><strong>{{ day.getDate() }}</strong></header>
				<div v-for="meal in meals.filter(item => item.date === iso(day))" :key="meal.id" class="meal-card"><small>{{ mealLabel(meal.slot) }}</small><a :href="`#/recipes/${meal.recipeId}`">{{ meal.recipeTitle }}</a><span>{{ meal.servings }} {{ t('smartcook', 'servings') }}</span><button @click="remove(meal.id)">×</button></div>
				<button class="add-meal" @click="selectedDate = iso(day)">＋</button>
			</article>
		</section>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Plan') }}</p><h2>{{ t('smartcook', 'Add a meal') }}</h2></div></div><div class="form-grid four"><label>{{ t('smartcook', 'Date') }}<input v-model="selectedDate" type="date"></label><label>{{ t('smartcook', 'Recipe') }}<select v-model.number="recipeId"><option v-for="recipe in recipes" :key="recipe.id" :value="recipe.id">{{ recipe.title }}</option></select></label><label>{{ t('smartcook', 'Meal') }}<select v-model="slot"><option value="breakfast">{{ t('smartcook', 'Breakfast') }}</option><option value="lunch">{{ t('smartcook', 'Lunch') }}</option><option value="dinner">{{ t('smartcook', 'Dinner') }}</option><option value="snack">{{ t('smartcook', 'Snack') }}</option></select></label><label>{{ t('smartcook', 'Servings') }}<input v-model.number="servings" type="number" min="1"></label></div><button class="primary" @click="add">{{ t('smartcook', 'Add to plan') }}</button></section>
	</div>
</template>
