import { expect, test } from '@playwright/test'
import { BOB, client, ensureBob, inDays } from '../helpers.js'

let admin
let bob

test.beforeAll(async () => {
	await ensureBob()
	admin = await client()
	bob = await client(BOB)
})
test.afterAll(async () => {
	await admin.reset()
	await bob.reset()
	await admin.dispose()
	await bob.dispose()
})
test.beforeEach(async () => {
	await admin.reset()
	await bob.reset()
})

test.describe('practitioners', () => {
	test('create, read, update, delete', async () => {
		const created = await admin.create('practitioners', { name: 'Dr Martin', specialty: 'Dentiste', phone: '0102030405', notes: 'Rue du Port' })
		expect(created).toMatchObject({ name: 'Dr Martin', specialty: 'Dentiste', phone: '0102030405', notes: 'Rue du Port' })

		expect(await admin.list('practitioners')).toHaveLength(1)
		expect(await admin.data(await admin.get(`practitioners/${created.id}`))).toMatchObject({ id: created.id })

		const updated = await admin.data(await admin.put(`practitioners/${created.id}`, { name: 'Dr Durand' }))
		expect(updated).toMatchObject({ name: 'Dr Durand', specialty: null })

		expect((await admin.delete(`practitioners/${created.id}`)).status()).toBe(200)
		expect((await admin.get(`practitioners/${created.id}`)).status()).toBe(404)
	})

	test('name is required', async () => {
		const response = await admin.post('practitioners', { specialty: 'ORL' })
		expect(response.status()).toBe(400)
	})

	test('rejects blank or oversized values and trims the rest', async () => {
		expect((await admin.post('practitioners', { name: '   ' })).status()).toBe(400)
		expect((await admin.post('practitioners', { name: 'x'.repeat(256) })).status()).toBe(400)
		expect((await admin.post('practitioners', { name: 'Dr X', phone: '1'.repeat(65) })).status()).toBe(400)

		const created = await admin.create('practitioners', { name: '  Dr Trim  ', specialty: '   ', phone: ' 0102 ' })
		expect(created).toMatchObject({ name: 'Dr Trim', specialty: null, phone: '0102' })
		expect((await admin.put(`practitioners/${created.id}`, { name: '' })).status()).toBe(400)
		expect((await admin.data(await admin.get(`practitioners/${created.id}`))).name).toBe('Dr Trim')
	})
})

test.describe('places', () => {
	test('create, read, update, delete', async () => {
		const created = await admin.create('places', { name: 'Clinique du Parc', address: '1 rue de la Paix, Paris' })
		expect(created).toMatchObject({ name: 'Clinique du Parc', address: '1 rue de la Paix, Paris' })

		const updated = await admin.data(await admin.put(`places/${created.id}`, { name: 'Clinique', address: 'Lyon' }))
		expect(updated).toMatchObject({ name: 'Clinique', address: 'Lyon' })

		expect((await admin.delete(`places/${created.id}`)).status()).toBe(200)
		expect(await admin.list('places')).toEqual([])
	})

	test('rejects a blank or oversized name', async () => {
		expect((await admin.post('places', { name: ' ' })).status()).toBe(400)
		expect((await admin.post('places', { name: 'x'.repeat(256) })).status()).toBe(400)
	})
})

