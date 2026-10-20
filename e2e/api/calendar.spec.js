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
async function setCalendar(calendarUri) {
	const response = await admin.put('settings', { calendarUri })
	expect(response.status(), `link calendar ${calendarUri}`).toBe(200)
	return admin.data(response)
}
async function unlink(deleteEvents) {
	const response = await admin.post('settings/unlink', { deleteEvents })
	expect(response.status(), 'unlink').toBe(200)
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
		startsAt,
		duration: 45,
		practitionerId: practitioner.id,
		placeId: place.id,
		reason: 'Détartrage; contrôle',
		notes: 'Prévoir la carte vitale',
	})
	expect(appointment.synced).toBe(true)
	const [event] = await events()
	expect(event).toContain('SUMMARY:Dr Martin – Détartrage\\; contrôle')
	expect(event).toContain('LOCATION:Cabinet\\, 1 rue de la Paix\\, Paris')
	expect(event).toContain('DESCRIPTION:Prévoir la carte vitale')
	expect(event).toContain('TRIGGER:-P1D')
	expect(event).toMatch(new RegExp(`DTSTART:${utc(startsAt)}`))
	expect(event).toMatch(new RegExp(`DTEND:${utc(startsAt + 45 * 60)}`))

	// déplacement : même événement, nouvelle heure
	await admin.put(`appointments/${appointment.id}`, {
		startsAt: startsAt + 7200,
		duration: 45,
		practitionerId: practitioner.id,
		placeId: place.id,
		reason: 'Détartrage; contrôle',
	})
	const moved = await events()
	expect(moved).toHaveLength(1)
	expect(moved[0]).toMatch(new RegExp(`DTSTART:${utc(startsAt + 7200)}`))

	// suppression
	await admin.delete(`appointments/${appointment.id}`)
	expect(await events()).toHaveLength(0)
})

test('renaming a practitioner or a place does not touch the events already written', async () => {
	await setCalendar('personal')
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
	const place = await admin.create('places', { name: 'Cabinet' })
	await admin.create('appointments', { startsAt: inDays(4), practitionerId: practitioner.id, placeId: place.id, reason: 'Suivi' })
	const before = await events()

	await admin.put(`practitioners/${practitioner.id}`, { name: 'Dr Durand' })
	await admin.put(`places/${place.id}`, { name: 'Clinique', address: 'Lyon' })

	expect(await events()).toEqual(before)
	expect(before[0]).toContain('SUMMARY:Dr Martin – Suivi')
	expect(before[0]).toContain('LOCATION:Cabinet')
})

test('labels are written as event categories when the appointment is saved, and renaming or deleting them leaves the events alone', async () => {
	await setCalendar('personal')
	const ankle = await admin.create('labels', { name: 'Cheville' })
	const other = await admin.create('labels', { name: 'Sport, loisirs' })
	const appointment = await admin.create('appointments', { startsAt: inDays(4), reason: 'Entorse', labelIds: [ankle.id, other.id] })

	expect((await events())[0]).toContain('CATEGORIES:Cheville,Sport\\, loisirs')

	await admin.put(`labels/${ankle.id}`, { name: 'Cheville gauche' })
	await admin.delete(`labels/${other.id}`)
	expect((await events())[0]).toContain('CATEGORIES:Cheville,Sport\\, loisirs')

	// l'événement se met à jour quand c'est le rendez-vous lui-même qui est enregistré
	const saved = await admin.put(`appointments/${appointment.id}`, { startsAt: appointment.startsAt, reason: 'Entorse', labelIds: [ankle.id] })
	expect(saved.status()).toBe(200)
	expect((await events())[0]).toContain('CATEGORIES:Cheville gauche\r')
})

test('deleting a practitioner leaves the event summary as it was', async () => {
	await setCalendar('personal')
	const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
	await admin.create('appointments', { startsAt: inDays(4), practitionerId: practitioner.id, reason: 'Suivi' })

	await admin.delete(`practitioners/${practitioner.id}`)

	expect((await events())[0]).toContain('SUMMARY:Dr Martin – Suivi')
})

test('linking creates the events of the existing appointments, and another calendar cannot be chosen while one is linked', async () => {
	await admin.create('appointments', { startsAt: inDays(3), reason: 'A' })
	await admin.create('appointments', { startsAt: inDays(6), reason: 'B' })
	await admin.create('appointments', { startsAt: inDays(8), reason: 'C' })
	expect(await events()).toHaveLength(0)

	await setCalendar('personal')
	expect(await events()).toHaveLength(3)

	// changer de calendrier, ou désactiver, passe par « délier »
	for (const calendarUri of [SECOND, null]) {
		const refused = await admin.put('settings', { calendarUri })
		expect(refused.status()).toBe(409)
	}
	expect((await admin.data(await admin.get('settings'))).calendarUri).toBe('personal')
	expect(await events('personal')).toHaveLength(3)
	expect(await events(SECOND)).toHaveLength(0)
})

test('unlinking can keep or delete the events, and linking the same calendar again finds them again', async () => {
	const first = await admin.create('appointments', { startsAt: inDays(3), reason: 'A' })
	await admin.create('appointments', { startsAt: inDays(6), reason: 'B' })
	await setCalendar('personal')
	const uidsOf = (list) => list.map((event) => /UID:(\S+)/.exec(event)[1]).sort()
	const original = uidsOf(await events('personal'))
	expect(original).toHaveLength(2)

	// délier en gardant : les événements restent, les rendez-vous ne sont plus synchronisés
	const settings = await unlink(false)
	expect(settings.calendarUri).toBeNull()
	expect(uidsOf(await events('personal'))).toEqual(original)
	expect((await admin.list('appointments')).every((a) => !a.synced)).toBe(true)

	// délié : modifier un rendez-vous n'écrit plus rien dans le calendrier
	await admin.put(`appointments/${first.id}`, { startsAt: first.startsAt, reason: 'A modifié' })
	expect((await events('personal')).join('')).not.toContain('A modifié')

	// lier l'autre calendrier y recrée les événements, avec les mêmes UID
	await setCalendar(SECOND)
	expect(uidsOf(await events(SECOND))).toEqual(original)

	// délier en supprimant : l'autre calendrier est vidé, le premier n'est pas touché
	await unlink(true)
	expect(await events(SECOND)).toHaveLength(0)
	expect(uidsOf(await events('personal'))).toEqual(original)
	expect((await admin.list('appointments')).every((a) => !a.synced)).toBe(true)

	// relier le premier : ses événements sont retrouvés (pas de doublon) et remis à jour
	await setCalendar('personal')
	expect(uidsOf(await events('personal'))).toEqual(original)
	expect((await events('personal')).join('')).toContain('SUMMARY:A modifié')
	expect((await admin.list('appointments')).every((a) => a.synced)).toBe(true)
})

test('unlinking deletes nothing but the events, and a calendar already unlinked can be unlinked again', async () => {
	const appointment = await admin.create('appointments', { startsAt: inDays(3), reason: 'A' })
	await setCalendar('personal')

	await unlink(true)
	await unlink(true)

	expect(await events('personal')).toHaveLength(0)
	expect(await admin.list('appointments')).toHaveLength(1)
	expect((await admin.list('appointments'))[0].id).toBe(appointment.id)
})

function utc(timestamp) {
	return new Date(timestamp * 1000).toISOString().replace(/[-:]/g, '').replace(/\.\d+Z$/, 'Z')
}
