/*
 * SmartCook compatibility frontend.
 * Dependency-free TypeScript bundle included in release archives so the app can
 * be installed without Node.js. The Vue/Vite source in src/ remains available
 * for teams that prefer the standard Nextcloud frontend stack.
 */

type JsonRecord = Record<string, unknown>

interface SmartCookWindow extends Window {
	OC?: {
		generateUrl?: (path: string, params?: Record<string, string | number>) => string
		requestToken?: string
	}
	t?: (app: string, text: string, params?: Record<string, string | number>) => string
}

interface NamedEntity { id?: number; name: string; color?: string | null }
interface Ingredient {
	id?: number
	name: string
	originalText?: string | null
	quantity?: string | null
	amount?: number | null
	unit?: string | null
	notes?: string | null
	optional?: boolean
	category?: string | null
	allergens?: string[]
	substitutes?: string[]
}
interface RecipeStep {
	id?: number
	text: string
	timerSeconds?: number | null
	temperature?: number | null
	temperatureUnit?: string | null
	notes?: string | null
}
interface RecipeMedia { id?: number; kind: string; path: string; mime?: string | null; altText?: string | null; fileSize?: number | null; createdAt?: number }
interface Recipe {
	id?: number
	title: string
	subtitle?: string | null
	description?: string | null
	language: string
	author?: string | null
	sourceName?: string | null
	sourceUrl?: string | null
	license?: string | null
	status: 'draft' | 'published'
	visibility: 'private' | 'shared' | 'public'
	favorite: boolean
	servings: number
	yieldText?: string | null
	prepTime: number
	restTime: number
	cookTime: number
	totalTime: number
	difficulty?: string | null
	costCents?: number | null
	currency?: string | null
	cuisine?: string | null
	course?: string | null
	mealType?: string | null
	cookingMethod?: string | null
	season?: string | null
	origin?: string | null
	calories?: number | null
	nutrition: Record<string, unknown>
	notes?: string | null
	imagePath?: string | null
	cookCount?: number
	createdAt?: number
	updatedAt?: number
	revision?: number
	ingredients: Ingredient[]
	steps: RecipeStep[]
	tools: NamedEntity[]
	tags: NamedEntity[]
	categories: NamedEntity[]
	media: RecipeMedia[]
}
interface Meal { id: number; recipeId: number; recipeTitle: string; date: string; slot: string; servings: number; notes?: string | null }
interface ShoppingItem { id: number; name: string; quantity?: string | null; unit?: string | null; category?: string | null; checked: boolean; notes?: string | null }
interface ShoppingList { id: number; name: string; status: string; items?: ShoppingItem[]; createdAt: number; updatedAt: number }
interface ImportPreview { recipe: Recipe; strategy: string; warnings: string[]; duplicates: Array<{ recipe: Recipe; score: number }> }

const smartWindow = window as SmartCookWindow
const appId = 'smartcook'
const root = document.getElementById('smartcook')
const publicRoot = document.getElementById('smartcook-public')
let busyCount = 0
let messageTimer = 0

const tr = (text: string): string => smartWindow.t ? smartWindow.t(appId, text) : text
const mealLabel = (slot: string): string => tr(({ breakfast: 'Breakfast', lunch: 'Lunch', dinner: 'Dinner', snack: 'Snack' } as Record<string, string>)[slot] || 'Meal')
const esc = (value: unknown): string => String(value ?? '')
	.replaceAll('&', '&amp;')
	.replaceAll('<', '&lt;')
	.replaceAll('>', '&gt;')
	.replaceAll('"', '&quot;')
	.replaceAll("'", '&#039;')
const attr = esc
const safeExternalUrl = (value: unknown): string => {
	const text = String(value ?? '')
	return /^https?:\/\//i.test(text) ? text : ''
}
const asNumber = (value: unknown, fallback = 0): number => {
	const parsed = Number(value)
	return Number.isFinite(parsed) ? parsed : fallback
}
const asText = (value: unknown): string => String(value ?? '').trim()
const dateIso = (value: Date): string => {
	const local = new Date(value.getTime() - value.getTimezoneOffset() * 60000)
	return local.toISOString().slice(0, 10)
}
const appUrl = (path: string): string => smartWindow.OC?.generateUrl
	? smartWindow.OC.generateUrl(`/apps/${appId}${path}`)
	: `/index.php/apps/${appId}${path}`
