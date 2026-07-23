<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { api } from '@/api/client'
import { notify, runBusy } from '@/stores/app'
import type { ImportPreview } from '@/types'

const kind = ref<'url' | 'text' | 'markdown' | 'json' | 'file'>('url')
const url = ref('')
const text = ref('')
const file = ref<File | null>(null)
const language = ref(document.documentElement.lang || 'it')
const useAi = ref(false)
const extracting = ref(false)
const provider = ref('')
const preview = ref<ImportPreview | null>(null)
const sourceKinds: Array<{ id: typeof kind.value; label: string }> = [
	{ id: 'url', label: 'URL' },
	{ id: 'text', label: t('smartcook', 'Text') },
	{ id: 'markdown', label: 'Markdown' },
	{ id: 'json', label: 'JSON' },
	{ id: 'file', label: t('smartcook', 'File / OCR') },
]

const canImport = computed(() => kind.value === 'file' ? file.value !== null : kind.value === 'url' ? url.value.trim() !== '' : text.value.trim() !== '')
const modalText = (key: string) => {
	if ((document.documentElement.lang || '').toLowerCase().startsWith('it')) {
		return {
			'AI extraction in progress': 'Estrazione AI in corso',
			'The AI is refining the recipe. This may take a little longer.': 'L’AI sta perfezionando la ricetta. Potrebbe richiedere qualche secondo in più.',
			'Extracting recipe data': 'Estrazione dei dati della ricetta',
			'Please wait while the recipe is being analyzed.': 'Attendi mentre la ricetta viene analizzata.',
		}[key] || key
	}
	return t('smartcook', key)
}

const extract = async () => {
	extracting.value = true
	try {
		await runBusy(async () => {
			preview.value = kind.value === 'file'
				? await api.previewFile(file.value!, language.value, useAi.value, provider.value || undefined)
				: await api.previewImport(kind.value, kind.value === 'url' ? { url: url.value, language: language.value } : { text: text.value, language: language.value }, useAi.value, provider.value || undefined)
			notify(t('smartcook', 'Recipe data extracted. Review it before saving.'))
		})
	} finally {
		extracting.value = false
	}
}

const save = async () => {
	if (!preview.value) return
	await runBusy(async () => {
		const saved = await api.createRecipe(preview.value!.recipe)
		notify(t('smartcook', 'Imported recipe saved'))
		location.hash = `#/recipes/${saved.id}`
	})
}
</script>

