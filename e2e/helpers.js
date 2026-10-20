import { request as playwrightRequest } from '@playwright/test'

export const BASE_URL = process.env.NC_URL ?? 'http://localhost:8480'
// comptes de test de l'instance de dev / CI (créés par dev/docker-compose.yml)
export const ADMIN = { user: 'admin', password: 'admin' }
export const BOB = { user: 'bob', password: 'Vitalog-e2e-pass-9271' }

export const nowSeconds = () => Math.floor(Date.now() / 1000)
export function inDays(days, hour = 10) {
	const date = new Date()
	date.setDate(date.getDate() + days)
	date.setHours(hour, 0, 0, 0)
	return Math.floor(date.getTime() / 1000)
}

const basic = ({ user, password }) => 'Basic ' + Buffer.from(`${user}:${password}`).toString('base64')

/** Client HTTP authentifié (OCS + WebDAV) pour un compte donné. */
export async function client(account = ADMIN) {
	const context = await playwrightRequest.newContext({
		baseURL: BASE_URL,
		extraHTTPHeaders: {
			Authorization: basic(account),
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
		},
	})
	const api = (path) => `/ocs/v2.php/apps/vitalog/api/${path}`
	const data = async (response) => (await response.json()).ocs.data

	return {
		context,
		account,
		get: (path) => context.get(api(path)),
		post: (path, body) => context.post(api(path), { data: body }),
		put: (path, body) => context.put(api(path), { data: body }),
		delete: (path) => context.delete(api(path)),
		data,
		/** Crée et renvoie la donnée (échoue si le serveur répond autre chose que 2xx). */
		async create(resource, body) {
			const response = await context.post(api(resource), { data: body })
			if (!response.ok()) {
				throw new Error(`POST ${resource} -> ${response.status()} ${await response.text()}`)
			}
			return data(response)
		},
		async list(resource) {
			return data(await context.get(api(resource)))
		},
		/** Supprime toutes les données Vitalog du compte. */
		async reset() {
			for (const resource of ['appointments', 'practitioners', 'places', 'labels']) {
				for (const item of await this.list(resource)) {
					await context.delete(api(`${resource}/${item.id}`))
				}
			}
			await context.post(api('settings/unlink'), { data: { deleteEvents: true } })
			// des événements gardés à la déliaison ne sont plus rattachés à aucun rendez-vous : on les retire du calendrier par défaut
			const calendar = `/remote.php/dav/calendars/${this.account.user}/personal/`
			const exported = await (await context.get(`${calendar}?export`)).text()
			for (const [, uid] of exported.replace(/\r?\n[ \t]/g, '').matchAll(/^UID:(\S+@vitalog)\r?$/gm)) {
				await context.delete(`${calendar}${uid}.ics`)
			}
		},
		dispose: () => context.dispose(),
	}
}

/** Crée le compte « bob » s'il n'existe pas (API de provisioning, droits admin). */
export async function ensureBob() {
	const admin = await client(ADMIN)
	const response = await admin.context.post('/ocs/v2.php/cloud/users', {
		data: { userid: BOB.user, password: BOB.password },
	})
	// 400 « already exists » est acceptable
	if (![200, 400].includes(response.status())) {
		throw new Error(`Could not create bob: ${response.status()} ${await response.text()}`)
	}
	await admin.dispose()
}

/** Dépose un fichier dans le dossier de l'utilisateur et renvoie son fileId. */
export async function uploadFile(account, name, content) {
	const c = await client(account)
	const url = `/remote.php/dav/files/${account.user}/${name}`
	const put = await c.context.put(url, { data: content })
	if (![201, 204].includes(put.status())) {
		throw new Error(`upload failed: ${put.status()}`)
	}
	const propfind = await c.context.fetch(url, {
		method: 'PROPFIND',
		headers: { Depth: '0', 'Content-Type': 'application/xml' },
		data: '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:fileid/></d:prop></d:propfind>',
	})
	const fileId = Number((await propfind.text()).match(/<oc:fileid>(\d+)</)[1])
	await c.dispose()
	return fileId
}

export async function deleteFile(account, name) {
	const c = await client(account)
	await c.context.delete(`/remote.php/dav/files/${account.user}/${name}`)
	await c.dispose()
}

/** Export ICS d'un calendrier de l'utilisateur. */
export async function exportCalendar(account, calendarUri) {
	const c = await client(account)
	const response = await c.context.get(`/remote.php/dav/calendars/${account.user}/${calendarUri}/?export`)
	const text = await response.text()
	await c.dispose()
	return text
}

/** Événements Vitalog (UID `…@vitalog`) d'un export ICS, dépliés. */
export function vitalogEvents(ics) {
	return ics
		.replace(/\r?\n[ \t]/g, '')
		.split('BEGIN:VEVENT')
		.slice(1)
		.filter((event) => /UID:.*@vitalog/.test(event))
}
