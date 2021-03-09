import tagger from '@jcubic/tagger';

/* global code_snippets_all_tags */

(function () {
	const tags_field = document.getElementById('snippet_tags');

	if (tags_field) {
		tagger(tags_field, {
			allow_spaces: true,
			allow_duplicates: false,
			completion: {list: code_snippets_all_tags},
			link: name => false
		});
	}
})();