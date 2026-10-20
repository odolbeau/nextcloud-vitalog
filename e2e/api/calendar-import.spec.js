import { expect, test } from '@playwright/test'
import { ADMIN, client, exportCalendar, inDays } from '../helpers.js'

let admin
// un calendrier supprimé reste en corbeille avec son URI : on en crée un nouveau à chaque exécution
const OTHER = `vitalog-e2e-import-${Date.now()}`

test.beforeAll(async () => {
	admin = await client()
	const created = await admin.context.fetch(`/remote.php/dav/calendars/${ADMIN.user}/${OTHER}/`, { method: 'MKCALENDAR' })
	expect(created.status()).toBe(201)
})
test.afterAll(async () => {
	await admin.reset()
	await admin.context.delete(`/remote.php/dav/calendars/${ADMIN.user}/${OTHER}/`)
	await admin.dispose()
})
test.beforeEach(async () => {
	await admin.reset()
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
	return uid
}

async function configure(calendarUri, importFromCalendar) {
	const response = await admin.put('settings', { calendarUri, importFromCalendar })
	expect(response.status()).toBe(200)
	return admin.data(response)
}

test('importing is off by default and needs a target calendar', async () => {
	expect((await admin.data(await admin.get('settings'))).importFromCalendar).toBe(false)

	expect((await configure(null, true)).importFromCalendar).toBe(false)
	expect((await configure('personal', true)).importFromCalendar).toBe(true)
	expect((await configure('personal', false)).importFromCalendar).toBe(false)
})

test('an event added while importing is off creates nothing', async () => {
	await configure('personal', false)

	await addEvent('personal', { startsAt: inDays(3), summary: 'Ignoré' })

	expect(await admin.list('appointments')).toHaveLength(0)
})

test('an event added to the target calendar becomes a planned appointment that stays linked to its event', async () => {
	const place = await admin.create('places', { name: 'Cabinet Martin' })
	await configure('personal', true)
	const startsAt = inDays(6, 15)

	const uid = await addEvent('personal', {
		startsAt,
		durationMinutes: 45,
		summary: 'Dentiste',
		location: 'Cabinet Martin\\, 1 rue de la Paix',
		description: 'Apporter la carte',
	})

	const [appointment] = await admin.list('appointments')
	expect(appointment).toMatchObject({
		startsAt,
		duration: 45,
		reason: 'Dentiste',
		notes: 'Apporter la carte',
		placeId: place.id,
		practitionerId: null,
		status: 'planned',
		synced: true,
	})

	// modifier le rendez-vous met à jour l'événement existant (pas de doublon)
	const updated = await admin.put(`appointments/${appointment.id}`, { ...appointment, reason: 'Dentiste (détartrage)' })
	expect(updated.status()).toBe(200)
	const ics = (await exportCalendar(ADMIN, 'personal')).replace(/\r?\n[ \t]/g, '')
	expect(ics.split(`UID:${uid}`)).toHaveLength(2)
	expect(ics).toContain('SUMMARY:Dentiste (détartrage)')

	// et le supprimer supprime l'événement
	await admin.delete(`appointments/${appointment.id}`)
	expect(await exportCalendar(ADMIN, 'personal')).not.toContain(uid)
})

test('an event that is already over becomes a done appointment', async () => {
	await configure('personal', true)

	await addEvent('personal', { startsAt: inDays(-10), summary: 'Radio' })

	const [appointment] = await admin.list('appointments')
	expect(appointment).toMatchObject({ reason: 'Radio', status: 'done' })
})

test('events in another calendar, recurring events and Vitalog\'s own events are ignored', async () => {
	await configure('personal', true)

	await addEvent(OTHER, { startsAt: inDays(3), summary: 'Autre calendrier' })
	await addEvent('personal', { startsAt: inDays(4), summary: 'Récurrent', extra: ['RRULE:FREQ=WEEKLY;COUNT=3'] })
	expect(await admin.list('appointments')).toHaveLength(0)

	// un rendez-vous créé dans Vitalog crée son événement, qui ne doit pas revenir en doublon
	await admin.create('appointments', { startsAt: inDays(5), reason: 'Depuis Vitalog' })
	expect(await admin.list('appointments')).toHaveLength(1)
})
