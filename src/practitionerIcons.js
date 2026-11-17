// Icônes des praticiens. Les clés sont celles acceptées par l'API (`Practitioner::ICONS`) ; sans icône choisie, elle est
// déduite de la spécialité (premier motif reconnu, dans l'ordre ci-dessous, sur le texte en minuscules et sans accents).
// Pas d'import ici : les tests e2e lisent ce fichier directement.

export const DEFAULT_ICON = 'stethoscope'

export const ICONS = [
	{ key: 'stethoscope', color: '#0082c9', match: /\b(generali|medecin|medecine generale|gp\b|family|doctor)/ },
	{ key: 'oncology', color: '#ad1457', match: /\b(onco|cancer|radiother|chimio|chemo)/ },
	{ key: 'tooth', color: '#1e88e5', match: /\b(dent|orthodont|stomato|parodont|implanto|endodont)/ },
	{ key: 'child', color: '#fb8c00', match: /\b(pediat|paediat|pedopsy|neonat|child|enfant)/ },
	{ key: 'pregnancy', color: '#d81b60', match: /\b(gyn|obstet|sages?-femmes?|midwi|maternit|pregnan|grossesse|fertil)/ },
	{ key: 'eye', color: '#00897b', match: /\b(ophtalm|ophthalm|orthopt|optic|optom|ocul|oeil|yeux|eyes?\b|vision)/ },
	{ key: 'ear', color: '#8e24aa', match: /\b(orl\b|ent\b|oto|audio|ears?\b|oreille)/ },
	{ key: 'heart', color: '#e53935', match: /\b(cardi|heart|coeur|vascul|angio|phlebo)/ },
	{ key: 'rehab', color: '#43a047', match: /\b(kine|physio|reeduc|rehab|ergo|psychomot|orthophon|speech|sport)/ },
	{ key: 'brain', color: '#7e57c2', match: /\b(neuro|psy|brain|cerveau|sophro|hypno)/ },
	{ key: 'lungs', color: '#039be5', match: /\b(pneumo|pulmo|respir|lung|poumon)/ },
	{ key: 'allergy', color: '#7cb342', match: /\b(allerg)/ },
	{ key: 'skin', color: '#f4511e', match: /\b(dermat|skin|peau)/ },
	{ key: 'stomach', color: '#ef6c00', match: /\b(gastro|hepato|digest|enterolog|proctolog|stomach)/ },
	{ key: 'kidney', color: '#00acc1', match: /\b(uro|nephro|kidney|reins?\b)/ },
	{ key: 'nutrition', color: '#689f38', match: /\b(nutri|diet)/ },
	{ key: 'foot', color: '#6d4c41', match: /\b(podo|pedicur|podiat|foot|feet|pieds?\b)/ },
	{ key: 'bone', color: '#8d6e63', match: /\b(rhumato|rheumato|orthop|osteo|chiropra|traumato|bones?\b|os\b)/ },
	{ key: 'imaging', color: '#546e7a', match: /\b(radio|imag|echo|irm\b|mri\b|scanner|ultraso|mammo|x-?ray)/ },
	{ key: 'lab', color: '#5e35b1', match: /\b(labo|biolog|analys|sang|blood|prelevement)/ },
	{ key: 'nursing', color: '#26a69a', match: /\b(infirm|nurse|nursing|vaccin|injection|soins)/ },
	{ key: 'pharmacy', color: '#2e7d32', match: /\b(pharma)/ },
	{ key: 'hospital', color: '#c62828', match: /\b(hopital|hospital|chirurg|surg|urgence|emergenc|clinique|clinic|anesth)/ },
]

const byKey = new Map(ICONS.map((icon) => [icon.key, icon]))

const normalize = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().replace(/œ/g, 'oe').replace(/æ/g, 'ae')

/** Icône reconnue dans un texte (« Chirurgien-dentiste » → dent), ou null. */
function match(text) {
	const normalized = normalize(text ?? '')
	return normalized === '' ? null : (ICONS.find((icon) => icon.match.test(normalized))?.key ?? null)
}

const chosen = (practitioner) => (practitioner?.icon && byKey.has(practitioner.icon) ? practitioner.icon : null)

/** Icône déduite d'une spécialité ; l'icône par défaut si rien n'est reconnu. */
export const iconFromSpecialty = (specialty) => match(specialty) ?? DEFAULT_ICON

/** Icône d'un praticien : celle choisie, sinon celle de sa spécialité. */
export const iconOf = (practitioner) => chosen(practitioner) ?? iconFromSpecialty(practitioner?.specialty)

/**
 * Icône d'un rendez-vous : celle de son praticien (choisie ou reconnue dans sa spécialité), sinon celle reconnue dans le
 * motif (« Prise de sang ») puis dans le nom du lieu (« Laboratoire… »).
 */
export function appointmentIcon(appointment, practitioner, place) {
	return chosen(practitioner)
		?? match(practitioner?.specialty)
		?? match(appointment.reason)
		?? match(place?.name)
		?? DEFAULT_ICON
}

export const iconColor = (key) => byKey.get(key)?.color ?? byKey.get(DEFAULT_ICON).color
