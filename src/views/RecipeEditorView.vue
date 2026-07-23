<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { api, exportUrl, mediaUrl } from '@/api/client'
import { notify, runBusy } from '@/stores/app'
import { emptyRecipe, type Recipe } from '@/types'

const props = defineProps<{ recipeId?: number }>()
const recipe = ref<Recipe>(emptyRecipe())
const activeTab = ref<'recipe' | 'sharing' | 'history'>('recipe')
const shares = ref<Array<Record<string, unknown>>>([])
const versions = ref<Array<{ revision: number; createdAt: number; userId: string }>>([])
const shareType = ref('link')
const shareWith = ref('')
const sharePassword = ref('')
const shareEdit = ref(false)
const attachment = ref<File | null>(null)
const coverAttachment = ref<File | null>(null)

const tagsText = computed({
	get: () => recipe.value.tags.map(item => item.name).join(', '),
	set: value => { recipe.value.tags = splitNames(value) },
})
const categoriesText = computed({
	get: () => recipe.value.categories.map(item => item.name).join(', '),
	set: value => { recipe.value.categories = splitNames(value) },
})
const toolsText = computed({
	get: () => recipe.value.tools.map(item => item.name).join(', '),
	set: value => { recipe.value.tools = splitNames(value) },
})
const totalTime = computed(() => recipe.value.prepTime + recipe.value.restTime + recipe.value.cookTime)

function splitNames(value: string) {
	return [...new Set(value.split(/[,;]+/).map(name => name.trim()).filter(Boolean))].map(name => ({ name }))
}

function timerParts(seconds?: number | null) {
	const value = Math.max(0, Number(seconds || 0))
	if (value >= 3600 && value % 3600 === 0) return { value: value / 3600, unit: 'hours' as const }
	if (value >= 60 && value % 60 === 0) return { value: value / 60, unit: 'minutes' as const }
	return { value: value || null, unit: 'seconds' as const }
}

function hydrateTimers(steps: Recipe['steps']) {
	return steps.map(step => ({ ...step, ...timerParts(step.timerSeconds) }))
}

function timerSeconds(value: number | null | undefined, unit: string | null | undefined) {
	if (value === null || value === undefined) return null
	const amount = Math.max(0, Number(value || 0))
	return unit === 'hours' ? Math.round(amount * 3600) : unit === 'minutes' ? Math.round(amount * 60) : Math.round(amount)
}

function formatBytes(value?: number | null) {
	if (!value || value < 0) return '—'
	if (value < 1024) return `${value} B`
	if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`
	return `${(value / (1024 * 1024)).toFixed(1)} MB`
}

function formatMediaDate(value?: number) {
	return value ? new Date(value * 1000).toLocaleString() : '—'
}

const load = async () => {
	if (!props.recipeId) {
		recipe.value = emptyRecipe()
		shares.value = []
		versions.value = []
		return
	}
	await runBusy(async () => {
		recipe.value = await api.recipe(props.recipeId!)
		recipe.value.steps = hydrateTimers(recipe.value.steps)
		shares.value = await api.shares(props.recipeId!)
		versions.value = await api.versions(props.recipeId!)
	})
}
watch(() => props.recipeId, load)
onMounted(load)

const save = async () => {
	await runBusy(async () => {
		recipe.value.totalTime = totalTime.value
		recipe.value.ingredients = recipe.value.ingredients.filter(item => item.name.trim() || item.originalText?.trim())
		recipe.value.steps = recipe.value.steps.filter(step => step.text.trim()).map(step => ({
			...step,
			timerSeconds: timerSeconds(step.timerValue, step.timerUnit),
		}))
		const saved = recipe.value.id
			? await api.updateRecipe(recipe.value.id, recipe.value)
			: await api.createRecipe(recipe.value)
		recipe.value = saved
		recipe.value.steps = hydrateTimers(recipe.value.steps)
		if (coverAttachment.value && saved.id) {
			await api.uploadMedia(saved.id, coverAttachment.value, 'image', t('smartcook', 'Cover image'))
			recipe.value = await api.recipe(saved.id)
			recipe.value.steps = hydrateTimers(recipe.value.steps)
			coverAttachment.value = null
		}
		notify(t('smartcook', 'Recipe saved'))
		if (!props.recipeId && saved.id) location.hash = `#/recipes/${saved.id}`
	})
}

const deleteRecipe = async () => {
	if (!recipe.value.id || !window.confirm(t('smartcook', 'Delete this recipe permanently?'))) return
	await runBusy(async () => {
		await api.deleteRecipe(recipe.value.id!)
		notify(t('smartcook', 'Recipe deleted'))
		location.hash = '#/recipes'
	})
}

