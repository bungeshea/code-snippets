import { __, _x } from '@wordpress/i18n'
import classnames from 'classnames'
import React from 'react'
import { KEYBOARD_KEYS } from '../../../types/KeyboardShortcut'
import { isMacOS } from '../../../utils/general'
import type { KeyboardShortcut } from '../../../types/KeyboardShortcut'

const shortcuts: Record<string, KeyboardShortcut> = {
	saveChanges: {
		label: __('Save changes', 'code-snippets'),
		mod: 'Cmd',
		key: 'S'
	},
	selectAll: {
		label: __('Select all', 'code-snippets'),
		mod: 'Cmd',
		key: 'A'
	},
	beginSearch: {
		label: __('Begin searching', 'code-snippets'),
		mod: 'Cmd',
		key: 'F'
	},
	findNext: {
		label: __('Find next', 'code-snippets'),
		mod: 'Cmd',
		key: 'G'
	},
	findPrevious: {
		label: __('Find previous', 'code-snippets'),
		mod: ['Shift', 'Cmd'],
		key: 'G'
	},
	replace: {
		label: __('Replace', 'code-snippets'),
		mod: ['Shift', 'Cmd'],
		key: 'F'
	},
	replaceAll: {
		label: __('Replace all', 'code-snippets'),
		mod: ['Shift', 'Cmd', 'Option'],
		key: 'R'
	},
	search: {
		label: __('Persistent search', 'code-snippets'),
		mod: 'Alt',
		key: 'F'
	},
	toggleComment: {
		label: __('Toggle comment', 'code-snippets'),
		mod: 'Cmd',
		key: '/'
	},
	swapLineUp: {
		label: __('Swap line up', 'code-snippets'),
		mod: 'Option',
		key: 'Up'
	},
	swapLineDown: {
		label: __('Swap line down', 'code-snippets'),
		mod: 'Option',
		key: 'Down'
	},
	autoIndent: {
		label: __('Auto-indent current line or selection', 'code-snippets'),
		mod: 'Shift',
		key: 'Tab'
	}
}

const SEP = _x('-', 'keyboard shortcut separator', 'code-snippets')

export interface CodeEditorShortcutsProps {
	editorTheme: string
}

export const CodeEditorShortcuts: React.FC<CodeEditorShortcutsProps> = ({ editorTheme }) =>
	<div className="snippet-editor-help">
		<div className={`editor-help-tooltip cm-s-${editorTheme}`}>
			{_x('?', 'help tooltip', 'code-snippets')}
		</div>

		<div className={classnames('editor-help-text', { 'platform-mac': isMacOS() })}>
			<table>
				{Object.entries(shortcuts).map(([name, { label, mod, key }]) =>
					<tr key={name}>
						<td>{label}</td>
						<td>
							{(Array.isArray(mod) ? mod : [mod]).map(modifier =>
								<span key={modifier}>
									{'Ctrl' === modifier || 'Cmd' === modifier ?
										<>
											<kbd className="pc-key">{KEYBOARD_KEYS.Ctrl}</kbd>
											<kbd className="mac-key">{KEYBOARD_KEYS.Cmd}</kbd>
											{SEP}
										</> :
										'Option' === mod ?
											<span className="mac-key">
												<kbd className="mac-key">{KEYBOARD_KEYS.Option}</kbd>{SEP}
											</span> :
											<><kbd>{KEYBOARD_KEYS[modifier]}</kbd>{SEP}</>}
								</span>
							)}
							<kbd>{KEYBOARD_KEYS[key]}</kbd>
						</td>
					</tr>
				)}
			</table>
		</div>
	</div>
