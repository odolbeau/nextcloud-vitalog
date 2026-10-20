import AxeBuilder from '@axe-core/playwright'
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

// voir a11y.spec.js : seul le contenu de l'application est analysé, le séparateur splitpanes (@nextcloud/vue 9.13.1) est exclu
async function expectNoViolations(page) {
	const { violations } = await new AxeBuilder({ page })
		.include('#content-vue')
		.exclude('.splitpanes__splitter')
		.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
		.analyze()
	expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([])
}

test('a label typed in the appointment form is created and attached', async ({ page }) => {
	await openApp(page)
	await page.getByRole('button', { name: 'New appointment' }).first().click()
	const form = dialog(page)
	await fill(form.getByLabel('Reason'), 'Entorse')
	const labels = form.getByRole('combobox', { name: 'Labels' })
	await labels.fill('Cheville')
	await page.getByRole('option', { name: 'Cheville' }).click()
	await form.getByRole('button', { name: 'Save' }).click()
	await expect(dialog(page)).toHaveCount(0)

	const [label] = await admin.list('labels')
	expect(label.name).toBe('Cheville')
	const [appointment] = await admin.list('appointments')
	expect(appointment.labelIds).toEqual([label.id])
	await expect(page.getByRole('article').getByRole('list', { name: 'Labels' })).toContainText('Cheville')
})

test('filtering by label brings together appointments of different practitioners, past and upcoming', async ({ page }) => {
	const ankle = await admin.create('labels', { name: 'Cheville', color: '#1565c0' })
	const blood = await admin.create('labels', { name: 'Don du sang' })
	const gp = await admin.create('practitioners', { name: 'Dr Généraliste' })
	const physio = await admin.create('practitioners', { name: 'Kiné Durand' })
	await admin.create('appointments', { startsAt: inDays(-20), reason: 'Consultation', practitionerId: gp.id, labelIds: [ankle.id] })
	await admin.create('appointments', { startsAt: inDays(4), reason: 'Séance 1', practitionerId: physio.id, labelIds: [ankle.id] })
	await admin.create('appointments', { startsAt: inDays(9), reason: 'Prélèvement', labelIds: [blood.id] })

	await openApp(page)
	await expect(items(page)).toHaveCount(2)

	// un clic sur la pastille du détail montre tous les rendez-vous du libellé (passés compris)
	await items(page).filter({ hasText: 'Séance 1' }).click()
	await page.getByRole('article').getByRole('button', { name: /Cheville/ }).click()
	await expect(page.getByRole('list', { name: 'All appointments' })).toBeVisible()
	await expect(items(page)).toHaveCount(2)
	await expect(items(page).filter({ hasText: 'Prélèvement' })).toHaveCount(0)

	// le filtre actif est visible sous la recherche et se retire d'un clic
	await expect(page.getByText('Cheville', { exact: true }).first()).toBeVisible()
	await page.getByRole('button', { name: 'Remove the label filter' }).click()
	await expect(items(page)).toHaveCount(3)

	// ou se change depuis le bouton de filtre
	await page.locator('[data-test="label-filter-button"]').click()
	await page.getByRole('radiogroup').getByText('Don du sang').click()
	await expect(items(page)).toHaveCount(1)
	// la fenêtre se referme après le choix ; on la rouvre pour réinitialiser
	await page.locator('[data-test="label-filter-button"]').click()
	await page.getByRole('button', { name: 'Reset the filter' }).click()
	await expect(items(page)).toHaveCount(3)

	// la recherche texte trouve aussi les libellés
	await page.getByLabel('Search appointments').fill('cheville')
	await expect(items(page)).toHaveCount(2)
	await expectNoViolations(page)
})

test('labels can be renamed, coloured and deleted from their page', async ({ page }) => {
	const ankle = await admin.create('labels', { name: 'Cheville' })
	await admin.create('appointments', { startsAt: inDays(4), reason: 'Séance', labelIds: [ankle.id] })

	await openApp(page)
	await nav(page, 'labels').click()
	const row = page.locator('[data-test^="label-row-"]')
	await expect(row).toHaveCount(1)
	await expect(row).toContainText('Appointments: 1')
	await expectNoViolations(page)

	await row.click()
	await fill(dialog(page).getByLabel('Name'), 'Cheville gauche')
	await dialog(page).getByText('Blue', { exact: true }).click()
	await expectNoViolations(page)
	await dialog(page).getByRole('button', { name: 'Save' }).click()
	await expect(row).toContainText('Cheville gauche')
	expect(await admin.list('labels')).toMatchObject([{ name: 'Cheville gauche', color: '#1565c0' }])

	await page.getByRole('button', { name: 'Show appointments labelled Cheville gauche' }).click()
	await expect(items(page)).toHaveCount(1)

	await nav(page, 'labels').click()
	await page.getByRole('button', { name: 'Delete Cheville gauche' }).click()
	await dialog(page).getByRole('button', { name: 'Delete', exact: true }).click()
	await expect(row).toHaveCount(0)
	expect(await admin.list('appointments')).toHaveLength(1)

	// plus de filtre fantôme après la suppression
	await nav(page, 'all').click()
	await expect(items(page)).toHaveCount(1)
})

test('the label form has no accessibility violations', async ({ page }) => {
	await admin.create('labels', { name: 'Cheville', color: '#b3261e' })
	await openApp(page)
	await nav(page, 'labels').click()
	await page.getByRole('button', { name: 'New label' }).click()
	await expect(dialog(page)).toBeVisible()
	await expectNoViolations(page)
})
