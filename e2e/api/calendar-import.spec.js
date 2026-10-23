import { expect, test } from '@playwright/test'
import { ADMIN, client, exportCalendar, inDays, VITALOG_CALENDAR } from '../helpers.js'

let admin
// un calendrier supprimé reste en corbeille avec son URI : on en crée un nouveau à chaque exécution
const OTHER = `vitalog-e2e-import-${Date.now()}`
// calendrier à importer : « personal » contient déjà l'événement d'accueil de Nextcloud
const EXISTING = `vitalog-e2e-existing-${Date.now()}`

test.beforeAll(async () => {
	admin = await client()
	const created = await admin.context.fetch(`/remote.php/dav/calendars/${ADMIN.user}/${OTHER}/`, { method: 'MKCALENDAR' })
	expect(created.status()).toBe(201)
	const existing = await admin.context.fetch(`/remote.php/dav/calendars/${ADMIN.user}/${EXISTING}/`, { method: 'MKCALENDAR' })
	expect(existing.status()).toBe(201)
})
test.afterAll(async () => {
	await admin.reset()
	await admin.context.delete(`/remote.php/dav/calendars/${ADMIN.user}/${OTHER}/`)
	await admin.context.delete(`/remote.php/dav/calendars/${ADMIN.user}/${EXISTING}/`)
	await admin.dispose()
})
test.beforeEach(async () => {
	await admin.reset()
})
// les événements « utilisateur » ajoutés par les tests ne sont rattachés à aucun rendez-vous : on les retire, sinon ils
// s'accumulent dans le calendrier « personal » du compte de test (et seraient importés au prochain lien de ce calendrier)
const added = []
test.afterEach(async () => {
	for (const [calendar, uid] of added.splice(0)) {
		await admin.context.delete(`/remote.php/dav/calendars/${ADMIN.user}/${calendar}/user-${uid}.ics`)
	}
})

const utc = (timestamp) => new Date(timestamp * 1000).toISOString().replace(/[-:]/g, '').replace(/\.\d+Z$/, 'Z')
let counter = 0

/** Ajoute un événement « utilisateur » (pas créé par Vitalog) dans un calendrier, comme le ferait l'app Calendrier. */
async function addEvent(calendar, { startsAt, durationMinutes = 60, summary, location, description, extra = [] }) {
	const uid = `import-${Date.now()}-${counter++}`
	const ics = [
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Vitalog e2e//EN',
		'BEGIN:VEVENT',
		`UID:${uid}`,
		`DTSTAMP:${utc(Math.floor(Date.now() / 1000))}`,
		`DTSTART:${utc(startsAt)}`,
		`DTEND:${utc(startsAt + durationMinutes * 60)}`,
		summary && `SUMMARY:${summary}`,
		location && `LOCATION:${location}`,
		description && `DESCRIPTION:${description}`,
		...extra,
		'END:VEVENT',
		'END:VCALENDAR',
	].filter(Boolean).join('\r\n') + '\r\n'
	const response = await admin.context.put(`/remote.php/dav/calendars/${ADMIN.user}/${calendar}/user-${uid}.ics`, {
		headers: { 'Content-Type': 'text/calendar' },
		data: ics,
	})
	expect(response.status(), 'add event').toBe(201)
	added.push([calendar, uid])

	return uid
}

async function importFrom(calendarUri) {
	const response = await admin.post('settings/import', { calendarUri })
	expect(response.status(), await response.text()).toBe(200)
	return (await admin.data(response)).imported
}

test('the calendars to import from are the user\'s own, not the Vitalog one nor birthdays', async () => {
	const { calendars } = await admin.data(await admin.get('settings'))
	const uris = calendars.map((c) => c.uri)

	expect(uris).toEqual(expect.arrayContaining(['personal', OTHER, EXISTING]))
	expect(uris).not.toContain(VITALOG_CALENDAR)
	expect(uris).not.toContain('contact_birthdays')
})

test('importing a calendar creates an appointment per event, past ones included, once', async () => {
	const place = await admin.create('places', { name: 'Cabinet Martin' })
	await addEvent(EXISTING, { startsAt: inDays(6, 15), durationMinutes: 45, summary: 'Dentiste', location: 'Cabinet Martin\\, 1 rue de la Paix', description: 'Apporter la carte' })
	await addEvent(EXISTING, { startsAt: inDays(-2000), summary: 'Passé' })
	await addEvent(EXISTING, { startsAt: inDays(6), summary: 'Récurrent', extra: ['RRULE:FREQ=WEEKLY;COUNT=3'] })
	await addEvent(OTHER, { startsAt: inDays(7), summary: 'Autre calendrier' })

	expect(await importFrom(EXISTING)).toBe(2)
	const appointments = await admin.list('appointments')
	expect(appointments.map((a) => a.reason).sort()).toEqual(['Dentiste', 'Passé'])
	expect(appointments.find((a) => a.reason === 'Dentiste')).toMatchObject({ startsAt: inDays(6, 15), duration: 45, notes: 'Apporter la carte', placeId: place.id })

	// rejouer ne crée rien de plus
	expect(await importFrom(EXISTING)).toBe(0)
	expect(await admin.list('appointments')).toHaveLength(2)
})

test('an unknown calendar is refused', async () => {
	expect((await admin.post('settings/import', { calendarUri: 'does-not-exist' })).status()).toBe(400)
	expect((await admin.post('settings/import', { calendarUri: VITALOG_CALENDAR })).status()).toBe(400)
})

test('importing is one-shot and one-way: later events and changes are not synchronised', async () => {
	const uid = await addEvent(EXISTING, { startsAt: inDays(3), summary: 'Contrôle' })
	expect(await importFrom(EXISTING)).toBe(1)

	// un événement ajouté ensuite n'est pas importé tout seul
	await addEvent(EXISTING, { startsAt: inDays(4), summary: 'Ajouté après' })
	expect(await admin.list('appointments')).toHaveLength(1)

	// modifier le rendez-vous ne touche pas à l'événement d'origine
	const [appointment] = await admin.list('appointments')
	await admin.put(`appointments/${appointment.id}`, { ...appointment, reason: 'Contrôle annuel' })
	const source = (await exportCalendar(ADMIN, EXISTING)).replace(/\r?\n[ \t]/g, '')
	expect(source).toContain(`UID:${uid}`)
	expect(source).toContain('SUMMARY:Contrôle\r')
	expect(source).not.toContain('Contrôle annuel')
})
