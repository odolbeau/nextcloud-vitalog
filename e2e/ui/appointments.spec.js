import { expect, test } from '@playwright/test'
import { client, exportCalendar, inDays, vitalogEvents } from '../helpers.js'
import { choose, dialog, fill, items, nav, openApp, selected, setLanguage, typeSpecialty } from './pages.js'

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
	await typeSpecialty(page, 'Dentiste')
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

test('a missing practitioner and place can be chosen from the details', async ({ page }) => {
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin', specialty: 'Dentiste' })
	const place = await admin.create('places', { name: 'Cabinet Rivoli' })
	await admin.create('appointments', { startsAt: inDays(5), reason: 'Contrôle' })
	await openApp(page)
	await items(page).first().click()
	const details = page.getByRole('article')

	await details.getByRole('button', { name: 'Choose a practitioner' }).click()
	await page.getByRole('option').filter({ hasText: 'Dr Martin' }).click()
	await expect(details).toContainText('Dr Martin')
	await expect(details).toContainText('Dentiste')

	await details.getByRole('button', { name: 'Choose a place' }).click()
	await page.getByRole('option').filter({ hasText: 'Cabinet Rivoli' }).click()
	await expect(details).toContainText('Cabinet Rivoli')

	const [stored] = await admin.list('appointments')
	expect(stored).toMatchObject({ practitionerId: practitioner.id, placeId: place.id, reason: 'Contrôle' })
})

test('the practitioner can be changed from the details', async ({ page }) => {
	const first = await admin.create('practitioners', { name: 'Dr Martin' })
	await admin.create('practitioners', { name: 'Dr Durand' })
	await admin.create('appointments', { startsAt: inDays(5), practitionerId: first.id })
	await openApp(page)
	await items(page).first().click()
	const details = page.getByRole('article')

	await details.getByRole('button', { name: 'Change the practitioner' }).click()
	await page.getByRole('option').filter({ hasText: 'Dr Durand' }).click()

	await expect(details).toContainText('Dr Durand')
	await expect(details).not.toContainText('Dr Martin')
})

test('the notes can be added, edited and cleared from the details', async ({ page }) => {
	await admin.create('appointments', { startsAt: inDays(5), reason: 'Contrôle' })
	await openApp(page)
	await items(page).first().click()
	const details = page.getByRole('article')

	await details.getByRole('button', { name: 'Add notes' }).click()
	await details.getByLabel('Notes').fill('Apporter la carte vitale')
	await details.getByRole('button', { name: 'Save' }).click()
	await expect(details).toContainText('Apporter la carte vitale')
	expect((await admin.list('appointments'))[0].notes).toBe('Apporter la carte vitale')

	await details.getByRole('button', { name: 'Edit the notes' }).click()
	await expect(details.getByLabel('Notes')).toHaveValue('Apporter la carte vitale')
	await details.getByLabel('Notes').fill('Apporter les radios')
	await details.getByRole('button', { name: 'Save' }).click()
	await expect(details).toContainText('Apporter les radios')

	// annuler ne change rien
	await details.getByRole('button', { name: 'Edit the notes' }).click()
	await details.getByLabel('Notes').fill('Brouillon')
	await details.getByRole('button', { name: 'Cancel' }).click()
	await expect(details).toContainText('Apporter les radios')

	// vider les notes les supprime
	await details.getByRole('button', { name: 'Edit the notes' }).click()
	await details.getByLabel('Notes').fill('')
	await details.getByRole('button', { name: 'Save' }).click()
	await expect(details.getByRole('button', { name: 'Add notes' })).toBeVisible()
	expect((await admin.list('appointments'))[0].notes).toBeNull()
})

