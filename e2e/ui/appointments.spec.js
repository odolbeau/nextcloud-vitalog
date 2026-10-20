import { expect, test } from '@playwright/test'
import { client, exportCalendar, inDays, vitalogEvents } from '../helpers.js'
import { choose, dialog, fill, items, nav, openApp, selected, setLanguage } from './pages.js'

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

test('first visit shows the empty state, then a full appointment can be created', async ({ page }) => {
	await openApp(page)
	await expect(page.getByText('Nothing planned')).toBeVisible()
	await expect(page.getByText('Select an appointment')).toBeVisible()

	await page.getByRole('button', { name: 'New appointment' }).first().click()
	const form = dialog(page)
	await expect(form.getByRole('heading', { name: 'New appointment' })).toBeVisible()

	await fill(form.getByLabel('Date and time'), '2030-03-15T09:30')
	await choose(page, 'Duration', '45 min')

	// praticien créé à la volée
	await form.getByRole('button', { name: 'New practitioner' }).click()
	const practitionerDialog = dialog(page).last()
	await fill(practitionerDialog.getByLabel('Name'), 'Dr Martin')
	await fill(practitionerDialog.getByLabel('Specialty'), 'Dentiste')
	await fill(practitionerDialog.getByLabel('Phone'), '01 02 03 04 05')
	await practitionerDialog.getByRole('button', { name: 'Save' }).click()
	await expect(selected(page, 'Practitioner')).toContainText('Dr Martin')

	// lieu créé à la volée
	await form.getByRole('button', { name: 'New place' }).click()
	await fill(dialog(page).last().getByLabel('Name'), 'Cabinet du Port')
	await fill(dialog(page).last().getByLabel('Address'), '12 quai du Port, Nantes')
	await dialog(page).last().getByRole('button', { name: 'Save' }).click()
	await expect(selected(page, 'Place')).toContainText('Cabinet du Port')

	await fill(form.getByLabel('Reason'), 'Détartrage')
	await fill(form.getByLabel('Notes'), 'Apporter la carte vitale')
	await form.getByRole('button', { name: 'Save' }).click()
	await expect(dialog(page)).toHaveCount(0)

	// la liste et le détail reflètent le rendez-vous, qui est sélectionné
	await expect(items(page)).toHaveCount(1)
	await expect(items(page).first()).toContainText('Détartrage')
	await expect(items(page).first()).toContainText('Dr Martin')
	const details = page.getByRole('article')
	await expect(details.getByRole('heading', { name: 'Détartrage' })).toBeVisible()
	await expect(details).toContainText('Dr Martin')
	await expect(details).toContainText('Dentiste')
	await expect(details.getByRole('link', { name: '01 02 03 04 05' })).toHaveAttribute('href', 'tel:01 02 03 04 05')
	await expect(details.getByRole('link', { name: /12 quai du Port/ })).toHaveAttribute('href', /openstreetmap\.org\/search\?query=12%20quai%20du%20Port/)
	await expect(details).toContainText('Apporter la carte vitale')
	await expect(details).toContainText('45 min')
	await expect(details).toContainText('Friday, March 15, 2030')
	await expect(details).not.toContainText(/ At | Min/)

	const [stored] = await admin.list('appointments')
	expect(stored).toMatchObject({ reason: 'Détartrage', duration: 45 })
	expect(new Date(stored.startsAt * 1000).getHours()).toBe(9)
})

test('the form cannot be saved without a date', async ({ page }) => {
	await openApp(page)
	await page.getByRole('button', { name: 'New appointment' }).first().click()
	const form = dialog(page)

	await fill(form.getByLabel('Date and time'), '')

	await expect(form.getByRole('button', { name: 'Save' })).toBeDisabled()
})

test('editing an appointment updates the details', async ({ page }) => {
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
	await admin.create('appointments', { startsAt: inDays(5), reason: 'Contrôle', practitionerId: practitioner.id })
	await openApp(page)

	await items(page).first().click()
	await page.getByRole('button', { name: 'Edit' }).click()
	const form = dialog(page)
	await expect(form.getByRole('heading', { name: 'Edit appointment' })).toBeVisible()
	await expect(form.getByLabel('Reason')).toHaveValue('Contrôle')
	await expect(selected(page, 'Practitioner')).toContainText('Dr Martin')

	await fill(form.getByLabel('Reason'), 'Suivi orthodontie')
	await choose(page, 'Duration', '1 h')
	await form.getByRole('button', { name: 'Save' }).click()

	const details = page.getByRole('article')
	await expect(details.getByRole('heading', { name: 'Suivi orthodontie' })).toBeVisible()
	await expect(details).toContainText('1 h')
	await expect(items(page).first()).toContainText('Suivi orthodontie')
})

