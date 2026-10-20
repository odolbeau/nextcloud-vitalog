import { expect, test } from '@playwright/test'
import { client, inDays } from '../helpers.js'
import { dialog, nav, setLanguage } from './pages.js'

let admin

test.beforeAll(async () => {
	admin = await client()
	await setLanguage('fr')
})
test.afterAll(async () => {
	await setLanguage('en')
	await admin.reset()
	await admin.dispose()
})

test('the interface is available in French', async ({ page }) => {
	await admin.reset()
	await admin.create('appointments', { startsAt: inDays(3), reason: 'Bilan', duration: 60 })
	await page.goto('/index.php/apps/vitalog/')

	await expect(page.getByRole('button', { name: 'Nouveau rendez-vous' }).first()).toBeVisible()
	await expect(nav(page, 'upcoming')).toContainText('À venir')
	await expect(nav(page, 'practitioners')).toContainText('Praticiens')
	await expect(nav(page, 'places')).toContainText('Lieux')
	await expect(nav(page, 'labels')).toContainText('Libellés')
	await expect(nav(page, 'settings')).toContainText('Synchro calendrier')

	await page.locator('[data-test^="appointment-"]').first().click()
	const details = page.getByRole('article')
	await expect(details).toContainText('1 h')
	await expect(details.getByRole('button', { name: 'Modifier' })).toBeVisible()
	// les dates suivent la locale (jour de la semaine en français, première lettre en capitale)
	await expect(details).toContainText(/(Lundi|Mardi|Mercredi|Jeudi|Vendredi|Samedi|Dimanche) \d{1,2} \p{L}+ \d{4} à \d{2}:\d{2}/iu)

	await page.getByRole('button', { name: 'Nouveau rendez-vous' }).first().click()
	await expect(dialog(page).getByRole('heading', { name: 'Nouveau rendez-vous' })).toBeVisible()
	await expect(dialog(page).getByLabel('Motif')).toBeVisible()
	await expect(dialog(page).getByRole('button', { name: 'Enregistrer' })).toBeVisible()
})

test('the calendar event uses the user language for its fallback title', async () => {
	const { exportCalendar, vitalogEvents, ADMIN } = await import('../helpers.js')
	await admin.reset()
	await admin.put('settings', { calendarUri: 'personal' })
	await admin.create('appointments', { startsAt: inDays(3) })

	const [event] = vitalogEvents(await exportCalendar(ADMIN, 'personal'))

	expect(event).toContain('SUMMARY:Rendez-vous médical')
})
