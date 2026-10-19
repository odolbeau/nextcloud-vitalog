import { expect, test } from '@playwright/test'
import { ADMIN, BOB, client, deleteFile, ensureBob, inDays, uploadFile } from '../helpers.js'

let admin
let bob
let appointment
const files = []

test.beforeAll(async () => {
	await ensureBob()
	admin = await client()
	bob = await client(BOB)
})
test.afterAll(async () => {
	await admin.reset()
	for (const [account, name] of files) {
		await deleteFile(account, name)
	}
	await admin.dispose()
	await bob.dispose()
})
test.beforeEach(async () => {
	await admin.reset()
	appointment = await admin.create('appointments', { startsAt: inDays(2), reason: 'Bilan' })
})

async function upload(account, name, content = 'résultat d’analyse') {
	files.push([account, name])
	return uploadFile(account, name, content)
}

test('attach a file, see its details, detach it', async () => {
	const fileId = await upload(ADMIN, 'vitalog-bilan.txt')

	const response = await admin.post(`appointments/${appointment.id}/attachments`, { fileId })
	expect(response.status()).toBe(201)
	const [attachment] = await admin.data(response)
	expect(attachment).toMatchObject({ fileId, name: 'vitalog-bilan.txt', path: '/vitalog-bilan.txt', mimetype: 'text/plain' })

	// visible sur le rendez-vous, en liste comme en détail
	const listed = (await admin.list('appointments'))[0]
	expect(listed.attachments).toHaveLength(1)
	expect((await admin.data(await admin.get(`appointments/${appointment.id}`))).attachments[0].name).toBe('vitalog-bilan.txt')

	expect((await admin.delete(`attachments/${attachment.id}`)).status()).toBe(200)
	expect((await admin.data(await admin.get(`appointments/${appointment.id}`))).attachments).toEqual([])
})

test('attaching the same file twice keeps a single attachment', async () => {
	const fileId = await upload(ADMIN, 'vitalog-doublon.txt')

	await admin.post(`appointments/${appointment.id}/attachments`, { fileId })
	const second = await admin.post(`appointments/${appointment.id}/attachments`, { fileId })

	expect(second.status()).toBe(201)
	expect(await admin.data(second)).toHaveLength(1)
})

test('rejects unknown files and files of other users', async () => {
	const bobsFile = await upload(BOB, 'vitalog-bob.txt')

	expect((await admin.post(`appointments/${appointment.id}/attachments`, { fileId: 99999999 })).status()).toBe(400)
	expect((await admin.post(`appointments/${appointment.id}/attachments`, { fileId: bobsFile })).status()).toBe(400)
	expect((await admin.data(await admin.get(`appointments/${appointment.id}`))).attachments).toEqual([])
})

test('a user cannot attach to or detach from another user’s appointment', async () => {
	const adminFile = await upload(ADMIN, 'vitalog-secret.txt')
	const attachmentList = await admin.data(await admin.post(`appointments/${appointment.id}/attachments`, { fileId: adminFile }))
	const bobsFile = await upload(BOB, 'vitalog-bob2.txt')

	expect((await bob.post(`appointments/${appointment.id}/attachments`, { fileId: bobsFile })).status()).toBe(404)
	expect((await bob.delete(`attachments/${attachmentList[0].id}`)).status()).toBe(404)
	expect((await admin.data(await admin.get(`appointments/${appointment.id}`))).attachments).toHaveLength(1)
})

test('a deleted file is flagged instead of breaking the appointment', async () => {
	const fileId = await upload(ADMIN, 'vitalog-ephemere.txt')
	await admin.post(`appointments/${appointment.id}/attachments`, { fileId })

	await deleteFile(ADMIN, 'vitalog-ephemere.txt')

	const [attachment] = (await admin.data(await admin.get(`appointments/${appointment.id}`))).attachments
	expect(attachment).toMatchObject({ fileId, name: null, path: null, mimetype: null })
})

test('deleting the appointment removes its attachments', async () => {
	const fileId = await upload(ADMIN, 'vitalog-cascade.txt')
	const [attachment] = await admin.data(await admin.post(`appointments/${appointment.id}/attachments`, { fileId }))

	await admin.delete(`appointments/${appointment.id}`)

	expect((await admin.delete(`attachments/${attachment.id}`)).status()).toBe(404)
})
