import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import { ADMIN, client, inDays } from '../helpers.js'
import { dialog, nav, openApp, setLanguage } from './pages.js'

let admin
const BOOK = `/remote.php/dav/addressbooks/users/${ADMIN.user}/contacts`
const created = []

async function putContact(uid, name, title, tel) {
	const data = ['BEGIN:VCARD', 'VERSION:3.0', `UID:${uid}`, `FN:${name}`, `N:${name};;;;`, `TITLE:${title}`, `TEL;TYPE=WORK:${tel}`, 'END:VCARD'].join('\r\n') + '\r\n'
	const response = await admin.context.put(`${BOOK}/${uid}.vcf`, { headers: { 'Content-Type': 'text/vcard' }, data })
	expect([201, 204]).toContain(response.status())
	created.push(uid)
}

test.beforeAll(async () => {
	await setLanguage('en')
	admin = await client()
})
test.afterAll(async () => {
	await admin.reset()
	for (const uid of created) {
		await admin.context.delete(`${BOOK}/${uid}.vcf`)
	}
	await admin.dispose()
})
test.beforeEach(async () => {
	await admin.reset()
})

const nameField = (page) => dialog(page).getByLabel('Name', { exact: true })

test('a practitioner picked from the contacts shows only the contact, and becomes editable once the contact is gone', async ({ page }) => {
	const tag = `Ui${Date.now()}`
	const uid = `vitalog-ui-${tag}`
	await putContact(uid, `Dr ${tag}`, 'Cardiologue', '0102030405')
	await openApp(page)
	await nav(page, 'practitioners').click()

	await page.getByRole('button', { name: 'New practitioner' }).click()
	const contact = dialog(page).getByRole('combobox', { name: 'Contact' })
	await contact.click()
	await contact.pressSequentially(tag)
	await page.locator('[role="option"]').filter({ hasText: `Dr ${tag}` }).first().click()

	// lié : les champs de la fiche disparaissent, il ne reste que le contact choisi
	await expect(contact).toBeVisible()
	await expect(nameField(page)).toHaveCount(0)
	await expect(dialog(page).getByLabel('Specialty')).toHaveCount(0)
	await expect(dialog(page).getByLabel('Phone')).toHaveCount(0)
	await expect(dialog(page).getByLabel('Notes')).toHaveCount(0)

	// accessibilité du formulaire lié
	// .loading-icon : le spinner de NcSelect est un role="img" sans nom dans @nextcloud/vue 9.13.1
	// (corrigé par nextcloud-libraries/nextcloud-vue#9098 : retirer l'exclusion avec la version qui l'embarque)
	const { violations } = await new AxeBuilder({ page }).include('[role="dialog"]').exclude('.loading-icon').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()
	expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([])

	await dialog(page).getByRole('button', { name: 'Save' }).click()
	await expect(dialog(page)).toHaveCount(0)
	await expect(page.locator('[data-test^="practitioner-"]')).toContainText(`Dr ${tag}`)
	expect((await admin.list('practitioners'))[0]).toMatchObject({ contactUid: uid, contactStatus: 'linked' })

	// rouvert : seul le lien vers la fiche reste, sans champs ni notes
	await page.locator('[data-test^="practitioner-"]').click()
	await expect(dialog(page).getByRole('link', { name: 'Edit in Contacts' })).toHaveAttribute('href', new RegExp(`/apps/contacts/direct/contact/${uid}`))
	await expect(nameField(page)).toHaveCount(0)
	await expect(dialog(page).getByLabel('Notes')).toHaveCount(0)
	await dialog(page).getByRole('button', { name: 'Cancel' }).click()

	// la fiche est supprimée : le praticien reste, signale le lien rompu et redevient modifiable
	await admin.context.delete(`${BOOK}/${uid}.vcf`)
	await page.reload()
	await nav(page, 'practitioners').click()
	await page.locator('[data-test^="practitioner-"]').click()
	await expect(dialog(page)).toContainText('no longer exists')
	await expect(nameField(page)).toBeVisible()
	await expect(nameField(page)).toBeEnabled()
	await expect(nameField(page)).toHaveValue(`Dr ${tag}`)
})

test('a local practitioner can be added to the contacts', async ({ page }) => {
	const practitioner = await admin.create('practitioners', { name: `Dr Poussé ${Date.now()}`, specialty: 'ORL' })
	await openApp(page)
	await nav(page, 'practitioners').click()
	await page.locator('[data-test^="practitioner-"]').click()

	await dialog(page).getByRole('button', { name: 'Add to my contacts' }).click()
	await expect(dialog(page)).toHaveCount(0)

	const [linked] = await admin.list('practitioners')
	expect(linked).toMatchObject({ id: practitioner.id, contactStatus: 'linked' })
	created.push(linked.contactUid)
})

test('the contacts menu link opens the appointments of a practitioner', async ({ page }) => {
	const doctor = await admin.create('practitioners', { name: 'Dr Lien' })
	const other = await admin.create('practitioners', { name: 'Dr Autre' })
	await admin.create('appointments', { startsAt: inDays(2), reason: 'Chez Lien', practitionerId: doctor.id })
	await admin.create('appointments', { startsAt: inDays(3), reason: 'Chez Autre', practitionerId: other.id })

	await page.goto(`/index.php/apps/vitalog/?practitioner=${doctor.id}`)

	await expect(page.locator('[data-test^="appointment-"]')).toHaveCount(1)
	await expect(page.locator('[data-test^="appointment-"]')).toContainText('Chez Lien')
})

test('linked practitioners link to their contact from the list and from the appointment', async ({ page }) => {
	const uid = `vitalog-ui-link-${Date.now()}`
	await putContact(uid, 'Dr Lien Direct', 'ORL', '0102030405')
	const doctor = await admin.create('practitioners', { name: 'x', contactUid: uid })
	await admin.create('appointments', { startsAt: inDays(2), reason: 'Visite', practitionerId: doctor.id })
	await openApp(page)

	await page.locator('[data-test^="appointment-"]').first().click()
	await expect(page.getByRole('article').getByRole('link', { name: 'Open in Contacts' })).toHaveAttribute('href', new RegExp(`/apps/contacts/direct/contact/${uid}`))

	await nav(page, 'practitioners').click()
	await expect(page.getByRole('link', { name: 'Open Dr Lien Direct in Contacts' })).toHaveAttribute('href', new RegExp(`/apps/contacts/direct/contact/${uid}`))
})
