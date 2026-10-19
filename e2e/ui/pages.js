import { expect } from '@playwright/test'
import { ADMIN, client } from '../helpers.js'

export const APP_URL = '/index.php/apps/vitalog/'

/** Fixe la langue de l'interface du compte admin (via l'API de provisioning). */
export async function setLanguage(language) {
	const admin = await client(ADMIN)
	for (const key of ['language', 'locale']) {
		const value = key === 'locale' ? (language === 'fr' ? 'fr_FR' : 'en_US') : language
		// l'API de provisioning répond parfois en erreur transitoire juste après un rechargement de l'app : on réessaie
		await expect(async () => {
			const response = await admin.context.put(`/ocs/v2.php/cloud/users/${ADMIN.user}`, { data: { key, value } })
			expect(response.status(), await response.text()).toBe(200)
		}).toPass({ timeout: 15_000 })
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
export function selected(page, label) {
	return dialog(page).last()
		.locator('.v-select', { has: page.getByRole('combobox', { name: label }) })
}

/**
 * Remplit un champ et vérifie que la valeur est bien restée : dans de rares cas (~1 %) le champ se vide juste après la saisie
 * le temps d'un rendu, avant de se stabiliser ; on réessaie jusqu'à ce que la valeur tienne.
 */
export function fill(locator, value) {
	return expect(async () => {
		await locator.fill(value)
		await expect(locator).toHaveValue(value, { timeout: 500 })
	}).toPass({ timeout: 10_000 })
}
