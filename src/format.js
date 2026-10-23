import { getCanonicalLocale } from '@nextcloud/l10n'

const locale = () => getCanonicalLocale()

/**
 * `startsAt` est un timestamp UNIX en secondes.
 *
 * @param startsAt
 */
export const toDate = (startsAt) => new Date(startsAt * 1000)

export function formatDateTime(startsAt) {
	return new Intl.DateTimeFormat(locale(), { dateStyle: 'full', timeStyle: 'short' }).format(toDate(startsAt))
}

export function formatShortDateTime(startsAt) {
	return new Intl.DateTimeFormat(locale(), { dateStyle: 'medium', timeStyle: 'short' }).format(toDate(startsAt))
}

export function formatDate(startsAt) {
	return new Intl.DateTimeFormat(locale(), { dateStyle: 'medium' }).format(toDate(startsAt))
}

export function formatTime(startsAt) {
	return new Intl.DateTimeFormat(locale(), { timeStyle: 'short' }).format(toDate(startsAt))
}

export const dayOf = (startsAt) => toDate(startsAt).getDate()

export function monthOf(startsAt) {
	return new Intl.DateTimeFormat(locale(), { month: 'short' }).format(toDate(startsAt))
}

export const yearOf = (startsAt) => toDate(startsAt).getFullYear()

export const toTimestamp = (date) => Math.floor(date.getTime() / 1000)

// Prochaine demi-heure pleine, pour pré-remplir un nouveau rendez-vous.
export function nextHalfHour() {
	const date = new Date()
	date.setSeconds(0, 0)
	date.setMinutes(date.getMinutes() < 30 ? 30 : 60)
	return date
}

/**
 * Les prix sont stockés en centimes ; la devise n'est pas enregistrée, seul le montant est affiché.
 *
 * @param cents
 */
export const formatPrice = (cents) => new Intl.NumberFormat(locale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100)

/**
 * Montant saisi (« 25 », « 25,5 », « 25.50 ») en centimes ; `null` si vide, `NaN` si invalide.
 *
 * @param text
 */
export function parsePrice(text) {
	const value = text.trim().replace(',', '.')
	if (value === '') {
		return null
	}
	return /^\d+(\.\d{1,2})?$/.test(value) ? Math.round(Number(value) * 100) : Number.NaN
}

/**
 * Centimes en montant éditable (« 25.5 » → « 25,50 » selon la locale, sans séparateur de milliers).
 *
 * @param cents
 */
export const priceToInput = (cents) => cents === null || cents === undefined ? '' : (cents / 100).toFixed(2).replace('.', ',')

/**
 * Jour local du rendez-vous au format AAAA-MM-JJ (adresse d'un jour dans l'app Calendrier).
 *
 * @param startsAt
 */
export function isoDay(startsAt) {
	const date = toDate(startsAt)
	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
