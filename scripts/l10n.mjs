// Extraction et contrôle des traductions.
//   node scripts/l10n.mjs extract   liste les chaînes sources (src/ et lib/)
//   node scripts/l10n.mjs check     vérifie que l10n/fr.json couvre toutes les chaînes et n'a aucune clé obsolète
//   node scripts/l10n.mjs build     génère l10n/fr.js depuis l10n/fr.json (format attendu par Nextcloud)
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

function walk(dir) {
	return readdirSync(dir).flatMap((name) => {
		const path = join(dir, name)
		return statSync(path).isDirectory() ? walk(path) : [path]
	})
}

// t('vitalog', 'texte', …) côté JS/Vue, $this->l->t('texte') côté PHP
const JS = /\bt\(\s*'vitalog'\s*,\s*'((?:[^'\\]|\\.)*)'/g
const PHP = /->t\(\s*'((?:[^'\\]|\\.)*)'/g

export function extract() {
	const strings = new Set()
	for (const file of walk('src').filter((f) => /\.(vue|js)$/.test(f))) {
		for (const [, text] of readFileSync(file, 'utf8').matchAll(JS)) {
			strings.add(text.replace(/\\'/g, '\''))
		}
	}
	for (const file of walk('lib').filter((f) => f.endsWith('.php'))) {
		for (const [, text] of readFileSync(file, 'utf8').matchAll(PHP)) {
			strings.add(text.replace(/\\'/g, '\''))
		}
	}
	return [...strings].sort()
}

const load = (lang) => JSON.parse(readFileSync(`l10n/${lang}.json`, 'utf8'))
const command = process.argv[2]

if (command === 'extract') {
	console.log(JSON.stringify(Object.fromEntries(extract().map((s) => [s, ''])), null, '\t'))
} else if (command === 'check') {
	const sources = extract()
	const { translations } = load('fr')
	const missing = sources.filter((s) => !translations[s])
	const stale = Object.keys(translations).filter((s) => !sources.includes(s))
	for (const s of missing) {
		console.error(`missing fr translation: ${s}`)
	}
	for (const s of stale) {
		console.error(`stale fr translation: ${s}`)
	}
	if (missing.length || stale.length) {
		process.exit(1)
	}
	console.log(`l10n ok (${sources.length} strings)`)
} else if (command === 'build') {
	const { translations, pluralForm } = load('fr')
	writeFileSync('l10n/fr.js', `OC.L10N.register(\n    "vitalog",\n    ${JSON.stringify(translations, null, 4).replace(/\n/g, '\n    ')},\n    ${JSON.stringify(pluralForm)});\n`)
} else {
	console.error('usage: l10n.mjs extract|check|build')
	process.exit(2)
}