const addIngredient = () => recipe.value.ingredients.push({ name: '', quantity: null, unit: null })
const addStep = () => recipe.value.steps.push({ text: '', timerValue: null, timerUnit: 'seconds' })

const createShare = async () => {
	if (!recipe.value.id) return
	await runBusy(async () => {
		const share = await api.createShare(recipe.value.id!, {
			type: shareType.value,
			shareWith: shareWith.value,
			password: sharePassword.value,
			permission: shareEdit.value ? 3 : 1,
		})
		shares.value.unshift(share)
		shareWith.value = ''
		sharePassword.value = ''
		notify(t('smartcook', 'Share created'))
	})
}

const removeShare = async (share: Record<string, unknown>) => {
	if (!recipe.value.id) return
	await runBusy(async () => {
		await api.deleteShare(recipe.value.id!, Number(share.id))
		shares.value = shares.value.filter(item => item.id !== share.id)
	})
}

const restore = async (revision: number) => {
	if (!recipe.value.id || !window.confirm(t('smartcook', 'Restore this version?'))) return
	await runBusy(async () => {
		recipe.value = await api.restore(recipe.value.id!, revision)
		versions.value = await api.versions(recipe.value.id!)
		notify(t('smartcook', 'Version restored'))
	})
}

const uploadAttachment = async () => {
	if (!recipe.value.id || !attachment.value) return
	await runBusy(async () => {
		await api.uploadMedia(recipe.value.id!, attachment.value!)
		recipe.value = await api.recipe(recipe.value.id!)
		attachment.value = null
		notify(t('smartcook', 'Attachment uploaded'))
	})
}

const copy = async (value: unknown) => {
	await navigator.clipboard.writeText(String(value || ''))
	notify(t('smartcook', 'Link copied'))
}
</script>