const mediaUrl = (id: number): string => appUrl(`/media/${id}`)
const formatBytes = (value?: number | null): string => {
	if (!value || value < 0) return '—'
	if (value < 1024) return `${value} B`
	if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`
	return `${(value / (1024 * 1024)).toFixed(1)} MB`
}
const formatMediaDate = (value?: number): string => value ? new Date(value * 1000).toLocaleString() : '—'
const recipeImageUrl = (value: unknown): string => {
	const text = String(value ?? '')
	const stored = /^media:(\d+)$/.exec(text)
	return stored ? mediaUrl(Number(stored[1])) : safeExternalUrl(text)
}
const exportUrl = (id: number, format: string): string => appUrl(`/recipes/${id}/export/${format}`)

class ApiError extends Error {
	status: number
	fields: Record<string, string>
	constructor(message: string, status: number, fields: Record<string, string> = {}) {
		super(message)
		this.status = status
		this.fields = fields
	}
}

async function request<T>(path: string, options: RequestInit & { json?: unknown } = {}): Promise<T> {
	const headers = new Headers(options.headers || {})
	headers.set('Accept', 'application/json')
	if (options.json !== undefined) {
		headers.set('Content-Type', 'application/json')
		options.body = JSON.stringify(options.json)
	}
	if ((options.method || 'GET').toUpperCase() !== 'GET' && smartWindow.OC?.requestToken) {
		headers.set('requesttoken', smartWindow.OC.requestToken)
	}
	const response = await fetch(appUrl(path), { ...options, headers, credentials: 'same-origin' })
	const contentType = response.headers.get('content-type') || ''
	const data = contentType.includes('json') ? await response.json() : await response.text()
	if (!response.ok) {
		const body = typeof data === 'object' && data !== null ? data as { error?: string; errors?: Record<string, string> } : {}
		throw new ApiError(body.error || `${tr('Request failed')} (${response.status})`, response.status, body.errors || {})
	}
	return data as T
}

function setBusy(active: boolean): void {
	busyCount = Math.max(0, busyCount + (active ? 1 : -1))
	const node = document.querySelector<HTMLElement>('[data-smartcook-busy]')
	if (node) {
		node.hidden = busyCount === 0
		node.textContent = tr('Working...')
	}
}

async function working<T>(operation: () => Promise<T>): Promise<T> {
	setBusy(true)
	clearNotice('error')
	try {
		return await operation()
	} catch (error) {
		showNotice(error instanceof Error ? error.message : tr('Unexpected error'), 'error')
		throw error
	} finally {
		setBusy(false)
	}
}

function showNotice(message: string, type: 'success' | 'error' = 'success'): void {
	const holder = document.querySelector<HTMLElement>('[data-smartcook-notices]')
	if (!holder) return
	holder.innerHTML = `<div class="notice ${type}" role="${type === 'error' ? 'alert' : 'status'}"><span>${esc(message)}</span><button type="button" aria-label="${attr(tr('Close'))}">x</button></div>`
	holder.querySelector('button')?.addEventListener('click', () => { holder.innerHTML = '' })
	window.clearTimeout(messageTimer)
	if (type === 'success') messageTimer = window.setTimeout(() => { holder.innerHTML = '' }, 4000)
}

function clearNotice(type?: 'success' | 'error'): void {
	const notice = document.querySelector<HTMLElement>('[data-smartcook-notices] .notice')
	if (!notice || (type && !notice.classList.contains(type))) return
	notice.remove()
}

function emptyRecipe(): Recipe {
	return {
		title: '', subtitle: null, description: null, language: document.documentElement.lang || 'en', author: null,
		sourceName: null, sourceUrl: null, license: null, status: 'draft', visibility: 'private', favorite: false,
		servings: 4, yieldText: null, prepTime: 0, restTime: 0, cookTime: 0, totalTime: 0, difficulty: null,
		costCents: null, currency: 'EUR', cuisine: null, course: null, mealType: null, cookingMethod: null,
		season: null, origin: null, calories: null, nutrition: {}, notes: null, imagePath: null,
		ingredients: [{ name: '', quantity: null, unit: null }], steps: [{ text: '' }], tools: [], tags: [], categories: [], media: [],
	}
}

function splitNames(value: string): NamedEntity[] {
	return [...new Set(value.split(/[,;]+/).map(item => item.trim()).filter(Boolean))].map(name => ({ name }))
}

function shellTitle(section: string, id?: number): string {
	const titles: Record<string, string> = {
		dashboard: tr('Dashboard'), recipes: tr('Recipes'), editor: id ? tr('Edit recipe') : tr('New recipe'),
		import: tr('Import'), planner: tr('Meal planner'), shopping: tr('Shopping lists'), settings: tr('Settings'),
	}
	return titles[section] || 'SmartCook'
}

function parseRoute(): { section: string; id?: number } {
	const parts = (location.hash.replace(/^#\/?/, '') || 'dashboard').split('/')
	if (parts[0] === 'recipes' && parts[1]) return { section: 'editor', id: asNumber(parts[1]) || undefined }
	if (parts[0] === 'new') return { section: 'editor' }
	return { section: parts[0] || 'dashboard' }
}

function renderShell(section: string, id?: number): HTMLElement {
	if (!root) throw new Error('SmartCook root was not found')
	const nav = [
		['dashboard', tr('Dashboard')], ['recipes', tr('Recipes')], ['import', tr('Import')],
		['planner', tr('Meal planner')], ['shopping', tr('Shopping lists')], ['settings', tr('Settings')],
	]
	root.innerHTML = `<div class="smartcook-shell">
		<aside class="smartcook-sidebar" aria-label="SmartCook">
			<div class="brand"><img src="${attr(appUrl('/img/app.svg'))}" alt=""><div><strong>SmartCook</strong><span>${esc(tr('Recipe intelligence'))}</span></div></div>
			<nav>${nav.map(([route, label]) => `<a class="${section === route || (route === 'recipes' && section === 'editor') ? 'active' : ''}" href="#/${route}">${esc(label)}</a>`).join('')}</nav>
			<a class="primary full" href="#/new">+ ${esc(tr('New recipe'))}</a>
		</aside>
		<main class="smartcook-content">
			<header class="page-header"><div><h1>${esc(shellTitle(section, id))}</h1><p>${esc(tr('Self-hosted recipes, structured and searchable'))}</p></div><div class="busy" data-smartcook-busy hidden>${esc(tr('Working...'))}</div></header>
			<div data-smartcook-notices></div>
			<div data-smartcook-view class="view-stack"></div>
		</main>
	</div>`
	return root.querySelector<HTMLElement>('[data-smartcook-view]')!
}

function recipeThumb(recipe: Recipe): string {
	const image = recipeImageUrl(recipe.imagePath)
	return image
		? `<img src="${attr(image)}" alt="">`
		: `<span>${esc((recipe.title || '?').slice(0, 1).toUpperCase())}</span>`
}

async function renderDashboard(view: HTMLElement): Promise<void> {
	interface Stats {
		recipeCount: number; favoriteCount: number; cookCount: number; averageTotalTime: number
		topTags: Array<{ name: string; count: number }>; topIngredients: Array<{ name: string; count: number }>; recentRecipes: Recipe[]
	}
	view.innerHTML = `<div class="skeleton-grid"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div>`
	const stats = await working(() => request<Stats>('/stats'))
	view.innerHTML = `<section class="metric-grid">
		<article><span>${esc(tr('Recipes'))}</span><strong>${stats.recipeCount}</strong><small>${esc(tr('in your library'))}</small></article>
		<article><span>${esc(tr('Favorites'))}</span><strong>${stats.favoriteCount}</strong><small>${esc(tr('saved for later'))}</small></article>
		<article><span>${esc(tr('Cooked'))}</span><strong>${stats.cookCount}</strong><small>${esc(tr('recorded preparations'))}</small></article>
		<article><span>${esc(tr('Average time'))}</span><strong>${stats.averageTotalTime} min</strong><small>${esc(tr('from start to finish'))}</small></article>
	</section>
	<section class="two-column">
		<article class="panel"><div class="panel-heading"><div><p class="eyebrow">${esc(tr('Library'))}</p><h2>${esc(tr('Recently updated'))}</h2></div><a href="#/recipes">${esc(tr('View all'))} -&gt;</a></div>
			${stats.recentRecipes.length ? `<div class="compact-list">${stats.recentRecipes.map(recipe => `<a href="#/recipes/${recipe.id}"><div class="recipe-thumb">${recipeThumb(recipe)}</div><div><strong>${esc(recipe.title)}</strong><small>${esc(recipe.cuisine || tr('Uncategorized'))} - ${asNumber(recipe.totalTime)} min</small></div><span>&rsaquo;</span></a>`).join('')}</div>` : `<div class="empty-state"><h3>${esc(tr('Your cookbook is ready'))}</h3><p>${esc(tr('Create a recipe or import one from a webpage or text.'))}</p><a class="primary" href="#/import">${esc(tr('Import a recipe'))}</a></div>`}
		</article>
		<div class="view-stack">
			<article class="panel dashboard-cloud"><p class="eyebrow">${esc(tr('Most used'))}</p><h2>${esc(tr('Ingredients'))}</h2><div class="tag-cloud">${stats.topIngredients.map(item => `<span>${esc(item.name)} <b>${item.count}</b></span>`).join('') || `<small>${esc(tr('No data yet'))}</small>`}</div></article>
			<article class="panel dashboard-cloud"><p class="eyebrow">${esc(tr('Organization'))}</p><h2>${esc(tr('Top tags'))}</h2><div class="tag-cloud">${stats.topTags.map(item => `<span>#${esc(item.name)} <b>${item.count}</b></span>`).join('') || `<small>${esc(tr('No tags yet'))}</small>`}</div></article>
		</div>
	</section>`
}

async function renderRecipes(view: HTMLElement): Promise<void> {
	view.innerHTML = `<section class="toolbar panel">
		<label class="search-field"><span>&#9906;</span><input data-search placeholder="${attr(tr('Search recipes, cuisine or course...'))}"></label>
		<label class="check-inline"><input data-favorites type="checkbox"> ${esc(tr('Favorites only'))}</label>
		<label>${esc(tr('Sort'))}<select data-sort><option value="updated_at">${esc(tr('Recently updated'))}</option><option value="title">${esc(tr('Title'))}</option><option value="total_time">${esc(tr('Total time'))}</option><option value="cook_count">${esc(tr('Most cooked'))}</option></select></label>
		<a class="primary" href="#/new">+ ${esc(tr('New recipe'))}</a>
	</section><section data-recipe-results></section>`
	const results = view.querySelector<HTMLElement>('[data-recipe-results]')!
	const search = view.querySelector<HTMLInputElement>('[data-search]')!
	const favorites = view.querySelector<HTMLInputElement>('[data-favorites]')!
	const sort = view.querySelector<HTMLSelectElement>('[data-sort]')!
	let timer = 0
	const load = async () => {
		const params = new URLSearchParams({ search: search.value, favorite: favorites.checked ? '1' : '', sort: sort.value })
		const payload = await working(() => request<{ recipes: Recipe[] }>(`/recipes?${params.toString()}`))
		const recipes = payload.recipes
		results.innerHTML = recipes.length ? `<div class="recipe-grid">${recipes.map(recipe => {
			const image = recipeImageUrl(recipe.imagePath)
			return `<article class="recipe-card"><a class="recipe-image" href="#/recipes/${recipe.id}">${image ? `<img src="${attr(image)}" alt="">` : `<div class="image-placeholder"><span>${esc(recipe.title.slice(0, 1).toUpperCase())}</span></div>`}<span class="time-pill">${asNumber(recipe.totalTime || recipe.prepTime + recipe.cookTime)} min</span></a>
			<div class="recipe-card-body"><div class="card-title"><div><p>${esc(recipe.cuisine || recipe.course || tr('Recipe'))}</p><a href="#/recipes/${recipe.id}"><h2>${esc(recipe.title)}</h2></a></div><button class="icon-button" data-favorite-id="${recipe.id}" data-favorite="${recipe.favorite ? '1' : '0'}" aria-label="${attr(tr('Toggle favorite'))}">${recipe.favorite ? '&#9733;' : '&#9734;'}</button></div><p>${esc(recipe.description || tr('No description'))}</p><div class="card-meta"><span>${asNumber(recipe.prepTime)} + ${asNumber(recipe.cookTime)} min</span><span>${asNumber(recipe.servings)}</span>${recipe.difficulty ? `<span>${esc(recipe.difficulty)}</span>` : ''}</div></div></article>`
		}).join('')}</div>` : `<section class="panel empty-state"><h2>${esc(tr('No recipes found'))}</h2><p>${esc(tr('Change the filters, create a recipe, or import one from a URL.'))}</p><div><a class="primary" href="#/import">${esc(tr('Import recipe'))}</a> <a class="secondary" href="#/new">${esc(tr('Create manually'))}</a></div></section>`
		results.querySelectorAll<HTMLButtonElement>('[data-favorite-id]').forEach(button => button.addEventListener('click', async () => {
			const id = asNumber(button.dataset.favoriteId)
			const newValue = button.dataset.favorite !== '1'
			await working(() => request(`/recipes/${id}/favorite`, { method: 'POST', json: { favorite: newValue } }))
			button.dataset.favorite = newValue ? '1' : '0'
			button.innerHTML = newValue ? '&#9733;' : '&#9734;'
			showNotice(newValue ? tr('Added to favorites') : tr('Removed from favorites'))
		}))
	}
	const delayed = () => { window.clearTimeout(timer); timer = window.setTimeout(() => { void load() }, 250) }
	search.addEventListener('input', delayed)
	favorites.addEventListener('change', () => { void load() })
	sort.addEventListener('change', () => { void load() })
	await load()
}

function textInput(label: string, field: string, value: unknown, options: { type?: string; className?: string; placeholder?: string; min?: number; step?: number } = {}): string {
	return `<label class="${attr(options.className || '')}">${esc(label)}<input data-field="${attr(field)}" type="${attr(options.type || 'text')}" value="${attr(value)}"${options.placeholder ? ` placeholder="${attr(options.placeholder)}"` : ''}${options.min !== undefined ? ` min="${options.min}"` : ''}${options.step !== undefined ? ` step="${options.step}"` : ''}></label>`
}

function textareaInput(label: string, field: string, value: unknown, rows = 4, className = ''): string {
	return `<label class="${attr(className)}">${esc(label)}<textarea data-field="${attr(field)}" rows="${rows}">${esc(value)}</textarea></label>`
}

function selectInput(label: string, field: string, value: unknown, options: Array<[string, string]>, className = ''): string {
	return `<label class="${attr(className)}">${esc(label)}<select data-field="${attr(field)}">${options.map(([id, name]) => `<option value="${attr(id)}"${String(value ?? '') === id ? ' selected' : ''}>${esc(name)}</option>`).join('')}</select></label>`
}

function ingredientRow(item: Ingredient = { name: '' }): string {
	return `<div class="ingredient-row" data-ingredient-row>
		<input data-ing-quantity value="${attr(item.quantity)}" placeholder="${attr(tr('Qty'))}" aria-label="${attr(tr('Quantity'))}">
		<input data-ing-unit value="${attr(item.unit)}" placeholder="${attr(tr('Unit'))}" aria-label="${attr(tr('Unit'))}">
		<input data-ing-name value="${attr(item.name)}" placeholder="${attr(tr('Ingredient'))}" aria-label="${attr(tr('Ingredient'))}">
		<input data-ing-notes value="${attr(item.notes)}" placeholder="${attr(tr('Notes'))}" aria-label="${attr(tr('Notes'))}">
		<label class="tiny-check"><input data-ing-optional type="checkbox"${item.optional ? ' checked' : ''}> ${esc(tr('Optional'))}</label>
		<button class="icon-button danger" data-remove-row type="button" aria-label="${attr(tr('Remove'))}">x</button>
	</div>`
}

function stepRow(item: RecipeStep = { text: '' }, index = 0): string {
	return `<div class="step-row" data-step-row><span class="step-number">${index + 1}</span><textarea data-step-text rows="3" placeholder="${attr(tr('Describe this step...'))}">${esc(item.text)}</textarea><div class="step-extras"><input data-step-timer type="number" min="0" value="${attr(item.timerSeconds)}" placeholder="${attr(tr('Timer seconds'))}"><input data-step-temp type="number" value="${attr(item.temperature)}" placeholder="${attr(tr('Temperature'))}"><select data-step-temp-unit><option value="C"${item.temperatureUnit === 'C' ? ' selected' : ''}>C</option><option value="F"${item.temperatureUnit === 'F' ? ' selected' : ''}>F</option></select></div><button class="icon-button danger" data-remove-row type="button" aria-label="${attr(tr('Remove'))}">x</button></div>`
}

function bindRowRemoval(container: HTMLElement): void {
	container.querySelectorAll<HTMLButtonElement>('[data-remove-row]').forEach(button => button.addEventListener('click', () => button.closest('[data-ingredient-row], [data-step-row]')?.remove()))
}

function collectRecipe(view: HTMLElement, existing: Recipe): Recipe {
	const value = (name: string): string => view.querySelector<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>(`[data-field="${name}"]`)?.value ?? ''
	const numeric = (name: string): number => asNumber(value(name))
	const ingredients = [...view.querySelectorAll<HTMLElement>('[data-ingredient-row]')].map(row => ({
		name: row.querySelector<HTMLInputElement>('[data-ing-name]')?.value.trim() || '',
		quantity: row.querySelector<HTMLInputElement>('[data-ing-quantity]')?.value.trim() || null,
		unit: row.querySelector<HTMLInputElement>('[data-ing-unit]')?.value.trim() || null,
		notes: row.querySelector<HTMLInputElement>('[data-ing-notes]')?.value.trim() || null,
		optional: row.querySelector<HTMLInputElement>('[data-ing-optional]')?.checked || false,
	})).filter(item => item.name)
	const steps = [...view.querySelectorAll<HTMLElement>('[data-step-row]')].map(row => ({
		text: row.querySelector<HTMLTextAreaElement>('[data-step-text]')?.value.trim() || '',
		timerSeconds: asNumber(row.querySelector<HTMLInputElement>('[data-step-timer]')?.value) || null,
		temperature: asNumber(row.querySelector<HTMLInputElement>('[data-step-temp]')?.value) || null,
		temperatureUnit: row.querySelector<HTMLSelectElement>('[data-step-temp-unit]')?.value || null,
	})).filter(item => item.text)
	const prepTime = numeric('prepTime')
	const restTime = numeric('restTime')
	const cookTime = numeric('cookTime')
	return {
		...existing,
		title: value('title').trim(), subtitle: value('subtitle').trim() || null, description: value('description').trim() || null,
		language: value('language').trim() || 'en', author: value('author').trim() || null, sourceName: value('sourceName').trim() || null,
		sourceUrl: value('sourceUrl').trim() || null, license: value('license').trim() || null,
		status: value('status') as Recipe['status'], visibility: value('visibility') as Recipe['visibility'],
		servings: Math.max(1, numeric('servings')), yieldText: value('yieldText').trim() || null,
		prepTime, restTime, cookTime, totalTime: prepTime + restTime + cookTime,
		difficulty: value('difficulty').trim() || null, costCents: numeric('costCents') || null, currency: value('currency').trim() || null,
		cuisine: value('cuisine').trim() || null, course: value('course').trim() || null, mealType: value('mealType').trim() || null,
		cookingMethod: value('cookingMethod').trim() || null, season: value('season').trim() || null, origin: value('origin').trim() || null,
		calories: numeric('calories') || null, notes: value('notes').trim() || null,
		ingredients, steps, tags: splitNames(value('tags')), categories: splitNames(value('categories')), tools: splitNames(value('tools')),
	}
}

function editorForm(recipe: Recipe): string {
	return `<section class="editor-top panel">
		<div><p class="eyebrow">${esc(recipe.id ? tr('Recipe details') : tr('Create manually'))}</p><h2>${esc(recipe.title || tr('Untitled recipe'))}</h2></div>
		<div class="editor-actions">${recipe.id ? `<button class="secondary" data-mark-cooked type="button">${esc(tr('Cooked today'))}</button>` : ''}<button class="primary" data-save-recipe type="button">${esc(tr('Save recipe'))}</button></div>
	</section>
	<div class="editor-layout">
		<main class="view-stack">
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Identity'))}</p><h2>${esc(tr('Recipe'))}</h2></div></div>
				<div class="form-grid">${textInput(tr('Title'), 'title', recipe.title, { className: 'span-2' })}${textInput(tr('Subtitle'), 'subtitle', recipe.subtitle, { className: 'span-2' })}${textareaInput(tr('Description'), 'description', recipe.description, 4, 'span-2')}
				${textInput(tr('Author'), 'author', recipe.author)}${textInput(tr('Language'), 'language', recipe.language)}${textInput(tr('Source name'), 'sourceName', recipe.sourceName)}${textInput(tr('Source URL'), 'sourceUrl', recipe.sourceUrl, { type: 'url' })}${textInput(tr('License'), 'license', recipe.license)}
				${selectInput(tr('Status'), 'status', recipe.status, [['draft', tr('Draft')], ['published', tr('Published')]])}${selectInput(tr('Visibility'), 'visibility', recipe.visibility, [['private', tr('Private')], ['shared', tr('Shared')], ['public', tr('Public')]])}</div>
			</section>
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Yield and timing'))}</p><h2>${esc(tr('Planning data'))}</h2></div></div><div class="form-grid four">${textInput(tr('Servings'), 'servings', recipe.servings, { type: 'number', min: 1 })}${textInput(tr('Yield'), 'yieldText', recipe.yieldText)}${textInput(tr('Preparation (min)'), 'prepTime', recipe.prepTime, { type: 'number', min: 0 })}${textInput(tr('Rest (min)'), 'restTime', recipe.restTime, { type: 'number', min: 0 })}${textInput(tr('Cooking (min)'), 'cookTime', recipe.cookTime, { type: 'number', min: 0 })}${textInput(tr('Difficulty'), 'difficulty', recipe.difficulty)}${textInput(tr('Calories'), 'calories', recipe.calories, { type: 'number', min: 0 })}${textInput(tr('Cost in cents'), 'costCents', recipe.costCents, { type: 'number', min: 0 })}${textInput(tr('Currency'), 'currency', recipe.currency)}</div></section>
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Structured list'))}</p><h2>${esc(tr('Ingredients'))}</h2></div><button class="secondary" data-add-ingredient type="button">+ ${esc(tr('Ingredient'))}</button></div><div data-ingredients>${(recipe.ingredients.length ? recipe.ingredients : [{ name: '' }]).map(item => ingredientRow(item)).join('')}</div></section>
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Method'))}</p><h2>${esc(tr('Procedure'))}</h2></div><button class="secondary" data-add-step type="button">+ ${esc(tr('Step'))}</button></div><div data-steps>${(recipe.steps.length ? recipe.steps : [{ text: '' }]).map((item, index) => stepRow(item, index)).join('')}</div></section>
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Classification'))}</p><h2>${esc(tr('Organization'))}</h2></div></div><div class="form-grid">${textInput(tr('Tags (comma separated)'), 'tags', recipe.tags.map(item => item.name).join(', '), { className: 'span-2' })}${textInput(tr('Categories'), 'categories', recipe.categories.map(item => item.name).join(', '), { className: 'span-2' })}${textInput(tr('Tools'), 'tools', recipe.tools.map(item => item.name).join(', '), { className: 'span-2' })}${textInput(tr('Cuisine'), 'cuisine', recipe.cuisine)}${textInput(tr('Course'), 'course', recipe.course)}${textInput(tr('Meal type'), 'mealType', recipe.mealType)}${textInput(tr('Cooking method'), 'cookingMethod', recipe.cookingMethod)}${textInput(tr('Season'), 'season', recipe.season)}${textInput(tr('Origin'), 'origin', recipe.origin)}${textareaInput(tr('Personal notes'), 'notes', recipe.notes, 5, 'span-2')}</div></section>
		</main>
		<aside class="view-stack editor-aside">
			${recipe.id ? `<section class="panel form-section"><p class="eyebrow">${esc(tr('Exports'))}</p><h2>${esc(tr('Download'))}</h2><div class="export-buttons"><a class="secondary" href="${attr(exportUrl(recipe.id, 'json'))}">JSON-LD</a><a class="secondary" href="${attr(exportUrl(recipe.id, 'markdown'))}">Markdown</a><a class="secondary" href="${attr(exportUrl(recipe.id, 'html'))}">HTML</a></div></section>
			<section class="panel form-section" data-media-section><p class="eyebrow">${esc(tr('Files'))}</p><h2>${esc(tr('Attachments'))}</h2><label>${esc(tr('Cover image'))}<input data-cover-file type="file" accept="image/*"><small>${esc(tr('The uploaded image becomes the recipe cover after saving.'))}</small></label><input data-media-file type="file"><button class="secondary full" data-upload-media type="button">${esc(tr('Upload attachment'))}</button><ul class="media-list">${recipe.media.map(item => item.id ? `<li><a href="${attr(mediaUrl(item.id))}" target="_blank" rel="noopener"><strong>${esc(item.altText || item.path.split('/').pop() || item.kind)}</strong><small>${esc(item.mime || item.kind)} · ${formatBytes(item.fileSize)} · ${formatMediaDate(item.createdAt)}</small></a></li>` : '').join('')}</ul></section>
			<section class="panel form-section" data-sharing-section><p class="eyebrow">${esc(tr('Access'))}</p><h2>${esc(tr('Sharing'))}</h2><div data-share-list></div><div class="share-form"><select data-share-type><option value="link">${esc(tr('Public link'))}</option><option value="user">${esc(tr('User'))}</option><option value="group">${esc(tr('Group'))}</option></select><input data-share-with placeholder="${attr(tr('User or group ID'))}"><input data-share-password type="password" placeholder="${attr(tr('Optional link password'))}"><label class="check-inline"><input data-share-edit type="checkbox"> ${esc(tr('Allow editing'))}</label><button class="secondary full" data-create-share type="button">${esc(tr('Create share'))}</button></div></section>
			<section class="panel form-section" data-history-section><p class="eyebrow">${esc(tr('Audit trail'))}</p><h2>${esc(tr('Version history'))}</h2><div data-version-list></div></section>
			<section class="panel form-section danger-zone"><h2>${esc(tr('Danger zone'))}</h2><button class="danger secondary full" data-delete-recipe type="button">${esc(tr('Delete recipe'))}</button></section>` : `<section class="panel empty-state"><h2>${esc(tr('Save first'))}</h2><p>${esc(tr('Attachments, sharing, exports and version history become available after the first save.'))}</p></section>`}
		</aside>
	</div>`
}

async function renderEditor(view: HTMLElement, id?: number): Promise<void> {
	let recipe = id ? (await working(() => request<{ recipe: Recipe }>(`/recipes/${id}`))).recipe : emptyRecipe()
	const paint = async (): Promise<void> => {
		view.innerHTML = editorForm(recipe)
		bindRowRemoval(view)
		view.querySelector<HTMLButtonElement>('[data-add-ingredient]')?.addEventListener('click', () => {
			const holder = view.querySelector<HTMLElement>('[data-ingredients]')!
			holder.insertAdjacentHTML('beforeend', ingredientRow())
			bindRowRemoval(holder)
		})
		view.querySelector<HTMLButtonElement>('[data-add-step]')?.addEventListener('click', () => {
			const holder = view.querySelector<HTMLElement>('[data-steps]')!
			holder.insertAdjacentHTML('beforeend', stepRow({ text: '' }, holder.querySelectorAll('[data-step-row]').length))
			bindRowRemoval(holder)
		})
		view.querySelector<HTMLButtonElement>('[data-save-recipe]')?.addEventListener('click', async () => {
			const payload = collectRecipe(view, recipe)
			const cover = view.querySelector<HTMLInputElement>('[data-cover-file]')?.files?.[0]
			const response = recipe.id
				? await working(() => request<{ recipe: Recipe }>(`/recipes/${recipe.id}`, { method: 'PUT', json: { recipe: payload } }))
				: await working(() => request<{ recipe: Recipe }>('/recipes', { method: 'POST', json: { recipe: payload } }))
			recipe = response.recipe
			if (cover && recipe.id) {
				const form = new FormData(); form.append('file', cover); form.append('kind', 'image'); form.append('altText', tr('Cover image'))
				await working(() => request(`/recipes/${recipe.id}/media`, { method: 'POST', body: form }))
				recipe = (await request<{ recipe: Recipe }>(`/recipes/${recipe.id}`)).recipe
			}
			showNotice(tr('Recipe saved'))
			if (!id && recipe.id) location.hash = `#/recipes/${recipe.id}`
			else await paint()
		})
		view.querySelector<HTMLButtonElement>('[data-mark-cooked]')?.addEventListener('click', async () => {
			if (!recipe.id) return
			await working(() => request(`/recipes/${recipe.id}/cooked`, { method: 'POST', json: {} }))
			showNotice(tr('Preparation recorded'))
		})
		view.querySelector<HTMLButtonElement>('[data-delete-recipe]')?.addEventListener('click', async () => {
			if (!recipe.id || !window.confirm(tr('Delete this recipe permanently?'))) return
			await working(() => request(`/recipes/${recipe.id}`, { method: 'DELETE' }))
			showNotice(tr('Recipe deleted'))
			location.hash = '#/recipes'
		})
		view.querySelector<HTMLButtonElement>('[data-upload-media]')?.addEventListener('click', async () => {
			if (!recipe.id) return
			const file = view.querySelector<HTMLInputElement>('[data-media-file]')?.files?.[0]
			if (!file) { showNotice(tr('Choose a file first'), 'error'); return }
			const form = new FormData(); form.append('file', file)
			await working(() => request(`/recipes/${recipe.id}/media`, { method: 'POST', body: form }))
			recipe = (await request<{ recipe: Recipe }>(`/recipes/${recipe.id}`)).recipe
			showNotice(tr('Attachment uploaded'))
			await paint()
		})
		if (recipe.id) {
			await Promise.all([loadShares(view, recipe.id), loadVersions(view, recipe.id)])
			const shareType = view.querySelector<HTMLSelectElement>('[data-share-type]')
			const shareWith = view.querySelector<HTMLInputElement>('[data-share-with]')
			const sharePassword = view.querySelector<HTMLInputElement>('[data-share-password]')
			const shareEdit = view.querySelector<HTMLInputElement>('[data-share-edit]')
			const updateShareFields = () => {
				if (shareWith) shareWith.disabled = shareType?.value === 'link'
				if (sharePassword) sharePassword.disabled = shareType?.value !== 'link'
			}
			shareType?.addEventListener('change', updateShareFields); updateShareFields()
			view.querySelector<HTMLButtonElement>('[data-create-share]')?.addEventListener('click', async () => {
				const type = shareType?.value || 'link'
				await working(() => request(`/recipes/${recipe.id}/shares`, { method: 'POST', json: { share: { type, shareWith: shareWith?.value || '', password: sharePassword?.value || '', permission: shareEdit?.checked ? 3 : 1 } } }))
				if (shareWith) shareWith.value = ''; if (sharePassword) sharePassword.value = ''
				showNotice(tr('Share created'))
				await loadShares(view, recipe.id!)
			})
		}
	}
	await paint()
}

