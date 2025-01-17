import type Prism from 'prismjs'
import type tinymce from 'tinymce'
import type { Snippet } from './Snippet'
import type { CodeEditorInstance, EditorOption, WordPressCodeEditor } from './WordPressCodeEditor'
import type { WordPressEditor } from './WordPressEditor'

declare global {
	interface Window {
		readonly wp: {
			readonly editor?: WordPressEditor
			readonly codeEditor: WordPressCodeEditor
		}
		readonly pagenow: string
		readonly ajaxurl: string
		readonly tinymce?: tinymce.EditorManager
		readonly wpActiveEditor?: string
		code_snippets_editor_preview?: CodeEditorInstance
		readonly code_snippets_editor_settings: EditorOption[]
		CODE_SNIPPETS_PRISM?: typeof Prism
		readonly CODE_SNIPPETS?: {
			isLicensed: boolean
			restAPI: {
				base: string
				snippets: string
				cloud: string
				nonce: string
				localToken: string
			}
			urls: {
				plugin: string
				manage: string
				addNew: string
				edit: string
				connectCloud: string
			}
		}
		readonly CODE_SNIPPETS_EDIT?: {
			snippet: Snippet
			pageTitleActions: Record<string, string>
			isPreview: boolean
			isLicensed: boolean
			enableDownloads: boolean
			scrollToNotices: boolean
			extraSaveButtons: boolean
			activateByDefault: boolean
			enableDescription: boolean
			editorTheme: string
			tagOptions: {
				enabled: boolean
				allowSpaces: boolean
				availableTags: string[]
			}
			descEditorOptions: {
				rows: number
				mediaButtons: boolean
			}
		}
	}
}
