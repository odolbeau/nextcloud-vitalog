import { expect } from '@playwright/test'
import { ADMIN, client } from '../helpers.js'

export const APP_URL = '/index.php/apps/vitalog/'

/** Fixe la langue de l'interface du compte admin (via l'API de provisioning). */
export async function setLanguage(language) {
	const admin = await client(ADMIN)
	for (const key of ['language', 'locale']) {
		const response = await admin.context.put(`/ocs/v2.php/cloud/users/${ADMIN.user}`, {
			data: { key, value: key === 'locale' ? (language === 'fr' ? 'fr_FR' : 'en_US') : language },
		})
		expect(response.ok()).toBeTruthy()
	}
	await admin.dispose()
}

export async function openApp(page) {
	await page.goto(APP_URL)
	await expect(page.getByRole('button', { name: 'New appointment' }).or(page.getByRole('button', { name: 'Nouveau rendez-vous' })).first()).toBeVisible()
	// la liste a fini de charger
	await expect(page.locator('.list__loading, .loading-icon').first()).toHaveCount(0)
}

export const nav = (page, name) => page.locator(`[data-test="nav-${name}"]`)
export const items = (page) => page.locator('[data-test^="appointment-"]')
export const dialog = (page) => page.getByRole('dialog')

/** Choisit une option d'un NcSelect identifié par son libellé. */
export async function choose(page, label, option) {
	const field = dialog(page).last().getByRole('combobox', { name: label })
	await field.click()
	// NcSelect découpe les libellés en deux <span> (ellipse) : on filtre sur le texte du DOM plutôt que sur le nom accessible
	await page.locator('[role="option"]').filter({ hasText: option }).first().click()
}

/** Valeur affichée d'un NcSelect (le combobox est l'<input> ; la valeur est dans le conteneur vue-select). */
export const selected = (page, label) => dialog(page).last()
	.locator('.v-select', { has: page.getByRole('combobox', { name: label }) })