test('Upcoming and Past depend only on the date', async ({ page }) => {
	await admin.create('appointments', { startsAt: inDays(3), reason: 'Bilan' })
	await admin.create('appointments', { startsAt: inDays(-3), reason: 'Radio' })
	await openApp(page)
	await expect(nav(page, 'upcoming')).toContainText('1')
	await expect(items(page)).toHaveCount(1)
	await expect(items(page).first()).toContainText('Bilan')

	await nav(page, 'past').click()
	await expect(items(page)).toHaveCount(1)
	await items(page).first().click()
	// plus de statut : ni pastille, ni action « effectué » / « annulé »
	await expect(page.getByRole('article').getByRole('heading', { name: 'Radio' })).toBeVisible()
	await expect(page.getByRole('button', { name: /Mark as|Cancel appointment/ })).toHaveCount(0)

	await nav(page, 'all').click()
	await expect(items(page)).toHaveCount(2)
})

test('deleting asks for confirmation', async ({ page }) => {
	await admin.create('appointments', { startsAt: inDays(3), reason: 'À supprimer' })
	await openApp(page)
	await items(page).first().click()

	await page.getByRole('button', { name: 'Delete', exact: true }).click()
	await expect(dialog(page).getByRole('heading', { name: 'Delete this appointment?' })).toBeVisible()
	await dialog(page).getByRole('button', { name: 'Cancel' }).click()
	await expect(items(page)).toHaveCount(1)
	expect(await admin.list('appointments')).toHaveLength(1)

	await page.getByRole('button', { name: 'Delete', exact: true }).click()
	await dialog(page).getByRole('button', { name: 'Delete', exact: true }).click()
	await expect(items(page)).toHaveCount(0)
	await expect(page.getByText('Nothing planned')).toBeVisible()
	expect(await admin.list('appointments')).toEqual([])
})

test('search narrows the list by reason, practitioner or place', async ({ page }) => {
	const dentist = await admin.create('practitioners', { name: 'Dr Martin', specialty: 'Dentiste' })
	const lab = await admin.create('places', { name: 'Laboratoire Central' })
	await admin.create('appointments', { startsAt: inDays(2), reason: 'Détartrage', practitionerId: dentist.id })
	await admin.create('appointments', { startsAt: inDays(4), reason: 'Prise de sang', placeId: lab.id })
	await admin.create('appointments', { startsAt: inDays(6), reason: 'Consultation', notes: 'migraines' })
	await openApp(page)
	await expect(items(page)).toHaveCount(3)
	const search = page.getByLabel('Search appointments')

	await fill(search, 'martin')
	await expect(items(page)).toHaveCount(1)
	await expect(items(page).first()).toContainText('Détartrage')

	await fill(search, 'laboratoire')
	await expect(items(page).first()).toContainText('Prise de sang')

	await fill(search, 'migraines')
	await expect(items(page).first()).toContainText('Consultation')

	await fill(search, 'zzz')
	await expect(page.getByText('No match')).toBeVisible()

	await page.getByRole('button', { name: 'Clear search' }).click()
	await expect(items(page)).toHaveCount(3)
})

test('upcoming is sorted soonest first, past most recent first', async ({ page }) => {
	await admin.create('appointments', { startsAt: inDays(9), reason: 'Plus tard' })
	await admin.create('appointments', { startsAt: inDays(1), reason: 'Bientôt' })
	await admin.create('appointments', { startsAt: inDays(-20), reason: 'Ancien' })
	await admin.create('appointments', { startsAt: inDays(-2), reason: 'Récent' })
	await openApp(page)

	await expect(items(page)).toHaveCount(2)
	await expect(items(page).nth(0)).toContainText('Bientôt')
	await expect(items(page).nth(1)).toContainText('Plus tard')

	await nav(page, 'past').click()
	await expect(items(page).nth(0)).toContainText('Récent')
	await expect(items(page).nth(1)).toContainText('Ancien')

	await nav(page, 'all').click()
	await expect(items(page)).toHaveCount(4)
})

test('attached files are listed and can be detached', async ({ page }) => {
	const { deleteFile, uploadFile, ADMIN } = await import('../helpers.js')
	const fileId = await uploadFile(ADMIN, 'vitalog-ui-ordonnance.txt', 'ordonnance')
	const appointment = await admin.create('appointments', { startsAt: inDays(3), reason: 'Bilan' })
	await admin.post(`appointments/${appointment.id}/attachments`, { fileId })
	try {
		await openApp(page)
		await items(page).first().click()
		const documents = page.getByRole('region', { name: 'Documents' })

		const link = documents.getByRole('link', { name: 'vitalog-ui-ordonnance.txt' })
		await expect(link).toHaveAttribute('href', new RegExp(`/f/${fileId}$`))

		await documents.getByRole('button', { name: 'Detach vitalog-ui-ordonnance.txt' }).click()
		await expect(link).toHaveCount(0)
		await expect(documents).toContainText('attach files from your Nextcloud')
		expect((await admin.list('appointments'))[0].attachments).toEqual([])
	} finally {
		await deleteFile(ADMIN, 'vitalog-ui-ordonnance.txt')
	}
})

