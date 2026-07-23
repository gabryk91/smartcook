export interface NamedEntity {
	id?: number
	name: string
	color?: string | null
	parentId?: number | null
}

export interface Ingredient {
	id?: number
	ingredientId?: number | null
	name: string
	originalText?: string | null
	quantity?: string | null
	amount?: number | null
	unit?: string | null
	notes?: string | null
	optional?: boolean
	group?: string | null
	category?: string | null
	allergens?: string[]
	substitutes?: string[]
	sortOrder?: number
}

export interface RecipeStep {
	id?: number
	text: string
	timerSeconds?: number | null
	timerValue?: number | null
	timerUnit?: 'seconds' | 'minutes' | 'hours' | null
	temperature?: number | null
	temperatureUnit?: string | null
	notes?: string | null
	sortOrder?: number
}

export interface RecipeMedia {
	id?: number
	kind: 'image' | 'video' | 'pdf' | 'attachment'
	path: string
	mime?: string | null
	altText?: string | null
	fileSize?: number | null
	createdAt?: number
	sortOrder?: number
}

export interface Recipe {
	id?: number
	uuid?: string
	ownerId?: string
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
	folderPath?: string | null
	cookCount?: number
	lastCooked?: number | null
	revision?: number
	createdAt?: number
	updatedAt?: number
	ingredients: Ingredient[]
	steps: RecipeStep[]
	tools: NamedEntity[]
	tags: NamedEntity[]
	categories: NamedEntity[]
	media: RecipeMedia[]
}

export interface ImportPreview {
	recipe: Recipe
	strategy: string
	warnings: string[]
	duplicates: Array<{ recipe: Recipe; score: number; reasons: string[] }>
}

export interface Meal {
	id: number
	recipeId: number
	recipeTitle: string
	date: string
	slot: string
	servings: number
	notes?: string | null
}

export interface ShoppingItem {
	id: number
	listId: number
	name: string
	quantity?: string | null
	amount?: number | null
	unit?: string | null
	category?: string | null
	checked: boolean
	notes?: string | null
}

export interface ShoppingList {
	id: number
	name: string
	status: string
	items?: ShoppingItem[]
	createdAt: number
	updatedAt: number
}

export const emptyRecipe = (): Recipe => ({
	title: '',
	subtitle: null,
	description: null,
	language: document.documentElement.lang || 'en',
	author: null,
	sourceName: null,
	sourceUrl: null,
	license: null,
	status: 'draft',
	visibility: 'private',
	favorite: false,
	servings: 4,
	yieldText: null,
	prepTime: 0,
	restTime: 0,
	cookTime: 0,
	totalTime: 0,
	difficulty: null,
	costCents: null,
	currency: 'EUR',
	cuisine: null,
	course: null,
	mealType: null,
	cookingMethod: null,
	season: null,
	origin: null,
	calories: null,
	nutrition: {},
	notes: null,
	imagePath: null,
	ingredients: [{ name: '', quantity: null, unit: null }],
	steps: [{ text: '' }],
	tools: [],
	tags: [],
	categories: [],
	media: [],
})