async function loadShares(view: HTMLElement, recipeId: number): Promise<void> {
	const holder = view.querySelector<HTMLElement>('[data-share-list]')
	if (!holder) return
	const shares = (await request<{ shares: Array<Record<string, unknown>> }>(`/recipes/${recipeId}/shares`)).shares
	holder.innerHTML = shares.length ? shares.map(share => `<div class="share-item"><div><strong>${esc(share.type === 'link' ? tr('Public link') : share.shareWith)}</strong><small>${share.url ? `<a href="${attr(share.url)}" target="_blank" rel="noopener">${esc(tr('Open link'))}</a>` : esc(String(share.type || ''))}${share.passwordProtected ? ` - ${esc(tr('password protected'))}` : ''}</small></div><button class="icon-button danger" data-delete-share="${attr(share.id)}" type="button">x</button></div>`).join('') : `<p>${esc(tr('Not shared yet'))}</p>`
	holder.querySelectorAll<HTMLButtonElement>('[data-delete-share]').forEach(button => button.addEventListener('click', async () => {
		await working(() => request(`/recipes/${recipeId}/shares/${asNumber(button.dataset.deleteShare)}`, { method: 'DELETE' }))
		await loadShares(view, recipeId)
	}))
}

async function loadVersions(view: HTMLElement, recipeId: number): Promise<void> {
	const holder = view.querySelector<HTMLElement>('[data-version-list]')
	if (!holder) return
	const versions = (await request<{ versions: Array<{ revision: number; createdAt: number; userId: string }> }>(`/recipes/${recipeId}/versions`)).versions
	holder.innerHTML = versions.length ? versions.slice(0, 20).map(version => `<div class="version-item"><div><strong>v${version.revision}</strong><small>${esc(new Date(version.createdAt * 1000).toLocaleString())} - ${esc(version.userId)}</small></div><button class="ghost" data-restore-version="${version.revision}" type="button">${esc(tr('Restore'))}</button></div>`).join('') : `<p>${esc(tr('No versions yet'))}</p>`
	holder.querySelectorAll<HTMLButtonElement>('[data-restore-version]').forEach(button => button.addEventListener('click', async () => {
		if (!window.confirm(tr('Restore this version?'))) return
		await working(() => request(`/recipes/${recipeId}/restore/${asNumber(button.dataset.restoreVersion)}`, { method: 'POST', json: {} }))
		showNotice(tr('Version restored'))
		location.hash = '#/recipes'
		window.setTimeout(() => { location.hash = `#/recipes/${recipeId}` }, 0)
	}))
}

