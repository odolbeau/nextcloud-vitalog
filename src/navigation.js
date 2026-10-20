import { onBeforeUnmount, onMounted, reactive } from 'vue'

/**
 * Navigation de l'application dans l'URL (?view=…&appointment=…&label=…&practitioner=…), pour que les boutons
 * précédent / suivant du navigateur, le rechargement et les liens partagés fonctionnent. Les valeurs par défaut
 * sont omises de l'URL. Pas de routes côté serveur : tout passe par la chaîne de requête de la page d'accueil.
 */
const VIEWS = ['upcoming', 'past', 'all', 'practitioners', 'places', 'labels']

function id(value) {
	const number = Number(value)
	return Number.isInteger(number) && number > 0 ? number : null
}

function parse(search) {
	const params = new URLSearchParams(search)
	const label = id(params.get('label'))
	const practitioner = id(params.get('practitioner'))
	const requested = params.get('view')
	// un lien vers un filtre (ex. menu des contacts) sans vue précise affiche tous les rendez-vous
	const view = VIEWS.includes(requested) ? requested : (label || practitioner ? 'all' : 'upcoming')

	return { view, appointment: id(params.get('appointment')), label, practitioner }
}

function toUrl(state) {
	const params = new URLSearchParams()
	if (state.view !== 'upcoming') {
		params.set('view', state.view)
	}
	for (const key of ['appointment', 'label', 'practitioner']) {
		if (state[key] !== null) {
			params.set(key, String(state[key]))
		}
	}
	const query = params.toString()

	return window.location.pathname + (query ? '?' + query : '')
}

export const nav = reactive(parse(window.location.search))

/**
 * Change l'état de navigation et l'inscrit dans l'historique (ou remplace l'entrée courante).
 * Changer de vue désélectionne le rendez-vous, sauf indication contraire.
 *
 * @param {object} changes parties de l'état à modifier
 * @param {{replace?: boolean}} options `replace` : ne pas créer d'entrée d'historique
 */
export function go(changes, { replace = false } = {}) {
	const next = { ...nav, ...changes }
	if ('view' in changes && !('appointment' in changes) && changes.view !== nav.view) {
		next.appointment = null
	}
	if (Object.keys(next).every((key) => next[key] === nav[key])) {
		return
	}
	Object.assign(nav, next)
	// une entrée « fenêtre ouverte » est remplacée : la fenêtre qui se ferme ne doit pas laisser d'entrée fantôme derrière elle
	if (replace || window.history.state?.vitalogDialog) {
		window.history.replaceState(null, '', toUrl(next))
	} else {
		window.history.pushState(null, '', toUrl(next))
	}
}

window.addEventListener('popstate', () => Object.assign(nav, parse(window.location.search)))

let dialogCount = 0

/**
 * À appeler dans une fenêtre (montée seulement quand elle est ouverte) : l'ouvrir ajoute une entrée d'historique,
 * « précédent » la ferme, et la fermer autrement (bouton, Échap, enregistrement) retire cette entrée.
 *
 * @param {() => void} close ferme la fenêtre
 */
export function useDialogHistory(close) {
	const token = `dialog-${++dialogCount}`
	const onPopState = () => {
		if (window.history.state?.vitalogDialog !== token) {
			close()
		}
	}
	onMounted(() => {
		window.history.pushState({ vitalogDialog: token }, '')
		window.addEventListener('popstate', onPopState)
	})
	onBeforeUnmount(() => {
		window.removeEventListener('popstate', onPopState)
		if (window.history.state?.vitalogDialog === token) {
			window.history.back()
		}
	})
}
