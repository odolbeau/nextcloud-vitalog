import { expect, test } from '@playwright/test'
import { client, inDays } from '../helpers.js'
import { dialog, items, setLanguage } from './pages.js'

let admin

test.beforeAll(async () => {
	await setLanguage('en')
	admin = await client()
})
test.afterAll(async () => {
	await admin.reset()
	await admin.dispose()
})
test.beforeEach(async () => {
	await admin.reset()
})

test('list, details and creation work on a phone', async ({ page }) => {
	await admin.create('appointments', { startsAt: inDays(3), reason: 'Bilan' })
	await page.goto('/index.php/apps/vitalog/')

	// pas de défilement horizontal
	await expect(items(page).first()).toBeVisible()
	expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)

	await items(page).first().click()
	await expect(page.getByRole('article')).toBeVisible()
	expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
})

test('the creation form fits the screen', async ({ page }) => {
	await page.goto('/index.php/apps/vitalog/')
	await page.getByRole('button', { name: 'Open navigation' }).click()
	await page.getByRole('button', { name: 'New appointment' }).first().click()

	const form = dialog(page)
	await expect(form.getByLabel('Reason')).toBeVisible()
	const box = await form.boundingBox()
	const viewport = page.viewportSize()
	expect(box.x).toBeGreaterThanOrEqual(0)
	expect(box.x + box.width).toBeLessThanOrEqual(viewport.width)
	await expect(form.getByRole('button', { name: 'Save' })).toBeVisible()
})

test('a long list can be scrolled on a phone', async ({ page }) => {
	for (let i = 0; i < 40; i++) {
		await admin.create('appointments', { startsAt: inDays(i + 1), reason: `RDV ${i + 1}` })
	}
	await page.goto('/index.php/apps/vitalog/')
	await expect(items(page).first()).toBeVisible()

	const last = items(page).last()
	await expect(last).not.toBeInViewport()
	// molette / glissement sur la liste
	const box = await items(page).first().boundingBox()
	await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
	await page.mouse.wheel(0, 4000)
	await expect(last).toBeInViewport()
})
