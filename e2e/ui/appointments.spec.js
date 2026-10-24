import { expect, test } from '@playwright/test'
import { client, inDays, VITALOG_CALENDAR } from '../helpers.js'
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
	await page.getByRole('button', { name: 'Edit', exact: true }).click()
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

test('the usual place of the practitioner fills the place, unless one was picked by hand', async ({ page }) => {
	const port = await admin.create('places', { name: 'Cabinet du Port' })
	const clinic = await admin.create('places', { name: 'Clinique Sud' })
	await admin.create('places', { name: 'Hôpital Nord' })
	await admin.create('practitioners', { name: 'Dr Martin', placeId: port.id })
	await admin.create('practitioners', { name: 'Dr Durand', placeId: clinic.id })
	await admin.create('practitioners', { name: 'Dr Petit' })
	await openApp(page)
	await page.getByRole('button', { name: 'New appointment' }).first().click()

	// pré-rempli, puis suivi tant qu'il vient d'un praticien
	await choose(page, 'Practitioner', 'Dr Martin')
	await expect(selected(page, 'Place')).toContainText('Cabinet du Port')
	await choose(page, 'Practitioner', 'Dr Durand')
	await expect(selected(page, 'Place')).toContainText('Clinique Sud')
	await choose(page, 'Practitioner', 'Dr Petit')
	await expect(selected(page, 'Place')).not.toContainText('Clinique Sud')

	// choisi à la main : plus jamais remplacé
	await choose(page, 'Place', 'Hôpital Nord')
	await choose(page, 'Practitioner', 'Dr Martin')
	await expect(selected(page, 'Place')).toContainText('Hôpital Nord')
	await dialog(page).getByRole('button', { name: 'Save' }).click()
	await expect(dialog(page)).toHaveCount(0)
	expect((await admin.list('appointments'))[0].placeId).not.toBe(port.id)
})

