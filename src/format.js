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