test('the price can be added, edited and cleared from the details', async ({ page }) => {
	await admin.create('appointments', { startsAt: inDays(5), reason: 'Contrôle' })
	await openApp(page)
	await items(page).first().click()
	const details = page.getByRole('article')

	await details.getByRole('button', { name: 'Add a price' }).click()
	await details.getByLabel('Price').fill('25,5')
	await details.getByRole('button', { name: 'Save' }).click()
	await expect(details).toContainText('25.50')
	expect((await admin.list('appointments'))[0].price).toBe(2550)

	// un montant invalide ne s'enregistre pas
	await details.getByRole('button', { name: 'Edit the price' }).click()
	await expect(details.getByLabel('Price')).toHaveValue('25,50')
	await details.getByLabel('Price').fill('abc')
	await expect(details.getByRole('button', { name: 'Save' })).toBeDisabled()
	await details.getByRole('button', { name: 'Cancel' }).click()
	await expect(details).toContainText('25.50')

	// gratuit (0) n'est pas « sans prix »
	await details.getByRole('button', { name: 'Edit the price' }).click()
	await details.getByLabel('Price').fill('0')
	await details.getByRole('button', { name: 'Save' }).click()
	expect((await admin.list('appointments'))[0].price).toBe(0)

	// vider le champ supprime le prix
	await details.getByRole('button', { name: 'Edit the price' }).click()
	await details.getByLabel('Price').fill('')
	await details.getByRole('button', { name: 'Save' }).click()
	await expect(details.getByRole('button', { name: 'Add a price' })).toBeVisible()
	expect((await admin.list('appointments'))[0].price).toBeNull()
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

test('search ignores accents and case, in the list and in the selects', async ({ page }) => {
	const practitioner = await admin.create('practitioners', { name: 'Dr Hélène Müller', specialty: 'Généraliste' })
	await admin.create('practitioners', { name: 'Dr Durand' })
	await admin.create('appointments', { startsAt: inDays(2), reason: 'Détartrage', practitionerId: practitioner.id })
	await admin.create('appointments', { startsAt: inDays(4), reason: 'Prise de sang' })
	await openApp(page)
	const search = page.getByLabel('Search appointments')

	await fill(search, 'detartrage')
	await expect(items(page)).toHaveCount(1)
	await fill(search, 'HELENE muller')
	await expect(items(page)).toHaveCount(1)
	await expect(items(page).first()).toContainText('Détartrage')
	// et l'inverse : des accents dans la saisie, pas dans les données
	await fill(search, 'prisé')
	await expect(items(page)).toHaveCount(1)
	await expect(items(page).first()).toContainText('Prise de sang')
	await fill(search, 'zzz')
	await expect(page.getByText('No match')).toBeVisible()
	await page.getByRole('button', { name: 'Clear search' }).click()

	await items(page).last().click()
	await page.getByRole('button', { name: 'Choose a practitioner' }).click()
	await page.keyboard.type('helene')
	await expect(page.locator('[role="option"]')).toHaveCount(1)
	await expect(page.locator('[role="option"]').first()).toContainText('Hélène Müller')
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
	await expect(settings.getByRole('button', { name: 'Link calendar' })).toBeDisabled()
	await choose(page, 'Calendar', 'Personal')
	await settings.getByRole('button', { name: 'Link calendar' }).click()

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

	// lié : on ne peut plus choisir un autre calendrier, seulement délier
	await nav(page, 'settings').click()
	const linked = dialog(page)
	await expect(linked).toContainText('Linked calendar')
	await expect(linked).toContainText('Personal')
	await expect(linked.getByRole('combobox', { name: 'Calendar' })).toHaveCount(0)
	await linked.getByRole('button', { name: 'Unlink calendar' }).click()
	await expect(linked.getByRole('heading', { name: 'Unlink the calendar' })).toBeVisible()
	// le choix par défaut est de garder les événements
	await expect(linked.getByRole('radio', { name: 'Keep the events in the calendar' })).toBeChecked()
	await linked.getByRole('button', { name: 'Unlink', exact: true }).click()

	await expect(dialog(page)).toHaveCount(0)
	await expect(page.getByRole('article')).toContainText('Calendar sync is off')
	expect((await admin.list('appointments'))[0]).toMatchObject({ id: appointment.id, synced: false })
	expect(vitalogEvents(await exportCalendar({ user: 'admin', password: 'admin' }, 'personal'))).toHaveLength(1)
})

test('unlinking from the settings can delete the events, and the calendar can be linked again', async ({ page }) => {
	await admin.create('appointments', { startsAt: inDays(3), reason: 'Bilan' })
	await admin.put('settings', { calendarUri: 'personal' })
	await openApp(page)
	await nav(page, 'settings').click()
	await dialog(page).getByRole('button', { name: 'Unlink calendar' }).click()

	// « Annuler » revient aux réglages sans rien délier
	await dialog(page).getByRole('button', { name: 'Cancel' }).click()
	await expect(dialog(page).getByRole('button', { name: 'Unlink calendar' })).toBeVisible()
	expect((await admin.data(await admin.get('settings'))).calendarUri).toBe('personal')

	await dialog(page).getByRole('button', { name: 'Unlink calendar' }).click()
	await dialog(page).getByText('Delete the events from the calendar').click()
	await dialog(page).getByRole('button', { name: 'Unlink', exact: true }).click()
	await expect(dialog(page)).toHaveCount(0)
	expect(vitalogEvents(await exportCalendar({ user: 'admin', password: 'admin' }, 'personal'))).toHaveLength(0)

	// et on peut lier de nouveau
	await nav(page, 'settings').click()
	await choose(page, 'Calendar', 'Personal')
	await dialog(page).getByRole('button', { name: 'Link calendar' }).click()
	await expect(dialog(page)).toHaveCount(0)
	expect(vitalogEvents(await exportCalendar({ user: 'admin', password: 'admin' }, 'personal'))).toHaveLength(1)
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
	const toggle = settings.getByRole('checkbox', { name: /Create an appointment for each event I add later/ })
	await expect(toggle).toBeDisabled()
	await expect(settings).toContainText('Recurring events are ignored')

	await choose(page, 'Calendar', 'Personal')
	await expect(toggle).toBeEnabled()
	await expect(toggle).not.toBeChecked()
	await toggle.click({ force: true })
	await settings.getByRole('button', { name: 'Link calendar' }).click()
	await expect(dialog(page)).toHaveCount(0)
	expect(await admin.data(await admin.get('settings'))).toMatchObject({ calendarUri: 'personal', importFromCalendar: true })

	// l'état est restitué à la réouverture ; une fois lié, l'option s'applique tout de suite (pas de bouton « Enregistrer »)
	await nav(page, 'settings').click()
	const linkedToggle = dialog(page).getByRole('switch', { name: /Create an appointment for each event I add to this calendar/ })
	await expect(linkedToggle).toBeChecked()
	await expect(dialog(page).getByRole('button', { name: 'Save' })).toHaveCount(0)
	await linkedToggle.click({ force: true })
	await expect(linkedToggle).not.toBeChecked()
	await expect.poll(async () => (await admin.data(await admin.get('settings'))).importFromCalendar).toBe(false)
	await dialog(page).getByRole('button', { name: 'Close' }).last().click()
	await expect(dialog(page)).toHaveCount(0)
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
	const existing = settings.getByRole('checkbox', { name: /Create appointments from the events already in this calendar/ })
	await expect(existing).toBeDisabled()
	await choose(page, 'Calendar', calendar)
	await expect(existing).toBeEnabled()
	await existing.check({ force: true })
	await settings.getByRole('button', { name: 'Link calendar' }).click()
	await expect(dialog(page)).toHaveCount(0)

	await expect(items(page)).toHaveCount(1)
	await expect(items(page).first()).toContainText('Déjà au calendrier')
	await expect(page.getByText('Appointments created from existing events: 1', { exact: true })).toBeVisible()

	await admin.context.delete(`/remote.php/dav/calendars/admin/${calendar}/`)
})
