import React from 'react'
import { Spinner } from '@wordpress/components'
import { isRTL } from '@wordpress/i18n'
import { useSnippetForm } from '../../hooks/useSnippetForm'
import { isNetworkAdmin } from '../../utils/general'
import { getSnippetType } from '../../utils/snippets'
import { Notices } from '../SnippetForm/page/Notices'
import { ShortcodeInfo } from './actions/ShortcodeInfo'
import { MultisiteSharingSettings } from './controls/MultisiteSharingSettings'
import { ExportButtons } from './actions/ExportButtons'
import { SubmitButton } from './actions/SubmitButtons'
import { ActivationSwitch } from './controls/ActivationSwitch'
import { DeleteButton } from './actions/DeleteButton'
import { PriorityInput } from './controls/PriorityInput'
import { RTLControl } from './controls/RTLControl'
import { TagsInput } from './controls/TagsInput'

export const EditorSidebar = () => {
	const { snippet, isWorking } = useSnippetForm()

	return (
		<div className="snippet-editor-sidebar">
			<div className="snippet-editor-sidebar-wrapper">
				<div className="box">
					{snippet.id ? <ActivationSwitch /> : null}

					{isNetworkAdmin() ? <MultisiteSharingSettings /> : null}

					{isRTL() ? <RTLControl /> : null}

					<PriorityInput />

					{window.CODE_SNIPPETS_EDIT?.tagOptions.enabled ? <TagsInput /> : null}

					{snippet.id ?
						<div className="row-actions visible">
							<ExportButtons />
							<DeleteButton />
						</div> : null}
				</div>

				<p className="submit">
					<SubmitButton />
					{isWorking ? <Spinner /> : ''}
				</p>

				<Notices />

				{'html' === getSnippetType(snippet) ? <ShortcodeInfo /> : null}
			</div>
		</div>
	)
}
