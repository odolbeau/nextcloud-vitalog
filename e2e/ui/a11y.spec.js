import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import { client, inDays } from '../helpers.js'
import { dialog, fill, nav, openApp, setLanguage } from './pages.js'

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
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin', specialty: 'Dentiste', phone: '0102030405' })
	const place = await admin.create('places', { name: 'Cabinet', address: '1 rue de la Paix' })
	await admin.create('appointments', { startsAt: inDays(3), reason: 'Détartrage', notes: 'À jeun', practitionerId: practitioner.id, placeId: place.id })
})

// On ne scanne que le contenu de l'application : l'interface de Nextcloud (en-tête, menus) n'est pas de notre ressort.
// Exclusion connue : le séparateur `splitpanes` de NcAppContent (@nextcloud/vue 9.13.1) a role="separator" focalisable
// sans aria-valuenow (règle aria-required-attr). Défaut de la dépendance, signalé au mainteneur du projet, non corrigé ici.
function scan(page) {
	return new AxeBuilder({ page })
		.include('#content-vue')
		.exclude('.splitpanes__splitter')
		.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
		.analyze()
}

async function expectNoViolations(page) {
	const { violations } = await scan(page)
	expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([])
}

test('appointment list and details', async ({ page }) => {
	await openApp(page)
	await page.locator('[data-test^="appointment-"]').first().click()
	await expect(page.getByRole('article')).toBeVisible()
	await expectNoViolations(page)
})

test('appointment form', async ({ page }) => {
	await openApp(page)
	await page.getByRole('button', { name: 'New appointment' }).first().click()
	await expect(dialog(page)).toBeVisible()
	await expectNoViolations(page)
})

test('practitioners and places', async ({ page }) => {
	await openApp(page)
	await nav(page, 'practitioners').click()
	await expect(page.locator('[data-test^="practitioner-"]')).toHaveCount(1)
	await expectNoViolations(page)
	await nav(page, 'places').click()
	await expect(page.locator('[data-test^="place-"]')).toHaveCount(1)
	await expectNoViolations(page)
})

test('calendar settings dialog', async ({ page }) => {
	await openApp(page)
	await nav(page, 'settings').click()
	await expect(dialog(page)).toBeVisible()
	await expectNoViolations(page)
})

test('the form can be used with the keyboard only', async ({ page }) => {
	await openApp(page)
	await page.getByRole('button', { name: 'New appointment' }).first().click()
	const form = dialog(page)

	// le premier champ reçoit le focus
	await expect(form.getByLabel('Date and time')).toBeFocused()

	// Échap ferme le dialogue sans rien enregistrer quand le focus est sur un bouton.
	// Limite connue de NcModal (@nextcloud/vue 9.13.1) : Échap est ignoré quand le focus est dans un champ de saisie.
	await form.getByRole('button', { name: 'Cancel' }).focus()
	await page.keyboard.press('Escape')
	await expect(dialog(page)).toHaveCount(0)
	expect(await admin.list('appointments')).toHaveLength(1)
})

test('a form can be submitted with Enter', async ({ page }) => {
	await openApp(page)
	await nav(page, 'places').click()
	await page.getByRole('button', { name: 'New place' }).click()

	await fill(dialog(page).getByLabel('Name'), 'Cabinet Enter')
	await page.keyboard.press('Enter')

	await expect(page.locator('[data-test^="place-"]').filter({ hasText: 'Cabinet Enter' })).toHaveCount(1)
})
