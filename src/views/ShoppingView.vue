<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { api } from '@/api/client'
import { notify, runBusy } from '@/stores/app'
import type { Recipe, ShoppingList } from '@/types'

const lists = ref<ShoppingList[]>([])
const selected = ref<ShoppingList | null>(null)
const recipes = ref<Recipe[]>([])
const choices = ref<Record<number, { selected: boolean; servings: number }>>({})
const listName = ref(t('smartcook', 'Weekly shopping'))
const newItem = ref('')
const choiceFor = (recipe: Recipe) => {
	const id = recipe.id ?? 0
	if (!choices.value[id]) choices.value[id] = { selected: false, servings: recipe.servings || 1 }
	return choices.value[id]!
}

const load = async () => {
	await runBusy(async () => {
		[lists.value, recipes.value] = await Promise.all([api.shoppingLists(), api.recipes({ sort: 'title', direction: 'ASC' })])
		for (const recipe of recipes.value) if (recipe.id && !choices.value[recipe.id]) choices.value[recipe.id] = { selected: false, servings: recipe.servings || 1 }
		if (lists.value[0]) selected.value = await api.shoppingList(lists.value[0].id)
	})
}
onMounted(load)

const open = async (list: ShoppingList) => { selected.value = await runBusy(() => api.shoppingList(list.id)) }
const create = async () => {
	const selection = Object.entries(choices.value).filter(([, value]) => value.selected).map(([id, value]) => ({ recipeId: Number(id), servings: value.servings }))
	await runBusy(async () => {
		selected.value = await api.createShoppingList(listName.value, selection)
		await load()
		notify(t('smartcook', 'Shopping list created'))
	})
}
const toggle = async (item: NonNullable<ShoppingList['items']>[number]) => {
	if (!selected.value) return
	item.checked = !item.checked
	await api.updateShoppingItem(selected.value.id, item.id, { checked: item.checked })
}
const addItem = async () => {
	if (!selected.value || !newItem.value.trim()) return
	await runBusy(async () => {
		await api.addShoppingItem(selected.value!.id, { name: newItem.value })
		selected.value = await api.shoppingList(selected.value!.id)
		newItem.value = ''
	})
}
const removeList = async (list: ShoppingList) => {
	if (!window.confirm(t('smartcook', 'Delete this shopping list?'))) return
	await runBusy(async () => { await api.deleteShoppingList(list.id); selected.value = null; await load() })
}
</script>

<template>
	<div class="shopping-layout">
		<aside class="panel list-sidebar"><div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Saved') }}</p><h2>{{ t('smartcook', 'Shopping lists') }}</h2></div></div><button v-for="list in lists" :key="list.id" :class="{ active: selected?.id === list.id }" @click="open(list)"><span><strong>{{ list.name }}</strong><small>{{ new Date(list.updatedAt * 1000).toLocaleDateString() }}</small></span><b>›</b></button><p v-if="!lists.length">{{ t('smartcook', 'No lists yet') }}</p></aside>
		<main class="view-stack">
			<section class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Generate') }}</p><h2>{{ t('smartcook', 'From recipes') }}</h2></div></div>
				<label>{{ t('smartcook', 'List name') }}<input v-model="listName"></label>
				<div class="recipe-selector"><label v-for="recipe in recipes" :key="recipe.id"><input v-if="recipe.id" v-model="choiceFor(recipe).selected" type="checkbox"><span>{{ recipe.title }}</span><input v-if="recipe.id" v-model.number="choiceFor(recipe).servings" type="number" min="1" :aria-label="t('smartcook', 'Servings')"></label></div>
				<button class="primary" @click="create">{{ t('smartcook', 'Generate shopping list') }}</button>
			</section>
			<section v-if="selected" class="panel shopping-sheet">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Active list') }}</p><h2>{{ selected.name }}</h2></div><button class="danger ghost" @click="removeList(selected)">{{ t('smartcook', 'Delete') }}</button></div>
				<div class="add-item"><input v-model="newItem" :placeholder="t('smartcook', 'Add an item…')" @keyup.enter="addItem"><button class="secondary" @click="addItem">＋</button></div>
				<div class="shopping-items"><label v-for="item in selected.items" :key="item.id" :class="{ checked: item.checked }"><input :checked="item.checked" type="checkbox" @change="toggle(item)"><span><strong>{{ item.quantity }} {{ item.unit }}</strong> {{ item.name }}<small>{{ item.category }} {{ item.notes }}</small></span></label></div>
			</section>
			<section v-else class="panel empty-state"><h2>{{ t('smartcook', 'Select or create a list') }}</h2><p>{{ t('smartcook', 'Quantities with compatible units are summed automatically.') }}</p></section>
		</main>
	</div>
</template>
