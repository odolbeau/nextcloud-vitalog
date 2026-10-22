import { expect, test } from '@playwright/test'
import { ADMIN, client, exportCalendar, inDays } from '../helpers.js'

let admin
// un calendrier supprimé reste en corbeille avec son URI : on en crée un nouveau à chaque exécution
const OTHER = `vitalog-e2e-import-${Date.now()}`
// calendrier dédié à l'import ponctuel : « personal » contient déjà l'événement d'accueil de Nextcloud
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

async function unlink(deleteEvents = false) {
	const response = await admin.post('settings/unlink', { deleteEvents })
	expect(response.status()).toBe(200)
}

async function configure(calendarUri, importFromCalendar, importExisting = false) {
	const response = await admin.put('settings', { calendarUri, importFromCalendar, importExisting })
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

test('an event added to the target calendar becomes an appointment that stays linked to its event', async () => {
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

test('an event that is already over is imported too', async () => {
	await configure('personal', true)

	await addEvent('personal', { startsAt: inDays(-10), summary: 'Radio' })

	const [appointment] = await admin.list('appointments')
	expect(appointment).toMatchObject({ reason: 'Radio', synced: true })
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

test('choosing a calendar can import the events it already contains, once', async () => {
	const place = await admin.create('places', { name: 'Cabinet Martin' })
	await addEvent(EXISTING, { startsAt: inDays(5), summary: 'Futur', location: 'Cabinet Martin' })
	await addEvent(EXISTING, { startsAt: inDays(-30), summary: 'Passé' })
	await addEvent(EXISTING, { startsAt: inDays(6), summary: 'Récurrent', extra: ['RRULE:FREQ=WEEKLY;COUNT=3'] })
	await addEvent(OTHER, { startsAt: inDays(7), summary: 'Autre calendrier' })
	// un rendez-vous Vitalog déjà synchronisé ne doit pas revenir en doublon
	await admin.create('appointments', { startsAt: inDays(8), reason: 'Déjà dans Vitalog' })

	// sans demander l'import ponctuel, rien n'est créé
	let settings = await configure(EXISTING, false, false)
	expect(settings.imported).toBe(0)
	expect(await admin.list('appointments')).toHaveLength(1)

	// l'import ponctuel se demande à la liaison : il faut délier (en gardant les événements) puis relier
	await unlink()
	settings = await configure(EXISTING, false, true)
	expect(settings.imported).toBe(2)
	// l'import ponctuel n'est pas mémorisé, ni l'import à chaque ajout
	expect(settings.importFromCalendar).toBe(false)
	const reasons = (await admin.list('appointments')).map((a) => a.reason).sort()
	expect(reasons).toEqual(['Déjà dans Vitalog', 'Futur', 'Passé'])
	expect((await admin.list('appointments')).find((a) => a.reason === 'Futur')).toMatchObject({ placeId: place.id, synced: true })

	// rejouer ne crée rien de plus
	await unlink()
	expect((await configure(EXISTING, false, true)).imported).toBe(0)
	expect(await admin.list('appointments')).toHaveLength(3)
})

test('editing an imported event changes only what Vitalog manages', async () => {
	const startsAt = inDays(5, 14)
	// heure de Paris telle qu'une app Calendrier l'écrit
	const local = (timestamp) => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Paris', dateStyle: 'short', timeStyle: 'medium' }).format(timestamp * 1000).replace(/[-: ]/g, '').replace(/^(\d{8})/, '$1T')
	const uid = `import-tz-${Date.now()}`
	const ics = [
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Vitalog e2e//EN',
		'BEGIN:VTIMEZONE',
		'TZID:Europe/Paris',
		'BEGIN:DAYLIGHT',
		'TZOFFSETFROM:+0100',
		'TZOFFSETTO:+0200',
		'TZNAME:CEST',
		'DTSTART:19700329T020000',
		'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
		'END:DAYLIGHT',
		'BEGIN:STANDARD',
		'TZOFFSETFROM:+0200',
		'TZOFFSETTO:+0100',
		'TZNAME:CET',
		'DTSTART:19701025T030000',
		'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
		'END:STANDARD',
		'END:VTIMEZONE',
		'BEGIN:VEVENT',
		`UID:${uid}`,
		`DTSTAMP:${utc(Math.floor(Date.now() / 1000))}`,
		`DTSTART;TZID=Europe/Paris:${local(startsAt)}`,
		`DTEND;TZID=Europe/Paris:${local(startsAt + 1800)}`,
		'SUMMARY:Contrôle',
		'ATTENDEE:mailto:dr@example.org',
		'X-KEEP:yes',
		'END:VEVENT',
		'END:VCALENDAR',
		'',
	].join('\r\n')
	const response = await admin.context.put(`/remote.php/dav/calendars/${ADMIN.user}/${EXISTING}/user-${uid}.ics`, { headers: { 'Content-Type': 'text/calendar' }, data: ics })
	expect(response.status(), 'add event').toBe(201)
	added.push([EXISTING, uid])

	expect((await configure(EXISTING, false, true)).imported).toBe(1)
	const [appointment] = await admin.list('appointments')
	await admin.put(`appointments/${appointment.id}`, { startsAt: appointment.startsAt, duration: appointment.duration, reason: 'Contrôle', notes: 'Apporter la carte' })

	const event = (await exportCalendar(ADMIN, EXISTING)).replace(/\r?\n[ \t]/g, '')
	// ce que Vitalog gère est à jour...
	expect(event).toContain('DESCRIPTION:Apporter la carte')
	expect(event).toContain('SEQUENCE:1')
	// ... et le reste est resté tel quel : fuseau d'origine, participant, propriété d'une autre application
	expect(event).toContain(`DTSTART;TZID=Europe/Paris:${local(startsAt)}`)
	expect(event).toContain('ATTENDEE:mailto:dr@example.org')
	expect(event).toContain('X-KEEP:yes')
	expect(event).not.toContain('@vitalog')
})