test.describe('appointments', () => {
	test('create, read, update, delete', async () => {
		const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
		const place = await admin.create('places', { name: 'Cabinet' })
		const startsAt = inDays(3)

		const created = await admin.create('appointments', {
			startsAt,
			duration: 45,
			practitionerId: practitioner.id,
			placeId: place.id,
			reason: 'Contrôle',
			notes: 'À jeun',
		})
		expect(created).toMatchObject({
			startsAt,
			duration: 45,
			practitionerId: practitioner.id,
			placeId: place.id,
			reason: 'Contrôle',
			notes: 'À jeun',
			synced: false,
			attachments: [],
		})

		const updated = await admin.data(await admin.put(`appointments/${created.id}`, { startsAt: startsAt + 3600, duration: 30 }))
		expect(updated).toMatchObject({ startsAt: startsAt + 3600, practitionerId: null, reason: null })

		expect((await admin.delete(`appointments/${created.id}`)).status()).toBe(200)
		expect((await admin.get(`appointments/${created.id}`)).status()).toBe(404)
	})

	test('lists appointments, most recent first', async () => {
		const early = await admin.create('appointments', { startsAt: inDays(1) })
		const late = await admin.create('appointments', { startsAt: inDays(9) })

		expect((await admin.list('appointments')).map((a) => a.id)).toEqual([late.id, early.id])
	})

	for (const [label, body] of [
		['zero duration', { startsAt: 1, duration: 0 }],
		['duration over 24 hours', { startsAt: 1, duration: 1441 }],
		['negative date', { startsAt: -1 }],
		['oversized reason', { startsAt: 1, reason: 'x'.repeat(256) }],
		['negative duration', { startsAt: 1, duration: -5 }],
		['unknown practitioner', { startsAt: 1, practitionerId: 987654 }],
		['unknown place', { startsAt: 1, placeId: 987654 }],
	]) {
		test(`rejects ${label}`, async () => {
			const response = await admin.post('appointments', body)
			expect(response.status()).toBe(400)
			expect(await admin.list('appointments')).toEqual([])
		})
	}

	test('startsAt is required', async () => {
		expect((await admin.post('appointments', { reason: 'x' })).status()).toBe(400)
	})

	test('deleting a practitioner or a place detaches their appointments', async () => {
		const practitioner = await admin.create('practitioners', { name: 'Dr Martin' })
		const place = await admin.create('places', { name: 'Cabinet' })
		const appointment = await admin.create('appointments', { startsAt: inDays(2), practitionerId: practitioner.id, placeId: place.id })

		await admin.delete(`practitioners/${practitioner.id}`)
		await admin.delete(`places/${place.id}`)

		expect(await admin.data(await admin.get(`appointments/${appointment.id}`))).toMatchObject({ practitionerId: null, placeId: null })
	})
})

test.describe('isolation between users', () => {
	test('a user cannot read, edit or delete another user’s data', async () => {
		const practitioner = await admin.create('practitioners', { name: 'Dr Privé' })
		const place = await admin.create('places', { name: 'Cabinet privé' })
		const appointment = await admin.create('appointments', { startsAt: inDays(2), practitionerId: practitioner.id })

		expect(await bob.list('appointments')).toEqual([])
		expect(await bob.list('practitioners')).toEqual([])
		expect(await bob.list('places')).toEqual([])

		for (const [resource, id] of [['appointments', appointment.id], ['practitioners', practitioner.id], ['places', place.id]]) {
			expect((await bob.get(`${resource}/${id}`)).status(), `GET ${resource}`).toBe(404)
			expect((await bob.put(`${resource}/${id}`, { name: 'hack', startsAt: 1 })).status(), `PUT ${resource}`).toBe(404)
			expect((await bob.delete(`${resource}/${id}`)).status(), `DELETE ${resource}`).toBe(404)
		}

		// les données d'admin sont intactes
		expect(await admin.data(await admin.get(`appointments/${appointment.id}`))).toMatchObject({ practitionerId: practitioner.id })
	})

	test('a user cannot link an appointment to another user’s practitioner or place', async () => {
		const practitioner = await admin.create('practitioners', { name: 'Dr Privé' })
		const place = await admin.create('places', { name: 'Cabinet privé' })

		expect((await bob.post('appointments', { startsAt: 1, practitionerId: practitioner.id })).status()).toBe(400)
		expect((await bob.post('appointments', { startsAt: 1, placeId: place.id })).status()).toBe(400)
	})
})

test('requires authentication', async ({ playwright }) => {
	const anonymous = await playwright.request.newContext({
		baseURL: process.env.NC_URL ?? 'http://localhost:8480',
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	expect((await anonymous.get('/ocs/v2.php/apps/vitalog/api/appointments')).status()).toBe(401)
	await anonymous.dispose()
})
