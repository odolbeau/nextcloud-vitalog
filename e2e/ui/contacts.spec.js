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

test('a practitioner can be picked from the contacts, is locked, and stays editable once the contact is gone', async ({ page }) => {
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

	await expect(nameField(page)).toHaveValue(`Dr ${tag}`)
	await expect(nameField(page)).toBeDisabled()
	await expect(dialog(page).getByLabel('Specialty')).toHaveValue('Cardiologue')
	await expect(dialog(page)).toContainText('come from the contact')

	// accessibilité du formulaire lié
	const { violations } = await new AxeBuilder({ page }).include('[role="dialog"]').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()
	expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([])

	await dialog(page).getByRole('button', { name: 'Save' }).click()
	await expect(dialog(page)).toHaveCount(0)
	await expect(page.locator('[data-test^="practitioner-"]')).toContainText(`Dr ${tag}`)
	expect((await admin.list('practitioners'))[0]).toMatchObject({ contactUid: uid, contactStatus: 'linked' })

	// la fiche est supprimée : le praticien reste, signale le lien rompu et redevient modifiable
	await admin.context.delete(`${BOOK}/${uid}.vcf`)
	await page.reload()
	await nav(page, 'practitioners').click()
	await page.locator('[data-test^="practitioner-"]').click()
	await expect(dialog(page)).toContainText('no longer exists')
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
