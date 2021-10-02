module.exports = {
	parser: '@typescript-eslint/parser',
	plugins: [
		'@typescript-eslint',
		`import`
	],
	parserOptions: {
		ecmaVersion: 9,
		sourceType: 'module'
	},
	env: {
		browser: true,
		node: true,
		es6: true
	},
	ignorePatterns: ['js/min/**/*'],
	extends: [
		'eslint:recommended',
		'plugin:@typescript-eslint/recommended',
		'plugin:import/recommended',
		'plugin:import/typescript'
	],
	settings: {
		'import/core-modules': ['tinymce', 'jquery', 'react']
	},
	rules: {
		'quotes': ['error', 'single'],
		'linebreak-style': ['error', 'unix'],
		'eqeqeq': ['error', 'always'],
		'indent': ['error', 'tab', {SwitchCase: 1}],
		'max-len': ['warn', 140],
		'array-bracket-newline': ['error', 'consistent'],
		'function-call-argument-newline': ['error', 'consistent'],
		'comma-dangle': ['error', 'only-multiline'],
		'no-tabs': ['error', {allowIndentationTabs: true}],
		'one-var': ['error', 'never'],
		'arrow-parens': ['error', 'as-needed'],
		'quote-props': ['error', 'consistent-as-needed'],
		'yoda': ['error', 'always'],
		'multiline-ternary': ['error', 'always-multiline'],
		'dot-notation': 'error',
		'operator-linebreak': ['error', 'after'],
		'no-extra-parens': ['warn', 'all'],
		'object-property-newline': ['error', {allowAllPropertiesOnSameLine: true}],
		'prefer-template': 'error',
		'no-magic-numbers': ['error', {ignore: [-1, 0, 1]}],
		'no-plusplus': ['error', {allowForLoopAfterthoughts: true}],
		'dot-location': ['error', 'property'],
		'capitalized-comments': ['error', 'always', {ignoreInlineComments: true, ignoreConsecutiveComments: true}],
		'no-invalid-this': 'error',
		'max-lines-per-function': ['error', {skipBlankLines: true, skipComments: true}],
		'prefer-named-capture-group': 'error',
		'func-style': ['error', 'expression'],
		'no-mixed-spaces-and-tabs': ['error', 'smart-tabs'],

		'no-ternary': 'off',
		'no-nested-ternary': 'off',
		'padded-blocks': 'off',
		'implicit-arrow-linebreak': 'off',

		// potentially revisit these later
		'curly': ['error', 'multi-line'],
		'no-alert': 'off',
		'camelcase': 'off',
		'sort-keys': 'off',
		'max-params': 'off',
		'sort-imports': 'off',
		'require-unicode-regexp': 'off',
		'array-element-newline': 'off',
		'space-before-function-paren': 'off'
	},
};