async function renderImport(view: HTMLElement): Promise<void> {
	let kind: 'url' | 'text' | 'markdown' | 'json' | 'file' = 'url'
	let preview: ImportPreview | null = null
	const paint = (): void => {
		view.innerHTML = `<section class="import-hero panel"><div><p class="eyebrow">${esc(tr('Smart import'))}</p><h2>${esc(tr('Turn almost any source into a structured recipe'))}</h2><p>${esc(tr('SmartCook checks Schema.org data first, then deterministic parsing, and uses AI only when requested.'))}</p></div><div class="pipeline"><span>URL / Text / PDF</span><b>-&gt;</b><span>${esc(tr('Parser'))}</span><b>-&gt;</b><span>${esc(tr('Review'))}</span><b>-&gt;</b><span>${esc(tr('Recipe'))}</span></div></section>
		<section class="two-column import-layout"><article class="panel form-section">
			<div class="source-tabs">${([['url', 'URL'], ['text', tr('Text')], ['markdown', 'Markdown'], ['json', 'JSON'], ['file', tr('File / OCR')]] as Array<[typeof kind, string]>).map(([id, label]) => `<button type="button" data-import-kind="${id}" class="${kind === id ? 'active' : ''}">${esc(label)}</button>`).join('')}</div>
			${kind === 'url' ? `<label>${esc(tr('Recipe URL'))}<input data-import-url type="url" placeholder="https://example.com/recipe"></label>` : kind === 'file' ? `<label>${esc(tr('PDF, image, Markdown, HTML or JSON'))}<input data-import-file type="file" accept="image/*,.pdf,.txt,.md,.markdown,.html,.htm,.json"><small>${esc(tr('Images and PDFs require a configured OCR/document extractor.'))}</small></label>` : `<label>${esc(tr('Source content'))}<textarea data-import-text rows="18" placeholder="${attr(tr('Paste the recipe, including ingredients and procedure...'))}"></textarea></label>`}
			<div class="form-grid"><label>${esc(tr('Output language'))}<input data-import-language value="${attr(document.documentElement.lang || 'it')}"></label><label>${esc(tr('AI provider override'))}<select data-import-provider><option value="">${esc(tr('Use settings'))}</option><option value="nextcloud">Nextcloud Assistant</option><option value="openai">OpenAI</option><option value="openrouter">OpenRouter</option><option value="ollama">Ollama</option><option value="localai">LocalAI</option><option value="mistral">Mistral</option><option value="anthropic">Anthropic</option><option value="gemini">Gemini</option></select></label></div>
			<label class="check-inline ai-toggle"><input data-import-ai type="checkbox"><span><b>${esc(tr('Use AI refinement'))}</b><small>${esc(tr('Optional fallback for incomplete or unstructured sources'))}</small></span></label>
			<button class="primary full" data-extract type="button">${esc(tr('Extract recipe data'))}</button>
		</article><article class="panel preview-panel" data-import-preview>${preview ? importPreviewHtml(preview) : `<div class="empty-preview"><div>&#8761;</div><h2>${esc(tr('Preview appears here'))}</h2><p>${esc(tr('The source is never saved as a recipe until you review and confirm the extracted fields.'))}</p></div>`}</article></section>`
		view.querySelectorAll<HTMLButtonElement>('[data-import-kind]').forEach(button => button.addEventListener('click', () => { kind = button.dataset.importKind as typeof kind; preview = null; paint() }))
		view.querySelector<HTMLButtonElement>('[data-extract]')?.addEventListener('click', async () => {
			const language = view.querySelector<HTMLInputElement>('[data-import-language]')?.value || 'en'
			const useAi = view.querySelector<HTMLInputElement>('[data-import-ai]')?.checked || false
			const provider = view.querySelector<HTMLSelectElement>('[data-import-provider]')?.value || ''
			if (kind === 'file') {
				const file = view.querySelector<HTMLInputElement>('[data-import-file]')?.files?.[0]
				if (!file) { showNotice(tr('Choose a file first'), 'error'); return }
				const form = new FormData(); form.append('file', file); form.append('language', language); form.append('useAi', String(useAi)); if (provider) form.append('provider', provider)
				preview = await working(() => request<ImportPreview>('/import/file', { method: 'POST', body: form }))
			} else {
				const text = view.querySelector<HTMLTextAreaElement>('[data-import-text]')?.value || ''
				const url = view.querySelector<HTMLInputElement>('[data-import-url]')?.value || ''
				if ((kind === 'url' && !url.trim()) || (kind !== 'url' && !text.trim())) { showNotice(tr('Enter a source first'), 'error'); return }
				preview = await working(() => request<ImportPreview>('/import/preview', { method: 'POST', json: { kind, payload: kind === 'url' ? { url, language } : { text, language }, useAi, provider: provider || null } }))
			}
			showNotice(tr('Recipe data extracted. Review it before saving.'))
			const holder = view.querySelector<HTMLElement>('[data-import-preview]')!
			holder.innerHTML = importPreviewHtml(preview)
			bindImportSave(holder)
		})
		if (preview) bindImportSave(view.querySelector<HTMLElement>('[data-import-preview]')!)
	}
	const bindImportSave = (holder: HTMLElement): void => {
		holder.querySelector<HTMLButtonElement>('[data-save-import]')?.addEventListener('click', async () => {
			if (!preview) return
			preview.recipe.title = holder.querySelector<HTMLInputElement>('[data-preview-title]')?.value.trim() || preview.recipe.title
			preview.recipe.description = holder.querySelector<HTMLTextAreaElement>('[data-preview-description]')?.value.trim() || preview.recipe.description
			const saved = (await working(() => request<{ recipe: Recipe }>('/recipes', { method: 'POST', json: { recipe: preview!.recipe } }))).recipe
			showNotice(tr('Imported recipe saved'))
			location.hash = `#/recipes/${saved.id}`
		})
	}
	paint()
}

