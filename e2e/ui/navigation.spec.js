import { expect, test } from '@playwright/test'
import { client, inDays } from '../helpers.js'
import { dialog, items, nav, openApp, setLanguage } from './pages.js'

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

const search = (page) => new URL(page.url()).search

test('previous and next walk through views, selection and filters', async ({ page }) => {
	const label = await admin.create('labels', { name: 'Cheville' })
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
	const first = await admin.create('appointments', { startsAt: inDays(2), reason: 'Consultation', practitionerId: practitioner.id, labelIds: [label.id] })
	await admin.create('appointments', { startsAt: inDays(4), reason: 'Séance' })

	await openApp(page)
	expect(search(page)).toBe('')

	await items(page).filter({ hasText: 'Consultation' }).click()
	await expect(page.getByRole('article')).toContainText('Consultation')
	expect(search(page)).toBe(`?appointment=${first.id}`)

	await nav(page, 'places').click()
	expect(search(page)).toBe('?view=places')
	await nav(page, 'all').click()
	await expect(items(page)).toHaveCount(2)

	// un filtre est une étape de navigation
	await page.locator('[data-test="filter-button"]').click()
	await page.getByRole('radiogroup', { name: 'Practitioner' }).getByText('Dr Martin').click()
	await page.keyboard.press('Escape')
	await expect(items(page)).toHaveCount(1)
	expect(search(page)).toBe(`?view=all&practitioner=${practitioner.id}`)

	await page.goBack()
	await expect(items(page)).toHaveCount(2)
	await page.goBack()
	await expect(page.getByText('No places yet')).toBeVisible()
	await page.goBack()
	await expect(page.getByRole('article')).toContainText('Consultation')
	await page.goBack()
	await expect(page.getByText('Select an appointment')).toBeVisible()
	expect(search(page)).toBe('')

	await page.goForward()
	await expect(page.getByRole('article')).toContainText('Consultation')
})

test('the URL can be reloaded or shared', async ({ page }) => {
	const label = await admin.create('labels', { name: 'Cheville' })
	const tagged = await admin.create('appointments', { startsAt: inDays(-5), reason: 'Passé', labelIds: [label.id] })
	await admin.create('appointments', { startsAt: inDays(4), reason: 'Autre' })

	await page.goto(`/index.php/apps/vitalog/?view=all&label=${label.id}&appointment=${tagged.id}`)
	await expect(items(page)).toHaveCount(1)
	await expect(page.getByRole('article')).toContainText('Passé')

	// des identifiants périmés sont ignorés
	await page.goto('/index.php/apps/vitalog/?view=past&label=9999&appointment=9999')
	await expect(items(page)).toHaveCount(1)
	await expect(page.getByText('Select an appointment')).toBeVisible()
	await expect.poll(() => search(page)).toBe('?view=past')
})

test('previous closes a dialog and next does not reopen it', async ({ page }) => {
	await openApp(page)
	await page.getByRole('button', { name: 'New appointment' }).first().click()
	await expect(dialog(page)).toBeVisible()

	await page.goBack()
	await expect(dialog(page)).toHaveCount(0)

	// une fenêtre refermée par ses boutons ne laisse pas d'entrée derrière elle
	const index = () => page.evaluate(() => navigation.currentEntry.index)
	const before = await index()
	await page.getByRole('button', { name: 'New appointment' }).first().click()
	await expect.poll(index).toBe(before + 1)
	await dialog(page).getByRole('button', { name: 'Cancel' }).click()
	await expect(dialog(page)).toHaveCount(0)
	await expect.poll(index).toBe(before)
})

test('saving an appointment leaves one history entry, and a nested dialog closes alone', async ({ page }) => {
	await openApp(page)
	await page.getByRole('button', { name: 'New appointment' }).first().click()
	const form = dialog(page)
	await form.getByRole('button', { name: 'New practitioner' }).click()
	await expect(dialog(page)).toHaveCount(2)
	await page.goBack()
	await expect(dialog(page)).toHaveCount(1)

	await form.getByRole('button', { name: 'Save' }).click()
	await expect(dialog(page)).toHaveCount(0)
	await expect(page.getByRole('article')).toBeVisible()
	await page.goBack()
	await expect(page.getByText('Select an appointment')).toBeVisible()
})