<template>
	<div class="view-stack editor-view">
		<div class="editor-actions">
			<div class="tabs">
				<button :class="{ active: activeTab === 'recipe' }" @click="activeTab = 'recipe'">{{ t('smartcook', 'Recipe') }}</button>
				<button v-if="recipe.id" :class="{ active: activeTab === 'sharing' }" @click="activeTab = 'sharing'">{{ t('smartcook', 'Sharing') }}</button>
				<button v-if="recipe.id" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">{{ t('smartcook', 'History') }}</button>
			</div>
			<div><button v-if="recipe.id" class="danger ghost" @click="deleteRecipe">{{ t('smartcook', 'Delete') }}</button><button class="primary" @click="save">{{ t('smartcook', 'Save recipe') }}</button></div>
		</div>

		<template v-if="activeTab === 'recipe'">
			<section class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Identity') }}</p><h2>{{ t('smartcook', 'Recipe overview') }}</h2></div><label class="check-inline"><input v-model="recipe.favorite" type="checkbox"> ★ {{ t('smartcook', 'Favorite') }}</label></div>
				<div class="form-grid">
					<label class="span-2">{{ t('smartcook', 'Title') }}<input v-model="recipe.title" required maxlength="255" :placeholder="t('smartcook', 'e.g. Risotto alla milanese')"></label>
					<label class="span-2">{{ t('smartcook', 'Subtitle') }}<input v-model="recipe.subtitle" maxlength="255"></label>
					<label class="span-2">{{ t('smartcook', 'Description') }}<textarea v-model="recipe.description" rows="4"></textarea></label>
					<label>{{ t('smartcook', 'Language') }}<input v-model="recipe.language" placeholder="it"></label>
					<label>{{ t('smartcook', 'Author') }}<input v-model="recipe.author"></label>
					<label>{{ t('smartcook', 'Status') }}<select v-model="recipe.status"><option value="draft">{{ t('smartcook', 'Draft') }}</option><option value="published">{{ t('smartcook', 'Published') }}</option></select></label>
					<label>{{ t('smartcook', 'Visibility') }}<select v-model="recipe.visibility"><option value="private">{{ t('smartcook', 'Private') }}</option><option value="shared">{{ t('smartcook', 'Shared') }}</option><option value="public">{{ t('smartcook', 'Public') }}</option></select></label>
					<label class="span-2">{{ t('smartcook', 'Source URL') }}<input v-model="recipe.sourceUrl" type="url" placeholder="https://…"></label>
					<label class="span-2">{{ t('smartcook', 'Image URL') }}<input v-model="recipe.imagePath" type="url" placeholder="https://…"><small>{{ t('smartcook', 'Or upload a cover image below') }}</small></label>
					<label class="span-2 cover-upload">{{ t('smartcook', 'Cover image') }}<input type="file" accept="image/*" @change="coverAttachment = ($event.target as HTMLInputElement).files?.[0] || null"><small>{{ t('smartcook', 'The uploaded image becomes the recipe cover after saving.') }}</small></label>
				</div>
			</section>

			<section class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Timing') }}</p><h2>{{ t('smartcook', 'Yield and preparation') }}</h2></div><strong>{{ totalTime }} min {{ t('smartcook', 'total') }}</strong></div>
				<div class="form-grid four">
					<label>{{ t('smartcook', 'Servings') }}<input v-model.number="recipe.servings" type="number" min="1"></label>
					<label>{{ t('smartcook', 'Preparation') }}<input v-model.number="recipe.prepTime" type="number" min="0"><small>min</small></label>
					<label>{{ t('smartcook', 'Rest') }}<input v-model.number="recipe.restTime" type="number" min="0"><small>min</small></label>
					<label>{{ t('smartcook', 'Cooking') }}<input v-model.number="recipe.cookTime" type="number" min="0"><small>min</small></label>
					<label>{{ t('smartcook', 'Difficulty') }}<input v-model="recipe.difficulty"></label>
					<label>{{ t('smartcook', 'Cuisine') }}<input v-model="recipe.cuisine"></label>
					<label>{{ t('smartcook', 'Course') }}<input v-model="recipe.course"></label>
					<label>{{ t('smartcook', 'Calories') }}<input v-model.number="recipe.calories" type="number" min="0"></label>
				</div>
			</section>

			<section class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Structured data') }}</p><h2>{{ t('smartcook', 'Ingredients') }}</h2></div><button class="secondary" @click="addIngredient">＋ {{ t('smartcook', 'Add ingredient') }}</button></div>
				<div class="ingredient-table">
					<div class="ingredient-head"><span>{{ t('smartcook', 'Quantity') }}</span><span>{{ t('smartcook', 'Unit') }}</span><span>{{ t('smartcook', 'Ingredient') }}</span><span>{{ t('smartcook', 'Notes') }}</span><span></span></div>
					<div v-for="(ingredient, index) in recipe.ingredients" :key="index" class="ingredient-row">
						<input v-model="ingredient.quantity" placeholder="2 ½">
						<input v-model="ingredient.unit" placeholder="g / ml / cup">
						<input v-model="ingredient.name" :placeholder="t('smartcook', 'Ingredient name')">
						<input v-model="ingredient.notes" :placeholder="t('smartcook', 'Optional notes')">
						<button class="icon-button danger" @click="recipe.ingredients.splice(index, 1)">×</button>
					</div>
				</div>
			</section>

			<section class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Method') }}</p><h2>{{ t('smartcook', 'Procedure') }}</h2></div><button class="secondary" @click="addStep">＋ {{ t('smartcook', 'Add step') }}</button></div>
				<div class="step-list">
					<div v-for="(step, index) in recipe.steps" :key="index" class="step-editor">
						<span>{{ index + 1 }}</span>
						<div><textarea v-model="step.text" rows="3" :placeholder="t('smartcook', 'Describe this step…')"></textarea><div class="inline-fields"><label>{{ t('smartcook', 'Timer quantity') }}<input v-model.number="step.timerValue" type="number" min="0" step="any"></label><label>{{ t('smartcook', 'Timer unit') }}<select v-model="step.timerUnit"><option value="seconds">{{ t('smartcook', 'seconds') }}</option><option value="minutes">{{ t('smartcook', 'minutes') }}</option><option value="hours">{{ t('smartcook', 'hours') }}</option></select></label><label class="temperature-field">{{ t('smartcook', 'Temperature') }}<span><input v-model.number="step.temperature" type="number"><select v-model="step.temperatureUnit"><option value="°C">°C</option><option value="°F">°F</option></select></span></label></div></div>
						<button class="icon-button danger" @click="recipe.steps.splice(index, 1)">×</button>
					</div>
				</div>
			</section>

			<section class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Organization') }}</p><h2>{{ t('smartcook', 'Tags, categories and tools') }}</h2></div></div>
				<div class="form-grid">
					<label class="span-2">{{ t('smartcook', 'Tags') }}<input v-model="tagsText" :placeholder="t('smartcook', 'vegetarian, quick, meal prep')"><small>{{ t('smartcook', 'Separate values with commas') }}</small></label>
					<label>{{ t('smartcook', 'Categories') }}<input v-model="categoriesText" :placeholder="t('smartcook', 'Main course, Dessert')"></label>
					<label>{{ t('smartcook', 'Tools') }}<input v-model="toolsText" :placeholder="t('smartcook', 'Oven, Mixer')"></label>
					<label class="span-2">{{ t('smartcook', 'Notes') }}<textarea v-model="recipe.notes" rows="3"></textarea></label>
				</div>
			</section>

			<section v-if="recipe.id" class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Nextcloud Files') }}</p><h2>{{ t('smartcook', 'Attachments') }}</h2></div></div>
				<div class="upload-row"><input type="file" @change="attachment = ($event.target as HTMLInputElement).files?.[0] || null"><button class="secondary" :disabled="!attachment" @click="uploadAttachment">{{ t('smartcook', 'Upload') }}</button></div>
				<ul class="attachment-list"><li v-for="item in recipe.media" :key="item.id || item.path"><a :href="item.id ? mediaUrl(item.id) : '#'" target="_blank" rel="noopener"><span>{{ item.kind === 'image' ? '▧' : item.kind === 'pdf' ? 'PDF' : '↗' }}</span><div><strong>{{ item.altText || item.path.split('/').pop() }}</strong><small>{{ item.mime || item.kind }} · {{ formatBytes(item.fileSize) }} · {{ formatMediaDate(item.createdAt) }}</small></div></a></li></ul>
			</section>
		</template>

		<section v-else-if="activeTab === 'sharing'" class="two-column">
			<article class="panel form-section">
				<p class="eyebrow">{{ t('smartcook', 'Access') }}</p><h2>{{ t('smartcook', 'Create a share') }}</h2>
				<div class="form-grid">
					<label>{{ t('smartcook', 'Type') }}<select v-model="shareType"><option value="link">{{ t('smartcook', 'Public link') }}</option><option value="user">{{ t('smartcook', 'Nextcloud user') }}</option><option value="group">{{ t('smartcook', 'Nextcloud group') }}</option></select></label>
					<label v-if="shareType !== 'link'">{{ t('smartcook', 'User or group ID') }}<input v-model="shareWith"></label>
					<label v-if="shareType === 'link'">{{ t('smartcook', 'Optional password') }}<input v-model="sharePassword" type="password"></label>
					<label class="check-inline"><input v-model="shareEdit" type="checkbox"> {{ t('smartcook', 'Allow editing') }}</label>
				</div><button class="primary" @click="createShare">{{ t('smartcook', 'Create share') }}</button>
			</article>
			<article class="panel"><p class="eyebrow">{{ t('smartcook', 'Existing access') }}</p><h2>{{ t('smartcook', 'Shares') }}</h2><div class="share-list"><div v-for="share in shares" :key="String(share.id)"><div><strong>{{ share.type === 'link' ? t('smartcook', 'Public link') : share.shareWith }}</strong><small>{{ Number(share.permission) & 2 ? t('smartcook', 'Can edit') : t('smartcook', 'Read only') }}<span v-if="share.passwordProtected"> · {{ t('smartcook', 'Password protected') }}</span></small><input v-if="share.url" :value="String(share.url)" readonly @focus="($event.target as HTMLInputElement).select()"></div><button v-if="share.url" class="secondary" @click="copy(share.url)">{{ t('smartcook', 'Copy') }}</button><button class="icon-button danger" @click="removeShare(share)">×</button></div><p v-if="!shares.length">{{ t('smartcook', 'No shares yet') }}</p></div></article>
		</section>

		<section v-else class="panel">
			<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Audit trail') }}</p><h2>{{ t('smartcook', 'Version history') }}</h2></div><div v-if="recipe.id" class="export-links"><a :href="exportUrl(recipe.id, 'json')">JSON-LD</a><a :href="exportUrl(recipe.id, 'markdown')">Markdown</a><a :href="exportUrl(recipe.id, 'html')">HTML / PDF</a></div></div>
			<div class="version-list"><div v-for="version in versions" :key="version.revision"><div><strong>{{ t('smartcook', 'Revision') }} {{ version.revision }}</strong><small>{{ new Date(version.createdAt * 1000).toLocaleString() }} · {{ version.userId }}</small></div><button class="secondary" :disabled="version.revision === recipe.revision" @click="restore(version.revision)">{{ t('smartcook', 'Restore') }}</button></div></div>
		</section>
	</div>
</template>