function importPreviewHtml(preview: ImportPreview): string {
	const recipe = preview.recipe
	return `<div class="section-heading"><div><p class="eyebrow">${esc(preview.strategy)}</p><h2>${esc(tr('Import preview'))}</h2></div><button class="primary" data-save-import type="button">${esc(tr('Save recipe'))}</button></div>
		${preview.warnings.length ? `<div class="warning-list">${preview.warnings.map(warning => `<p>${esc(warning)}</p>`).join('')}</div>` : ''}
		<label>${esc(tr('Title'))}<input data-preview-title value="${attr(recipe.title)}"></label><label>${esc(tr('Description'))}<textarea data-preview-description rows="3">${esc(recipe.description)}</textarea></label>
		<div class="preview-metrics"><span>${recipe.servings} ${esc(tr('servings'))}</span><span>${recipe.prepTime} min ${esc(tr('prep'))}</span><span>${recipe.cookTime} min ${esc(tr('cook'))}</span><span>${recipe.totalTime} min ${esc(tr('total'))}</span></div>
		<div class="preview-columns"><div><h3>${esc(tr('Ingredients'))} <small>${recipe.ingredients.length}</small></h3><ul>${recipe.ingredients.map(item => `<li><b>${esc(item.quantity)} ${esc(item.unit)}</b> ${esc(item.name)}</li>`).join('')}</ul></div><div><h3>${esc(tr('Procedure'))} <small>${recipe.steps.length}</small></h3><ol>${recipe.steps.map(step => `<li>${esc(step.text)}</li>`).join('')}</ol></div></div>
		${preview.duplicates.length ? `<div class="duplicate-box"><h3>${esc(tr('Possible duplicates'))}</h3>${preview.duplicates.map(match => `<a href="#/recipes/${match.recipe.id}">${esc(match.recipe.title)} <span>${Math.round(match.score * 100)}%</span></a>`).join('')}</div>` : ''}`
}

