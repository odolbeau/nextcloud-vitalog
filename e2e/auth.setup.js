import { expect, test as setup } from '@playwright/test'
import { ADMIN, ensureBob } from './helpers.js'

setup('log in as admin and prepare accounts', async ({ page }) => {
	await ensureBob()
	await page.goto('/index.php/login')
	await page.locator('input[name="user"]').fill(ADMIN.user)
	await page.locator('input[name="password"]').fill(ADMIN.password)
	await page.getByRole('button', { name: /^log in$/i }).click()
	await expect(page).not.toHaveURL(/login/)
	await page.context().storageState({ path: 'e2e/.auth/admin.json' })
})
