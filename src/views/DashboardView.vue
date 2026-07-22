<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { api, recipeImageUrl } from '@/api/client'
import { runBusy } from '@/stores/app'
import type { Recipe } from '@/types'

interface Stats {
	recipeCount: number
	favoriteCount: number
	cookCount: number
	averageTotalTime: number
	topTags: Array<{ name: string; count: number }>
	topIngredients: Array<{ name: string; count: number }>
	recentRecipes: Recipe[]
}
const stats = ref<Stats | null>(null)
onMounted(() => runBusy(async () => { stats.value = await api.stats() }).catch(() => undefined))
</script>

<template>
	<div v-if="stats" class="view-stack">
		<section class="metric-grid">
			<article><span>{{ t('smartcook', 'Recipes') }}</span><strong>{{ stats.recipeCount }}</strong><small>{{ t('smartcook', 'in your library') }}</small></article>
			<article><span>{{ t('smartcook', 'Favorites') }}</span><strong>{{ stats.favoriteCount }}</strong><small>{{ t('smartcook', 'saved for later') }}</small></article>
			<article><span>{{ t('smartcook', 'Cooked') }}</span><strong>{{ stats.cookCount }}</strong><small>{{ t('smartcook', 'recorded preparations') }}</small></article>
			<article><span>{{ t('smartcook', 'Average time') }}</span><strong>{{ stats.averageTotalTime }} min</strong><small>{{ t('smartcook', 'from start to finish') }}</small></article>
		</section>
		<section class="two-column">
			<article class="panel">
				<div class="panel-heading"><div><p class="eyebrow">{{ t('smartcook', 'Library') }}</p><h2>{{ t('smartcook', 'Recently updated') }}</h2></div><a href="#/recipes">{{ t('smartcook', 'View all') }} →</a></div>
				<div v-if="stats.recentRecipes.length" class="compact-list">
					<a v-for="recipe in stats.recentRecipes" :key="recipe.id" :href="`#/recipes/${recipe.id}`">
						<div class="recipe-thumb"><img v-if="recipeImageUrl(recipe.imagePath)" :src="recipeImageUrl(recipe.imagePath)" alt=""><span v-else>{{ recipe.title.slice(0, 1).toUpperCase() }}</span></div>
						<div><strong>{{ recipe.title }}</strong><small>{{ recipe.cuisine || t('smartcook', 'Uncategorized') }} · {{ recipe.totalTime || 0 }} min</small></div>
						<span>›</span>
					</a>
				</div>
				<div v-else class="empty-state"><h3>{{ t('smartcook', 'Your cookbook is ready') }}</h3><p>{{ t('smartcook', 'Create a recipe or import one from a webpage or text.') }}</p><a class="primary" href="#/import">{{ t('smartcook', 'Import a recipe') }}</a></div>
			</article>
			<div class="view-stack">
				<article class="panel"><p class="eyebrow">{{ t('smartcook', 'Most used') }}</p><h2>{{ t('smartcook', 'Ingredients') }}</h2><div class="tag-cloud"><span v-for="item in stats.topIngredients" :key="item.name">{{ item.name }} <b>{{ item.count }}</b></span><small v-if="!stats.topIngredients.length">{{ t('smartcook', 'No data yet') }}</small></div></article>
				<article class="panel"><p class="eyebrow">{{ t('smartcook', 'Organization') }}</p><h2>{{ t('smartcook', 'Top tags') }}</h2><div class="tag-cloud"><span v-for="item in stats.topTags" :key="item.name">#{{ item.name }} <b>{{ item.count }}</b></span><small v-if="!stats.topTags.length">{{ t('smartcook', 'No tags yet') }}</small></div></article>
			</div>
		</section>
	</div>
	<div v-else class="skeleton-grid"><div v-for="i in 4" :key="i" class="skeleton"></div></div>
</template>