function startOfWeek(date: Date): Date {
	const copy = new Date(date)
	const day = (copy.getDay() + 6) % 7
	copy.setDate(copy.getDate() - day)
	copy.setHours(0, 0, 0, 0)
	return copy
}

async function renderPlanner(view: HTMLElement): Promise<void> {
	let weekStart = startOfWeek(new Date())
	let recipes: Recipe[] = []
	let meals: Meal[] = []
	const load = async (): Promise<void> => {
		const days = Array.from({ length: 7 }, (_, index) => { const day = new Date(weekStart); day.setDate(day.getDate() + index); return day })
		const [recipeResponse, mealResponse] = await working(() => Promise.all([
			request<{ recipes: Recipe[] }>('/recipes?sort=title&direction=ASC'),
			request<{ meals: Meal[] }>(`/planner?from=${dateIso(days[0])}&to=${dateIso(days[6])}`),
		]))
		recipes = recipeResponse.recipes; meals = mealResponse.meals
		paint(days)
	}
	const paint = (days: Date[]): void => {
		view.innerHTML = `<section class="toolbar panel planner-toolbar"><button class="secondary" data-week-back type="button">&larr;</button><div><p class="eyebrow">${esc(tr('Week'))}</p><h2>${esc(days[0].toLocaleDateString())} - ${esc(days[6].toLocaleDateString())}</h2></div><button class="secondary" data-week-forward type="button">&rarr;</button><button class="ghost" data-week-today type="button">${esc(tr('Today'))}</button></section>
		<section class="planner-grid">${days.map(day => `<article class="day-column panel ${dateIso(day) === dateIso(new Date()) ? 'today' : ''}"><header><span>${esc(day.toLocaleDateString(undefined, { weekday: 'short' }))}</span><strong>${day.getDate()}</strong></header>${meals.filter(meal => meal.date === dateIso(day)).map(meal => `<div class="meal-card"><small>${esc(mealLabel(meal.slot))}</small><a href="#/recipes/${meal.recipeId}">${esc(meal.recipeTitle)}</a><span>${meal.servings} ${esc(tr('servings'))}</span><button data-delete-meal="${meal.id}" type="button">x</button></div>`).join('')}<button class="add-meal" data-select-date="${dateIso(day)}" type="button">+</button></article>`).join('')}</section>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Plan'))}</p><h2>${esc(tr('Add a meal'))}</h2></div></div><div class="form-grid four"><label>${esc(tr('Date'))}<input data-meal-date type="date" value="${dateIso(new Date())}"></label><label>${esc(tr('Recipe'))}<select data-meal-recipe>${recipes.map(recipe => `<option value="${recipe.id}">${esc(recipe.title)}</option>`).join('')}</select></label><label>${esc(tr('Meal'))}<select data-meal-slot><option value="breakfast">${esc(tr('Breakfast'))}</option><option value="lunch">${esc(tr('Lunch'))}</option><option value="dinner" selected>${esc(tr('Dinner'))}</option><option value="snack">${esc(tr('Snack'))}</option></select></label><label>${esc(tr('Servings'))}<input data-meal-servings type="number" min="1" value="2"></label></div><button class="primary" data-add-meal type="button">${esc(tr('Add to plan'))}</button></section>`
		view.querySelector<HTMLButtonElement>('[data-week-back]')?.addEventListener('click', () => { weekStart.setDate(weekStart.getDate() - 7); void load() })
		view.querySelector<HTMLButtonElement>('[data-week-forward]')?.addEventListener('click', () => { weekStart.setDate(weekStart.getDate() + 7); void load() })
		view.querySelector<HTMLButtonElement>('[data-week-today]')?.addEventListener('click', () => { weekStart = startOfWeek(new Date()); void load() })
		view.querySelectorAll<HTMLButtonElement>('[data-select-date]').forEach(button => button.addEventListener('click', () => { const input = view.querySelector<HTMLInputElement>('[data-meal-date]'); if (input) input.value = button.dataset.selectDate || '' }))
		view.querySelectorAll<HTMLButtonElement>('[data-delete-meal]').forEach(button => button.addEventListener('click', async () => { await working(() => request(`/planner/${asNumber(button.dataset.deleteMeal)}`, { method: 'DELETE' })); await load() }))
		view.querySelector<HTMLButtonElement>('[data-add-meal]')?.addEventListener('click', async () => {
			const date = view.querySelector<HTMLInputElement>('[data-meal-date]')?.value || ''
			const recipeId = asNumber(view.querySelector<HTMLSelectElement>('[data-meal-recipe]')?.value)
			const slot = view.querySelector<HTMLSelectElement>('[data-meal-slot]')?.value || 'dinner'
			const servings = asNumber(view.querySelector<HTMLInputElement>('[data-meal-servings]')?.value, 1)
			if (!date || !recipeId) { showNotice(tr('Choose a date and recipe'), 'error'); return }
			await working(() => request('/planner', { method: 'POST', json: { meal: { date, recipeId, slot, servings } } }))
			showNotice(tr('Meal added')); await load()
		})
	}
	await load()
}

