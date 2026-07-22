<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { api } from '@/api/client'
import { notify, runBusy } from '@/stores/app'

const settings = ref<Record<string, any>>({
	language: 'auto', measurementSystem: 'metric', attachmentsFolder: 'SmartCook', aiProvider: 'nextcloud', aiEndpoint: '', aiModel: '', aiTemperature: 0.1, aiTimeout: 90, aiApiKey: '', hasAiApiKey: false,
	ocrProvider: 'disabled', ocrEndpoint: '', ocrLanguage: 'ita+eng', tesseractPath: 'tesseract', pdfToTextPath: 'pdftotext', ocrApiKey: '', hasOcrApiKey: false, maxImportBytes: 3000000,
})

onMounted(() => runBusy(async () => { settings.value = { ...settings.value, ...await api.settings() } }).catch(() => undefined))
const save = async () => {
	await runBusy(async () => {
		settings.value = { ...settings.value, ...await api.saveSettings(settings.value) }
		settings.value.aiApiKey = ''
		settings.value.ocrApiKey = ''
		notify(t('smartcook', 'Settings saved'))
	})
}
</script>

<template>
	<div class="settings-layout">
		<main class="view-stack">
			<section class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'General') }}</p><h2>{{ t('smartcook', 'Library preferences') }}</h2></div></div>
				<div class="form-grid">
					<label>{{ t('smartcook', 'Default language') }}<input v-model="settings.language" placeholder="auto / it / en"></label>
					<label>{{ t('smartcook', 'Measurement system') }}<select v-model="settings.measurementSystem"><option value="metric">{{ t('smartcook', 'Metric') }}</option><option value="imperial">{{ t('smartcook', 'Imperial') }}</option></select></label>
					<label class="span-2">{{ t('smartcook', 'Attachments folder in Nextcloud Files') }}<input v-model="settings.attachmentsFolder" placeholder="SmartCook"></label>
					<label>{{ t('smartcook', 'Maximum URL import size') }}<input v-model.number="settings.maxImportBytes" type="number" min="100000" max="20000000"><small>{{ t('smartcook', 'bytes') }}</small></label>
				</div>
			</section>

			<section class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Optional intelligence') }}</p><h2>{{ t('smartcook', 'AI provider') }}</h2></div><span class="status-pill" :class="{ enabled: settings.aiProvider !== 'disabled' }">{{ settings.aiProvider === 'disabled' ? t('smartcook', 'Disabled') : t('smartcook', 'Enabled') }}</span></div>
				<div class="form-grid">
					<label>{{ t('smartcook', 'Provider') }}<select v-model="settings.aiProvider"><option value="disabled">{{ t('smartcook', 'Disabled') }}</option><option value="nextcloud">Nextcloud Assistant</option><option value="openai">OpenAI</option><option value="openrouter">OpenRouter</option><option value="ollama">Ollama</option><option value="localai">LocalAI</option><option value="mistral">Mistral</option><option value="anthropic">Anthropic</option><option value="gemini">Gemini</option><option value="custom">{{ t('smartcook', 'Custom OpenAI-compatible') }}</option></select></label>
					<label>{{ t('smartcook', 'Model') }}<input v-model="settings.aiModel" placeholder="model name"></label>
					<label class="span-2">{{ t('smartcook', 'Endpoint') }}<input v-model="settings.aiEndpoint" type="url" placeholder="https://…"><small>{{ t('smartcook', 'Leave empty for the provider default. Ollama and LocalAI use local defaults.') }}</small></label>
					<label>{{ t('smartcook', 'API key') }}<input v-model="settings.aiApiKey" type="password" autocomplete="new-password" :placeholder="settings.hasAiApiKey ? t('smartcook', 'Key already stored; leave blank to keep it') : t('smartcook', 'API key')"></label>
					<label>{{ t('smartcook', 'Temperature') }}<input v-model.number="settings.aiTemperature" type="number" min="0" max="2" step="0.1"></label>
					<label>{{ t('smartcook', 'Timeout') }}<input v-model.number="settings.aiTimeout" type="number" min="10" max="300"><small>{{ t('smartcook', 'seconds') }}</small></label>
				</div>
				<div class="info-box"><b>{{ t('smartcook', 'Nextcloud Assistant') }}</b><p>{{ t('smartcook', 'Uses the language-model provider already configured by the instance administrator and requires no duplicate API key.') }}</p></div>
			</section>

			<section class="panel form-section">
				<div class="section-heading"><div><p class="eyebrow">{{ t('smartcook', 'Documents') }}</p><h2>{{ t('smartcook', 'OCR and PDF extraction') }}</h2></div><span class="status-pill" :class="{ enabled: settings.ocrProvider !== 'disabled' }">{{ settings.ocrProvider === 'disabled' ? t('smartcook', 'Disabled') : t('smartcook', 'Enabled') }}</span></div>
				<div class="form-grid">
					<label>{{ t('smartcook', 'Extractor') }}<select v-model="settings.ocrProvider"><option value="disabled">{{ t('smartcook', 'Disabled') }}</option><option value="local">{{ t('smartcook', 'Local Tesseract / pdftotext') }}</option><option value="external">{{ t('smartcook', 'External HTTP service') }}</option></select></label>
					<label>{{ t('smartcook', 'OCR languages') }}<input v-model="settings.ocrLanguage" placeholder="ita+eng"></label>
					<template v-if="settings.ocrProvider === 'local'"><label>{{ t('smartcook', 'Tesseract executable') }}<input v-model="settings.tesseractPath"></label><label>{{ t('smartcook', 'pdftotext executable') }}<input v-model="settings.pdfToTextPath"></label></template>
					<template v-if="settings.ocrProvider === 'external'"><label class="span-2">{{ t('smartcook', 'External endpoint') }}<input v-model="settings.ocrEndpoint" type="url"></label><label>{{ t('smartcook', 'API key') }}<input v-model="settings.ocrApiKey" type="password" autocomplete="new-password" :placeholder="settings.hasOcrApiKey ? t('smartcook', 'Key already stored; leave blank to keep it') : t('smartcook', 'API key')"></label></template>
				</div>
			</section>
			<div class="save-bar"><button class="primary" @click="save">{{ t('smartcook', 'Save settings') }}</button></div>
		</main>
		<aside class="panel privacy-card"><p class="eyebrow">{{ t('smartcook', 'Privacy') }}</p><h2>{{ t('smartcook', 'You control every processor') }}</h2><p>{{ t('smartcook', 'Deterministic URL and text parsing stays in your Nextcloud. Content is sent to an AI or external OCR service only when you enable and invoke it.') }}</p><ul><li>{{ t('smartcook', 'API keys are encrypted with the Nextcloud server secret.') }}</li><li>{{ t('smartcook', 'Imported data is always shown as an editable preview.') }}</li><li>{{ t('smartcook', 'URL imports reject private and reserved network addresses.') }}</li></ul></aside>
	</div>
</template>
