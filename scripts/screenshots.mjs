// Regenerates docs/screenshots/*.png (the App Store listing) from a dev instance: `node scripts/screenshots.mjs`.
// It REPLACES the admin's Vitalog data with English demo data and switches the admin to English; re-seed afterwards if needed.
import { chromium } from '@playwright/test'

const BASE = process.env.NC_URL ?? 'http://localhost:8480'
const AUTH = 'Basic ' + Buffer.from('admin:admin').toString('base64')
const api = async (method, path, body) => {
	const response = await fetch(`${BASE}/ocs/v2.php/${path}?format=json`, {
		method,
		headers: { Authorization: AUTH, 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' },
		body: body && JSON.stringify(body),
	})
	if (!response.ok) throw new Error(`${method} ${path}: ${response.status} ${await response.text()}`)
	return (await response.json()).ocs.data
}
const vitalog = (path) => `apps/vitalog/api/${path}`

for (const key of ['language', 'locale']) {
	await api('PUT', 'cloud/users/admin', { key, value: key === 'locale' ? 'en_US' : 'en' })
}
for (const kind of ['appointments', 'practitioners', 'places', 'labels']) {
	for (const item of await api('GET', vitalog(kind))) await api('DELETE', vitalog(`${kind}/${item.id}`))
}

const at = (days, hour, minute = 0) => {
	const date = new Date()
	date.setDate(date.getDate() + days)
	date.setHours(hour, minute, 0, 0)
	return Math.floor(date.getTime() / 1000)
}
const dentist = await api('POST', vitalog('practitioners'), { name: 'Dr Sophie Martin', specialty: 'Dentist', phone: '01 42 00 11 22' })
const cardio = await api('POST', vitalog('practitioners'), { name: 'Dr Paul Lefèvre', specialty: 'Cardiologist', phone: '01 45 33 44 55' })
const eyes = await api('POST', vitalog('practitioners'), { name: 'Dr Claire Moreau', specialty: 'Ophthalmologist' })
const park = await api('POST', vitalog('places'), { name: 'Park Dental Clinic', address: '12 Park Street' })
const hospital = await api('POST', vitalog('places'), { name: 'Saint-Louis Hospital', address: '3 Health Avenue' })
const teeth = await api('POST', vitalog('labels'), { name: 'Teeth', color: '#1565c0' })
const heart = await api('POST', vitalog('labels'), { name: 'Heart', color: '#b3261e' })
await api('POST', vitalog('labels'), { name: 'Blood donation' })
const create = (data) => api('POST', vitalog('appointments'), data)
await create({ startsAt: at(4, 14, 30), duration: 45, practitionerId: dentist.id, placeId: park.id, reason: 'Scaling', labelIds: [teeth.id], notes: 'Bring the insurance card' })
await create({ startsAt: at(12, 9), duration: 30, practitionerId: cardio.id, placeId: hospital.id, reason: 'Yearly check-up', labelIds: [heart.id] })
await create({ startsAt: at(25, 16), duration: 30, practitionerId: eyes.id, reason: 'New glasses' })
await create({ startsAt: at(-20, 10), duration: 60, practitionerId: cardio.id, placeId: hospital.id, reason: 'Electrocardiogram', labelIds: [heart.id], notes: 'Nothing to report' })
await create({ startsAt: at(-3, 11), duration: 30, practitionerId: dentist.id, placeId: park.id, reason: 'Toothache', labelIds: [teeth.id] })

const browser = await chromium.launch()
const context = await browser.newContext({ storageState: 'e2e/.auth/admin.json', viewport: { width: 1280, height: 760 }, colorScheme: 'light', locale: 'en-US' })
const page = await context.newPage()
const shot = (name) => page.screenshot({ path: `docs/screenshots/${name}.png` })

await page.goto(`${BASE}/index.php/apps/vitalog/`)
await page.locator('[data-test^="appointment-"]').first().click()
await page.waitForTimeout(600)
await shot('appointments')

await page.locator('[data-test="filter-button"]').click()
await page.getByRole('radiogroup', { name: 'Label' }).getByText('Heart', { exact: true }).click()
await page.waitForTimeout(400)
await shot('filter')
await page.keyboard.press('Escape')

await page.locator('[data-test="nav-labels"]').click()
await page.waitForTimeout(400)
await shot('labels')

await page.goto(`${BASE}/index.php/apps/vitalog/`)
await page.getByRole('button', { name: 'New appointment' }).click()
await page.waitForTimeout(600)
await shot('form')

await browser.close()
console.log('screenshots written to docs/screenshots')
