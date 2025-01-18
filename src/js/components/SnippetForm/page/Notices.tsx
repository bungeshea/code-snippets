import classnames from 'classnames'
import React, { useEffect } from 'react'
import { __, sprintf } from '@wordpress/i18n'
import { useSnippetForm } from '../../../hooks/useSnippetForm'
import type { MouseEventHandler, ReactNode} from 'react'

interface DismissibleNoticeProps {
	classNames?: classnames.Argument
	onRemove: MouseEventHandler<HTMLButtonElement>
	children?: ReactNode
	autoHide?: boolean
}

const DismissibleNotice: React.FC<DismissibleNoticeProps> = ({ classNames, onRemove, children, autoHide = true }) => {
	useEffect(() => {
		if (autoHide) {
			const timer = setTimeout(() => {
				onRemove({} as React.MouseEvent<HTMLButtonElement>)
			}, 5000)
			
			return () => clearTimeout(timer)
		}
		return () => {} // eslint-disable-line
	}, [autoHide, onRemove])

	return (
		<div id="message" className={classnames('cs-sticky-notice notice fade is-dismissible', classNames)}>
			<>{children}</>

			<button type="button" className="notice-dismiss" onClick={event => {
				event.preventDefault()
				onRemove(event)
			}}>
				<span className="screen-reader-text">{__('Dismiss notice.', 'code-snippets')}</span>
			</button>
		</div>
	)
}

export const Notices: React.FC = () => {
	const { currentNotice, setCurrentNotice, snippet, setSnippet } = useSnippetForm()

	return <>
		{currentNotice ?
			<DismissibleNotice classNames={currentNotice[0]} onRemove={() => setCurrentNotice(undefined)}>
				<p>{currentNotice[1]}</p>
			</DismissibleNotice> :
			null}

		{snippet.code_error ?
			<DismissibleNotice
				classNames="error"
				onRemove={() => setSnippet(previous => ({ ...previous, code_error: null }))}
				autoHide={false}
			>
				<p>
					<strong>{sprintf(
						// translators: %d: line number.
						__('Snippet automatically deactivated due to an error on line %d:', 'code-snippets'),
						snippet.code_error[1]
					)}</strong>

					<blockquote>{snippet.code_error[0]}</blockquote>
				</p>
			</DismissibleNotice> :
			null}
	</>
}