test('calendar sync can be enabled from the settings', async ({ page }) => {
	const appointment = await admin.create('appointments', { startsAt: inDays(3), reason: 'Bilan' })
	await openApp(page)
	await items(page).first().click()
	await expect(page.getByRole('article')).toContainText('Calendar sync is off')

	await nav(page, 'settings').click()
	const settings = dialog(page)
	await expect(settings.getByRole('heading', { name: 'Calendar sync' })).toBeVisible()
	await choose(page, 'Calendar', 'Personal')
	await settings.getByRole('button', { name: 'Save' }).click()

	await expect(page.getByRole('article')).toContainText('Added to your calendar')
	const events = vitalogEvents(await exportCalendar({ user: 'admin', password: 'admin' }, 'personal'))
	expect(events).toHaveLength(1)
	expect(events[0]).toContain('SUMMARY:Bilan')

	// lien permanent vers l'événement dans l'app Calendrier
	const link = page.getByRole('article').getByRole('link', { name: 'Open in Calendar' })
	await expect(link).not.toHaveAttribute('target', '_blank')
	const href = await link.getAttribute('href')
	expect(href).toMatch(/\/apps\/calendar\/object\/.+(@|%40)vitalog$/)
	const redirect = await admin.context.get(href, { maxRedirects: 0 })
	expect(redirect.headers().location).toContain('/apps/calendar/edit/')

	// désactivation
	await nav(page, 'settings').click()
	await expect(selected(page, 'Calendar')).toContainText('Personal')
	await choose(page, 'Calendar', 'Sync disabled')
	await dialog(page).getByRole('button', { name: 'Save' }).click()
	await expect(page.getByRole('article')).toContainText('Calendar sync is off')
	expect((await admin.list('appointments'))[0]).toMatchObject({ id: appointment.id, synced: false })
})

test('an appointment created at a past date shows up in Past', async ({ page }) => {
	await openApp(page)
	await page.getByRole('button', { name: 'New appointment' }).first().click()
	const form = dialog(page)
	await fill(form.getByLabel('Date and time'), '2020-03-15T09:30')
	await fill(form.getByLabel('Reason'), 'Vaccin')
	await form.getByRole('button', { name: 'Save' }).click()
	await expect(dialog(page)).toHaveCount(0)

	// l'app bascule sur la liste qui contient le rendez-vous : « Passés »
	await expect(items(page)).toHaveCount(1)
	await expect(items(page).first()).toContainText('Vaccin')
	await nav(page, 'upcoming').click()
	await expect(page.getByText('Nothing planned')).toBeVisible()
})

test('importing events from the calendar can be enabled from the settings, with its limits explained', async ({ page }) => {
	await openApp(page)
	await nav(page, 'settings').click()
	const settings = dialog(page)
	const toggle = settings.getByRole('switch', { name: /Create an appointment when I add an event/ })
	await expect(toggle).toBeDisabled()
	await expect(settings).toContainText('Recurring events are not supported')
	await expect(settings).toContainText('one-way')

	await choose(page, 'Calendar', 'Personal')
	await expect(toggle).toBeEnabled()
	await expect(toggle).not.toBeChecked()
	await toggle.click({ force: true })
	await settings.getByRole('button', { name: 'Save' }).click()
	await expect(dialog(page)).toHaveCount(0)
	expect(await admin.data(await admin.get('settings'))).toMatchObject({ calendarUri: 'personal', importFromCalendar: true })

	// l'état est restitué à la réouverture
	await nav(page, 'settings').click()
	await expect(dialog(page).getByRole('switch', { name: /Create an appointment when I add an event/ })).toBeChecked()
})

test('selecting a calendar can import the events it already contains', async ({ page }) => {
	const uid = `ui-existing-${Date.now()}`
	// calendrier dédié : « personal » contient déjà l'événement d'accueil de Nextcloud
	const calendar = `vitalog-e2e-ui-existing-${Date.now()}`
	expect((await admin.context.fetch(`/remote.php/dav/calendars/admin/${calendar}/`, { method: 'MKCALENDAR' })).status()).toBe(201)
	const start = new Date(Date.now() + 4 * 86400_000).toISOString().replace(/[-:]/g, '').replace(/\.\d+Z$/, 'Z')
	const ics = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Vitalog e2e//EN', 'BEGIN:VEVENT', `UID:${uid}`, `DTSTAMP:${start}`, `DTSTART:${start}`, `DTEND:${start}`, 'SUMMARY:Déjà au calendrier', 'END:VEVENT', 'END:VCALENDAR', ''].join('\r\n')
	expect((await admin.context.put(`/remote.php/dav/calendars/admin/${calendar}/${uid}.ics`, { headers: { 'Content-Type': 'text/calendar' }, data: ics })).status()).toBe(201)

	await openApp(page)
	await nav(page, 'settings').click()
	const settings = dialog(page)
	const existing = settings.getByRole('checkbox', { name: /Also create appointments from the events already/ })
	await expect(existing).toBeDisabled()
	await choose(page, 'Calendar', calendar)
	await expect(existing).toBeEnabled()
	await existing.check({ force: true })
	await settings.getByRole('button', { name: 'Save' }).click()
	await expect(dialog(page)).toHaveCount(0)

	await expect(items(page)).toHaveCount(1)
	await expect(items(page).first()).toContainText('Déjà au calendrier')
	await expect(page.getByText('Appointments created from existing events: 1', { exact: true })).toBeVisible()

	await admin.context.delete(`/remote.php/dav/calendars/admin/${calendar}/`)
})
