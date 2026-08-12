"use strict";
/*
 * SmartCook compatibility frontend.
 * Dependency-free TypeScript bundle included in release archives so the app can
 * be installed without Node.js. The Vue/Vite source in src/ remains available
 * for teams that prefer the standard Nextcloud frontend stack.
 */
const smartWindow = window;
const appId = 'smartcook';
const root = document.getElementById('smartcook');
const publicRoot = document.getElementById('smartcook-public');
let busyCount = 0;
let messageTimer = 0;
const fallbackTranslations = {
    it: {
        // Keep bundle-only UI text translated when the Nextcloud catalog has not been regenerated yet.
        minutes: 'minuti',
        hours: 'ore',
        'Remove this ingredient?': 'Rimuovere questo ingrediente?',
        'Remove this step?': 'Rimuovere questo passaggio?',
        'Delete this share?': 'Eliminare questa condivisione?',
        'Delete this meal?': 'Eliminare questo pasto?',
        'Empty week': 'Svuota settimana',
        'Clear all meals from this week?': 'Rimuovere tutti i pasti assegnati a questa settimana?',
        'Search recipes...': 'Cerca ricette...',
        'Search categories...': 'Cerca categorie...',
        'Search tags...': 'Cerca tag...',
        'Top categories': 'Categorie più usate',
        'No categories yet': 'Nessuna categoria',
        'Selected items': 'Elementi selezionati',
        'Additional attachment': 'Allegato aggiuntivo',
        'Generate with AI': 'Genera con AI',
        'AI meal planner': 'Pianificatore pasti AI',
        'Weekly instruction (optional)': 'Istruzione per questa settimana (opzionale)',
        'Meal plan generated': 'Piano pasti generato',
        'Dietary preferences and constraints': 'Preferenze alimentari e vincoli',
        'Maximum cooking time per meal': 'Tempo massimo di cucina per pasto',
        'Default servings': 'Porzioni predefinite',
        'Planner prompt': 'Prompt del pianificatore',
        'Example: use more legumes and prepare leftovers for lunch': 'Esempio: usa più legumi e prepara gli avanzi per il pranzo',
        'Example: vegetarian, no peanuts, low salt': 'Esempio: vegetariana, senza arachidi, poco sale',
        'Import previews': 'Anteprime di importazione',
        'Received imports': 'Importazioni ricevute',
        'Imports sent from SmartCook Connector appear here.': 'Qui compaiono le importazioni inviate da SmartCook Connector.',
        'Refresh': 'Aggiorna',
        'Open preview': 'Apri anteprima',
        'Waiting for processing': 'In attesa di elaborazione',
        'Processing import': 'Importazione in elaborazione',
        'Import failed': 'Importazione non riuscita',
        'No received imports yet': 'Nessuna importazione ricevuta',
        'Loading...': 'Caricamento...',
        'Select import': 'Seleziona importazione',
        'Delete import': 'Elimina',
        'Delete this received import?': 'Eliminare questa importazione ricevuta?',
        'Imported recipes saved': 'Ricette importate salvate',
        'Importing file': 'Importazione file',
        'Importing recipe': 'Importazione ricetta',
        of: 'di',
        'Please wait while the source is analyzed.': 'Attendi mentre la fonte viene analizzata.',
        'recipes extracted. Review them before saving.': 'ricette estratte. Controllale prima di salvarle.',
        'Save all recipes': 'Salva tutte le ricette',
        'Saving recipe': 'Salvataggio ricetta',
		'Servings per recipe': 'Porzioni per ricetta',
		'Add': 'Aggiungi',
		'Cost': 'Costo',
		'Cost and currency': 'Costo e valuta',
		'Planning details help': 'Indica porzioni e resa per ricalcolare la ricetta; i tempi sono espressi in minuti e vengono sommati automaticamente.',
		'Exclude from meal planner': 'Escludi dal pianificatore pasti',
		'Choose existing values or type a new one. Press Enter or comma to add it.': 'Scegli un valore esistente oppure scrivine uno nuovo. Premi Invio o virgola per aggiungerlo.',
		'Select an option': 'Seleziona un’opzione',
		'Spring': 'Primavera',
		'Summer': 'Estate',
		'Autumn': 'Autunno',
		'Winter': 'Inverno',
        'Select one or more files. Images and PDFs require a configured OCR/document extractor.': 'Seleziona uno o più file. Immagini e PDF richiedono un estrattore OCR/documenti configurato.',
        'Some files could not be imported': 'Non è stato possibile importare alcuni file',
        'Some recipes could not be saved': 'Non è stato possibile salvare alcune ricette',
        'Timer quantity': 'Quantità timer',
        'Timer unit': 'Unità timer',
        'View recipes': 'Visualizza ricette',
        'Select one or more categories': 'Seleziona una o più categorie',
        'Select one or more tags': 'Seleziona uno o più tag',
        'Find cover image': 'Trova immagine di copertina',
        'Cover image found': 'Immagine di copertina trovata',
        'Remove attachment?': 'Rimuovere l’allegato?',
        'Attachment removed': 'Allegato rimosso',
        'Google image search': 'Ricerca immagini Google',
        'Image search provider': 'Provider ricerca immagini',
        'Pexels API key': 'Chiave API Pexels',
        'Unsplash access key': 'Chiave di accesso Unsplash',
        'Remove stored Pexels key': 'Rimuovi chiave Pexels salvata',
        'Remove stored Unsplash key': 'Rimuovi chiave Unsplash salvata',
        'Find covers for all missing recipes': 'Trova copertine per tutte le ricette senza immagine',
        'This will search for and download covers for all your recipes without an image. Continue?': 'Saranno cercate e scaricate le copertine per tutte le tue ricette senza immagine. Continuare?',
        'Cover search completed': 'Ricerca copertine completata',
        failed: 'non riuscite',
        'Choose a cover image': 'Scegli un’immagine di copertina',
        'Use this image': 'Usa questa immagine',
        'Programmable Search engine ID': 'ID motore Programmable Search',
        'Google API key': 'Chiave API Google',
        'Key already stored; leave blank to keep it': 'Chiave già salvata; lascia vuoto per conservarla',
        'Remove stored Google key': 'Rimuovi chiave Google salvata',
        'Searches Google Images using the recipe title and saves the first suitable result as the cover.': 'Cerca in Google Immagini usando il titolo della ricetta e salva il primo risultato idoneo come copertina.',
        'Uses the selected provider with the recipe title and saves the first suitable result as the cover.': 'Usa il provider selezionato con il titolo della ricetta e salva il primo risultato idoneo come copertina.',
    },
};
const tr = (text) => {
    const language = String(document.documentElement.lang || '').toLowerCase().split('-')[0];
    return fallbackTranslations[language]?.[text] || (smartWindow.t ? smartWindow.t(appId, text) : text);
};
const displayUnit = (unit) => {
    const language = String(document.documentElement.lang || '').toLowerCase().split('-')[0];
    if (language !== 'it')
        return String(unit ?? '');
    return ({
        tsp: 'cucchiaino', tbsp: 'cucchiaio', cup: 'tazza', pc: 'pz', clove: 'spicchio',
        pinch: 'pizzico', to_taste: 'q.b.',
    })[String(unit ?? '').toLowerCase()] || String(unit ?? '');
};
const mealLabel = (slot) => tr({ breakfast: 'Breakfast', lunch: 'Lunch', dinner: 'Dinner', snack: 'Snack' }[slot] || 'Meal');
const esc = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
const attr = esc;
const safeExternalUrl = (value) => {
    const text = String(value ?? '');
    return /^https?:\/\//i.test(text) ? text : '';
};
const asNumber = (value, fallback = 0) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};
const asText = (value) => String(value ?? '').trim();
const dateIso = (value) => {
    const local = new Date(value.getTime() - value.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
};
const appUrl = (path) => smartWindow.OC?.generateUrl
    ? smartWindow.OC.generateUrl(`/apps/${appId}${path}`)
    : `/index.php/apps/${appId}${path}`;
const appVersion = '1.0.40';
const appIconUrl = `/custom_apps/smartcook/img/app-${appVersion}.svg?v=${appVersion}`;
const faviconUrl = `/custom_apps/smartcook/img/favicon-${appVersion}.ico?v=${appVersion}`;

function installFavicon() {
	let icon = document.head.querySelector('link[data-smartcook-favicon]');
	if (!icon) {
		icon = document.createElement('link');
		icon.rel = 'icon';
		icon.dataset.smartcookFavicon = 'true';
		document.head.append(icon);
	}
	icon.type = 'image/x-icon';
	icon.href = faviconUrl;
}

installFavicon();
const mediaUrl = (id) => appUrl(`/media/${id}`);
const formatBytes = (value) => {
    if (!value || value < 0)
        return '—';
    if (value < 1024)
        return `${value} B`;
    if (value < 1024 * 1024)
        return `${(value / 1024).toFixed(1)} KB`;
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
};
const formatMediaDate = (value) => value ? new Date(value * 1000).toLocaleString() : '—';
const recipeImageUrl = (value) => {
    const text = String(value ?? '');
    const stored = /^media:(\d+)$/.exec(text);
    return stored ? mediaUrl(Number(stored[1])) : safeExternalUrl(text);
};
const exportUrl = (id, format) => appUrl(`/recipes/${id}/export/${format}`);
class ApiError extends Error {
    constructor(message, status, fields = {}) {
        super(message);
        this.status = status;
        this.fields = fields;
    }
}
async function request(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (options.json !== undefined) {
        headers.set('Content-Type', 'application/json');
        options.body = JSON.stringify(options.json);
    }
    if (smartWindow.OC?.requestToken) {
        headers.set('requesttoken', smartWindow.OC.requestToken);
    }
    const response = await fetch(appUrl(path), { ...options, headers, credentials: 'same-origin' });
    const contentType = response.headers.get('content-type') || '';
    const data = contentType.includes('json') ? await response.json() : await response.text();
    if (!response.ok) {
        const body = typeof data === 'object' && data !== null ? data : {};
        throw new ApiError(body.error || `${tr('Request failed')} (${response.status})`, response.status, body.errors || {});
    }
    return data;
}
function setBusy(active) {
    busyCount = Math.max(0, busyCount + (active ? 1 : -1));
    const node = document.querySelector('[data-smartcook-busy]');
    if (node) {
        node.hidden = busyCount === 0;
        node.textContent = tr('Working...');
    }
}
async function working(operation) {
    setBusy(true);
    clearNotice('error');
    try {
        return await operation();
    }
    catch (error) {
        showNotice(error instanceof Error ? error.message : tr('Unexpected error'), 'error');
        throw error;
    }
    finally {
        setBusy(false);
    }
}
function showNotice(message, type = 'success') {
    const holder = document.querySelector('[data-smartcook-notices]');
    if (!holder)
        return;
    holder.innerHTML = `<div class="notice ${type}" role="${type === 'error' ? 'alert' : 'status'}"><span>${esc(message)}</span><button type="button" aria-label="${attr(tr('Close'))}">x</button></div>`;
    holder.querySelector('button')?.addEventListener('click', () => { holder.innerHTML = ''; });
    window.clearTimeout(messageTimer);
    if (type === 'success')
        messageTimer = window.setTimeout(() => { holder.innerHTML = ''; }, 4000);
}
function clearNotice(type) {
    const notice = document.querySelector('[data-smartcook-notices] .notice');
    if (!notice || (type && !notice.classList.contains(type)))
        return;
    notice.remove();
}
function emptyRecipe() {
    return {
        title: '', subtitle: null, description: null, language: document.documentElement.lang || 'en', author: null,
        sourceName: null, sourceUrl: null, license: null, status: 'draft', visibility: 'private', favorite: false, excludeFromPlanner: false,
        servings: 4, yieldText: null, prepTime: 0, restTime: 0, cookTime: 0, totalTime: 0, difficulty: null,
        costCents: null, currency: 'EUR', cuisine: null, course: null, mealType: null, cookingMethod: null,
        season: null, origin: null, calories: null, nutrition: {}, notes: null, imagePath: null,
        ingredients: [{ name: '', quantity: null, unit: null }], steps: [{ text: '' }], tools: [], tags: [], categories: [], media: [],
    };
}
function splitNames(value) {
    return [...new Set(value.split(/[,;]+/).map(item => item.trim()).filter(Boolean))].map(name => ({ name }));
}
function shellTitle(section, id) {
    const titles = {
        dashboard: tr('Dashboard'), recipes: tr('Recipes'), recipe: tr('Recipe overview'), editor: id ? tr('Edit recipe') : tr('New recipe'),
        import: tr('Import'), planner: tr('Meal planner'), shopping: tr('Shopping lists'), settings: tr('Settings'),
    };
    return titles[section] || 'SmartCook';
}
function parseRoute() {
    const [path, query = ''] = (location.hash.replace(/^#\/?/, '') || 'dashboard').split('?');
    const parts = path.split('/');
    const params = new URLSearchParams(query);
    if (parts[0] === 'recipes' && parts[1]) {
        const id = asNumber(parts[1]) || undefined;
        return { section: parts[2] === 'edit' ? 'editor' : 'recipe', id, params };
    }
    if (parts[0] === 'new')
        return { section: 'editor', params };
    return { section: parts[0] || 'dashboard', params };
}
function renderShell(section, id) {
    if (!root)
        throw new Error('SmartCook root was not found');
    const nav = [
        ['dashboard', tr('Dashboard')], ['recipes', tr('Recipes')], ['import', tr('Import')],
        ['planner', tr('Meal planner')], ['shopping', tr('Shopping lists')], ['settings', tr('Settings')],
    ];
    root.innerHTML = `<div class="smartcook-shell">
		<aside class="smartcook-sidebar" aria-label="SmartCook">
			<div class="brand"><img src="${attr(appIconUrl)}" alt=""><div><strong>SmartCook</strong><span>${esc(tr('Recipe intelligence'))}</span></div></div>
			<nav>${nav.map(([route, label]) => `<a class="${section === route || (route === 'recipes' && ['recipe', 'editor'].includes(section)) ? 'active' : ''}" href="#/${route}">${esc(label)}</a>`).join('')}</nav>
			<a class="primary mobile-create" href="#/new" aria-label="${attr(tr('New recipe'))}"><span class="mobile-create-icon" aria-hidden="true">+</span><span class="mobile-create-label">${esc(tr('New recipe'))}</span></a>
		</aside>
		<main class="smartcook-content">
			<header class="page-header"><div><h1>${esc(shellTitle(section, id))}</h1></div><div class="busy" data-smartcook-busy hidden>${esc(tr('Working...'))}</div></header>
			<div data-smartcook-notices></div>
			<div data-smartcook-view class="view-stack"></div>
		</main>
	</div>`;
    return root.querySelector('[data-smartcook-view]');
}
function recipeThumb(recipe) {
    const image = recipeImageUrl(recipe.imagePath);
    return image
        ? `<img src="${attr(image)}" alt="">`
        : `<span>${esc((recipe.title || '?').slice(0, 1).toUpperCase())}</span>`;
}
async function renderDashboard(view) {
    view.innerHTML = `<div class="skeleton-grid"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div>`;
    const stats = await working(() => request('/stats'));
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
			<article class="panel dashboard-cloud"><p class="eyebrow">${esc(tr('Organization'))}</p><h2>${esc(tr('Top categories'))}</h2><div class="tag-cloud">${(stats.topCategories || []).map(item => `<a href="#/recipes?categories=${encodeURIComponent(item.name)}">${esc(item.name)} <b>${item.count}</b></a>`).join('') || `<small>${esc(tr('No categories yet'))}</small>`}</div></article>
			<article class="panel dashboard-cloud"><p class="eyebrow">${esc(tr('Most used'))}</p><h2>${esc(tr('Ingredients'))}</h2><div class="tag-cloud">${stats.topIngredients.map(item => `<a href="#/recipes?ingredients=${encodeURIComponent(item.name)}">${esc(item.name)} <b>${item.count}</b></a>`).join('') || `<small>${esc(tr('No data yet'))}</small>`}</div></article>
			<article class="panel dashboard-cloud"><p class="eyebrow">${esc(tr('Organization'))}</p><h2>${esc(tr('Top tags'))}</h2><div class="tag-cloud">${stats.topTags.map(item => `<a href="#/recipes?tags=${encodeURIComponent(item.name)}">#${esc(item.name)} <b>${item.count}</b></a>`).join('') || `<small>${esc(tr('No tags yet'))}</small>`}</div></article>
		</div>
	</section>`;
}
function taxonomyPicker(kind, items, selected) {
	const searchLabel = kind === 'categories' ? tr('Search categories...') : tr('Search tags...');
	return `<div class="taxonomy-picker" data-taxonomy-picker="${attr(kind)}"><button class="taxonomy-picker-trigger" data-taxonomy-trigger type="button" aria-expanded="false"><span data-taxonomy-count>${esc(tr('Selected items'))}: ${selected.length}</span><span aria-hidden="true">&#9662;</span></button><div class="taxonomy-picker-menu" data-taxonomy-menu hidden><input data-taxonomy-search type="search" autocomplete="off" placeholder="${attr(searchLabel)}"><div class="taxonomy-picker-options">${items.map(item => { const name = String(item.name || ''); return `<label data-taxonomy-option><input type="checkbox" value="${attr(name)}"${selected.includes(name) ? ' checked' : ''}><span>${esc(name)}</span></label>`; }).join('')}<p data-taxonomy-empty hidden>${esc(tr('No data yet'))}</p></div></div></div>`;
}
function bindTaxonomyPicker(view, kind, selected, onChange) {
	const picker = view.querySelector(`[data-taxonomy-picker="${kind}"]`);
	if (!picker)
		return;
	const trigger = picker.querySelector('[data-taxonomy-trigger]');
	const menu = picker.querySelector('[data-taxonomy-menu]');
	const search = picker.querySelector('[data-taxonomy-search]');
	const count = picker.querySelector('[data-taxonomy-count]');
	const options = [...picker.querySelectorAll('[data-taxonomy-option]')];
	const empty = picker.querySelector('[data-taxonomy-empty]');
	const update = () => {
		selected.splice(0, selected.length, ...options.filter(option => option.querySelector('input')?.checked).map(option => option.querySelector('input')?.value || ''));
		if (count)
			count.textContent = `${tr('Selected items')}: ${selected.length}`;
		onChange();
	};
	const filter = () => {
		const query = search?.value.trim().toLocaleLowerCase() || '';
		let visible = 0;
		options.forEach(option => {
			const matches = !query || option.textContent?.toLocaleLowerCase().includes(query);
			option.hidden = !matches;
			visible += matches ? 1 : 0;
		});
		if (empty)
			empty.hidden = visible > 0;
	};
	const close = () => { menu.hidden = true; trigger?.setAttribute('aria-expanded', 'false'); };
	trigger?.addEventListener('click', () => {
		menu.hidden = !menu.hidden;
		trigger.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
		if (!menu.hidden)
			search?.focus();
	});
	search?.addEventListener('input', filter);
	search?.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
	options.forEach(option => option.querySelector('input')?.addEventListener('change', update));
	view.addEventListener('click', event => { if (!picker.contains(event.target)) close(); });
}
async function renderRecipes(view, routeParams = new URLSearchParams()) {
    const taxonomy = await working(() => request('/taxonomy'));
    const selectedTags = [...new Set(routeParams.getAll('tags'))];
    const selectedCategories = [...new Set(routeParams.getAll('categories'))];
    view.innerHTML = `<section class="toolbar panel">
		<label class="search-field"><span>&#9906;</span><input data-search placeholder="${attr(tr('Search recipes, cuisine or course...'))}"></label>
		<div class="taxonomy-filter"><span>${esc(tr('Categories'))}</span>${taxonomyPicker('categories', taxonomy.categories || [], selectedCategories)}</div>
		<div class="taxonomy-filter"><span>${esc(tr('Tags'))}</span>${taxonomyPicker('tags', taxonomy.tags || [], selectedTags)}</div>
		<label class="check-inline"><input data-favorites type="checkbox"> ${esc(tr('Favorites only'))}</label>
		<label>${esc(tr('Sort'))}<select data-sort><option value="updated_at">${esc(tr('Recently updated'))}</option><option value="title:ASC">${esc(tr('Title'))} A-Z</option><option value="title:DESC">${esc(tr('Title'))} Z-A</option><option value="total_time">${esc(tr('Total time'))}</option><option value="cook_count">${esc(tr('Most cooked'))}</option></select></label>
		<a class="primary" href="#/new">+ ${esc(tr('New recipe'))}</a>
	</section><section data-recipe-results></section>`;
    const results = view.querySelector('[data-recipe-results]');
    const search = view.querySelector('[data-search]');
    const favorites = view.querySelector('[data-favorites]');
    const sort = view.querySelector('[data-sort]');
    const ingredients = routeParams.getAll('ingredients');
    const filterLabel = [...selectedCategories, ...selectedTags.map(name => `#${name}`), ...ingredients].join(', ');
    if (filterLabel) {
        search.setAttribute('aria-label', `${tr('Search recipes, cuisine or course...')}: ${filterLabel}`);
        search.title = filterLabel;
    }
    const recipeSortKey = 'smartcook.recipe-sort';
    try {
        const savedSort = window.localStorage.getItem(recipeSortKey);
        if (savedSort && [...sort.options].some(option => option.value === savedSort))
            sort.value = savedSort;
    }
    catch (_) {
        // Continue with the default ordering when browser storage is unavailable.
    }
    let timer = 0;
    const load = async () => {
        const [sortField, direction] = sort.value.split(':');
        const params = new URLSearchParams({ search: search.value, favorite: favorites.checked ? '1' : '', sort: sortField, ...(direction ? { direction } : {}) });
        selectedTags.forEach(tag => params.append('tags', tag));
        selectedCategories.forEach(category => params.append('categories', category));
        ingredients.forEach(ingredient => params.append('ingredients', ingredient));
        const payload = await working(() => request(`/recipes?${params.toString()}`));
        const recipes = payload.recipes;
        results.innerHTML = recipes.length ? `<div class="recipe-grid">${recipes.map(recipe => {
            const image = recipeImageUrl(recipe.imagePath);
            return `<article class="recipe-card"><a class="recipe-image" href="#/recipes/${recipe.id}">${image ? `<img src="${attr(image)}" alt="">` : `<div class="image-placeholder"><span>${esc(recipe.title.slice(0, 1).toUpperCase())}</span></div>`}<span class="time-pill">${asNumber(recipe.totalTime || recipe.prepTime + recipe.cookTime)} min</span></a>
			<div class="recipe-card-body"><div class="card-title"><div><p>${esc(recipe.cuisine || recipe.course || tr('Recipe'))}</p><a href="#/recipes/${recipe.id}"><h2>${esc(recipe.title)}</h2></a></div><button class="icon-button" data-favorite-id="${recipe.id}" data-favorite="${recipe.favorite ? '1' : '0'}" aria-label="${attr(tr('Toggle favorite'))}">${recipe.favorite ? '&#9733;' : '&#9734;'}</button></div><p>${esc(recipe.description || tr('No description'))}</p><div class="card-meta"><span>${asNumber(recipe.prepTime)} + ${asNumber(recipe.cookTime)} min</span><span>${asNumber(recipe.servings)}</span>${recipe.difficulty ? `<span>${esc(recipe.difficulty)}</span>` : ''}</div></div></article>`;
        }).join('')}</div>` : `<section class="panel empty-state"><h2>${esc(tr('No recipes found'))}</h2><p>${esc(tr('Change the filters, create a recipe, or import one from a URL.'))}</p><div><a class="primary" href="#/import">${esc(tr('Import recipe'))}</a> <a class="secondary" href="#/new">${esc(tr('Create manually'))}</a></div></section>`;
        results.querySelectorAll('[data-favorite-id]').forEach(button => button.addEventListener('click', async () => {
            const id = asNumber(button.dataset.favoriteId);
            const newValue = button.dataset.favorite !== '1';
            await working(() => request(`/recipes/${id}/favorite`, { method: 'POST', json: { favorite: newValue } }));
            button.dataset.favorite = newValue ? '1' : '0';
            button.innerHTML = newValue ? '&#9733;' : '&#9734;';
            showNotice(newValue ? tr('Added to favorites') : tr('Removed from favorites'));
        }));
    };
    const delayed = () => { window.clearTimeout(timer); timer = window.setTimeout(() => { void load(); }, 250); };
    search.addEventListener('input', delayed);
	bindTaxonomyPicker(view, 'categories', selectedCategories, () => { void load(); });
	bindTaxonomyPicker(view, 'tags', selectedTags, () => { void load(); });
    favorites.addEventListener('change', () => { void load(); });
    sort.addEventListener('change', () => {
        try {
            window.localStorage.setItem(recipeSortKey, sort.value);
        }
        catch (_) {
            // The selected order still applies for the current page.
        }
        void load();
    });
    await load();
}
function textInput(label, field, value, options = {}) {
    return `<label class="${attr(options.className || '')}">${esc(label)}<input data-field="${attr(field)}" type="${attr(options.type || 'text')}" value="${attr(value)}"${options.placeholder ? ` placeholder="${attr(options.placeholder)}"` : ''}${options.min !== undefined ? ` min="${options.min}"` : ''}${options.step !== undefined ? ` step="${options.step}"` : ''}></label>`;
}
function labelInput(label, control, className = '') {
    return `<label class="${attr(className)}">${esc(label)}${control}</label>`;
}
function textareaInput(label, field, value, rows = 4, className = '') {
    return `<label class="${attr(className)}">${esc(label)}<textarea data-field="${attr(field)}" rows="${rows}">${esc(value)}</textarea></label>`;
}
function selectInput(label, field, value, options, className = '') {
    return `<label class="${attr(className)}">${esc(label)}<select data-field="${attr(field)}">${options.map(([id, name]) => `<option value="${attr(id)}"${String(value ?? '') === id ? ' selected' : ''}>${esc(name)}</option>`).join('')}</select></label>`;
}
function predefinedOptions(currentValue, options) {
    const current = String(currentValue || '').trim();
    return current && !options.some(([value]) => value === current) ? [[current, current], ...options] : options;
}
function ingredientRow(item = { name: '' }) {
    return `<div class="ingredient-row" data-ingredient-row>
		<input data-ing-quantity value="${attr(item.quantity)}" placeholder="${attr(tr('Qty'))}" aria-label="${attr(tr('Quantity'))}">
		<input data-ing-unit value="${attr(displayUnit(item.unit))}" placeholder="${attr(tr('Unit'))}" aria-label="${attr(tr('Unit'))}">
		<input data-ing-name value="${attr(item.name)}" placeholder="${attr(tr('Ingredient'))}" aria-label="${attr(tr('Ingredient'))}">
		<input data-ing-notes value="${attr(item.notes)}" placeholder="${attr(tr('Notes'))}" aria-label="${attr(tr('Notes'))}">
		<label class="tiny-check"><input data-ing-optional type="checkbox"${item.optional ? ' checked' : ''}> ${esc(tr('Optional'))}</label>
		<button class="icon-button danger" data-remove-row type="button" aria-label="${attr(tr('Remove'))}">x</button>
	</div>`;
}
function timerParts(seconds) {
    const value = Math.max(0, Number(seconds || 0));
    if (value >= 3600 && value % 3600 === 0)
        return { value: value / 3600, unit: 'hours' };
    return { value: value ? value / 60 : null, unit: 'minutes' };
}
function timerSeconds(value, unit) {
    if (value === null || value === undefined || value === 0)
        return null;
    const amount = Math.max(0, Number(value || 0));
    return unit === 'hours' ? Math.round(amount * 3600) : Math.round(amount * 60);
}
function stepRow(item = { text: '' }, index = 0) {
    const timer = timerParts(item.timerSeconds);
    return `<div class="step-row" data-step-row><span class="step-number">${index + 1}</span><textarea data-step-text rows="3" placeholder="${attr(tr('Describe this step...'))}">${esc(item.text)}</textarea><div class="step-extras"><input data-step-timer type="number" min="0" step="any" value="${attr(timer.value)}" placeholder="${attr(tr('Timer quantity'))}"><select data-step-timer-unit aria-label="${attr(tr('Timer unit'))}"><option value="minutes"${timer.unit === 'minutes' ? ' selected' : ''}>${esc(tr('minutes'))}</option><option value="hours"${timer.unit === 'hours' ? ' selected' : ''}>${esc(tr('hours'))}</option></select><input data-step-temp type="number" value="${attr(item.temperature)}" placeholder="${attr(tr('Temperature'))}"><select data-step-temp-unit><option value="C"${item.temperatureUnit === 'C' ? ' selected' : ''}>C</option><option value="F"${item.temperatureUnit === 'F' ? ' selected' : ''}>F</option></select></div><button class="icon-button danger" data-remove-row type="button" aria-label="${attr(tr('Remove'))}">x</button></div>`;
}
function recipeViewer(recipe) {
    const image = recipeImageUrl(recipe.imagePath);
    const ingredientItems = recipe.ingredients.map(item => {
        const measure = [item.quantity, displayUnit(item.unit)].filter(Boolean).join(' ');
        return `<li><span>${esc(measure)}</span><strong>${esc(item.name)}</strong>${item.optional ? `<em>${esc(tr('Optional'))}</em>` : ''}${item.notes ? `<small>${esc(item.notes)}</small>` : ''}</li>`;
    }).join('');
    const steps = recipe.steps.map((step, index) => {
        const details = [];
        if (step.timerSeconds) {
            const timer = timerParts(step.timerSeconds);
            details.push(`${timer.value} ${tr(timer.unit)}`);
        }
        if (step.temperature)
            details.push(`${step.temperature}°${step.temperatureUnit || 'C'}`);
        return `<li><span class="recipe-step-number">${index + 1}</span><div><p>${esc(step.text)}</p>${details.length ? `<small>${esc(details.join(' · '))}</small>` : ''}</div></li>`;
    }).join('');
    return `<article class="recipe-view panel">
        <header class="recipe-view-header"><div class="recipe-view-image-wrap">${image ? `<img class="recipe-view-image" src="${attr(image)}" alt="">` : `<div class="recipe-view-image image-placeholder" aria-hidden="true"><span>${esc(recipe.title.slice(0, 1).toUpperCase())}</span></div><button class="cover-search-button" data-find-cover type="button" title="${attr(tr('Find cover image'))}" aria-label="${attr(tr('Find cover image'))}">&#10024;</button>`}</div><div class="recipe-view-heading"><p class="eyebrow">${esc(recipe.cuisine || recipe.course || tr('Recipe'))}</p><h2>${esc(recipe.title)}</h2>${recipe.subtitle ? `<p class="recipe-view-subtitle">${esc(recipe.subtitle)}</p>` : ''}${recipe.description ? `<p class="recipe-view-description">${esc(recipe.description)}</p>` : ''}<div class="recipe-view-actions"><button class="secondary" data-view-mark-cooked type="button">${esc(tr('Cooked today'))}</button><a class="primary" href="#/recipes/${recipe.id}/edit">${esc(tr('Edit recipe'))}</a></div></div></header>
        <div class="recipe-view-meta"><span><strong>${asNumber(recipe.servings)}</strong> ${esc(tr('servings'))}</span><span class="recipe-view-time-marker" aria-hidden="true"></span><span><strong>${asNumber(recipe.prepTime)}</strong> ${esc(tr('prep'))}</span><span><strong>${asNumber(recipe.cookTime)}</strong> ${esc(tr('cook'))}</span><span><strong>${asNumber(recipe.totalTime)}</strong> ${esc(tr('total'))}</span></div>
        <div class="recipe-view-content"><section><h3>${esc(tr('Ingredients'))}</h3><ul class="recipe-view-ingredients">${ingredientItems || `<li>${esc(tr('No data yet'))}</li>`}</ul></section><section><h3>${esc(tr('Procedure'))}</h3><ol class="recipe-view-steps">${steps || `<li>${esc(tr('No data yet'))}</li>`}</ol></section></div>
    </article>`;
}
function coverPreviewUrl(recipeId, thumbnailUrl) {
    return appUrl(`/recipes/${recipeId}/cover/preview?url=${encodeURIComponent(thumbnailUrl)}`);
}
function chooseCoverCandidate(recipeId, candidates) {
    return new Promise(resolve => {
        const safeCandidates = (candidates || []).filter(candidate => safeExternalUrl(candidate.url) && safeExternalUrl(candidate.thumbnailUrl));
        const modal = document.createElement('div');
        modal.className = 'cover-picker-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', tr('Choose a cover image'));
        modal.innerHTML = `<div class="cover-picker-card"><div class="section-heading"><h2>${esc(tr('Choose a cover image'))}</h2><button class="icon-button" data-close-cover-picker type="button" aria-label="${attr(tr('Close'))}">x</button></div><div class="cover-picker-grid">${safeCandidates.map((candidate, index) => `<button data-cover-candidate="${index}" type="button"><img src="${attr(coverPreviewUrl(recipeId, candidate.thumbnailUrl))}" alt=""><span>${esc(candidate.label || tr('Use this image'))}</span></button>`).join('')}</div><button class="secondary" data-close-cover-picker type="button">${esc(tr('Cancel'))}</button></div>`;
        const close = (candidate = null) => { modal.remove(); resolve(candidate); };
        modal.querySelectorAll('[data-close-cover-picker]').forEach(button => button.addEventListener('click', () => close()));
        modal.querySelectorAll('[data-cover-candidate]').forEach(button => button.addEventListener('click', () => close(safeCandidates[asNumber(button.dataset.coverCandidate)] || null)));
        modal.addEventListener('click', event => { if (event.target === modal) close(); });
        document.body.append(modal);
        modal.querySelector('[data-cover-candidate], [data-close-cover-picker]')?.focus();
    });
}
async function renderRecipe(view, id) {
    const recipe = (await working(() => request(`/recipes/${id}`))).recipe;
    view.innerHTML = recipeViewer(recipe);
    view.querySelector('[data-view-mark-cooked]')?.addEventListener('click', async () => {
        await working(() => request(`/recipes/${recipe.id}/cooked`, { method: 'POST', json: {} }));
        showNotice(tr('Preparation recorded'));
    });
    view.querySelector('[data-find-cover]')?.addEventListener('click', async () => {
        const response = await working(() => request(`/recipes/${recipe.id}/cover/search`, { method: 'POST', json: {} }));
        const candidate = await chooseCoverCandidate(recipe.id, response.candidates);
        if (!candidate)
            return;
        await working(() => request(`/recipes/${recipe.id}/cover`, { method: 'POST', json: { url: candidate.url, downloadUrl: candidate.downloadUrl || '' } }));
        showNotice(tr('Cover image found'));
        await renderRecipe(view, id);
    });
}
function bindRowRemoval(container) {
    container.querySelectorAll('[data-remove-row]').forEach(button => button.addEventListener('click', () => {
        const row = button.closest('[data-ingredient-row], [data-step-row]');
        if (!row)
            return;
        const message = row.matches('[data-step-row]') ? 'Remove this step?' : 'Remove this ingredient?';
        if (!window.confirm(tr(message)))
            return;
        row.remove();
    }));
}
function collectRecipe(view, existing) {
    const value = (name) => view.querySelector(`[data-field="${name}"]`)?.value ?? '';
    const numeric = (name) => asNumber(value(name));
    const ingredients = [...view.querySelectorAll('[data-ingredient-row]')].map(row => ({
        name: row.querySelector('[data-ing-name]')?.value.trim() || '',
        quantity: row.querySelector('[data-ing-quantity]')?.value.trim() || null,
        unit: row.querySelector('[data-ing-unit]')?.value.trim() || null,
        notes: row.querySelector('[data-ing-notes]')?.value.trim() || null,
        optional: row.querySelector('[data-ing-optional]')?.checked || false,
    })).filter(item => item.name);
    const steps = [...view.querySelectorAll('[data-step-row]')].map(row => ({
        text: row.querySelector('[data-step-text]')?.value.trim() || '',
        timerSeconds: timerSeconds(asNumber(row.querySelector('[data-step-timer]')?.value) || null, row.querySelector('[data-step-timer-unit]')?.value),
        temperature: asNumber(row.querySelector('[data-step-temp]')?.value) || null,
        temperatureUnit: row.querySelector('[data-step-temp-unit]')?.value || null,
    })).filter(item => item.text);
    const prepTime = numeric('prepTime');
    const restTime = numeric('restTime');
    const cookTime = numeric('cookTime');
    return {
        ...existing,
        title: value('title').trim(), subtitle: value('subtitle').trim() || null, description: value('description').trim() || null,
        language: value('language').trim() || 'en', author: value('author').trim() || null, sourceName: value('sourceName').trim() || null,
        sourceUrl: value('sourceUrl').trim() || null, license: value('license').trim() || null,
        status: value('status'), visibility: value('visibility'),
        excludeFromPlanner: view.querySelector('[data-field="excludeFromPlanner"]')?.checked || false,
        servings: Math.max(1, numeric('servings')), yieldText: value('yieldText').trim() || null,
        prepTime, restTime, cookTime, totalTime: prepTime + restTime + cookTime,
        difficulty: value('difficulty').trim() || null, costCents: Math.round(Math.max(0, numeric('costAmount')) * 100) || null, currency: value('currency').trim().toUpperCase() || null,
        cuisine: value('cuisine').trim() || null, course: value('course').trim() || null, mealType: value('mealType').trim() || null,
        cookingMethod: value('cookingMethod').trim() || null, season: value('season').trim() || null, origin: value('origin').trim() || null,
        calories: numeric('calories') || null, notes: value('notes').trim() || null,
        ingredients, steps, tags: splitNames(value('tags')), categories: splitNames(value('categories')), tools: splitNames(value('tools')),
    };
}
function chipPicker(name, selected, suggestions, single = false) {
	const values = [...new Map(selected.map(item => String(item?.name || item).trim()).filter(Boolean).map(item => [item.toLocaleLowerCase(), item])).values()].slice(0, single ? 1 : undefined);
	const options = [...new Map(suggestions.map(item => String(item?.name || item).trim()).filter(Boolean).map(item => [item.toLocaleLowerCase(), item])).values()];
	return `<div class="chip-picker" data-chip-picker="${attr(name)}"${single ? ' data-chip-single="true"' : ''}><input data-field="${attr(name)}" type="hidden" value="${attr(values.join(', '))}"><div class="chip-picker-input" data-chip-input-wrap><span data-chip-values>${values.map(value => `<button class="chip-picker-value" data-chip-remove="${attr(value)}" type="button">${esc(value)} <span aria-hidden="true">×</span></button>`).join('')}</span><input data-chip-input type="text" autocomplete="off" placeholder="${attr(tr('Choose existing values or type a new one. Press Enter or comma to add it.'))}" role="combobox" aria-autocomplete="list" aria-expanded="false"></div><div class="chip-picker-options" data-chip-options role="listbox" hidden>${options.map(value => `<button data-chip-option="${attr(value)}" type="button" role="option">${esc(value)}</button>`).join('')}<p data-chip-empty hidden>${esc(tr('No data yet'))}</p></div></div>`;
}
function bindChipPicker(view, name) {
	const picker = view.querySelector(`[data-chip-picker="${name}"]`);
	const hidden = picker?.querySelector(`[data-field="${name}"]`);
	const input = picker?.querySelector('[data-chip-input]');
	const options = picker?.querySelector('[data-chip-options]');
	const valuesHolder = picker?.querySelector('[data-chip-values]');
	if (!picker || !hidden || !input || !options || !valuesHolder)
		return;
	const single = picker.dataset.chipSingle === 'true';
	const available = [...options.querySelectorAll('[data-chip-option]')].map(option => option.dataset.chipOption || '');
	let values = [...new Map(String(hidden.value || '').split(/[,;]+/).map(value => value.trim()).filter(Boolean).map(value => [value.toLocaleLowerCase(), value])).values()].slice(0, single ? 1 : undefined);
	const sync = () => { hidden.value = values.join(', '); valuesHolder.innerHTML = values.map(value => `<button class="chip-picker-value" data-chip-remove="${attr(value)}" type="button">${esc(value)} <span aria-hidden="true">×</span></button>`).join(''); };
	const close = () => { options.hidden = true; input.setAttribute('aria-expanded', 'false'); };
	const renderOptions = () => {
		const query = input.value.trim();
		const normalizedQuery = query.toLocaleLowerCase();
		let count = 0;
		[...options.querySelectorAll('[data-chip-option]')].forEach(option => {
			const value = option.dataset.chipOption || '';
			const visible = !values.some(selected => selected.toLocaleLowerCase() === value.toLocaleLowerCase()) && (!normalizedQuery || value.toLocaleLowerCase().includes(normalizedQuery));
			option.hidden = !visible;
			count += visible ? 1 : 0;
		});
		options.querySelector('[data-chip-create]')?.remove();
		if (query && !values.some(value => value.toLocaleLowerCase() === normalizedQuery) && !available.some(value => value.toLocaleLowerCase() === normalizedQuery))
			options.insertAdjacentHTML('beforeend', `<button data-chip-create="${attr(query)}" type="button" role="option">${esc(tr('Add'))}: ${esc(query)}</button>`);
		const empty = options.querySelector('[data-chip-empty]');
		if (empty)
			empty.hidden = count > 0 || Boolean(query);
	};
	const add = (raw) => {
		String(raw || '').split(/[,;]+/).map(value => value.trim()).filter(Boolean).forEach(value => {
			if (single)
				values = [value];
			else if (!values.some(selected => selected.toLocaleLowerCase() === value.toLocaleLowerCase()))
				values.push(value);
		});
		input.value = '';
		sync();
		renderOptions();
	};
	sync();
	input.addEventListener('focus', () => { options.hidden = false; input.setAttribute('aria-expanded', 'true'); renderOptions(); });
	input.addEventListener('input', () => { options.hidden = false; input.setAttribute('aria-expanded', 'true'); renderOptions(); });
	input.addEventListener('keydown', event => {
		if (event.key === 'Enter' || event.key === ',') { event.preventDefault(); add(input.value); }
		else if (event.key === 'Backspace' && !input.value && values.length) { values.pop(); sync(); renderOptions(); }
		else if (event.key === 'Escape') close();
	});
	options.addEventListener('click', event => {
		const option = event.target.closest('[data-chip-option], [data-chip-create]');
		if (option)
			add(option.dataset.chipOption || option.dataset.chipCreate || '');
	});
	valuesHolder.addEventListener('click', event => {
		const button = event.target.closest('[data-chip-remove]');
		if (!button)
			return;
		values = values.filter(value => value.toLocaleLowerCase() !== String(button.dataset.chipRemove || '').toLocaleLowerCase());
		sync();
		renderOptions();
	});
	view.addEventListener('click', event => { if (!picker.contains(event.target)) close(); });
}
function editorForm(recipe, taxonomy = {}) {
	const costAmount = recipe.costCents === null || recipe.costCents === undefined ? '' : (asNumber(recipe.costCents) / 100).toFixed(2);
    return `<div class="editor-actions editor-save-actions"><div>${recipe.id ? `<button class="secondary" data-mark-cooked type="button">${esc(tr('Cooked today'))}</button>` : ''}<button class="primary" data-save-recipe type="button">${esc(tr('Save recipe'))}</button></div></div>
	<section class="editor-top panel">
		<div><p class="eyebrow">${esc(recipe.id ? tr('Recipe details') : tr('Create manually'))}</p><h2>${esc(recipe.title || tr('Untitled recipe'))}</h2></div>
	</section>
	<div class="editor-layout">
		<main class="view-stack">
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Identity'))}</p><h2>${esc(tr('Recipe'))}</h2></div></div>
				<div class="form-grid">${textInput(tr('Title'), 'title', recipe.title, { className: 'span-2' })}${textInput(tr('Subtitle'), 'subtitle', recipe.subtitle, { className: 'span-2' })}${textareaInput(tr('Description'), 'description', recipe.description, 4, 'span-2')}
				${textInput(tr('Author'), 'author', recipe.author)}${textInput(tr('Language'), 'language', recipe.language)}${textInput(tr('Source name'), 'sourceName', recipe.sourceName)}${textInput(tr('Source URL'), 'sourceUrl', recipe.sourceUrl, { type: 'url' })}${textInput(tr('License'), 'license', recipe.license)}
				${selectInput(tr('Status'), 'status', recipe.status, [['draft', tr('Draft')], ['published', tr('Published')]])}${selectInput(tr('Visibility'), 'visibility', recipe.visibility, [['private', tr('Private')], ['shared', tr('Shared')], ['public', tr('Public')]])}</div>
			</section>
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Yield and timing'))}</p><h2>${esc(tr('Planning data'))}</h2><p class="section-help">${esc(tr('Planning details help'))}</p></div></div><div class="planning-grid">${textInput(tr('Servings'), 'servings', recipe.servings, { type: 'number', min: 1 })}${textInput(tr('Yield'), 'yieldText', recipe.yieldText)}${textInput(tr('Preparation (min)'), 'prepTime', recipe.prepTime, { type: 'number', min: 0 })}${textInput(tr('Rest (min)'), 'restTime', recipe.restTime, { type: 'number', min: 0 })}${textInput(tr('Cooking (min)'), 'cookTime', recipe.cookTime, { type: 'number', min: 0 })}${textInput(tr('Difficulty'), 'difficulty', recipe.difficulty)}${textInput(tr('Calories'), 'calories', recipe.calories, { type: 'number', min: 0 })}<label class="planning-cost">${esc(tr('Cost and currency'))}<span><input data-field="costAmount" type="number" min="0" step="0.01" inputmode="decimal" value="${attr(costAmount)}" placeholder="0.00"><input data-field="currency" value="${attr(recipe.currency || 'EUR')}" maxlength="3" aria-label="${attr(tr('Currency'))}"></span></label><label class="check-inline"><input data-field="excludeFromPlanner" type="checkbox"${recipe.excludeFromPlanner ? ' checked' : ''}> ${esc(tr('Exclude from meal planner'))}</label></div></section>
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Structured list'))}</p><h2>${esc(tr('Ingredients'))}</h2></div><button class="secondary" data-add-ingredient type="button">+ ${esc(tr('Ingredient'))}</button></div><div data-ingredients>${(recipe.ingredients.length ? recipe.ingredients : [{ name: '' }]).map(item => ingredientRow(item)).join('')}</div></section>
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Method'))}</p><h2>${esc(tr('Procedure'))}</h2></div><button class="secondary" data-add-step type="button">+ ${esc(tr('Step'))}</button></div><div data-steps>${(recipe.steps.length ? recipe.steps : [{ text: '' }]).map((item, index) => stepRow(item, index)).join('')}</div></section>
			<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Classification'))}</p><h2>${esc(tr('Organization'))}</h2><p class="section-help">${esc(tr('Choose existing values or type a new one. Press Enter or comma to add it.'))}</p></div></div><div class="form-grid">${labelInput(tr('Tags'), chipPicker('tags', recipe.tags || [], taxonomy.tags || []), 'span-2')}${labelInput(tr('Categories'), chipPicker('categories', recipe.categories || [], taxonomy.categories || []), 'span-2')}${labelInput(tr('Tools'), chipPicker('tools', recipe.tools || [], taxonomy.tools || []), 'span-2')}${labelInput(tr('Cuisine'), chipPicker('cuisine', [recipe.cuisine].filter(Boolean), taxonomy.cuisine || [], true))}${labelInput(tr('Course'), chipPicker('course', [recipe.course].filter(Boolean), taxonomy.course || [], true))}${labelInput(tr('Meal type'), chipPicker('mealType', [recipe.mealType].filter(Boolean), taxonomy.mealType || [], true))}${labelInput(tr('Cooking method'), chipPicker('cookingMethod', [recipe.cookingMethod].filter(Boolean), taxonomy.cookingMethod || [], true))}${selectInput(tr('Season'), 'season', recipe.season, predefinedOptions(recipe.season, [['', tr('Select an option')], ['Primavera', tr('Spring')], ['Estate', tr('Summer')], ['Autunno', tr('Autumn')], ['Inverno', tr('Winter')]]))}${labelInput(tr('Origin'), chipPicker('origin', [recipe.origin].filter(Boolean), taxonomy.origin || [], true))}${textareaInput(tr('Personal notes'), 'notes', recipe.notes, 5, 'span-2')}</div></section>
		</main>
		<aside class="view-stack editor-aside">
			${recipe.id ? `<section class="panel form-section"><p class="eyebrow">${esc(tr('Exports'))}</p><h2>${esc(tr('Download'))}</h2><div class="export-buttons"><a class="secondary" href="${attr(exportUrl(recipe.id, 'json'))}">JSON-LD</a><a class="secondary" href="${attr(exportUrl(recipe.id, 'markdown'))}">Markdown</a><a class="secondary" href="${attr(exportUrl(recipe.id, 'html'))}">HTML</a></div></section>
			<section class="panel form-section" data-media-section><p class="eyebrow">${esc(tr('Files'))}</p><h2>${esc(tr('Attachments'))}</h2><div class="media-upload-group cover-upload"><label><strong>${esc(tr('Cover image'))}</strong><input data-cover-file type="file" accept="image/*"></label><small>${esc(tr('The uploaded image becomes the recipe cover after saving.'))}</small></div><div class="media-upload-group"><label><strong>${esc(tr('Additional attachment'))}</strong><input data-media-file type="file"></label><button class="secondary" data-upload-media type="button">${esc(tr('Upload attachment'))}</button></div><ul class="media-list">${recipe.media.map(item => item.id ? `<li><a href="${attr(mediaUrl(item.id))}" target="_blank" rel="noopener"><strong>${esc(item.altText || item.path.split('/').pop() || item.kind)}</strong><small>${esc(item.mime || item.kind)} · ${formatBytes(item.fileSize)} · ${formatMediaDate(item.createdAt)}</small></a><button class="icon-button danger" data-delete-media="${attr(item.id)}" type="button" aria-label="${attr(tr('Delete'))}">x</button></li>` : '').join('')}</ul></section>
			<section class="panel form-section" data-sharing-section><p class="eyebrow">${esc(tr('Access'))}</p><h2>${esc(tr('Sharing'))}</h2><div data-share-list></div><div class="share-form"><select data-share-type><option value="link">${esc(tr('Public link'))}</option><option value="user">${esc(tr('User'))}</option><option value="group">${esc(tr('Group'))}</option></select><input data-share-with placeholder="${attr(tr('User or group ID'))}"><input data-share-password type="password" placeholder="${attr(tr('Optional link password'))}"><label class="check-inline"><input data-share-edit type="checkbox"> ${esc(tr('Allow editing'))}</label><button class="secondary" data-create-share type="button">${esc(tr('Create share'))}</button></div></section>
			<section class="panel form-section" data-history-section><p class="eyebrow">${esc(tr('Audit trail'))}</p><h2>${esc(tr('Version history'))}</h2><div class="version-list" data-version-list></div></section>
			<section class="panel form-section danger-zone"><h2>${esc(tr('Danger zone'))}</h2><button class="danger secondary" data-delete-recipe type="button">${esc(tr('Delete recipe'))}</button></section>` : `<section class="panel empty-state"><h2>${esc(tr('Save first'))}</h2><p>${esc(tr('Attachments, sharing, exports and version history become available after the first save.'))}</p></section>`}
		</aside>
	</div>`;
}
async function renderEditor(view, id) {
    let recipe = id ? (await working(() => request(`/recipes/${id}`))).recipe : emptyRecipe();
	const [taxonomy, recipeResponse] = await working(() => Promise.all([request('/taxonomy'), request('/recipes?sort=title&direction=ASC')]));
	['cuisine', 'course', 'mealType', 'cookingMethod', 'origin'].forEach(field => {
		taxonomy[field] = [...new Map((recipeResponse.recipes || []).map(item => String(item[field] || '').trim()).filter(Boolean).map(value => [value.toLocaleLowerCase(), value])).values()];
	});
    const paint = async () => {
        view.innerHTML = editorForm(recipe, taxonomy);
        bindRowRemoval(view);
		['tags', 'categories', 'tools', 'cuisine', 'course', 'mealType', 'cookingMethod', 'origin'].forEach(name => bindChipPicker(view, name));
        view.querySelector('[data-add-ingredient]')?.addEventListener('click', () => {
            const holder = view.querySelector('[data-ingredients]');
            holder.insertAdjacentHTML('beforeend', ingredientRow());
            bindRowRemoval(holder);
        });
        view.querySelector('[data-add-step]')?.addEventListener('click', () => {
            const holder = view.querySelector('[data-steps]');
            holder.insertAdjacentHTML('beforeend', stepRow({ text: '' }, holder.querySelectorAll('[data-step-row]').length));
            bindRowRemoval(holder);
        });
        view.querySelector('[data-save-recipe]')?.addEventListener('click', async () => {
            const payload = collectRecipe(view, recipe);
            const cover = view.querySelector('[data-cover-file]')?.files?.[0];
            const response = recipe.id
                ? await working(() => request(`/recipes/${recipe.id}`, { method: 'PUT', json: { recipe: payload } }))
                : await working(() => request('/recipes', { method: 'POST', json: { recipe: payload } }));
            recipe = response.recipe;
            if (cover && recipe.id) {
                const form = new FormData();
                form.append('file', cover);
                form.append('kind', 'image');
                form.append('altText', tr('Cover image'));
                await working(() => request(`/recipes/${recipe.id}/media`, { method: 'POST', body: form }));
                recipe = (await request(`/recipes/${recipe.id}`)).recipe;
            }
            showNotice(tr('Recipe saved'));
            if (!id && recipe.id)
                location.hash = `#/recipes/${recipe.id}`;
            else
                await paint();
        });
        view.querySelector('[data-mark-cooked]')?.addEventListener('click', async () => {
            if (!recipe.id)
                return;
            await working(() => request(`/recipes/${recipe.id}/cooked`, { method: 'POST', json: {} }));
            showNotice(tr('Preparation recorded'));
        });
        view.querySelector('[data-delete-recipe]')?.addEventListener('click', async () => {
            if (!recipe.id || !window.confirm(tr('Delete this recipe permanently?')))
                return;
            await working(() => request(`/recipes/${recipe.id}`, { method: 'DELETE' }));
            showNotice(tr('Recipe deleted'));
            location.hash = '#/recipes';
        });
        view.querySelector('[data-upload-media]')?.addEventListener('click', async () => {
            if (!recipe.id)
                return;
            const file = view.querySelector('[data-media-file]')?.files?.[0];
            if (!file) {
                showNotice(tr('Choose a file first'), 'error');
                return;
            }
            const form = new FormData();
            form.append('file', file);
            await working(() => request(`/recipes/${recipe.id}/media`, { method: 'POST', body: form }));
            recipe = (await request(`/recipes/${recipe.id}`)).recipe;
            showNotice(tr('Attachment uploaded'));
            await paint();
        });
        view.querySelectorAll('[data-delete-media]').forEach(button => button.addEventListener('click', async () => {
            if (!recipe.id || !window.confirm(tr('Remove attachment?')))
                return;
            await working(() => request(`/recipes/${recipe.id}/media/${asNumber(button.dataset.deleteMedia)}`, { method: 'DELETE' }));
            recipe = (await request(`/recipes/${recipe.id}`)).recipe;
            showNotice(tr('Attachment removed'));
            await paint();
        }));
        if (recipe.id) {
            await Promise.all([loadShares(view, recipe.id), loadVersions(view, recipe.id)]);
            const shareType = view.querySelector('[data-share-type]');
            const shareWith = view.querySelector('[data-share-with]');
            const sharePassword = view.querySelector('[data-share-password]');
            const shareEdit = view.querySelector('[data-share-edit]');
            const updateShareFields = () => {
                if (shareWith)
                    shareWith.disabled = shareType?.value === 'link';
                if (sharePassword)
                    sharePassword.disabled = shareType?.value !== 'link';
            };
            shareType?.addEventListener('change', updateShareFields);
            updateShareFields();
            view.querySelector('[data-create-share]')?.addEventListener('click', async () => {
                const type = shareType?.value || 'link';
                await working(() => request(`/recipes/${recipe.id}/shares`, { method: 'POST', json: { share: { type, shareWith: shareWith?.value || '', password: sharePassword?.value || '', permission: shareEdit?.checked ? 3 : 1 } } }));
                if (shareWith)
                    shareWith.value = '';
                if (sharePassword)
                    sharePassword.value = '';
                showNotice(tr('Share created'));
                await loadShares(view, recipe.id);
            });
        }
    };
    await paint();
}
async function loadShares(view, recipeId) {
    const holder = view.querySelector('[data-share-list]');
    if (!holder)
        return;
    const shares = (await request(`/recipes/${recipeId}/shares`)).shares;
    holder.innerHTML = shares.length ? shares.map(share => `<div class="share-item"><div><strong>${esc(share.type === 'link' ? tr('Public link') : share.shareWith)}</strong><small>${share.url ? `<a href="${attr(share.url)}" target="_blank" rel="noopener">${esc(tr('Open link'))}</a>` : esc(String(share.type || ''))}${share.passwordProtected ? ` - ${esc(tr('password protected'))}` : ''}</small></div><button class="icon-button danger" data-delete-share="${attr(share.id)}" type="button">x</button></div>`).join('') : `<p>${esc(tr('Not shared yet'))}</p>`;
    holder.querySelectorAll('[data-delete-share]').forEach(button => button.addEventListener('click', async () => {
        if (!window.confirm(tr('Delete this share?')))
            return;
        await working(() => request(`/recipes/${recipeId}/shares/${asNumber(button.dataset.deleteShare)}`, { method: 'DELETE' }));
        await loadShares(view, recipeId);
    }));
}
async function loadVersions(view, recipeId) {
    const holder = view.querySelector('[data-version-list]');
    if (!holder)
        return;
    const versions = (await request(`/recipes/${recipeId}/versions`)).versions;
    holder.innerHTML = versions.length ? versions.slice(0, 20).map(version => `<div class="version-item"><div><strong>v${version.revision}</strong><small>${esc(new Date(version.createdAt * 1000).toLocaleString())} - ${esc(version.userId)}</small></div><button class="ghost" data-restore-version="${version.revision}" type="button">${esc(tr('Restore'))}</button></div>`).join('') : `<p>${esc(tr('No versions yet'))}</p>`;
    holder.querySelectorAll('[data-restore-version]').forEach(button => button.addEventListener('click', async () => {
        if (!window.confirm(tr('Restore this version?')))
            return;
        await working(() => request(`/recipes/${recipeId}/restore/${asNumber(button.dataset.restoreVersion)}`, { method: 'POST', json: {} }));
        showNotice(tr('Version restored'));
        location.hash = '#/recipes';
        window.setTimeout(() => { location.hash = `#/recipes/${recipeId}`; }, 0);
    }));
}
async function renderImport(view) {
    let kind = 'url';
    let previews = [];
    let savedPreviews = new Set();
    let activeExternalJobId = null;
    const setImportBusy = (active, title = tr('Importing recipe'), detail = tr('Please wait while the source is analyzed.')) => {
        const modal = view.querySelector('[data-import-loading]');
        if (modal) {
            const titleNode = modal.querySelector('[data-import-loading-title]');
            const detailNode = modal.querySelector('[data-import-loading-detail]');
            if (titleNode)
                titleNode.textContent = title;
            if (detailNode)
                detailNode.textContent = detail;
            modal.hidden = !active;
        }
    };
    const paint = () => {
        view.innerHTML = `<section class="import-hero panel"><div><p class="eyebrow">${esc(tr('Smart import'))}</p><h2>${esc(tr('Turn almost any source into a structured recipe'))}</h2><p>${esc(tr('SmartCook checks Schema.org data first, then deterministic parsing, and uses AI only when requested.'))}</p></div></section>
		<section class="panel" data-external-import-inbox><div class="section-heading"><div><p class="eyebrow">${esc(tr('Received imports'))}</p><h2>${esc(tr('Received imports'))}</h2><p>${esc(tr('Imports sent from SmartCook Connector appear here.'))}</p></div><button class="ghost" data-refresh-external-imports type="button">${esc(tr('Refresh'))}</button></div><p>${esc(tr('Loading...'))}</p></section>
		<section class="two-column import-layout"><article class="panel form-section">
			<div class="source-tabs">${[['url', 'URL'], ['text', tr('Text')], ['markdown', 'Markdown'], ['json', 'JSON'], ['file', tr('File / OCR')]].map(([id, label]) => `<button type="button" data-import-kind="${id}" class="${kind === id ? 'active' : ''}">${esc(label)}</button>`).join('')}</div>
            ${kind === 'url' ? `<label>${esc(tr('Recipe URL'))}<input data-import-url type="url" placeholder="https://example.com/recipe"></label>` : kind === 'file' ? `<label>${esc(tr('PDF, image, Markdown, HTML or JSON'))}<input data-import-file type="file" multiple accept="image/*,.pdf,.txt,.md,.markdown,.html,.htm,.json"><small>${esc(tr('Select one or more files. Images and PDFs require a configured OCR/document extractor.'))}</small></label>` : `<label>${esc(tr('Source content'))}<textarea data-import-text rows="18" placeholder="${attr(tr('Paste the recipe, including ingredients and procedure...'))}"></textarea></label>`}
			<div class="form-grid"><label>${esc(tr('Output language'))}<input data-import-language value="${attr(document.documentElement.lang || 'it')}"></label><label>${esc(tr('AI provider override'))}<select data-import-provider><option value="">${esc(tr('Use settings'))}</option><option value="nextcloud">Nextcloud Assistant</option><option value="openai">OpenAI</option><option value="openrouter">OpenRouter</option><option value="ollama">Ollama</option><option value="localai">LocalAI</option><option value="mistral">Mistral</option><option value="anthropic">Anthropic</option><option value="gemini">Gemini</option></select></label></div>
			<label class="check-inline ai-toggle"><input data-import-ai type="checkbox"><span><b>${esc(tr('Use AI refinement'))}</b><small>${esc(tr('Optional fallback for incomplete or unstructured sources'))}</small></span></label>
			<button class="primary" data-extract type="button">${esc(tr('Extract recipe data'))}</button>
        </article><article class="panel preview-panel" data-import-preview>${importPreviewsHtml(previews, savedPreviews)}</article></section>
		<div class="blocking-modal" data-import-loading hidden role="dialog" aria-modal="true" aria-labelledby="smartcook-import-loading-title"><div class="blocking-modal-card"><div class="loading-spinner" aria-hidden="true"></div><h2 id="smartcook-import-loading-title" data-import-loading-title>${esc(tr('Importing recipe'))}</h2><p data-import-loading-detail>${esc(tr('Please wait while the source is analyzed.'))}</p></div></div>`;
        view.querySelectorAll('[data-import-kind]').forEach(button => button.addEventListener('click', () => { kind = button.dataset.importKind; previews = []; savedPreviews = new Set(); activeExternalJobId = null; paint(); }));
        const loadInbox = async () => {
            const inbox = view.querySelector('[data-external-import-inbox]');
            if (!inbox)
                return;
            try {
                const jobs = (await request('/import/jobs?limit=50')).jobs || [];
                inbox.innerHTML = externalImportInboxHtml(jobs);
                inbox.querySelector('[data-refresh-external-imports]')?.addEventListener('click', loadInbox);
                inbox.querySelectorAll('[data-delete-external-import]').forEach(button => button.addEventListener('click', async () => {
                    if (!window.confirm(tr('Delete this received import?')))
                        return;
                    await working(() => request(`/import/jobs/${button.dataset.deleteExternalImport}`, { method: 'DELETE' }));
                    await loadInbox();
                }));
                inbox.querySelectorAll('[data-select-external-import]').forEach(button => button.addEventListener('click', () => {
                    const job = jobs.find(item => String(item.id) === String(button.dataset.selectExternalImport));
                    if (!job)
                        return;
                    kind = job.kind === 'text' ? 'text' : 'url';
                    paint();
                    const source = job.payload?.[kind] || job.sourceRef || '';
                    const sourceInput = kind === 'url' ? view.querySelector('[data-import-url]') : view.querySelector('[data-import-text]');
                    if (sourceInput)
                        sourceInput.value = source;
                    activeExternalJobId = job.id;
                    sourceInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }));
                inbox.querySelectorAll('[data-open-external-import]').forEach(button => button.addEventListener('click', () => {
                    const job = jobs.find(item => String(item.id) === String(button.dataset.openExternalImport));
                    if (!job?.result?.recipe) {
                        showNotice(tr('Waiting for processing'), 'error');
                        return;
                    }
                    previews = [job.result];
                    savedPreviews = new Set();
                    activeExternalJobId = job.id;
                    const previewHolder = view.querySelector('[data-import-preview]');
                    previewHolder.innerHTML = importPreviewsHtml(previews, savedPreviews);
                    bindImportSave(previewHolder);
                    previewHolder.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }));
            }
            catch (error) {
                inbox.innerHTML = `<div class="section-heading"><div><p class="eyebrow">${esc(tr('Received imports'))}</p><h2>${esc(tr('Received imports'))}</h2></div><button class="ghost" data-refresh-external-imports type="button">${esc(tr('Refresh'))}</button></div><p>${esc(error instanceof Error ? error.message : tr('Unexpected error'))}</p>`;
                inbox.querySelector('[data-refresh-external-imports]')?.addEventListener('click', loadInbox);
            }
        };
        view.querySelector('[data-refresh-external-imports]')?.addEventListener('click', loadInbox);
        view.querySelector('[data-extract]')?.addEventListener('click', async () => {
            const language = view.querySelector('[data-import-language]')?.value || 'en';
            const useAi = view.querySelector('[data-import-ai]')?.checked || false;
            const provider = view.querySelector('[data-import-provider]')?.value || '';
            if (kind === 'file') {
                const files = Array.from(view.querySelector('[data-import-file]')?.files || []);
                if (!files.length) {
                    showNotice(tr('Choose a file first'), 'error');
                    return;
                }
                previews = [];
                savedPreviews = new Set();
                const failedFiles = [];
                try {
                    for (const [index, file] of files.entries()) {
                        setImportBusy(true, `${tr('Importing file')} ${index + 1} ${tr('of')} ${files.length}`, file.name);
                        const form = new FormData();
                        form.append('file', file);
                        form.append('language', language);
                        form.append('useAi', String(useAi));
                        if (provider)
                            form.append('provider', provider);
                        try {
                            previews.push(await request('/import/file', { method: 'POST', body: form }));
                        }
                        catch (error) {
                            failedFiles.push(`${file.name}: ${error instanceof Error ? error.message : tr('Unexpected error')}`);
                        }
                    }
                }
                finally {
                    setImportBusy(false);
                }
                if (failedFiles.length)
                    showNotice(`${tr('Some files could not be imported')}: ${failedFiles.join('; ')}`, 'error');
                if (!previews.length)
                    return;
            }
            else {
                const text = view.querySelector('[data-import-text]')?.value || '';
                const url = view.querySelector('[data-import-url]')?.value || '';
                if ((kind === 'url' && !url.trim()) || (kind !== 'url' && !text.trim())) {
                    showNotice(tr('Enter a source first'), 'error');
                    return;
                }
                setImportBusy(true);
                try {
                    previews = [await working(() => request('/import/preview', { method: 'POST', json: { kind, payload: kind === 'url' ? { url, language } : { text, language }, useAi, provider: provider || null } }))];
                    savedPreviews = new Set();
                }
                finally {
                    setImportBusy(false);
                }
            }
            showNotice(previews.length > 1 ? `${previews.length} ${tr('recipes extracted. Review them before saving.')}` : tr('Recipe data extracted. Review it before saving.'));
            const holder = view.querySelector('[data-import-preview]');
            holder.innerHTML = importPreviewsHtml(previews, savedPreviews);
            bindImportSave(holder);
        });
        if (previews.length)
            bindImportSave(view.querySelector('[data-import-preview]'));
        loadInbox();
    };
    const bindImportSave = (holder) => {
        holder.querySelector('[data-save-all-import]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const pendingIndexes = previews.map((_, index) => index).filter(index => !savedPreviews.has(index));
            if (!pendingIndexes.length) {
                location.hash = '#/recipes';
                return;
            }
            holder.querySelectorAll('[data-import-preview-card]').forEach((card, index) => {
                const preview = previews[index];
                preview.recipe.title = card.querySelector('[data-preview-title]')?.value.trim() || preview.recipe.title;
                preview.recipe.description = card.querySelector('[data-preview-description]')?.value.trim() || preview.recipe.description;
            });
            button.disabled = true;
            const failedRecipes = [];
            try {
                for (const [position, index] of pendingIndexes.entries()) {
                    const preview = previews[index];
                    setImportBusy(true, `${tr('Saving recipe')} ${position + 1} ${tr('of')} ${pendingIndexes.length}`, preview.recipe.title);
                    try {
                        await request('/recipes', { method: 'POST', json: { recipe: preview.recipe } });
                        savedPreviews.add(index);
                    }
                    catch (error) {
                        failedRecipes.push(`${preview.recipe.title}: ${error instanceof Error ? error.message : tr('Unexpected error')}`);
                    }
                }
            }
            finally {
                setImportBusy(false);
                button.disabled = false;
            }
            if (failedRecipes.length) {
                showNotice(`${tr('Some recipes could not be saved')}: ${failedRecipes.join('; ')}`, 'error');
                holder.innerHTML = importPreviewsHtml(previews, savedPreviews);
                bindImportSave(holder);
                return;
            }
            showNotice(tr('Imported recipes saved'));
            if (activeExternalJobId !== null) {
                await request(`/import/jobs/${activeExternalJobId}`, { method: 'DELETE' });
                activeExternalJobId = null;
            }
            location.hash = '#/recipes';
        });
    };
    paint();
}
function externalImportInboxHtml(jobs) {
    // Keep pre-upgrade remote jobs visible too, so failed entries can be removed.
    const entries = jobs.filter(job => ['url', 'text'].includes(job.kind));
    return `<div class="section-heading"><div><p class="eyebrow">${esc(tr('Received imports'))}</p><h2>${esc(tr('Received imports'))}</h2><p>${esc(tr('Imports sent from SmartCook Connector appear here.'))}</p></div><button class="ghost" data-refresh-external-imports type="button">${esc(tr('Refresh'))}</button></div>${entries.length ? `<div class="version-list">${entries.map(job => {
        const status = job.status === 'complete' ? tr('Open preview') : job.status === 'failed' ? tr('Import failed') : job.status === 'running' ? tr('Processing import') : tr('Waiting for processing');
        const source = String(job.sourceRef || job.payload?.text || job.payload?.url || job.kind).slice(0, 180);
        const error = job.status === 'failed' && job.error ? `<small>${esc(job.error)}</small>` : '';
        const mainAction = job.status === 'complete' && job.result?.recipe
            ? `<button class="primary" data-open-external-import="${attr(job.id)}" type="button">${esc(tr('Open preview'))}</button>`
            : job.status !== 'running' ? `<button class="primary" data-select-external-import="${attr(job.id)}" type="button">${esc(tr('Select import'))}</button>` : '';
        return `<div class="version-item"><div><strong>${esc(source)}</strong><small>${esc(new Date(job.createdAt * 1000).toLocaleString())} · ${esc(status)}</small>${error}</div><div class="button-row">${mainAction}<button class="ghost danger" data-delete-external-import="${attr(job.id)}" type="button">${esc(tr('Delete import'))}</button></div></div>`;
    }).join('')}</div>` : `<p>${esc(tr('No received imports yet'))}</p>`}`;
}
function importPreviewsHtml(previews, savedPreviews) {
    if (!previews.length)
        return `<div class="empty-preview"><div>&#8761;</div><h2>${esc(tr('Preview appears here'))}</h2><p>${esc(tr('The source is never saved as a recipe until you review and confirm the extracted fields.'))}</p></div>`;
    const pending = previews.length - savedPreviews.size;
    return `<div class="section-heading"><div><p class="eyebrow">${previews.length} ${esc(tr('recipes extracted. Review them before saving.'))}</p><h2>${esc(tr('Import previews'))}</h2></div><button class="primary" data-save-all-import type="button">${esc(pending ? tr('Save all recipes') : tr('View recipes'))}</button></div>${previews.map((item, index) => importPreviewHtml(item, savedPreviews.has(index))).join('')}`;
}
function importPreviewHtml(preview, saved = false) {
    const recipe = preview.recipe;
    return `<div class="import-preview-card" data-import-preview-card><div class="section-heading"><div><p class="eyebrow">${esc(preview.strategy)}</p><h3>${esc(recipe.title || tr('Import preview'))}</h3></div>${saved ? `<span class="status-pill enabled">${esc(tr('Saved'))}</span>` : ''}</div>
		${preview.warnings.length ? `<div class="warning-list">${preview.warnings.map(warning => `<p>${esc(warning)}</p>`).join('')}</div>` : ''}
		<label>${esc(tr('Title'))}<input data-preview-title value="${attr(recipe.title)}"></label><label>${esc(tr('Description'))}<textarea data-preview-description rows="3">${esc(recipe.description)}</textarea></label>
		<div class="preview-metrics"><span>${recipe.servings} ${esc(tr('servings'))}</span><span>${recipe.prepTime} min ${esc(tr('prep'))}</span><span>${recipe.cookTime} min ${esc(tr('cook'))}</span><span>${recipe.totalTime} min ${esc(tr('total'))}</span></div>
		<div class="preview-columns"><div><h3>${esc(tr('Ingredients'))} <small>${recipe.ingredients.length}</small></h3><ul>${recipe.ingredients.map(item => `<li><b>${esc(item.quantity)} ${esc(displayUnit(item.unit))}</b> ${esc(item.name)}</li>`).join('')}</ul></div><div><h3>${esc(tr('Procedure'))} <small>${recipe.steps.length}</small></h3><ol>${recipe.steps.map(step => `<li>${esc(step.text)}</li>`).join('')}</ol></div></div>
		${preview.duplicates.length ? `<div class="duplicate-box"><h3>${esc(tr('Possible duplicates'))}</h3>${preview.duplicates.map(match => `<a href="#/recipes/${match.recipe.id}">${esc(match.recipe.title)} <span>${Math.round(match.score * 100)}%</span></a>`).join('')}</div>` : ''}</div>`;
}
function startOfWeek(date) {
    const copy = new Date(date);
    const day = (copy.getDay() + 6) % 7;
    copy.setDate(copy.getDate() - day);
    copy.setHours(0, 0, 0, 0);
    return copy;
}
function recipeSearchPicker(recipes) {
	return `<div class="recipe-search-picker" data-recipe-search-picker><input data-meal-recipe-search type="search" autocomplete="off" placeholder="${attr(tr('Search recipes...'))}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="smartcook-recipe-options"><input data-meal-recipe type="hidden"><div id="smartcook-recipe-options" class="recipe-search-options" data-meal-recipe-options role="listbox" hidden>${recipes.map(recipe => `<button data-recipe-id="${attr(recipe.id)}" data-recipe-title="${attr(recipe.title)}" type="button" role="option">${esc(recipe.title)}</button>`).join('')}<p data-no-recipe-results hidden>${esc(tr('No recipes found'))}</p></div></div>`;
}
function bindRecipeSearchPicker(view) {
	const picker = view.querySelector('[data-recipe-search-picker]');
	const search = view.querySelector('[data-meal-recipe-search]');
	const selected = view.querySelector('[data-meal-recipe]');
	const options = view.querySelector('[data-meal-recipe-options]');
	if (!picker || !search || !selected || !options)
		return;
	const buttons = [...options.querySelectorAll('[data-recipe-id]')];
	const noResults = options.querySelector('[data-no-recipe-results]');
	const filter = (term = search.value) => {
		const query = term.trim().toLocaleLowerCase();
		let count = 0;
		buttons.forEach(button => {
			const visible = !query || (button.dataset.recipeTitle || '').toLocaleLowerCase().includes(query);
			button.hidden = !visible;
			count += visible ? 1 : 0;
		});
		if (noResults)
			noResults.hidden = count > 0;
	};
	const close = () => {
		options.hidden = true;
		search.setAttribute('aria-expanded', 'false');
	};
	const open = () => {
		options.hidden = false;
		search.setAttribute('aria-expanded', 'true');
	};
	const choose = (button) => {
		selected.value = button.dataset.recipeId || '';
		search.value = button.dataset.recipeTitle || '';
		close();
	};
	search.addEventListener('focus', () => { open(); filter(''); search.select(); });
	search.addEventListener('input', () => { selected.value = ''; open(); filter(); });
	search.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			close();
			return;
		}
		if (event.key === 'ArrowDown') {
			const first = buttons.find(button => !button.hidden);
			if (first) {
				event.preventDefault();
				first.focus();
			}
		}
	});
	buttons.forEach(button => button.addEventListener('click', () => choose(button)));
	view.addEventListener('click', (event) => { if (!picker.contains(event.target)) close(); });
}
async function renderPlanner(view) {
    let weekStart = startOfWeek(new Date());
    let recipes = [];
    let meals = [];
    const load = async () => {
        const days = Array.from({ length: 7 }, (_, index) => { const day = new Date(weekStart); day.setDate(day.getDate() + index); return day; });
        const [recipeResponse, mealResponse] = await working(() => Promise.all([
            request('/recipes?sort=title&direction=ASC'),
            request(`/planner?from=${dateIso(days[0])}&to=${dateIso(days[6])}`),
        ]));
        recipes = recipeResponse.recipes.filter(recipe => !recipe.excludeFromPlanner);
        meals = mealResponse.meals;
        paint(days);
    };
    const paint = (days) => {
		view.innerHTML = `<section class="toolbar panel planner-toolbar"><button class="secondary" data-week-back type="button">&larr;</button><div><p class="eyebrow">${esc(tr('Week'))}</p><h2>${esc(days[0].toLocaleDateString())} - ${esc(days[6].toLocaleDateString())}</h2></div><button class="secondary" data-week-forward type="button">&rarr;</button><div class="planner-toolbar-actions"><button class="ghost" data-week-today type="button">${esc(tr('Today'))}</button><button class="secondary" data-clear-week type="button"${meals.length ? '' : ' disabled'}>${esc(tr('Empty week'))}</button></div></section>
		<section class="panel form-section planner-ai"><div class="section-heading"><div><p class="eyebrow">${esc(tr('AI meal planner'))}</p><h2>${esc(tr('Generate with AI'))}</h2></div></div><div class="form-grid"><label class="span-2">${esc(tr('Weekly instruction (optional)'))}<textarea data-planner-instruction rows="2" placeholder="${attr(tr('Example: use more legumes and prepare leftovers for lunch'))}"></textarea></label></div><button class="primary" data-generate-plan type="button">${esc(tr('Generate with AI'))}</button></section>
		<section class="planner-grid">${days.map(day => `<article class="day-column panel ${dateIso(day) === dateIso(new Date()) ? 'today' : ''}"><header><span>${esc(day.toLocaleDateString(undefined, { weekday: 'short' }))}</span><strong>${day.getDate()}</strong></header>${meals.filter(meal => meal.date === dateIso(day)).map(meal => `<div class="meal-card"><small>${esc(mealLabel(meal.slot))}</small><a href="#/recipes/${meal.recipeId}">${esc(meal.recipeTitle)}</a><span>${meal.servings} ${esc(tr('servings'))}</span><button data-delete-meal="${meal.id}" type="button">x</button></div>`).join('')}<button class="add-meal" data-select-date="${dateIso(day)}" type="button">+</button></article>`).join('')}</section>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Plan'))}</p><h2>${esc(tr('Add a meal'))}</h2></div></div><div class="form-grid four"><label>${esc(tr('Date'))}<input data-meal-date type="date" value="${dateIso(new Date())}"></label><label>${esc(tr('Recipe'))}${recipeSearchPicker(recipes)}</label><label>${esc(tr('Meal'))}<select data-meal-slot><option value="breakfast">${esc(tr('Breakfast'))}</option><option value="lunch">${esc(tr('Lunch'))}</option><option value="dinner" selected>${esc(tr('Dinner'))}</option><option value="snack">${esc(tr('Snack'))}</option></select></label><label>${esc(tr('Servings'))}<input data-meal-servings type="number" min="1" value="2"></label></div><button class="primary" data-add-meal type="button">${esc(tr('Add to plan'))}</button></section>`;
        view.querySelector('[data-week-back]')?.addEventListener('click', () => { weekStart.setDate(weekStart.getDate() - 7); void load(); });
        view.querySelector('[data-week-forward]')?.addEventListener('click', () => { weekStart.setDate(weekStart.getDate() + 7); void load(); });
        view.querySelector('[data-week-today]')?.addEventListener('click', () => { weekStart = startOfWeek(new Date()); void load(); });
		bindRecipeSearchPicker(view);
		view.querySelector('[data-clear-week]')?.addEventListener('click', async () => {
			if (!window.confirm(tr('Clear all meals from this week?')))
				return;
			await working(() => Promise.all(meals.map(meal => request(`/planner/${meal.id}`, { method: 'DELETE' }))));
			await load();
		});
		view.querySelector('[data-generate-plan]')?.addEventListener('click', async () => {
			const instruction = view.querySelector('[data-planner-instruction]')?.value || '';
			await working(() => request('/planner', { method: 'POST', json: { plan: { from: dateIso(days[0]), to: dateIso(days[6]), instruction } } }));
			showNotice(tr('Meal plan generated'));
			await load();
		});
        view.querySelectorAll('[data-select-date]').forEach(button => button.addEventListener('click', () => { const input = view.querySelector('[data-meal-date]'); if (input)
            input.value = button.dataset.selectDate || ''; }));
        view.querySelectorAll('[data-delete-meal]').forEach(button => button.addEventListener('click', async () => {
            if (!window.confirm(tr('Delete this meal?')))
                return;
            await working(() => request(`/planner/${asNumber(button.dataset.deleteMeal)}`, { method: 'DELETE' }));
            await load();
        }));
        view.querySelector('[data-add-meal]')?.addEventListener('click', async () => {
            const date = view.querySelector('[data-meal-date]')?.value || '';
            const recipeId = asNumber(view.querySelector('[data-meal-recipe]')?.value);
            const slot = view.querySelector('[data-meal-slot]')?.value || 'dinner';
            const servings = asNumber(view.querySelector('[data-meal-servings]')?.value, 1);
            if (!date || !recipeId) {
                showNotice(tr('Choose a date and recipe'), 'error');
                return;
            }
            await working(() => request('/planner', { method: 'POST', json: { meal: { date, recipeId, slot, servings } } }));
            showNotice(tr('Meal added'));
            await load();
        });
    };
    await load();
}
async function renderShopping(view) {
    let lists = [];
    let recipes = [];
    let selected = null;
    const load = async () => {
        const [listResponse, recipeResponse] = await working(() => Promise.all([
            request('/shopping'), request('/recipes?sort=title&direction=ASC'),
        ]));
        lists = listResponse.lists;
        recipes = recipeResponse.recipes;
        if (selected)
            selected = (await request(`/shopping/${selected.id}`)).list;
        else if (lists[0])
            selected = (await request(`/shopping/${lists[0].id}`)).list;
        paint();
    };
    const open = async (id) => { selected = (await working(() => request(`/shopping/${id}`))).list; paint(); };
    const paint = () => {
        view.innerHTML = `<div class="shopping-layout"><aside class="panel list-sidebar"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Saved'))}</p><h2>${esc(tr('Shopping lists'))}</h2></div></div>${lists.map(list => `<button class="${selected?.id === list.id ? 'active' : ''}" data-open-list="${list.id}" type="button"><span><strong>${esc(list.name)}</strong><small>${esc(new Date(list.updatedAt * 1000).toLocaleDateString())}</small></span><b>&rsaquo;</b></button>`).join('') || `<p>${esc(tr('No lists yet'))}</p>`}</aside>
		<main class="view-stack"><section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Generate'))}</p><h2>${esc(tr('From recipes'))}</h2></div></div><label>${esc(tr('List name'))}<input data-list-name value="${attr(tr('Weekly shopping'))}"></label><p class="recipe-selector-heading">${esc(tr('Servings per recipe'))}</p><div class="recipe-selector">${recipes.map(recipe => `<label><input data-list-recipe="${recipe.id}" type="checkbox"><span>${esc(recipe.title)}</span><input data-list-servings="${recipe.id}" type="number" min="1" value="${recipe.servings || 1}" aria-label="${attr(tr('Servings'))}"></label>`).join('')}</div><button class="primary" data-create-list type="button">${esc(tr('Generate shopping list'))}</button></section>
		${selected ? `<section class="panel shopping-sheet"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Active list'))}</p><h2>${esc(selected.name)}</h2></div><button class="danger ghost" data-delete-list type="button">${esc(tr('Delete'))}</button></div><div class="add-item"><input data-new-item placeholder="${attr(tr('Add an item...'))}"><button class="secondary" data-add-item type="button">+</button></div><div class="shopping-items">${(selected.items || []).map(item => `<label class="${item.checked ? 'checked' : ''}"><input data-toggle-item="${item.id}" type="checkbox"${item.checked ? ' checked' : ''}><span><strong>${esc(item.quantity)} ${esc(displayUnit(item.unit))}</strong> ${esc(item.name)}<small>${esc([item.category, item.notes].filter(Boolean).join(' - '))}</small></span></label>`).join('')}</div></section>` : `<section class="panel empty-state"><h2>${esc(tr('Select or create a list'))}</h2><p>${esc(tr('Quantities with compatible units are summed automatically.'))}</p></section>`}</main></div>`;
        view.querySelectorAll('[data-open-list]').forEach(button => button.addEventListener('click', () => { void open(asNumber(button.dataset.openList)); }));
        view.querySelector('[data-create-list]')?.addEventListener('click', async () => {
            const name = view.querySelector('[data-list-name]')?.value.trim() || tr('Shopping list');
            const selections = [...view.querySelectorAll('[data-list-recipe]:checked')].map(box => ({ recipeId: asNumber(box.dataset.listRecipe), servings: asNumber(view.querySelector(`[data-list-servings="${box.dataset.listRecipe}"]`)?.value, 1) }));
            selected = (await working(() => request('/shopping', { method: 'POST', json: { name, recipes: selections } }))).list;
            showNotice(tr('Shopping list created'));
            await load();
        });
        view.querySelectorAll('[data-toggle-item]').forEach(box => box.addEventListener('change', async () => {
            if (!selected)
                return;
            await working(() => request(`/shopping/${selected.id}/items/${asNumber(box.dataset.toggleItem)}`, { method: 'PUT', json: { item: { checked: box.checked } } }));
            await open(selected.id);
        }));
        const addItem = async () => {
            if (!selected)
                return;
            const input = view.querySelector('[data-new-item]');
            if (!input?.value.trim())
                return;
            await working(() => request(`/shopping/${selected.id}/items`, { method: 'POST', json: { item: { name: input.value.trim() } } }));
            await open(selected.id);
        };
        view.querySelector('[data-add-item]')?.addEventListener('click', () => { void addItem(); });
        view.querySelector('[data-new-item]')?.addEventListener('keydown', event => { if (event.key === 'Enter') {
            event.preventDefault();
            void addItem();
        } });
        view.querySelector('[data-delete-list]')?.addEventListener('click', async () => {
            if (!selected || !window.confirm(tr('Delete this shopping list?')))
                return;
            await working(() => request(`/shopping/${selected.id}`, { method: 'DELETE' }));
            selected = null;
            await load();
        });
    };
    await load();
}
async function renderSettings(view) {
    const settings = (await working(() => request('/settings'))).settings;
	view.innerHTML = `<div class="settings-layout"><main class="view-stack"><div class="editor-actions settings-actions"><div><button class="primary" data-save-settings type="button">${esc(tr('Save settings'))}</button></div></div>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('General'))}</p><h2>${esc(tr('Library preferences'))}</h2></div></div><div class="form-grid"><label>${esc(tr('Default language'))}<input data-setting="language" value="${attr(settings.language)}" placeholder="auto / it / en"></label><label>${esc(tr('Measurement system'))}<select data-setting="measurementSystem"><option value="metric"${settings.measurementSystem === 'metric' ? ' selected' : ''}>${esc(tr('Metric'))}</option><option value="imperial"${settings.measurementSystem === 'imperial' ? ' selected' : ''}>${esc(tr('Imperial'))}</option></select></label><label class="span-2">${esc(tr('Attachments folder in Nextcloud Files'))}<input data-setting="attachmentsFolder" value="${attr(settings.attachmentsFolder)}"></label><label>${esc(tr('Maximum URL import size'))}<input data-setting="maxImportBytes" type="number" min="100000" max="20000000" value="${settings.maxImportBytes}"><small>${esc(tr('bytes'))}</small></label></div></section>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Optional intelligence'))}</p><h2>${esc(tr('AI provider'))}</h2></div><span class="status-pill ${settings.aiProvider !== 'disabled' ? 'enabled' : ''}">${esc(settings.aiProvider === 'disabled' ? tr('Disabled') : tr('Enabled'))}</span></div><div class="form-grid">
			<label>${esc(tr('Provider'))}<select data-setting="aiProvider"><option value="disabled">${esc(tr('Disabled'))}</option><option value="nextcloud">Nextcloud Assistant</option><option value="openai">OpenAI</option><option value="openrouter">OpenRouter</option><option value="ollama">Ollama</option><option value="localai">LocalAI</option><option value="mistral">Mistral</option><option value="anthropic">Anthropic</option><option value="gemini">Gemini</option><option value="custom">${esc(tr('Custom OpenAI-compatible'))}</option></select></label>
			<label>${esc(tr('Model'))}<input data-setting="aiModel" value="${attr(settings.aiModel)}"></label><label class="span-2">${esc(tr('Endpoint'))}<input data-setting="aiEndpoint" type="url" value="${attr(settings.aiEndpoint)}" placeholder="https://..."><small>${esc(tr('Leave empty for the provider default. Ollama and LocalAI use local defaults.'))}</small></label>
			<label>${esc(tr('API key'))}<input data-setting="aiApiKey" type="password" autocomplete="new-password" placeholder="${attr(settings.hasAiApiKey ? tr('Key already stored; leave blank to keep it') : tr('API key'))}"></label><label class="check-inline secret-clear"><input data-setting-clear="aiApiKey" type="checkbox"> ${esc(tr('Remove stored key'))}</label><label>${esc(tr('Temperature'))}<input data-setting="aiTemperature" type="number" min="0" max="2" step="0.1" value="${settings.aiTemperature}"></label><label>${esc(tr('Timeout'))}<input data-setting="aiTimeout" type="number" min="10" max="300" value="${settings.aiTimeout}"><small>${esc(tr('seconds'))}</small></label>
		</div><div class="info-box"><b>Nextcloud Assistant</b><p>${esc(tr('Uses the language-model provider already configured by the instance administrator and requires no duplicate API key.'))}</p></div></section>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Cover image'))}</p><h2>${esc(tr('Image search provider'))}</h2></div></div><div class="form-grid"><label>${esc(tr('Image search provider'))}<select data-setting="coverImageProvider"><option value="google">Google</option><option value="pexels">Pexels</option><option value="unsplash">Unsplash</option></select></label><label class="span-2">${esc(tr('Programmable Search engine ID'))}<input data-setting="googleImageSearchEngineId" value="${attr(settings.googleImageSearchEngineId)}"></label><label>${esc(tr('Google API key'))}<input data-setting="googleImageSearchApiKey" type="password" autocomplete="new-password" placeholder="${attr(settings.hasGoogleImageSearchApiKey ? tr('Key already stored; leave blank to keep it') : tr('Google API key'))}"></label><label class="check-inline secret-clear"><input data-setting-clear="googleImageSearchApiKey" type="checkbox"> ${esc(tr('Remove stored Google key'))}</label><label>${esc(tr('Pexels API key'))}<input data-setting="pexelsApiKey" type="password" autocomplete="new-password" placeholder="${attr(settings.hasPexelsApiKey ? tr('Key already stored; leave blank to keep it') : tr('Pexels API key'))}"></label><label class="check-inline secret-clear"><input data-setting-clear="pexelsApiKey" type="checkbox"> ${esc(tr('Remove stored Pexels key'))}</label><label>${esc(tr('Unsplash access key'))}<input data-setting="unsplashAccessKey" type="password" autocomplete="new-password" placeholder="${attr(settings.hasUnsplashAccessKey ? tr('Key already stored; leave blank to keep it') : tr('Unsplash access key'))}"></label><label class="check-inline secret-clear"><input data-setting-clear="unsplashAccessKey" type="checkbox"> ${esc(tr('Remove stored Unsplash key'))}</label></div><p class="section-help">${esc(tr('Uses the selected provider with the recipe title and saves the first suitable result as the cover.'))}</p></section>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('AI meal planner'))}</p><h2>${esc(tr('Planner prompt'))}</h2></div></div><div class="form-grid"><label class="span-2">${esc(tr('Planner prompt'))}<textarea data-setting="aiPlannerPrompt" rows="3">${esc(settings.aiPlannerPrompt)}</textarea></label><label class="span-2">${esc(tr('Dietary preferences and constraints'))}<textarea data-setting="plannerPreferences" rows="3" placeholder="${attr(tr('Example: vegetarian, no peanuts, low salt'))}">${esc(settings.plannerPreferences)}</textarea></label><label>${esc(tr('Maximum cooking time per meal'))}<input data-setting="plannerCookingTime" type="number" min="5" max="600" value="${settings.plannerCookingTime}"><small>${esc(tr('minutes'))}</small></label><label>${esc(tr('Default servings'))}<input data-setting="plannerServings" type="number" min="1" max="30" value="${settings.plannerServings}"></label></div></section>
		<section class="panel form-section"><div class="section-heading"><div><p class="eyebrow">${esc(tr('Documents'))}</p><h2>${esc(tr('OCR and PDF extraction'))}</h2></div><span class="status-pill ${settings.ocrProvider !== 'disabled' ? 'enabled' : ''}">${esc(settings.ocrProvider === 'disabled' ? tr('Disabled') : tr('Enabled'))}</span></div><div class="form-grid"><label>${esc(tr('Extractor'))}<select data-setting="ocrProvider"><option value="disabled">${esc(tr('Disabled'))}</option><option value="local">${esc(tr('Local Tesseract / pdftotext'))}</option><option value="external">${esc(tr('External HTTP service'))}</option></select></label><label>${esc(tr('OCR languages'))}<input data-setting="ocrLanguage" value="${attr(settings.ocrLanguage)}"></label><label>${esc(tr('Tesseract executable'))}<input data-setting="tesseractPath" value="${attr(settings.tesseractPath)}"></label><label>${esc(tr('pdftotext executable'))}<input data-setting="pdfToTextPath" value="${attr(settings.pdfToTextPath)}"></label><label class="span-2">${esc(tr('External endpoint'))}<input data-setting="ocrEndpoint" type="url" value="${attr(settings.ocrEndpoint)}"></label><label>${esc(tr('API key'))}<input data-setting="ocrApiKey" type="password" autocomplete="new-password" placeholder="${attr(settings.hasOcrApiKey ? tr('Key already stored; leave blank to keep it') : tr('API key'))}"></label><label class="check-inline secret-clear"><input data-setting-clear="ocrApiKey" type="checkbox"> ${esc(tr('Remove stored key'))}</label></div></section>
		<section class="panel form-section danger-zone"><p class="eyebrow">${esc(tr('Danger zone'))}</p><h2>${esc(tr('Cover image'))}</h2><p>${esc(tr('Uses the selected provider with the recipe title and saves the first suitable result as the cover.'))}</p><button class="danger secondary full" data-fill-missing-covers type="button">${esc(tr('Find covers for all missing recipes'))}</button></section>
		</main>
		<aside class="panel privacy-card"><p class="eyebrow">${esc(tr('Privacy'))}</p><h2>${esc(tr('You control every processor'))}</h2><p>${esc(tr('Deterministic URL and text parsing stays in your Nextcloud. Content is sent to an AI or external OCR service only when you enable and invoke it.'))}</p><ul><li>${esc(tr('API keys are encrypted with the Nextcloud server secret.'))}</li><li>${esc(tr('Imported data is always shown as an editable preview.'))}</li><li>${esc(tr('URL imports reject private and reserved network addresses.'))}</li></ul></aside></div>`;
    const aiProvider = view.querySelector('[data-setting="aiProvider"]');
    if (aiProvider)
        aiProvider.value = settings.aiProvider;
    const ocrProvider = view.querySelector('[data-setting="ocrProvider"]');
    if (ocrProvider)
        ocrProvider.value = settings.ocrProvider;
    const coverImageProvider = view.querySelector('[data-setting="coverImageProvider"]');
    if (coverImageProvider)
        coverImageProvider.value = settings.coverImageProvider || 'google';
    view.querySelector('[data-save-settings]')?.addEventListener('click', async () => {
        const payload = {};
        view.querySelectorAll('[data-setting]').forEach(field => {
            const key = field.dataset.setting || '';
            if (!key)
                return;
            payload[key] = field instanceof HTMLInputElement && field.type === 'number' ? asNumber(field.value) : field.value;
        });
        payload.clearAiApiKey = view.querySelector('[data-setting-clear="aiApiKey"]')?.checked || false;
        payload.clearOcrApiKey = view.querySelector('[data-setting-clear="ocrApiKey"]')?.checked || false;
        payload.clearGoogleImageSearchApiKey = view.querySelector('[data-setting-clear="googleImageSearchApiKey"]')?.checked || false;
        payload.clearPexelsApiKey = view.querySelector('[data-setting-clear="pexelsApiKey"]')?.checked || false;
        payload.clearUnsplashAccessKey = view.querySelector('[data-setting-clear="unsplashAccessKey"]')?.checked || false;
        await working(() => request('/settings', { method: 'PUT', json: { settings: payload } }));
        showNotice(tr('Settings saved'));
        await renderSettings(view);
    });
    view.querySelector('[data-fill-missing-covers]')?.addEventListener('click', async () => {
        if (!window.confirm(tr('This will search for and download covers for all your recipes without an image. Continue?')))
            return;
        let remaining = 1;
        let succeeded = 0;
        let failed = 0;
        while (remaining > 0) {
            const response = await working(() => request('/settings/fill-missing-covers', { method: 'POST', json: {} }));
            const result = response.result || {};
            succeeded += asNumber(result.succeeded);
            failed += asNumber(result.failed);
            remaining = asNumber(result.remaining);
        }
        showNotice(`${tr('Cover search completed')}: ${succeeded} ✓${failed ? ` · ${failed} ${tr('failed')}` : ''}`);
    });
}
async function renderPublic(rootNode) {
    const token = rootNode.dataset.token || '';
    const load = async (password = '') => {
        rootNode.innerHTML = `<main class="public-page"><div class="public-brand"><img src="${attr(appIconUrl)}" alt=""><strong>SmartCook</strong></div><p>${esc(tr('Loading...'))}</p></main>`;
        try {
            const payload = await request(`/public/${encodeURIComponent(token)}/data`, { method: 'POST', json: { password } });
            const recipe = payload.recipe;
            const image = recipeImageUrl(recipe.imagePath);
            rootNode.innerHTML = `<main class="public-page"><div class="public-brand"><img src="${attr(appIconUrl)}" alt=""><strong>SmartCook</strong></div><article class="public-recipe">${image ? `<img class="hero" src="${attr(image)}" alt="">` : ''}<p class="eyebrow">${esc(recipe.cuisine)}${recipe.course ? ` - ${esc(recipe.course)}` : ''}</p><h1>${esc(recipe.title)}</h1><p class="lead">${esc(recipe.description)}</p><div class="metrics"><div><strong>${recipe.servings}</strong><span>${esc(tr('Servings'))}</span></div><div><strong>${recipe.prepTime} min</strong><span>${esc(tr('Preparation'))}</span></div><div><strong>${recipe.cookTime} min</strong><span>${esc(tr('Cooking'))}</span></div><div><strong>${recipe.totalTime} min</strong><span>${esc(tr('Total'))}</span></div></div><div class="public-grid"><section><h2>${esc(tr('Ingredients'))}</h2><ul>${recipe.ingredients.map(item => `<li><b>${esc(item.quantity)} ${esc(displayUnit(item.unit))}</b> ${esc(item.name)} <small>${esc(item.notes)}</small></li>`).join('')}</ul></section><section><h2>${esc(tr('Method'))}</h2><ol>${recipe.steps.map(step => `<li>${esc(step.text)}</li>`).join('')}</ol></section></div></article></main>`;
        }
        catch (error) {
            const message = error instanceof Error ? error.message : tr('Could not load the shared recipe');
            rootNode.innerHTML = `<main class="public-page"><div class="public-brand"><img src="${attr(appIconUrl)}" alt=""><strong>SmartCook</strong></div><form class="password-card"><h1>${esc(tr('Shared recipe'))}</h1><p>${esc(message)}</p><label>${esc(tr('Password'))}<input data-public-password type="password" autocomplete="current-password"></label><button class="primary" type="submit">${esc(tr('Open recipe'))}</button></form></main>`;
            rootNode.querySelector('form')?.addEventListener('submit', event => { event.preventDefault(); void load(rootNode.querySelector('[data-public-password]')?.value || ''); });
        }
    };
    await load();
}
async function route() {
    if (!root)
        return;
    const current = parseRoute();
    const view = renderShell(current.section, current.id);
    try {
        switch (current.section) {
            case 'dashboard':
                await renderDashboard(view);
                break;
            case 'recipes':
                await renderRecipes(view, current.params);
                break;
            case 'recipe':
                await renderRecipe(view, current.id);
                break;
            case 'editor':
                await renderEditor(view, current.id);
                break;
            case 'import':
                await renderImport(view);
                break;
            case 'planner':
                await renderPlanner(view);
                break;
            case 'shopping':
                await renderShopping(view);
                break;
            case 'settings':
                await renderSettings(view);
                break;
            default: await renderDashboard(view);
        }
    }
    catch (error) {
        if (!(error instanceof ApiError))
            console.error(error);
    }
}
if (publicRoot) {
    void renderPublic(publicRoot);
}
else if (root) {
    window.addEventListener('hashchange', () => { void route(); });
    void route();
}
