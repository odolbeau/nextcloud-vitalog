import { defineConfig, devices } from '@playwright/test'

const baseURL = process.env.NC_URL ?? 'http://localhost:8480'

export default defineConfig({
	testDir: 'e2e',
	// les tests partagent l'état d'une seule instance Nextcloud : on les exécute l'un après l'autre
	workers: 1,
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{ name: 'setup', testMatch: /auth\.setup\.js/ },
		{
			name: 'api',
			testMatch: /api\/.*\.spec\.js/,
		},
		{
			name: 'desktop',
			testMatch: /ui\/.*\.spec\.js/,
			testIgnore: /mobile/,
			use: { ...devices['Desktop Chrome'], storageState: 'e2e/.auth/admin.json' },
			dependencies: ['setup'],
		},
		{
			name: 'mobile',
			testMatch: /ui\/mobile\.spec\.js/,
			use: { ...devices['Pixel 7'], storageState: 'e2e/.auth/admin.json' },
			dependencies: ['setup'],
		},
	],
})