async function renderShopping(view: HTMLElement): Promise<void> {
	let lists: ShoppingList[] = []
	let recipes: Recipe[] = []
	let selected: ShoppingList | null = null
	const load = async (): Promise<void> => {
		const [listResponse, recipeResponse] = await working(() => Promise.all([
			request<{ lists: ShoppingList[] }>('/shopping'), request<{ recipes: Recipe[] }>('/recipes?sort=title&direction=ASC'),
		]))
		lists = listResponse.lists; recipes = recipeResponse.recipes
		if (selected) selected = (await request<{ list: ShoppingList }>(`/shopping/${selected.id}`)).list
		else if (lists[0]) selected = (await request<{ list: ShoppingList }>(`/shopping/${lists[0].id}`)).list
		paint()
	}
	const open = async (id: number): Promise<void> => { selected = (await working(() => request<{ list: ShoppingList }>(`/shopping/${id}`))).list; paint() }
	const paint = (): void => {
		view.innerHTML = `<div class="shopping-layout"><aside class="panel list-sidebar"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Saved'))}</p><h2>${esc(tr('Shopping lists'))}</h2></div></div>${lists.map(list => `<button class="${selected?.id === list.id ? 'active' : ''}" data-open-list="${list.id}" type="button"><span><strong>${esc(list.name)}</strong><small>${esc(new Date(list.updatedAt * 1000).toLocaleDateString())}</small></span><b>&rsaquo;</b></button>`).join('') || `<p>${esc(tr('No lists yet'))}</p>`}</aside>
		<main class="view-stack"><section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Generate'))}</p><h2>${esc(tr('From recipes'))}</h2></div></div><label>${esc(tr('List name'))}<input data-list-name value="${attr(tr('Weekly shopping'))}"></label><div class="recipe-selector">${recipes.map(recipe => `<label><input data-list-recipe="${recipe.id}" type="checkbox"><span>${esc(recipe.title)}</span><input data-list-servings="${recipe.id}" type="number" min="1" value="${recipe.servings || 1}" aria-label="${attr(tr('Servings'))}"></label>`).join('')}</div><button class="primary" data-create-list type="button">${esc(tr('Generate shopping list'))}</button></section>
		${selected ? `<section class="panel shopping-sheet"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Active list'))}</p><h2>${esc(selected.name)}</h2></div><button class="danger ghost" data-delete-list type="button">${esc(tr('Delete'))}</button></div><div class="add-item"><input data-new-item placeholder="${attr(tr('Add an item...'))}"><button class="secondary" data-add-item type="button">+</button></div><div class="shopping-items">${(selected.items || []).map(item => `<label class="${item.checked ? 'checked' : ''}"><input data-toggle-item="${item.id}" type="checkbox"${item.checked ? ' checked' : ''}><span><strong>${esc(item.quantity)} ${esc(item.unit)}</strong> ${esc(item.name)}<small>${esc([item.category, item.notes].filter(Boolean).join(' - '))}</small></span></label>`).join('')}</div></section>` : `<section class="panel empty-state"><h2>${esc(tr('Select or create a list'))}</h2><p>${esc(tr('Quantities with compatible units are summed automatically.'))}</p></section>`}</main></div>`
		view.querySelectorAll<HTMLButtonElement>('[data-open-list]').forEach(button => button.addEventListener('click', () => { void open(asNumber(button.dataset.openList)) }))
		view.querySelector<HTMLButtonElement>('[data-create-list]')?.addEventListener('click', async () => {
			const name = view.querySelector<HTMLInputElement>('[data-list-name]')?.value.trim() || tr('Shopping list')
			const selections = [...view.querySelectorAll<HTMLInputElement>('[data-list-recipe]:checked')].map(box => ({ recipeId: asNumber(box.dataset.listRecipe), servings: asNumber(view.querySelector<HTMLInputElement>(`[data-list-servings="${box.dataset.listRecipe}"]`)?.value, 1) }))
			selected = (await working(() => request<{ list: ShoppingList }>('/shopping', { method: 'POST', json: { name, recipes: selections } }))).list
			showNotice(tr('Shopping list created')); await load()
		})
		view.querySelectorAll<HTMLInputElement>('[data-toggle-item]').forEach(box => box.addEventListener('change', async () => {
			if (!selected) return
			await working(() => request(`/shopping/${selected!.id}/items/${asNumber(box.dataset.toggleItem)}`, { method: 'PUT', json: { item: { checked: box.checked } } })); await open(selected.id)
		}))
		const addItem = async (): Promise<void> => {
			if (!selected) return
			const input = view.querySelector<HTMLInputElement>('[data-new-item]')
			if (!input?.value.trim()) return
			await working(() => request(`/shopping/${selected!.id}/items`, { method: 'POST', json: { item: { name: input.value.trim() } } })); await open(selected.id)
		}
		view.querySelector<HTMLButtonElement>('[data-add-item]')?.addEventListener('click', () => { void addItem() })
		view.querySelector<HTMLInputElement>('[data-new-item]')?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); void addItem() } })
		view.querySelector<HTMLButtonElement>('[data-delete-list]')?.addEventListener('click', async () => {
			if (!selected || !window.confirm(tr('Delete this shopping list?'))) return
			await working(() => request(`/shopping/${selected!.id}`, { method: 'DELETE' })); selected = null; await load()
		})
	}
	await load()
}

interface UserSettings {
	language: string
	measurementSystem: string
	attachmentsFolder: string
	aiProvider: string
	aiEndpoint: string
	aiModel: string
	aiTemperature: number
	aiTimeout: number
	aiApiKey?: string
	hasAiApiKey: boolean
	ocrProvider: string
	ocrEndpoint: string
	ocrLanguage: string
	tesseractPath: string
	pdfToTextPath: string
	ocrApiKey?: string
	hasOcrApiKey: boolean
	maxImportBytes: number
}

