import { expect, test } from '@playwright/test'
import { ADMIN, BOB, client, DEFAULT_CALENDAR, ensureBob, exportCalendar, inDays, VITALOG_CALENDAR, vitalogEvents } from '../helpers.js'

let admin
let bob
const CALENDAR = `/remote.php/dav/calendars/${ADMIN.user}/${VITALOG_CALENDAR}/`

test.beforeAll(async () => {
	await ensureBob()
	admin = await client()
	bob = await client(BOB)
})
test.afterAll(async () => {
	await admin.reset()
	await admin.dispose()
	await bob.dispose()
})
test.beforeEach(async () => {
	await admin.reset()
})

const events = async () => vitalogEvents(await exportCalendar(ADMIN, VITALOG_CALENDAR))
const utc = (timestamp) => new Date(timestamp * 1000).toISOString().replace(/[-:]/g, '').replace(/\.\d+Z$/, 'Z')
// heure de Paris telle qu'une app Calendrier l'écrit
const local = (timestamp) => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Paris', dateStyle: 'short', timeStyle: 'medium' }).format(timestamp * 1000).replace(/[-: ]/g, '').replace(/^(\d{8})/, '$1T')

async function setTimezone(value) {
	const response = await admin.context.put(`/ocs/v2.php/cloud/users/${ADMIN.user}`, { data: { key: 'timezone', value } })
	expect(response.status(), await response.text()).toBe(200)
}

test('the Vitalog calendar is listed in the user\'s calendars, read-only', async () => {
	const response = await admin.context.fetch(`/remote.php/dav/calendars/${ADMIN.user}/`, {
		method: 'PROPFIND',
		headers: { Depth: '1', 'Content-Type': 'application/xml' },
		data: '<d:propfind xmlns:d="DAV:"><d:prop><d:displayname/><d:current-user-privilege-set/></d:prop></d:propfind>',
	})
	expect(response.status()).toBe(207)
	const body = await response.text()
	const calendar = body.split('<d:response>').find((part) => part.includes(VITALOG_CALENDAR))
	expect(calendar).toContain(`<d:displayname>${DEFAULT_CALENDAR.name}</d:displayname>`)
	expect(calendar).toContain('<d:read/>')
	expect(calendar).not.toContain('<d:write/>')
})

test('the calendar is named and green by default, both set from Vitalog or from a calendar client', async () => {
	const appearance = async () => {
		const response = await admin.context.fetch(CALENDAR, {
			method: 'PROPFIND',
			headers: { Depth: '0', 'Content-Type': 'application/xml' },
			data: '<d:propfind xmlns:d="DAV:" xmlns:a="http://apple.com/ns/ical/"><d:prop><d:displayname/><a:calendar-color/></d:prop></d:propfind>',
		})
		expect(response.status()).toBe(207)
		const body = await response.text()
		return { name: body.match(/displayname>([^<]*)</)?.[1], color: body.match(/calendar-color[^>]*>([^<]*)</)?.[1] }
	}
	expect(await appearance()).toEqual(DEFAULT_CALENDAR)

	const set = await admin.put('settings/calendar', { name: '  Santé  ', color: '#B6469D' })
	expect(await admin.data(set)).toEqual({ calendarName: 'Santé', calendarColor: '#b6469d' })
	expect(await appearance()).toEqual({ name: 'Santé', color: '#b6469d' })
	expect((await admin.put('settings/calendar', { color: 'green' })).status()).toBe(400)
	expect((await admin.put('settings/calendar', { name: '  ' })).status()).toBe(400)

	// comme l'app Calendrier quand on y renomme le calendrier ou change sa couleur : les réglages de Vitalog suivent
	const patch = await admin.context.fetch(CALENDAR, {
		method: 'PROPPATCH',
		headers: { 'Content-Type': 'application/xml' },
		data: '<d:propertyupdate xmlns:d="DAV:" xmlns:a="http://apple.com/ns/ical/"><d:set><d:prop><d:displayname>⚕️ Rendez-vous</d:displayname><a:calendar-color>#0082C9FF</a:calendar-color></d:prop></d:set></d:propertyupdate>',
	})
	expect(patch.status()).toBe(207)
	expect(await patch.text()).not.toMatch(/HTTP\/1\.1 (4|5)\d\d/)
	expect(await appearance()).toEqual({ name: '⚕️ Rendez-vous', color: '#0082c9' })
	expect(await admin.data(await admin.get('settings'))).toMatchObject({ calendarName: '⚕️ Rendez-vous', calendarColor: '#0082c9' })

	// bob a les siens
	expect(await bob.data(await bob.get('settings'))).toMatchObject({ calendarName: DEFAULT_CALENDAR.name, calendarColor: DEFAULT_CALENDAR.color })
})

