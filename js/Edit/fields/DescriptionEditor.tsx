import domReady from '@wordpress/dom-ready'
import React, { useCallback, useEffect } from 'react'
import { __ } from '@wordpress/i18n'
import { SnippetInputProps } from '../../types/SnippetInputProps'

export const EDITOR_ID = 'snippet_description'

const DEFAULT_ROWS = 5

const TOOLBAR_BUTTONS = [
	'bold',
	'italic',
	'underline',
	'strikethrough',
	'blockquote',
	'bullist',
	'numlist',
	'alignleft',
	'aligncenter',
	'alignright',
	'link',
	'wp_adv',
	'code_snippets'
].join(' ')

const TOOLBAR_BUTTONS_2 = [
	'formatselect',
	'forecolor',
	'pastetext',
	'removeformat',
	'charmap',
	'outdent',
	'indent',
	'undo',
	'redo',
	'spellchecker'
].join(' ')

const initializeEditor = (onChange: (content: string) => void) => {
	window.wp.editor?.initialize(EDITOR_ID, {
		mediaButtons: window.CODE_SNIPPETS_EDIT?.descEditorOptions.mediaButtons,
		quicktags: true,
		tinymce: {
			toolbar: [TOOLBAR_BUTTONS, TOOLBAR_BUTTONS_2],
			setup: editor => {
				editor.on('change', () => onChange(editor.getContent()))
			}
		}
	})
}

export const DescriptionEditor: React.FC<SnippetInputProps> = ({ snippet, setSnippet }) => {
	const onChange = useCallback(
		(desc: string) => setSnippet(previous => ({ ...previous, desc })),
		[setSnippet]
	)

	useEffect(() => {
		domReady(() => initializeEditor(onChange))
	}, [onChange])

	return window.CODE_SNIPPETS_EDIT?.enableDescription ?
		<div className="snippet-description-container">
			<h2>
				<label htmlFor={EDITOR_ID}>
					{__('Description', 'code-snippets')}
				</label>
			</h2>

			<textarea
				id={EDITOR_ID}
				className="wp-editor-area"
				onChange={event => onChange(event.target.value)}
				autoComplete="off"
				rows={window.CODE_SNIPPETS_EDIT?.descEditorOptions.rows ?? DEFAULT_ROWS}
				cols={40}
			>{snippet.desc}</textarea>
		</div> :
		null
}
