import { expect, test } from '@playwright/test'
import { client, inDays } from '../helpers.js'
import { choose, dialog, fill, items, nav, openApp, setLanguage, typeSpecialty } from './pages.js'

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

// la liste des praticiens d'une spécialité
const specialty = (page, name) => page.locator('[data-test="specialty"]').filter({ has: page.getByRole('heading', { name, exact: true }) })

test.describe('practitioners', () => {
	test('empty state, create, edit, delete', async ({ page }) => {
		await openApp(page)
		await nav(page, 'practitioners').click()
		await expect(page.getByText('No practitioners yet')).toBeVisible()

		await page.getByRole('button', { name: 'New practitioner' }).click()
		const form = dialog(page)
		await fill(form.getByLabel('Name'), 'Dr Durand')
		await typeSpecialty(page, 'Cardiologue')
		await fill(form.getByLabel('Phone'), '04 05 06 07 08')
		// plus de notes propres à Vitalog sur un praticien
		await expect(form.getByLabel('Notes')).toHaveCount(0)
		await form.getByRole('button', { name: 'Save' }).click()

		const row = page.locator('[data-test^="practitioner-"]')
		await expect(row).toHaveCount(1)
		await expect(row.first()).toContainText('Dr Durand')
		await expect(row.first()).toContainText('04 05 06 07 08')
		await expect(specialty(page, 'Cardiologue')).toContainText('Dr Durand')

		await row.first().click()
		await expect(dialog(page).getByRole('heading', { name: 'Edit practitioner' })).toBeVisible()
		await typeSpecialty(page, 'Cardiologie')
		await dialog(page).getByRole('button', { name: 'Save' }).click()
		await expect(specialty(page, 'Cardiologie')).toContainText('Dr Durand')
		await expect(specialty(page, 'Cardiologue')).toHaveCount(0)

		await page.getByRole('button', { name: 'Delete Dr Durand' }).click()
		await expect(dialog(page).getByRole('heading', { name: 'Delete Dr Durand?' })).toBeVisible()
		await dialog(page).getByRole('button', { name: 'Delete', exact: true }).click()
		await expect(row).toHaveCount(0)
		expect(await admin.list('practitioners')).toEqual([])
	})

	test('the specialty is picked among the existing ones or added', async ({ page }) => {
		await admin.create('practitioners', { name: 'Dr A', specialty: 'Dentiste' })
		await admin.create('practitioners', { name: 'Dr B', specialty: 'dentiste' })
		await admin.create('practitioners', { name: 'Dr C', specialty: 'Cardiologue' })
		await openApp(page)
		await nav(page, 'practitioners').click()

		await page.getByRole('button', { name: 'New practitioner' }).click()
		const form = dialog(page)
		await fill(form.getByLabel('Name'), 'Dr D')
		await form.getByRole('combobox', { name: 'Specialty' }).click()
		// une seule entrée par spécialité, quelle que soit la casse
		await expect(page.getByRole('option')).toHaveText(['Cardiologue', 'Dentiste'])
		await page.locator('[role="option"]').filter({ hasText: 'Cardiologue' }).click()
		await form.getByRole('button', { name: 'Save' }).click()
		await expect(specialty(page, 'Cardiologue')).toContainText('Dr D')

		await page.getByRole('button', { name: 'New practitioner' }).click()
		await fill(dialog(page).getByLabel('Name'), 'Dr E')
		await typeSpecialty(page, 'Dermatologue')
		await dialog(page).getByRole('button', { name: 'Save' }).click()
		await expect(specialty(page, 'Dermatologue')).toContainText('Dr E')
		expect((await admin.list('practitioners')).map((p) => p.specialty).sort()).toEqual(['Cardiologue', 'Cardiologue', 'Dentiste', 'Dermatologue', 'dentiste'])
	})

	test('the list is grouped by specialty, regardless of case, those without one last', async ({ page }) => {
		await admin.create('practitioners', { name: 'Dr Vidal' })
		await admin.create('practitioners', { name: 'Dr Bernard', specialty: 'dentiste' })
		await admin.create('practitioners', { name: 'Dr Petit', specialty: 'Cardiologue' })
		await admin.create('practitioners', { name: 'Dr Arnaud', specialty: 'Dentiste' })
		await openApp(page)
		await nav(page, 'practitioners').click()

		await expect(page.locator('[data-test="specialty"] h3')).toHaveText(['Cardiologue', 'Dentiste', 'No specialty'])
		await expect(specialty(page, 'Dentiste').locator('[data-test^="practitioner-"]')).toHaveCount(2)
		await expect(specialty(page, 'Dentiste')).toContainText('Dr Arnaud')
		await expect(specialty(page, 'Dentiste')).toContainText('Dr Bernard')
		await expect(specialty(page, 'No specialty')).toContainText('Dr Vidal')
	})

	test('the usual place is picked in the practitioner form and shown in the list', async ({ page }) => {
		const place = await admin.create('places', { name: 'Cabinet du Port' })
		const practitioner = await admin.create('practitioners', { name: 'Dr Martin', phone: '0102030405' })
		await openApp(page)
		await nav(page, 'practitioners').click()

		await page.locator(`[data-test="practitioner-${practitioner.id}"]`).click()
		await choose(page, 'Usual place', 'Cabinet du Port')
		await dialog(page).getByRole('button', { name: 'Save' }).click()

		await expect(page.locator(`[data-test="practitioner-${practitioner.id}"]`)).toContainText('0102030405 · Cabinet du Port')
		expect((await admin.list('practitioners'))[0].placeId).toBe(place.id)
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
		await fill(dialog(page).getByLabel('Practical information'), 'Interphone 12B, code 4521')
		await dialog(page).getByRole('button', { name: 'Save' }).click()

		const row = page.locator('[data-test^="place-"]')
		await expect(row).toHaveCount(1)
		await expect(row.first()).toContainText('Clinique du Parc')
		await expect(row.first()).toContainText('3 avenue du Parc, Lyon')
		await expect(row.first()).toContainText('Interphone 12B, code 4521')
		expect((await admin.list('places'))[0].notes).toBe('Interphone 12B, code 4521')

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

test('the practical information of a place is shown in the appointment details', async ({ page }) => {
	const place = await admin.create('places', { name: 'Cabinet', address: '1 rue de la Paix', notes: 'Interphone 12B, code 4521' })
	await admin.create('appointments', { startsAt: inDays(3), reason: 'Contrôle', placeId: place.id })
	await openApp(page)
	await items(page).first().click()
	await expect(page.getByRole('article')).toContainText('Interphone 12B, code 4521')
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
