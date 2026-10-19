import { expect, test } from '@playwright/test'
import { ADMIN, client, exportCalendar, inDays, vitalogEvents } from '../helpers.js'

let admin
// un calendrier supprimé reste en corbeille avec son URI : on en crée un nouveau à chaque exécution
const SECOND = `vitalog-e2e-${Date.now()}`

test.beforeAll(async () => {
	admin = await client()
	// calendrier secondaire pour tester le déplacement des événements
	const created = await admin.context.fetch(`/remote.php/dav/calendars/${ADMIN.user}/${SECOND}/`, { method: 'MKCALENDAR' })
	expect(created.status()).toBe(201)
})
test.afterAll(async () => {
	await admin.reset()
	await admin.context.delete(`/remote.php/dav/calendars/${ADMIN.user}/${SECOND}/`)
	await admin.dispose()
})
test.beforeEach(async () => {
	await admin.reset()
})

const events = async (calendar = 'personal') => vitalogEvents(await exportCalendar(ADMIN, calendar))
const setCalendar = async (calendarUri) => {
	const response = await admin.put('settings', { calendarUri })
	expect(response.status(), `select calendar ${calendarUri}`).toBe(200)
	return admin.data(response)
}

test('settings list the writable calendars and start with sync disabled', async () => {
	const settings = await admin.data(await admin.get('settings'))

	expect(settings.calendarUri).toBeNull()
	expect(settings.calendars.map((c) => c.uri)).toEqual(expect.arrayContaining(['personal', SECOND]))
	expect(settings.calendars.map((c) => c.uri)).not.toContain('contact_birthdays')
})

test('rejects an unknown calendar', async () => {
	const response = await admin.put('settings', { calendarUri: 'does-not-exist' })

	expect(response.status()).toBe(400)
	expect((await admin.data(await admin.get('settings'))).calendarUri).toBeNull()
})

test('without a target calendar nothing is written', async () => {
	const appointment = await admin.create('appointments', { startsAt: inDays(2) })

	expect(appointment.synced).toBe(false)
	expect(await events()).toHaveLength(0)
})

test('creating, editing, cancelling and deleting keeps the calendar in sync', async () => {
	await setCalendar('personal')
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
	const place = await admin.create('places', { name: 'Cabinet', address: '1 rue de la Paix, Paris' })
	const startsAt = inDays(5, 14)

	// création
	const appointment = await admin.create('appointments', {
		startsAt, duration: 45, practitionerId: practitioner.id, placeId: place.id, reason: 'Détartrage; contrôle', notes: 'Prévoir la carte vitale',
	})
	expect(appointment.synced).toBe(true)
	let [event] = await events()
	expect(event).toContain('SUMMARY:Dr Martin – Détartrage\\; contrôle')
	expect(event).toContain('LOCATION:Cabinet\\, 1 rue de la Paix\\, Paris')
	expect(event).toContain('DESCRIPTION:Prévoir la carte vitale')
	expect(event).toContain('TRIGGER:-P1D')
	expect(event).toMatch(new RegExp(`DTSTART:${utc(startsAt)}`))
	expect(event).toMatch(new RegExp(`DTEND:${utc(startsAt + 45 * 60)}`))

	// déplacement : même événement, nouvelle heure
	await admin.put(`appointments/${appointment.id}`, {
		startsAt: startsAt + 7200, duration: 45, practitionerId: practitioner.id, placeId: place.id, reason: 'Détartrage; contrôle',
	})
	const moved = await events()
	expect(moved).toHaveLength(1)
	expect(moved[0]).toMatch(new RegExp(`DTSTART:${utc(startsAt + 7200)}`))

	// annulation : l'événement disparaît
	await admin.put(`appointments/${appointment.id}`, { startsAt, status: 'cancelled' })
	expect(await events()).toHaveLength(0)
	expect((await admin.data(await admin.get(`appointments/${appointment.id}`))).synced).toBe(false)

	// replanification : il revient
	await admin.put(`appointments/${appointment.id}`, { startsAt, status: 'planned' })
	expect(await events()).toHaveLength(1)

	// suppression
	await admin.delete(`appointments/${appointment.id}`)
	expect(await events()).toHaveLength(0)
})

test('renaming a practitioner or a place updates the event', async () => {
	await setCalendar('personal')
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
	const place = await admin.create('places', { name: 'Cabinet' })
	await admin.create('appointments', { startsAt: inDays(4), practitionerId: practitioner.id, placeId: place.id, reason: 'Suivi' })

	await admin.put(`practitioners/${practitioner.id}`, { name: 'Dr Durand' })
	await admin.put(`places/${place.id}`, { name: 'Clinique', address: 'Lyon' })

	const [event] = await events()
	expect(event).toContain('SUMMARY:Dr Durand – Suivi')
	expect(event).toContain('LOCATION:Clinique\\, Lyon')
})

test('deleting a practitioner updates the event summary', async () => {
	await setCalendar('personal')
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
	await admin.create('appointments', { startsAt: inDays(4), practitionerId: practitioner.id, reason: 'Suivi' })

	await admin.delete(`practitioners/${practitioner.id}`)

	const [event] = await events()
	expect(event).toContain('SUMMARY:Suivi')
	expect(event).not.toContain('Dr Martin')
})

test('choosing a calendar syncs existing appointments, switching moves them, disabling removes them', async () => {
	await admin.create('appointments', { startsAt: inDays(3), reason: 'A' })
	await admin.create('appointments', { startsAt: inDays(6), reason: 'B' })
	await admin.create('appointments', { startsAt: inDays(8), reason: 'Annulé', status: 'cancelled' })
	expect(await events()).toHaveLength(0)

	await setCalendar('personal')
	expect(await events()).toHaveLength(2)

	await setCalendar(SECOND)
	expect(await events('personal')).toHaveLength(0)
	expect(await events(SECOND)).toHaveLength(2)

	await setCalendar(null)
	expect(await events(SECOND)).toHaveLength(0)
	expect((await admin.list('appointments')).every((a) => !a.synced)).toBe(true)
})

function utc(timestamp) {
	return new Date(timestamp * 1000).toISOString().replace(/[-:]/g, '').replace(/\.\d+Z$/, 'Z')
}
