import Prism from 'prismjs'
import 'prismjs/components/prism-markup'
import 'prismjs/components/prism-markup-templating'
import 'prismjs/components/prism-clike'
import 'prismjs/components/prism-css'
import 'prismjs/components/prism-php'
import 'prismjs/components/prism-javascript'
import 'prismjs/plugins/line-highlight/prism-line-highlight'
import 'prismjs/plugins/line-numbers/prism-line-numbers'
import 'prismjs/plugins/toolbar/prism-toolbar'
import 'prismjs/plugins/show-language/prism-show-language'
import 'prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard'
import 'prismjs/plugins/inline-color/prism-inline-color'
import 'prismjs/plugins/previewers/prism-previewers'
import 'prismjs/plugins/autolinker/prism-autolinker'

document.addEventListener('readystatechange', () => {
	if ('complete' === document.readyState) {
		Prism.highlightAll()
	}
})

window.CODE_SNIPPETS_PRISM = Prism