async function renderSettings(view: HTMLElement): Promise<void> {
	const settings = (await working(() => request<{ settings: UserSettings }>('/settings'))).settings
	view.innerHTML = `<div class="settings-layout"><main class="view-stack">
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('General'))}</p><h2>${esc(tr('Library preferences'))}</h2></div></div><div class="form-grid"><label>${esc(tr('Default language'))}<input data-setting="language" value="${attr(settings.language)}" placeholder="auto / it / en"></label><label>${esc(tr('Measurement system'))}<select data-setting="measurementSystem"><option value="metric"${settings.measurementSystem === 'metric' ? ' selected' : ''}>${esc(tr('Metric'))}</option><option value="imperial"${settings.measurementSystem === 'imperial' ? ' selected' : ''}>${esc(tr('Imperial'))}</option></select></label><label class="span-2">${esc(tr('Attachments folder in Nextcloud Files'))}<input data-setting="attachmentsFolder" value="${attr(settings.attachmentsFolder)}"></label><label>${esc(tr('Maximum URL import size'))}<input data-setting="maxImportBytes" type="number" min="100000" max="20000000" value="${settings.maxImportBytes}"><small>${esc(tr('bytes'))}</small></label></div></section>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Optional intelligence'))}</p><h2>${esc(tr('AI provider'))}</h2></div><span class="status-pill ${settings.aiProvider !== 'disabled' ? 'enabled' : ''}">${esc(settings.aiProvider === 'disabled' ? tr('Disabled') : tr('Enabled'))}</span></div><div class="form-grid">
			<label>${esc(tr('Provider'))}<select data-setting="aiProvider"><option value="disabled">${esc(tr('Disabled'))}</option><option value="nextcloud">Nextcloud Assistant</option><option value="openai">OpenAI</option><option value="openrouter">OpenRouter</option><option value="ollama">Ollama</option><option value="localai">LocalAI</option><option value="mistral">Mistral</option><option value="anthropic">Anthropic</option><option value="gemini">Gemini</option><option value="custom">${esc(tr('Custom OpenAI-compatible'))}</option></select></label>
			<label>${esc(tr('Model'))}<input data-setting="aiModel" value="${attr(settings.aiModel)}"></label><label class="span-2">${esc(tr('Endpoint'))}<input data-setting="aiEndpoint" type="url" value="${attr(settings.aiEndpoint)}" placeholder="https://..."><small>${esc(tr('Leave empty for the provider default. Ollama and LocalAI use local defaults.'))}</small></label>
			<label>${esc(tr('API key'))}<input data-setting="aiApiKey" type="password" autocomplete="new-password" placeholder="${attr(settings.hasAiApiKey ? tr('Key already stored; leave blank to keep it') : tr('API key'))}"></label><label class="check-inline secret-clear"><input data-setting-clear="aiApiKey" type="checkbox"> ${esc(tr('Remove stored key'))}</label><label>${esc(tr('Temperature'))}<input data-setting="aiTemperature" type="number" min="0" max="2" step="0.1" value="${settings.aiTemperature}"></label><label>${esc(tr('Timeout'))}<input data-setting="aiTimeout" type="number" min="10" max="300" value="${settings.aiTimeout}"><small>${esc(tr('seconds'))}</small></label>
		</div><div class="info-box"><b>Nextcloud Assistant</b><p>${esc(tr('Uses the language-model provider already configured by the instance administrator and requires no duplicate API key.'))}</p></div></section>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Documents'))}</p><h2>${esc(tr('OCR and PDF extraction'))}</h2></div><span class="status-pill ${settings.ocrProvider !== 'disabled' ? 'enabled' : ''}">${esc(settings.ocrProvider === 'disabled' ? tr('Disabled') : tr('Enabled'))}</span></div><div class="form-grid"><label>${esc(tr('Extractor'))}<select data-setting="ocrProvider"><option value="disabled">${esc(tr('Disabled'))}</option><option value="local">${esc(tr('Local Tesseract / pdftotext'))}</option><option value="external">${esc(tr('External HTTP service'))}</option></select></label><label>${esc(tr('OCR languages'))}<input data-setting="ocrLanguage" value="${attr(settings.ocrLanguage)}"></label><label>${esc(tr('Tesseract executable'))}<input data-setting="tesseractPath" value="${attr(settings.tesseractPath)}"></label><label>${esc(tr('pdftotext executable'))}<input data-setting="pdfToTextPath" value="${attr(settings.pdfToTextPath)}"></label><label class="span-2">${esc(tr('External endpoint'))}<input data-setting="ocrEndpoint" type="url" value="${attr(settings.ocrEndpoint)}"></label><label>${esc(tr('API key'))}<input data-setting="ocrApiKey" type="password" autocomplete="new-password" placeholder="${attr(settings.hasOcrApiKey ? tr('Key already stored; leave blank to keep it') : tr('API key'))}"></label><label class="check-inline secret-clear"><input data-setting-clear="ocrApiKey" type="checkbox"> ${esc(tr('Remove stored key'))}</label></div></section>
		<div class="save-bar"><button class="primary" data-save-settings type="button">${esc(tr('Save settings'))}</button></div></main>
		<aside class="panel privacy-card"><p class="eyebrow">${esc(tr('Privacy'))}</p><h2>${esc(tr('You control every processor'))}</h2><p>${esc(tr('Deterministic URL and text parsing stays in your Nextcloud. Content is sent to an AI or external OCR service only when you enable and invoke it.'))}</p><ul><li>${esc(tr('API keys are encrypted with the Nextcloud server secret.'))}</li><li>${esc(tr('Imported data is always shown as an editable preview.'))}</li><li>${esc(tr('URL imports reject private and reserved network addresses.'))}</li></ul></aside></div>`
	const aiProvider = view.querySelector<HTMLSelectElement>('[data-setting="aiProvider"]'); if (aiProvider) aiProvider.value = settings.aiProvider
	const ocrProvider = view.querySelector<HTMLSelectElement>('[data-setting="ocrProvider"]'); if (ocrProvider) ocrProvider.value = settings.ocrProvider
	view.querySelector<HTMLButtonElement>('[data-save-settings]')?.addEventListener('click', async () => {
		const payload: Record<string, unknown> = {}
		view.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[data-setting]').forEach(field => {
			const key = field.dataset.setting || ''
			if (!key) return
			payload[key] = field instanceof HTMLInputElement && field.type === 'number' ? asNumber(field.value) : field.value
		})
		payload.clearAiApiKey = view.querySelector<HTMLInputElement>('[data-setting-clear="aiApiKey"]')?.checked || false
		payload.clearOcrApiKey = view.querySelector<HTMLInputElement>('[data-setting-clear="ocrApiKey"]')?.checked || false
		await working(() => request('/settings', { method: 'PUT', json: { settings: payload } }))
		showNotice(tr('Settings saved'))
		await renderSettings(view)
	})
}

async function renderPublic(rootNode: HTMLElement): Promise<void> {
	const token = rootNode.dataset.token || ''
	const load = async (password = ''): Promise<void> => {
		rootNode.innerHTML = `<main class="public-page"><div class="public-brand"><img src="${attr(appUrl('/img/app.svg'))}" alt=""><strong>SmartCook</strong></div><p>${esc(tr('Loading...'))}</p></main>`
		try {
			const payload = await request<{ recipe: Recipe }>(`/public/${encodeURIComponent(token)}/data`, { method: 'POST', json: { password } })
			const recipe = payload.recipe
			const image = recipeImageUrl(recipe.imagePath)
			rootNode.innerHTML = `<main class="public-page"><div class="public-brand"><img src="${attr(appUrl('/img/app.svg'))}" alt=""><strong>SmartCook</strong></div><article class="public-recipe">${image ? `<img class="hero" src="${attr(image)}" alt="">` : ''}<p class="eyebrow">${esc(recipe.cuisine)}${recipe.course ? ` - ${esc(recipe.course)}` : ''}</p><h1>${esc(recipe.title)}</h1><p class="lead">${esc(recipe.description)}</p><div class="metrics"><div><strong>${recipe.servings}</strong><span>${esc(tr('Servings'))}</span></div><div><strong>${recipe.prepTime} min</strong><span>${esc(tr('Preparation'))}</span></div><div><strong>${recipe.cookTime} min</strong><span>${esc(tr('Cooking'))}</span></div><div><strong>${recipe.totalTime} min</strong><span>${esc(tr('Total'))}</span></div></div><div class="public-grid"><section><h2>${esc(tr('Ingredients'))}</h2><ul>${recipe.ingredients.map(item => `<li><b>${esc(item.quantity)} ${esc(item.unit)}</b> ${esc(item.name)} <small>${esc(item.notes)}</small></li>`).join('')}</ul></section><section><h2>${esc(tr('Method'))}</h2><ol>${recipe.steps.map(step => `<li>${esc(step.text)}</li>`).join('')}</ol></section></div></article></main>`
		} catch (error) {
			const message = error instanceof Error ? error.message : tr('Could not load the shared recipe')
			rootNode.innerHTML = `<main class="public-page"><div class="public-brand"><img src="${attr(appUrl('/img/app.svg'))}" alt=""><strong>SmartCook</strong></div><form class="password-card"><h1>${esc(tr('Shared recipe'))}</h1><p>${esc(message)}</p><label>${esc(tr('Password'))}<input data-public-password type="password" autocomplete="current-password"></label><button class="primary" type="submit">${esc(tr('Open recipe'))}</button></form></main>`
			rootNode.querySelector('form')?.addEventListener('submit', event => { event.preventDefault(); void load(rootNode.querySelector<HTMLInputElement>('[data-public-password]')?.value || '') })
		}
	}
	await load()
}

async function route(): Promise<void> {
	if (!root) return
	const current = parseRoute()
	const view = renderShell(current.section, current.id)
	try {
		switch (current.section) {
			case 'dashboard': await renderDashboard(view); break
			case 'recipes': await renderRecipes(view); break
			case 'editor': await renderEditor(view, current.id); break
			case 'import': await renderImport(view); break
			case 'planner': await renderPlanner(view); break
			case 'shopping': await renderShopping(view); break
			case 'settings': await renderSettings(view); break
			default: await renderDashboard(view)
		}
	} catch (error) {
		if (!(error instanceof ApiError)) console.error(error)
	}
}

if (publicRoot) {
	void renderPublic(publicRoot)
} else if (root) {
	window.addEventListener('hashchange', () => { void route() })
	void route()
}