test('choosing a practitioner from the details fills an empty place only', async ({ page }) => {
	const port = await admin.create('places', { name: 'Cabinet du Port' })
	const clinic = await admin.create('places', { name: 'Clinique Sud' })
	const martin = await admin.create('practitioners', { name: 'Dr Martin', placeId: port.id })
	const without = await admin.create('appointments', { startsAt: inDays(5), reason: 'Sans lieu' })
	const elsewhere = await admin.create('appointments', { startsAt: inDays(6), reason: 'Ailleurs', placeId: clinic.id })
	await openApp(page)
	const details = page.getByRole('article')

	for (const reason of ['Sans lieu', 'Ailleurs']) {
		await items(page).filter({ hasText: reason }).click()
		await details.getByRole('button', { name: 'Choose a practitioner' }).click()
		await page.getByRole('option').filter({ hasText: 'Dr Martin' }).click()
		await expect(details).toContainText('Dr Martin')
	}

	const stored = Object.fromEntries((await admin.list('appointments')).map((a) => [a.id, a]))
	expect(stored[without.id]).toMatchObject({ practitionerId: martin.id, placeId: port.id })
	expect(stored[elsewhere.id]).toMatchObject({ practitionerId: martin.id, placeId: clinic.id })
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

test('practitioners are offered by their latest appointment first, then by name', async ({ page }) => {
	const old = await admin.create('practitioners', { name: 'Dr Ancien' })
	const recent = await admin.create('practitioners', { name: 'Dr Récent' })
	await admin.create('practitioners', { name: 'Dr Zéro' })
	await admin.create('practitioners', { name: 'Dr Aucun' })
	// la date du rendez-vous compte, pas l'ordre de saisie : celui de Dr Ancien est enregistré en dernier
	await admin.create('appointments', { startsAt: inDays(10), practitionerId: recent.id })
	await admin.create('appointments', { startsAt: inDays(-30), practitionerId: old.id })
	await openApp(page)

	await page.getByRole('button', { name: 'New appointment' }).first().click()
	await dialog(page).getByRole('combobox', { name: 'Practitioner' }).click()
	await expect(page.getByRole('option')).toHaveText(['Dr Récent', 'Dr Ancien', 'Dr Aucun', 'Dr Zéro'])
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

test('the title can be edited from the details', async ({ page }) => {
	await admin.create('appointments', { startsAt: inDays(5), reason: 'Contrôle' })
	await openApp(page)
	await items(page).first().click()
	const details = page.getByRole('article')

	await details.getByRole('button', { name: 'Edit the title' }).click()
	await expect(details.getByLabel('Reason')).toHaveValue('Contrôle')
	await details.getByLabel('Reason').fill('Contrôle annuel')
	await details.getByLabel('Reason').press('Enter')
	await expect(details.getByRole('heading', { name: 'Contrôle annuel' })).toBeVisible()
	expect((await admin.list('appointments'))[0].reason).toBe('Contrôle annuel')

	// Échap annule
	await details.getByRole('button', { name: 'Edit the title' }).click()
	await details.getByLabel('Reason').fill('Brouillon')
	await details.getByLabel('Reason').press('Escape')
	await expect(details.getByRole('heading', { name: 'Contrôle annuel' })).toBeVisible()

	// un titre vide est supprimé
	await details.getByRole('button', { name: 'Edit the title' }).click()
	await details.getByLabel('Reason').fill('')
	await details.getByRole('button', { name: 'Save' }).click()
	await expect(details.getByRole('heading', { name: 'Medical appointment' })).toBeVisible()
	expect((await admin.list('appointments'))[0].reason).toBeNull()
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
	await expect.poll(async () => (await admin.list('appointments'))[0].price).toBe(2550)

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
	await expect.poll(async () => (await admin.list('appointments'))[0].price).toBe(0)

	// vider le champ supprime le prix
	await details.getByRole('button', { name: 'Edit the price' }).click()
	await details.getByLabel('Price').fill('')
	await details.getByRole('button', { name: 'Save' }).click()
	await expect(details.getByRole('button', { name: 'Add a price' })).toBeVisible()
	await expect.poll(async () => (await admin.list('appointments'))[0].price).toBeNull()
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

test('the list shows a heading at each change of year, kept in view while scrolling', async ({ page }) => {
	const year = new Date().getFullYear()
	const at = (y, month) => Math.floor(new Date(y, month, 15, 10).getTime() / 1000)
	for (let i = 0; i < 15; i++) {
		await admin.create('appointments', { startsAt: at(year - 1, i % 12) + i * 60, reason: `Suivi ${i}` })
	}
	await admin.create('appointments', { startsAt: at(year - 3, 5), reason: 'Très ancien' })
	await admin.create('appointments', { startsAt: inDays(-1), reason: 'Hier' })
	await openApp(page)
	await nav(page, 'past').click()

	const headings = page.getByRole('list', { name: 'Past appointments' }).getByRole('heading')
	const yesterday = new Date(Date.now() - 86400 * 1000).getFullYear()
	await expect(headings).toHaveText([...new Set([yesterday, year - 1, year - 3])].map(String))
	await expect(page.getByRole('list', { name: String(year - 3) }).getByRole('listitem')).toHaveCount(1)

	// au bas des rendez-vous d'une année, son intertitre reste affiché au-dessus d'eux
	const lastYear = page.getByRole('list', { name: String(year - 1) })
	await lastYear.locator('[data-test^="appointment-"]').last().scrollIntoViewIfNeeded()
	const heading = await page.locator(`[data-test="year-${year - 1}"] h3`).boundingBox()
	const first = await lastYear.locator('[data-test^="appointment-"]').first().boundingBox()
	expect(first.y).toBeLessThan(heading.y)
	await expect(page.locator(`[data-test="year-${year - 1}"] h3`)).toBeInViewport()

	// la recherche retire les années sans résultat
	await page.getByLabel('Search appointments').fill('Très ancien')
	await expect(headings).toHaveText([String(year - 3)])
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

test('the details link to the appointment in the Vitalog calendar of the Calendar app', async ({ page }) => {
	const appointment = await admin.create('appointments', { startsAt: inDays(3), reason: 'Bilan' })
	await openApp(page)
	await items(page).first().click()
	await expect(page.getByRole('article')).toContainText('In your Vitalog calendar')

	const link = page.getByRole('article').getByRole('link', { name: 'Open in Calendar' })
	await expect(link).not.toHaveAttribute('target', '_blank')
	const href = await link.getAttribute('href')
	const [, object] = href.match(/\/apps\/calendar\/timeGridDay\/\d{4}-\d{2}-\d{2}\/edit\/popover\/([^/]+)\/next$/)
	expect(Buffer.from(decodeURIComponent(object), 'base64').toString()).toBe(`/remote.php/dav/calendars/admin/${VITALOG_CALENDAR}/appointment-${appointment.id}.ics`)
})

test('the events of a calendar can be imported from the settings', async ({ page }) => {
	const uid = `ui-existing-${Date.now()}`
	// calendrier dédié : « personal » contient déjà l'événement d'accueil de Nextcloud
	const calendar = `vitalog-e2e-ui-existing-${Date.now()}`
	expect((await admin.context.fetch(`/remote.php/dav/calendars/admin/${calendar}/`, { method: 'MKCALENDAR' })).status()).toBe(201)
	const start = new Date(Date.now() + 4 * 86400_000).toISOString().replace(/[-:]/g, '').replace(/\.\d+Z$/, 'Z')
	const ics = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Vitalog e2e//EN', 'BEGIN:VEVENT', `UID:${uid}`, `DTSTAMP:${start}`, `DTSTART:${start}`, `DTEND:${start}`, 'SUMMARY:Déjà au calendrier', 'END:VEVENT', 'END:VCALENDAR', ''].join('\r\n')
	expect((await admin.context.put(`/remote.php/dav/calendars/admin/${calendar}/${uid}.ics`, { headers: { 'Content-Type': 'text/calendar' }, data: ics })).status()).toBe(201)

	try {
		await openApp(page)
		await nav(page, 'settings').click()
		const settings = dialog(page)
		await expect(settings.getByRole('heading', { name: 'Your Vitalog calendar' })).toBeVisible()
		await expect(settings.getByRole('button', { name: 'Import', exact: true })).toBeDisabled()
		await choose(page, 'Calendar to import', calendar)
		await settings.getByRole('button', { name: 'Import', exact: true }).click()
		await expect(dialog(page)).toHaveCount(0)

		await expect(items(page)).toHaveCount(1)
		await expect(items(page).first()).toContainText('Déjà au calendrier')
		await expect(page.getByText('Appointments imported: 1', { exact: true })).toBeVisible()
	} finally {
		await admin.context.delete(`/remote.php/dav/calendars/admin/${calendar}/`)
	}
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