<template>
	<div class="view-stack">
		<section class="import-hero panel">
			<div><p class="eyebrow">{{ t('smartcook', 'Smart import') }}</p><h2>{{ t('smartcook', 'Turn almost any source into a structured recipe') }}</h2><p>{{ t('smartcook', 'SmartCook checks Schema.org data first, then deterministic parsing, and uses AI only when requested.') }}</p></div>
			<div class="pipeline"><span>URL / Text / PDF</span><b>→</b><span>Parser</span><b>→</b><span>Review</span><b>→</b><span>Recipe</span></div>
		</section>
		<section class="two-column import-layout">
			<article class="panel form-section">
				<div class="source-tabs"><button v-for="entry in sourceKinds" :key="entry.id" :class="{ active: kind === entry.id }" @click="kind = entry.id">{{ entry.label }}</button></div>
				<label v-if="kind === 'url'">{{ t('smartcook', 'Recipe URL') }}<input v-model="url" type="url" placeholder="https://example.com/recipe"></label>
				<label v-else-if="kind === 'file'">{{ t('smartcook', 'PDF, image, Markdown, HTML or JSON') }}<input type="file" accept="image/*,.pdf,.txt,.md,.markdown,.html,.htm,.json" @change="file = ($event.target as HTMLInputElement).files?.[0] || null"><small>{{ t('smartcook', 'Images and PDFs require a configured OCR/document extractor.') }}</small></label>
				<label v-else>{{ t('smartcook', 'Source content') }}<textarea v-model="text" rows="18" :placeholder="t('smartcook', 'Paste the recipe, including ingredients and procedure…')"></textarea></label>
				<div class="form-grid">
					<label>{{ t('smartcook', 'Output language') }}<input v-model="language" placeholder="it"></label>
					<label>{{ t('smartcook', 'AI provider override') }}<select v-model="provider"><option value="">{{ t('smartcook', 'Use settings') }}</option><option value="nextcloud">Nextcloud Assistant</option><option value="openai">OpenAI</option><option value="openrouter">OpenRouter</option><option value="ollama">Ollama</option><option value="localai">LocalAI</option><option value="mistral">Mistral</option><option value="anthropic">Anthropic</option><option value="gemini">Gemini</option></select></label>
				</div>
				<label class="check-inline ai-toggle"><input v-model="useAi" type="checkbox"> <span><b>{{ t('smartcook', 'Use AI refinement') }}</b><small>{{ t('smartcook', 'Optional fallback for incomplete or unstructured sources') }}</small></span></label>
				<button class="primary full" :disabled="!canImport" @click="extract">{{ t('smartcook', 'Extract recipe data') }}</button>
			</article>

			<article class="panel preview-panel">
				<template v-if="preview">
					<div class="section-heading"><div><p class="eyebrow">{{ preview.strategy }}</p><h2>{{ t('smartcook', 'Import preview') }}</h2></div><button class="primary" @click="save">{{ t('smartcook', 'Save recipe') }}</button></div>
					<div v-if="preview.warnings.length" class="warning-list"><p v-for="warning in preview.warnings" :key="warning">⚠ {{ warning }}</p></div>
					<img v-if="preview.recipe.imagePath && /^https?:\/\//i.test(preview.recipe.imagePath)" class="import-cover-preview" :src="preview.recipe.imagePath" :alt="preview.recipe.title">
					<label>{{ t('smartcook', 'Title') }}<input v-model="preview.recipe.title"></label>
					<label>{{ t('smartcook', 'Description') }}<textarea v-model="preview.recipe.description" rows="3"></textarea></label>
					<div class="preview-metrics"><span>{{ preview.recipe.servings }} {{ t('smartcook', 'servings') }}</span><span>{{ preview.recipe.prepTime }} min {{ t('smartcook', 'prep') }}</span><span>{{ preview.recipe.cookTime }} min {{ t('smartcook', 'cook') }}</span><span>{{ preview.recipe.totalTime }} min {{ t('smartcook', 'total') }}</span></div>
					<div class="preview-columns"><div><h3>{{ t('smartcook', 'Ingredients') }} <small>{{ preview.recipe.ingredients.length }}</small></h3><ul><li v-for="(item, index) in preview.recipe.ingredients" :key="index"><b>{{ item.quantity }} {{ item.unit }}</b> {{ item.name }}</li></ul></div><div><h3>{{ t('smartcook', 'Procedure') }} <small>{{ preview.recipe.steps.length }}</small></h3><ol><li v-for="(step, index) in preview.recipe.steps" :key="index">{{ step.text }}</li></ol></div></div>
					<div v-if="preview.duplicates.length" class="duplicate-box"><h3>{{ t('smartcook', 'Possible duplicates') }}</h3><a v-for="match in preview.duplicates" :key="match.recipe.id" :href="`#/recipes/${match.recipe.id}`">{{ match.recipe.title }} <span>{{ Math.round(match.score * 100) }}%</span></a></div>
				</template>
				<div v-else class="empty-preview"><div>⌁</div><h2>{{ t('smartcook', 'Preview appears here') }}</h2><p>{{ t('smartcook', 'The source is never saved as a recipe until you review and confirm the extracted fields.') }}</p></div>
			</article>
		</section>
		<div v-if="extracting" class="blocking-modal" role="dialog" aria-modal="true" aria-live="polite">
			<div class="blocking-modal-card">
				<span class="loading-spinner" aria-hidden="true"></span>
				<h2>{{ useAi ? modalText('AI extraction in progress') : modalText('Extracting recipe data') }}</h2>
				<p>{{ useAi ? modalText('The AI is refining the recipe. This may take a little longer.') : modalText('Please wait while the recipe is being analyzed.') }}</p>
			</div>
		</div>
	</div>
</template>
