import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		// pas de JSDoc dans ce projet : les commentaires expliquent le « pourquoi », les types viennent de l'usage
		rules: {
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/require-param-description': 'off',
			'jsdoc/require-returns': 'off',
			'jsdoc/require-returns-type': 'off',
			'jsdoc/require-returns-description': 'off',
		},
	},
]
