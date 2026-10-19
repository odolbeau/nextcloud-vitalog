import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	main: 'src/main.js',
}, {
	config: {
		css: {
			preprocessorOptions: {
				scss: { api: 'modern-compiler' },
			},
		},
	},
})
