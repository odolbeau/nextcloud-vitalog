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
	const settings = ref({ calendarUri: null, importFromCalendar: false, calendarAppEnabled: false, calendars: [] })
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

	const practitionerById = (id) => practitioners.value.find((p) => p.id === id) ?? null
	const placeById = (id) => places.value.find((p) => p.id === id) ?? null

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
			const [a, p, l, s] = await Promise.all([
				api.list('appointments'),
				api.list('practitioners'),
				api.list('places'),
				api.getSettings(),
			])
			appointments.value = a
			practitioners.value = p
			places.value = l
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
			// les événements du calendrier portent le nom / l'adresse : le serveur les a mis à jour
			await refreshAppointments()
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

	const savePlace = (form, id = null) => saveReference('places', places, form, id)
	const deletePlace = (id) => deleteReference('places', places, id)

	async function saveCalendar(calendarUri, importFromCalendar = false) {
		const saved = await guard(() => api.saveSettings(calendarUri, importFromCalendar), t('vitalog', 'The calendar setting could not be saved.'))
		if (saved) {
			settings.value = saved
			await refreshAppointments()
		}
		return saved
	}

	return {
		appointments,
		practitioners,
		places,
		settings,
		loaded,
		loadError,
		upcoming,
		past,
		all,
		isPast,
		practitionerById,
		placeById,
		load,
		saveAppointment,
		deleteAppointment,
		addAttachment,
		removeAttachment,
		savePractitioner,
		createContactFor,
		deletePractitioner,
		savePlace,
		deletePlace,
		saveCalendar,
	}
})