test('each appointment is an event, kept up to date with the appointment and its references', async () => {
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
	const place = await admin.create('places', { name: 'Cabinet', address: '1 rue de la Paix, Paris' })
	const label = await admin.create('labels', { name: 'Dents' })
	const startsAt = inDays(5, 14)
	const appointment = await admin.create('appointments', {
		startsAt,
		duration: 45,
		practitionerId: practitioner.id,
		placeId: place.id,
		reason: 'Détartrage; contrôle',
		notes: 'Prévoir la carte vitale',
		labelIds: [label.id],
	})

	const [event] = await events()
	expect(event).toContain(`UID:appointment-${appointment.id}@vitalog`)
	expect(event).toContain('SUMMARY:Dr Martin – Détartrage\\; contrôle')
	expect(event).toContain('LOCATION:Cabinet\\, 1 rue de la Paix\\, Paris')
	expect(event).toContain('DESCRIPTION:Prévoir la carte vitale')
	expect(event).toContain('CATEGORIES:Dents')
	expect(event).toContain('TRIGGER:-P1D')
	expect(event).toContain(`DTSTART:${utc(startsAt)}`)
	expect(event).toContain(`DTEND:${utc(startsAt + 45 * 60)}`)

	// déplacer le rendez-vous, renommer le praticien : l'événement suit, sans rien enregistrer d'autre
	await admin.put(`appointments/${appointment.id}`, { ...appointment, startsAt: startsAt + 7200 })
	await admin.put(`practitioners/${practitioner.id}`, { name: 'Dr Durand' })
	const [moved] = await events()
	expect(moved).toContain(`DTSTART:${utc(startsAt + 7200)}`)
	expect(moved).toContain('SUMMARY:Dr Durand – Détartrage\\; contrôle')

	// supprimer le rendez-vous retire l'événement
	await admin.delete(`appointments/${appointment.id}`)
	expect(await events()).toHaveLength(0)
})

test('a time range query returns only the appointments of that range', async () => {
	await admin.create('appointments', { startsAt: inDays(2), reason: 'Dans la semaine' })
	await admin.create('appointments', { startsAt: inDays(40), reason: 'Plus tard' })

	const response = await admin.context.fetch(CALENDAR, {
		method: 'REPORT',
		headers: { Depth: '1', 'Content-Type': 'application/xml' },
		data: `<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:prop><c:calendar-data/></d:prop>
			<c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT">
			<c:time-range start="${utc(inDays(0, 0))}" end="${utc(inDays(7, 0))}"/></c:comp-filter></c:comp-filter></c:filter></c:calendar-query>`,
	})
	expect(response.status()).toBe(207)
	const body = await response.text()
	expect(body).toContain('SUMMARY:Dans la semaine')
	expect(body).not.toContain('Plus tard')
})

test('events cannot be created, changed or deleted from a calendar client', async () => {
	const appointment = await admin.create('appointments', { startsAt: inDays(3), reason: 'Bilan' })
	const object = `${CALENDAR}appointment-${appointment.id}.ics`
	const ics = 'BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:x\r\nDTSTART:20300101T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n'

	expect((await admin.context.put(object, { headers: { 'Content-Type': 'text/calendar' }, data: ics })).status()).toBe(403)
	expect((await admin.context.put(`${CALENDAR}new.ics`, { headers: { 'Content-Type': 'text/calendar' }, data: ics })).status()).toBe(403)
	expect((await admin.context.delete(object)).status()).toBe(403)
	expect((await admin.context.get(object)).status()).toBe(200)
	expect(await admin.list('appointments')).toHaveLength(1)
})

test('another user sees neither the calendar nor its events', async () => {
	const appointment = await admin.create('appointments', { startsAt: inDays(3), reason: 'Secret' })

	expect((await bob.context.get(`${CALENDAR}appointment-${appointment.id}.ics`)).status()).toBe(404)
	// son propre calendrier « Vitalog » est vide
	expect(vitalogEvents(await exportCalendar(BOB, VITALOG_CALENDAR))).toHaveLength(0)
})

test('events are written in the timezone of the user, like the Calendar app does', async () => {
	await setTimezone('Europe/Paris')
	try {
		// 14:00 à Paris, quelle que soit la saison
		const startsAt = inDays(5, 14)
		await admin.create('appointments', { startsAt, duration: 45, reason: 'Contrôle' })

		const [event] = await events()
		expect(event).toContain(`DTSTART;TZID=Europe/Paris:${local(startsAt)}`)
		expect(event).toContain(`DTEND;TZID=Europe/Paris:${local(startsAt + 45 * 60)}`)
		expect(await exportCalendar(ADMIN, VITALOG_CALENDAR)).toContain('BEGIN:VTIMEZONE')
		expect(event).not.toMatch(/DTSTART:/)
	} finally {
		await setTimezone('UTC')
	}
})
