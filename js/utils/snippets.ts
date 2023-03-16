import { Snippet, SnippetScope, SnippetType } from '../types/Snippet'
import { isNetworkAdmin } from './general'

export const createEmptySnippet = (): Snippet => ({
	id: 0,
	name: '',
	desc: '',
	code: '',
	tags: [],
	scope: 'global',
	modified: '',
	active: false,
	network: isNetworkAdmin(),
	shared_network: null,
	priority: 10
})

export const getSnippetType = (snippet: Snippet | SnippetScope): SnippetType => {
	const scope = 'string' === typeof snippet ? snippet : snippet.scope

	if (scope.endsWith('-css')) {
		return 'css'
	}

	if (scope.endsWith('-js')) {
		return 'js'
	}

	if (scope.endsWith('content')) {
		return 'html'
	}

	if ('condition' === scope) {
		return 'cond'
	}

	return 'php'
}

export const isProType = (type: SnippetType): boolean =>
	'css' === type || 'js' === type
