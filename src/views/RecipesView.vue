<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { api, recipeImageUrl } from '@/api/client'
import { notify, runBusy } from '@/stores/app'
import type { Recipe } from '@/types'

const recipes = ref<Recipe[]>([])
const search = ref('')
const favorites = ref(false)
const sort = ref('updated_at')
let timer: number | undefined

const load = () => runBusy(async () => {
	recipes.value = await api.recipes({ search: search.value, favorite: favorites.value ? 1 : '', sort: sort.value })
}).catch(() => undefined)

watch([search, favorites, sort], () => {
	window.clearTimeout(timer)
	timer = window.setTimeout(load, 250)
})
onMounted(load)

const toggleFavorite = async (recipe: Recipe) => {
	if (!recipe.id) return
	await runBusy(async () => {
		await api.favorite(recipe.id!, !recipe.favorite)
		recipe.favorite = !recipe.favorite
		notify(recipe.favorite ? t('smartcook', 'Added to favorites') : t('smartcook', 'Removed from favorites'))
	})
}
</script>

<template>
	<div class="view-stack">
		<section class="toolbar panel">
			<label class="search-field"><span>⌕</span><input v-model="search" :placeholder="t('smartcook', 'Search recipes, cuisine or course…')"></label>
			<label class="check-inline"><input v-model="favorites" type="checkbox"> {{ t('smartcook', 'Favorites only') }}</label>
			<label>{{ t('smartcook', 'Sort') }}<select v-model="sort"><option value="updated_at">{{ t('smartcook', 'Recently updated') }}</option><option value="title">{{ t('smartcook', 'Title') }}</option><option value="total_time">{{ t('smartcook', 'Total time') }}</option><option value="cook_count">{{ t('smartcook', 'Most cooked') }}</option></select></label>
			<a class="primary" href="#/new">＋ {{ t('smartcook', 'New recipe') }}</a>
		</section>
		<section v-if="recipes.length" class="recipe-grid">
			<article v-for="recipe in recipes" :key="recipe.id" class="recipe-card">
				<a class="recipe-image" :href="`#/recipes/${recipe.id}`">
					<img v-if="recipeImageUrl(recipe.imagePath)" :src="recipeImageUrl(recipe.imagePath)" alt="">
					<div v-else class="image-placeholder"><span>{{ recipe.title.slice(0, 1).toUpperCase() }}</span></div>
					<span class="time-pill">{{ recipe.totalTime || recipe.prepTime + recipe.cookTime }} min</span>
				</a>
				<div class="recipe-card-body">
					<div class="card-title"><div><p>{{ recipe.categories[0]?.name || recipe.course || t('smartcook', 'Recipe') }}</p><a :href="`#/recipes/${recipe.id}`"><h2>{{ recipe.title }}</h2></a></div><button class="icon-button" :aria-label="t('smartcook', 'Toggle favorite')" @click="toggleFavorite(recipe)">{{ recipe.favorite ? '★' : '☆' }}</button></div>
					<p>{{ recipe.description || t('smartcook', 'No description') }}</p>
					<div class="card-footer"><div class="card-meta"><span>◷ {{ recipe.prepTime }} + {{ recipe.cookTime }} min</span><span>{{ recipe.servings }} {{ t('smartcook', 'servings') }}</span><span v-if="recipe.difficulty">{{ recipe.difficulty }}</span><span v-if="recipe.cuisine" class="cuisine-label">{{ t('smartcook', 'Cuisine') }}: {{ recipe.cuisine }}</span></div></div>
				</div>
			</article>
		</section>
		<section v-else class="panel empty-state"><h2>{{ t('smartcook', 'No recipes found') }}</h2><p>{{ t('smartcook', 'Change the filters, create a recipe, or import one from an URL.') }}</p><div><a class="primary" href="#/import">{{ t('smartcook', 'Import recipe') }}</a> <a class="secondary" href="#/new">{{ t('smartcook', 'Create manually') }}</a></div></section>
	</div>
</template>
