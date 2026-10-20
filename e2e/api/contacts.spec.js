import { expect, test } from '@playwright/test'
import { ADMIN, BOB, client, ensureBob } from '../helpers.js'

let admin
let bob
const BOOK = `/remote.php/dav/addressbooks/users/${ADMIN.user}/contacts`
const uids = []

function vcard(uid, { name, org, title, tel }) {
	return [
		'BEGIN:VCARD',
		'VERSION:3.0',
		`UID:${uid}`,
		`FN:${name}`,
		`N:${name};;;;`,
		org && `ORG:${org}`,
		title && `TITLE:${title}`,
		tel && `TEL;TYPE=WORK:${tel}`,
		'END:VCARD',
	].filter(Boolean).join('\r\n') + '\r\n'
}

async function putContact(uid, fields) {
	const response = await admin.context.put(`${BOOK}/${uid}.vcf`, { headers: { 'Content-Type': 'text/vcard' }, data: vcard(uid, fields) })
	expect([201, 204], 'put contact').toContain(response.status())
	uids.push(uid)
}
const search = async (client, term) => (await client.data(await client.get(`contacts?search=${encodeURIComponent(term)}`))).contacts

test.beforeAll(async () => {
	await ensureBob()
	admin = await client()
	bob = await client(BOB)
})
test.afterAll(async () => {
	await admin.reset()
	for (const uid of uids) {
		await admin.context.delete(`${BOOK}/${uid}.vcf`)
	}
	await admin.dispose()
	await bob.dispose()
})
test.beforeEach(async () => {
	await admin.reset()
})

test('contacts can be searched, and are private to their owner', async () => {
	const tag = `Zorglub${Date.now()}`
	await putContact(`vitalog-${tag}`, { name: `Dr ${tag}`, org: 'Clinique du Parc', title: 'Cardiologue', tel: '01 02 03 04 05' })

	const found = await search(admin, tag)
	expect(found).toHaveLength(1)
	expect(found[0]).toMatchObject({ uid: `vitalog-${tag}`, name: `Dr ${tag}`, specialty: 'Cardiologue', phone: '01 02 03 04 05' })
	expect(await search(bob, tag)).toHaveLength(0)
	expect((await admin.data(await admin.get('contacts?search='))).contacts).toEqual([])
})

test('a practitioner linked to a contact follows the contact, then keeps its data when the contact is deleted', async () => {
	const tag = `Lien${Date.now()}`
	const uid = `vitalog-${tag}`
	await putContact(uid, { name: `Dr ${tag}`, title: 'ORL', tel: '0102030405' })

	const created = await admin.create('practitioners', { name: 'ignoré', contactUid: uid })
	expect(created).toMatchObject({ name: `Dr ${tag}`, specialty: 'ORL', phone: '0102030405', contactUid: uid, contactStatus: 'linked' })
	expect(created.contactUrl).toContain('/apps/contacts/direct/contact/')

	// la fiche change : le praticien suit
	await putContact(uid, { name: `Dr ${tag} Renommé`, title: 'ORL', tel: '0999999999' })
	const [followed] = await admin.list('practitioners')
	expect(followed).toMatchObject({ name: `Dr ${tag} Renommé`, phone: '0999999999', contactStatus: 'linked' })

	// champs verrouillés : les valeurs reçues sont ignorées
	const edited = await admin.data(await admin.put(`practitioners/${created.id}`, { name: 'Autre', specialty: 'Autre', phone: '1', contactUid: uid }))
	expect(edited).toMatchObject({ name: `Dr ${tag} Renommé`, specialty: 'ORL' })
	expect(edited).not.toHaveProperty('notes')

	// la fiche disparaît : la copie reste, le lien est signalé, les champs redeviennent modifiables
	await admin.context.delete(`${BOOK}/${uid}.vcf`)
	const [orphan] = await admin.list('practitioners')
	expect(orphan).toMatchObject({ name: `Dr ${tag} Renommé`, contactUid: uid, contactStatus: 'missing', contactUrl: null })
	const fixed = await admin.data(await admin.put(`practitioners/${created.id}`, { name: 'Dr Libre', contactUid: uid }))
	expect(fixed.name).toBe('Dr Libre')

	// délier
	const unlinked = await admin.data(await admin.put(`practitioners/${created.id}`, { name: 'Dr Libre', contactUid: null }))
	expect(unlinked).toMatchObject({ contactUid: null, contactStatus: 'none' })
})

test('linking to an unknown contact is rejected', async () => {
	const response = await admin.post('practitioners', { name: 'X', contactUid: 'does-not-exist' })
	expect(response.status()).toBe(400)
	expect(await admin.list('practitioners')).toHaveLength(0)
})

test('a local practitioner can be added to the address book and becomes linked', async () => {
	const practitioner = await admin.create('practitioners', { name: `Dr Poussé ${Date.now()}`, specialty: 'Dentiste', phone: '0611223344' })
	expect(practitioner.contactStatus).toBe('none')

	const response = await admin.post(`practitioners/${practitioner.id}/contact`, {})
	expect(response.status()).toBe(201)
	const linked = await admin.data(response)
	expect(linked).toMatchObject({ name: practitioner.name, specialty: 'Dentiste', phone: '0611223344', contactStatus: 'linked' })
	uids.push(linked.contactUid)
	expect(await search(admin, practitioner.name)).toHaveLength(1)

	// déjà lié
	expect((await admin.post(`practitioners/${practitioner.id}/contact`, {})).status()).toBe(400)
})

test('a user cannot link a practitioner to another user\'s contact', async () => {
	const tag = `Prive${Date.now()}`
	await putContact(`vitalog-${tag}`, { name: `Dr ${tag}` })

	expect((await bob.post('practitioners', { name: 'X', contactUid: `vitalog-${tag}` })).status()).toBe(400)
})
