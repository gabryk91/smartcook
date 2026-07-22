import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type { ImportPreview, Meal, Recipe, ShoppingList } from '@/types'

const appUrl = (path: string) => generateUrl(`/apps/smartcook${path}`)

export const mediaUrl = (id: number) => appUrl(`/media/${id}`)
export const recipeImageUrl = (value?: string | null) => {
	const stored = /^media:(\d+)$/.exec(value || '')
	if (stored) return mediaUrl(Number(stored[1]))
	return /^https?:\/\//i.test(value || '') ? value || '' : ''
}
export const exportUrl = (id: number, format: string) => appUrl(`/recipes/${id}/export/${format}`)

export const api = {
	async stats() {
		return (await axios.get(appUrl('/stats'))).data
	},
	async recipes(params: Record<string, unknown> = {}): Promise<Recipe[]> {
		return (await axios.get(appUrl('/recipes'), { params })).data.recipes
	},
	async recipe(id: number): Promise<Recipe> {
		return (await axios.get(appUrl(`/recipes/${id}`))).data.recipe
	},
	async createRecipe(recipe: Recipe): Promise<Recipe> {
		return (await axios.post(appUrl('/recipes'), { recipe })).data.recipe
	},
	async updateRecipe(id: number, recipe: Partial<Recipe>): Promise<Recipe> {
		return (await axios.put(appUrl(`/recipes/${id}`), { recipe })).data.recipe
	},
	async deleteRecipe(id: number): Promise<void> {
		await axios.delete(appUrl(`/recipes/${id}`))
	},
	async favorite(id: number, favorite: boolean): Promise<void> {
		await axios.post(appUrl(`/recipes/${id}/favorite`), { favorite })
	},
	async cooked(id: number): Promise<void> {
		await axios.post(appUrl(`/recipes/${id}/cooked`))
	},
	async versions(id: number) {
		return (await axios.get(appUrl(`/recipes/${id}/versions`))).data.versions
	},
	async restore(id: number, revision: number): Promise<Recipe> {
		return (await axios.post(appUrl(`/recipes/${id}/restore/${revision}`))).data.recipe
	},
	async previewImport(kind: string, payload: Record<string, unknown>, useAi: boolean, provider?: string): Promise<ImportPreview> {
		return (await axios.post(appUrl('/import/preview'), { kind, payload, useAi, provider })).data
	},
	async previewFile(file: File, language: string, useAi: boolean, provider?: string): Promise<ImportPreview> {
		const form = new FormData()
		form.append('file', file)
		form.append('language', language)
		form.append('useAi', String(useAi))
		if (provider) form.append('provider', provider)
		return (await axios.post(appUrl('/import/file'), form)).data
	},
	async taxonomy() {
		return (await axios.get(appUrl('/taxonomy'))).data
	},
	async planner(from: string, to: string): Promise<Meal[]> {
		return (await axios.get(appUrl('/planner'), { params: { from, to } })).data.meals
	},
	async createMeal(meal: Record<string, unknown>): Promise<Meal> {
		return (await axios.post(appUrl('/planner'), { meal })).data.meal
	},
	async deleteMeal(id: number): Promise<void> {
		await axios.delete(appUrl(`/planner/${id}`))
	},
	async shoppingLists(): Promise<ShoppingList[]> {
		return (await axios.get(appUrl('/shopping'))).data.lists
	},
	async shoppingList(id: number): Promise<ShoppingList> {
		return (await axios.get(appUrl(`/shopping/${id}`))).data.list
	},
	async createShoppingList(name: string, recipes: Array<{ recipeId: number; servings: number }>): Promise<ShoppingList> {
		return (await axios.post(appUrl('/shopping'), { name, recipes })).data.list
	},
	async updateShoppingItem(listId: number, itemId: number, item: Record<string, unknown>) {
		return (await axios.put(appUrl(`/shopping/${listId}/items/${itemId}`), { item })).data.item
	},
	async addShoppingItem(listId: number, item: Record<string, unknown>) {
		return (await axios.post(appUrl(`/shopping/${listId}/items`), { item })).data.item
	},
	async deleteShoppingList(id: number): Promise<void> {
		await axios.delete(appUrl(`/shopping/${id}`))
	},
	async shares(recipeId: number) {
		return (await axios.get(appUrl(`/recipes/${recipeId}/shares`))).data.shares
	},
	async createShare(recipeId: number, share: Record<string, unknown>) {
		return (await axios.post(appUrl(`/recipes/${recipeId}/shares`), { share })).data.share
	},
	async deleteShare(recipeId: number, shareId: number): Promise<void> {
		await axios.delete(appUrl(`/recipes/${recipeId}/shares/${shareId}`))
	},
	async settings() {
		return (await axios.get(appUrl('/settings'))).data.settings
	},
	async saveSettings(settings: Record<string, unknown>) {
		return (await axios.put(appUrl('/settings'), { settings })).data.settings
	},
	async uploadMedia(recipeId: number, file: File, kind?: string, altText?: string) {
		const form = new FormData()
		form.append('file', file)
		if (kind) form.append('kind', kind)
		if (altText) form.append('altText', altText)
		return (await axios.post(appUrl(`/recipes/${recipeId}/media`), form)).data.media
	},
	async publicRecipe(token: string, password = '') {
		return (await axios.post(appUrl(`/public/${encodeURIComponent(token)}/data`), { password })).data
	},
}
