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

test('labels can be created, renamed, coloured and listed alphabetically', async () => {
	const zebra = await admin.create('labels', { name: 'Zèbre' })
	const ankle = await admin.create('labels', { name: '  Cheville ', color: '#AA3300' })
	expect(ankle).toMatchObject({ name: 'Cheville', color: '#aa3300' })
	expect(zebra.color).toBeNull()

	expect((await admin.list('labels')).map((l) => l.name)).toEqual(['Cheville', 'Zèbre'])

	const renamed = await admin.put(`labels/${ankle.id}`, { name: 'Cheville gauche', color: null })
	expect(renamed.status()).toBe(200)
	expect(await admin.data(renamed)).toMatchObject({ name: 'Cheville gauche', color: null })
})

test('names are required and unique, colours must be hex', async () => {
	await admin.create('labels', { name: 'Cheville' })

	expect((await admin.post('labels', { name: '  ' })).status()).toBe(400)
	expect((await admin.post('labels', { name: 'cheville' })).status()).toBe(400)
	expect((await admin.post('labels', { name: 'Autre', color: 'red' })).status()).toBe(400)
})

test('appointments from different practitioners and places can share labels', async () => {
	const ankle = await admin.create('labels', { name: 'Cheville' })
	const blood = await admin.create('labels', { name: 'Don du sang' })
	const gp = await admin.create('practitioners', { name: 'Dr Généraliste' })
	const physio = await admin.create('practitioners', { name: 'Kiné' })

	const first = await admin.create('appointments', { startsAt: inDays(2), practitionerId: gp.id, labelIds: [ankle.id] })
	const second = await admin.create('appointments', { startsAt: inDays(5), practitionerId: physio.id, labelIds: [ankle.id, blood.id, ankle.id] })
	const third = await admin.create('appointments', { startsAt: inDays(8) })

	expect(first.labelIds).toEqual([ankle.id])
	expect(second.labelIds).toEqual([ankle.id, blood.id].sort((a, b) => a - b))
	expect(third.labelIds).toEqual([])

	// une mise à jour remplace les libellés
	const updated = await admin.put(`appointments/${second.id}`, { ...second, labelIds: [blood.id] })
	expect((await admin.data(updated)).labelIds).toEqual([blood.id])
	const listed = await admin.list('appointments')
	expect(listed.find((a) => a.id === first.id).labelIds).toEqual([ankle.id])
})

test('deleting a label keeps the appointments, deleting an appointment keeps the label', async () => {
	const ankle = await admin.create('labels', { name: 'Cheville' })
	const appointment = await admin.create('appointments', { startsAt: inDays(2), labelIds: [ankle.id] })

	await admin.delete(`appointments/${appointment.id}`)
	expect(await admin.list('labels')).toHaveLength(1)

	const again = await admin.create('appointments', { startsAt: inDays(3), labelIds: [ankle.id] })
	expect((await admin.delete(`labels/${ankle.id}`)).status()).toBe(200)
	const [kept] = await admin.list('appointments')
	expect(kept).toMatchObject({ id: again.id, labelIds: [] })
})

test('labels are private to their owner', async () => {
	const mine = await admin.create('labels', { name: 'Privé' })
	const bobs = await bob.create('labels', { name: 'Privé' }) // même nom chez un autre utilisateur : autorisé

	expect(await bob.list('labels')).toHaveLength(1)
	expect((await bob.put(`labels/${mine.id}`, { name: 'Volé' })).status()).toBe(404)
	expect((await bob.delete(`labels/${mine.id}`)).status()).toBe(404)
	expect((await bob.post('appointments', { startsAt: inDays(2), labelIds: [mine.id] })).status()).toBe(400)
	expect(bobs.id).not.toBe(mine.id)
})
