import { reactive } from 'vue'

export const appState = reactive({
	busy: 0,
	message: '',
	error: '',
})

export const runBusy = async <T>(operation: () => Promise<T>): Promise<T> => {
	appState.busy++
	appState.error = ''
	try {
		return await operation()
	} catch (error: unknown) {
		const candidate = error as { response?: { data?: { error?: string } }; message?: string }
		appState.error = candidate.response?.data?.error || candidate.message || 'Unexpected error'
		throw error
	} finally {
		appState.busy--
	}
}

export const notify = (message: string) => {
	appState.message = message
	window.setTimeout(() => {
		if (appState.message === message) appState.message = ''
	}, 3500)
}
