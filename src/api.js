import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const config = { headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' } }
const url = (path) => generateOcsUrl('apps/vitalog/api/' + path)
const unwrap = (response) => response.data.ocs.data

export const list = (resource) => axios.get(url(resource), config).then(unwrap)
export const create = (resource, body) => axios.post(url(resource), body, config).then(unwrap)
export const update = (resource, id, body) => axios.put(url(`${resource}/${id}`), body, config).then(unwrap)
export const destroy = (resource, id) => axios.delete(url(`${resource}/${id}`), config).then(unwrap)

export function addAttachment(appointmentId, fileId) {
	return axios.post(url(`appointments/${appointmentId}/attachments`), { fileId }, config).then(unwrap)
}
export const removeAttachment = (id) => axios.delete(url(`attachments/${id}`), config).then(unwrap)

export const searchContacts = (search) => axios.get(url('contacts'), { ...config, params: { search } }).then(unwrap)
export const createContact = (practitionerId) => axios.post(url(`practitioners/${practitionerId}/contact`), {}, config).then(unwrap)

export const getSettings = () => axios.get(url('settings'), config).then(unwrap)
export const saveSettings = (calendarUri, importFromCalendar) => axios.put(url('settings'), { calendarUri, importFromCalendar }, config).then(unwrap)

/**
 * Message d'erreur renvoyé par le serveur, le cas échéant.
 *
 * @param error
 */
export const errorMessage = (error) => error?.response?.data?.ocs?.data?.message || null
