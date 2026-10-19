import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	main: 'src/main.js',
}, {
	// le CSS des composants est découpé en chunks que Nextcloud ne charge pas : on l'injecte depuis le JS
	inlineCSS: { relativeCSSInjection: true },
	config: {
		css: {
			preprocessorOptions: {
				scss: { api: 'modern-compiler' },
			},
		},
	},
})
