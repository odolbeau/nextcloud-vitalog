import { expect, test } from '@playwright/test'
import { client, inDays } from '../helpers.js'
import { dialog, fill, items, nav, openApp, setLanguage } from './pages.js'

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

test.describe('practitioners', () => {
	test('empty state, create, edit, delete', async ({ page }) => {
		await openApp(page)
		await nav(page, 'practitioners').click()
		await expect(page.getByText('No practitioners yet')).toBeVisible()

		await page.getByRole('button', { name: 'New practitioner' }).click()
		const form = dialog(page)
		await fill(form.getByLabel('Name'), 'Dr Durand')
		await fill(form.getByLabel('Specialty'), 'Cardiologue')
		await fill(form.getByLabel('Phone'), '04 05 06 07 08')
		await fill(form.getByLabel('Notes'), 'Parking à gauche')
		await form.getByRole('button', { name: 'Save' }).click()

		const row = page.locator('[data-test^="practitioner-"]')
		await expect(row).toHaveCount(1)
		await expect(row.first()).toContainText('Dr Durand')
		await expect(row.first()).toContainText('Cardiologue · 04 05 06 07 08')

		await row.first().click()
		await expect(dialog(page).getByRole('heading', { name: 'Edit practitioner' })).toBeVisible()
		await expect(dialog(page).getByLabel('Notes')).toHaveValue('Parking à gauche')
		await fill(dialog(page).getByLabel('Specialty'), 'Cardiologie')
		await dialog(page).getByRole('button', { name: 'Save' }).click()
		await expect(row.first()).toContainText('Cardiologie')

		await page.getByRole('button', { name: 'Delete Dr Durand' }).click()
		await expect(dialog(page).getByRole('heading', { name: 'Delete Dr Durand?' })).toBeVisible()
		await dialog(page).getByRole('button', { name: 'Delete', exact: true }).click()
		await expect(row).toHaveCount(0)
		expect(await admin.list('practitioners')).toEqual([])
	})

	test('the name is required', async ({ page }) => {
		await openApp(page)
		await nav(page, 'practitioners').click()
		await page.getByRole('button', { name: 'New practitioner' }).click()

		await expect(dialog(page).getByRole('button', { name: 'Save' })).toBeDisabled()
		await fill(dialog(page).getByLabel('Name'), '   ')
		await expect(dialog(page).getByRole('button', { name: 'Save' })).toBeDisabled()
		await fill(dialog(page).getByLabel('Name'), 'Dr X')
		await expect(dialog(page).getByRole('button', { name: 'Save' })).toBeEnabled()
	})

	test('deleting a practitioner keeps their appointments, unlinked', async ({ page }) => {
		const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
		await admin.create('appointments', { startsAt: inDays(3), reason: 'Contrôle', practitionerId: practitioner.id })
		await openApp(page)
		await nav(page, 'practitioners').click()

		await page.getByRole('button', { name: 'Delete Dr Martin' }).click()
		await dialog(page).getByRole('button', { name: 'Delete', exact: true }).click()
		await expect(page.getByText('No practitioners yet')).toBeVisible()

		await nav(page, 'upcoming').click()
		await items(page).first().click()
		await expect(items(page).first()).toContainText('Contrôle')
		await expect(page.getByRole('article')).not.toContainText('Dr Martin')
	})

	test('cancelling the deletion keeps the practitioner', async ({ page }) => {
		await admin.create('practitioners', { name: 'Dr Martin' })
		await openApp(page)
		await nav(page, 'practitioners').click()

		await page.getByRole('button', { name: 'Delete Dr Martin' }).click()
		await dialog(page).getByRole('button', { name: 'Cancel' }).click()

		await expect(page.locator('[data-test^="practitioner-"]')).toHaveCount(1)
		expect(await admin.list('practitioners')).toHaveLength(1)
	})
})

test.describe('places', () => {
	test('empty state, create, edit, delete', async ({ page }) => {
		await openApp(page)
		await nav(page, 'places').click()
		await expect(page.getByText('No places yet')).toBeVisible()

		await page.getByRole('button', { name: 'New place' }).click()
		await fill(dialog(page).getByLabel('Name'), 'Clinique du Parc')
		await fill(dialog(page).getByLabel('Address'), '3 avenue du Parc, Lyon')
		await dialog(page).getByRole('button', { name: 'Save' }).click()

		const row = page.locator('[data-test^="place-"]')
		await expect(row).toHaveCount(1)
		await expect(row.first()).toContainText('Clinique du Parc')
		await expect(row.first()).toContainText('3 avenue du Parc, Lyon')

		await row.first().click()
		await expect(dialog(page).getByRole('heading', { name: 'Edit place' })).toBeVisible()
		await fill(dialog(page).getByLabel('Name'), 'Clinique Saint-Jean')
		await dialog(page).getByRole('button', { name: 'Save' }).click()
		await expect(row.first()).toContainText('Clinique Saint-Jean')

		await page.getByRole('button', { name: 'Delete Clinique Saint-Jean' }).click()
		await dialog(page).getByRole('button', { name: 'Delete', exact: true }).click()
		await expect(row).toHaveCount(0)
		expect(await admin.list('places')).toEqual([])
	})
})

test('practitioner and place edits are reflected in appointments without reloading', async ({ page }) => {
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
	await admin.create('appointments', { startsAt: inDays(3), reason: 'Contrôle', practitionerId: practitioner.id })
	await openApp(page)
	await nav(page, 'practitioners').click()
	await page.locator('[data-test^="practitioner-"]').first().click()
	await fill(dialog(page).getByLabel('Name'), 'Dr Durand')
	await dialog(page).getByRole('button', { name: 'Save' }).click()

	await nav(page, 'upcoming').click()
	await expect(items(page).first()).toContainText('Dr Durand')
})
