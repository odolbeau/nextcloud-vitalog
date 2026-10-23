import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { getLoggerBuilder } from '@nextcloud/logger'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import * as api from '../api.js'

const logger = getLoggerBuilder().setApp('vitalog').detectUser().build()

export const useDataStore = defineStore('data', () => {
	const appointments = ref([])
	const practitioners = ref([])
	const places = ref([])
	const labels = ref([])
	const settings = ref({ calendarAppEnabled: false, calendarPath: null, calendars: [] })
	const loaded = ref(false)
	const loadError = ref(false)

	const now = () => Math.floor(Date.now() / 1000)
	// « passé » ne dépend que de la date : le rendez-vous est terminé
	const isPast = (a) => a.startsAt + a.duration * 60 < now()
	const upcoming = computed(() => appointments.value
		.filter((a) => !isPast(a))
		.sort((a, b) => a.startsAt - b.startsAt))
	const past = computed(() => appointments.value
		.filter(isPast)
		.sort((a, b) => b.startsAt - a.startsAt))
	const all = computed(() => [...appointments.value].sort((a, b) => b.startsAt - a.startsAt))

	// date du rendez-vous le plus récent (à venir compris) de chaque praticien ; sans rendez-vous, ordre alphabétique en fin de liste
	const practitionersByRecency = computed(() => {
		const latest = new Map()
		for (const a of appointments.value) {
			if (a.practitionerId !== null && a.startsAt > (latest.get(a.practitionerId) ?? -Infinity)) {
				latest.set(a.practitionerId, a.startsAt)
			}
		}
		return [...practitioners.value].sort((a, b) => (latest.get(b.id) ?? -Infinity) - (latest.get(a.id) ?? -Infinity) || a.name.localeCompare(b.name))
	})
	const practitionerById = (id) => practitioners.value.find((p) => p.id === id) ?? null
	const placeById = (id) => places.value.find((p) => p.id === id) ?? null
	const labelById = (id) => labels.value.find((l) => l.id === id) ?? null
	const labelsOf = (appointment) => (appointment.labelIds ?? []).map(labelById).filter(Boolean)
	const countForLabel = (id) => appointments.value.filter((a) => a.labelIds?.includes(id)).length

	/**
	 * Exécute l'action et affiche l'erreur serveur ; renvoie `undefined` en cas d'échec.
	 *
	 * @param action
	 * @param failureMessage
	 */
	async function guard(action, failureMessage) {
		try {
			return await action()
		} catch (error) {
			logger.error('Vitalog request failed', { error })
			showError(api.errorMessage(error) || failureMessage)
			return undefined
		}
	}

	async function load() {
		loadError.value = false
		try {
			const [a, p, l, lb, s] = await Promise.all([
				api.list('appointments'),
				api.list('practitioners'),
				api.list('places'),
				api.list('labels'),
				api.getSettings(),
			])
			appointments.value = a
			practitioners.value = p
			places.value = l
			labels.value = lb
			settings.value = s
		} catch (error) {
			logger.error('Vitalog could not load data', { error })
			loadError.value = true
		} finally {
			loaded.value = true
		}
	}

	const replaceIn = (list, item) => {
		const index = list.value.findIndex((i) => i.id === item.id)
		if (index === -1) {
			list.value.push(item)
		} else {
			list.value.splice(index, 1, item)
		}
	}

	async function saveAppointment(form, id = null) {
		const saved = await guard(
			() => (id ? api.update('appointments', id, form) : api.create('appointments', form)),
			t('vitalog', 'The appointment could not be saved.'),
		)
		if (saved) {
			replaceIn(appointments, saved)
		}
		return saved
	}

	/** Les libellés tapés à la volée n'existent pas encore : on les crée (ou on réutilise l'existant du même nom). `null` en cas d'échec. */
	async function resolveLabelIds(options) {
		const ids = []
		for (const option of options) {
			let id = option.id
			if (id === null) {
				const existing = labels.value.find((l) => l.name.toLowerCase() === option.label.toLowerCase())
				id = existing?.id ?? (await saveLabel({ name: option.label, color: null }))?.id ?? null
			}
			if (id === null) {
				return null
			}
			ids.push(id)
		}

		return [...new Set(ids)]
	}

	/** Modifie quelques champs d'un rendez-vous : l'API remplace tout, on renvoie donc les autres valeurs telles quelles. */
	function updateAppointment(appointment, changes) {
		const { startsAt, duration, practitionerId, placeId, reason, notes, price, labelIds } = appointment
		return saveAppointment({ startsAt, duration, practitionerId, placeId, reason, notes, price, labelIds, ...changes }, appointment.id)
	}

	async function deleteAppointment(id) {
		const done = await guard(() => api.destroy('appointments', id), t('vitalog', 'The appointment could not be deleted.'))
		if (done !== undefined) {
			appointments.value = appointments.value.filter((a) => a.id !== id)
			showSuccess(t('vitalog', 'Appointment deleted'))
		}
		return done !== undefined
	}

	async function addAttachment(appointment, fileId) {
		const attachments = await guard(
			() => api.addAttachment(appointment.id, fileId),
			t('vitalog', 'The file could not be attached.'),
		)
		if (attachments) {
			replaceIn(appointments, { ...appointment, attachments })
		}
		return attachments
	}

	async function removeAttachment(appointment, attachmentId) {
		const done = await guard(() => api.removeAttachment(attachmentId), t('vitalog', 'The file could not be detached.'))
		if (done !== undefined) {
			replaceIn(appointments, {
				...appointment,
				attachments: appointment.attachments.filter((a) => a.id !== attachmentId),
			})
		}
	}

	async function saveReference(resource, list, form, id = null) {
		const saved = await guard(
			() => (id ? api.update(resource, id, form) : api.create(resource, form)),
			t('vitalog', 'Saving failed.'),
		)
		if (saved) {
			replaceIn(list, saved)
		}
		return saved
	}

	async function deleteReference(resource, list, id) {
		const done = await guard(() => api.destroy(resource, id), t('vitalog', 'Deletion failed.'))
		if (done !== undefined) {
			list.value = list.value.filter((i) => i.id !== id)
			await refreshAppointments()
		}
		return done !== undefined
	}

	async function refreshAppointments() {
		const fresh = await guard(() => api.list('appointments'), t('vitalog', 'Refreshing failed.'))
		if (fresh) {
			appointments.value = fresh
		}
	}

	const savePractitioner = (form, id = null) => saveReference('practitioners', practitioners, form, id)
	const deletePractitioner = (id) => deleteReference('practitioners', practitioners, id)
	async function createContactFor(practitioner) {
		const saved = await guard(() => api.createContact(practitioner.id), t('vitalog', 'The contact could not be created.'))
		if (saved) {
			replaceIn(practitioners, saved)
		}
		return saved
	}

	const sortLabels = () => labels.value.sort((a, b) => a.name.localeCompare(b.name))
	async function saveLabel(form, id = null) {
		const saved = await saveReference('labels', labels, form, id)
		if (saved) {
			sortLabels()
		}
		return saved
	}
	const deleteLabel = (id) => deleteReference('labels', labels, id)

	const savePlace = (form, id = null) => saveReference('places', places, form, id)
	const deletePlace = (id) => deleteReference('places', places, id)

	/** Import ponctuel : crée un rendez-vous par événement du calendrier. Renvoie le nombre créé, ou `null` en cas d'échec. */
	async function importCalendar(calendarUri) {
		const result = await guard(() => api.importCalendar(calendarUri), t('vitalog', 'The events could not be imported.'))
		if (!result) {
			return null
		}
		await refreshAppointments()
		showSuccess(t('vitalog', 'Appointments imported: {count}', { count: result.imported }))
		return result.imported
	}

	return {
		appointments,
		practitioners,
		places,
		labels,
		settings,
		loaded,
		loadError,
		upcoming,
		past,
		all,
		isPast,
		practitionersByRecency,
		practitionerById,
		placeById,
		labelById,
		labelsOf,
		countForLabel,
		load,
		saveAppointment,
		updateAppointment,
		resolveLabelIds,
		deleteAppointment,
		addAttachment,
		removeAttachment,
		savePractitioner,
		createContactFor,
		deletePractitioner,
		savePlace,
		deletePlace,
		saveLabel,
		deleteLabel,
		importCalendar,
	}
})
