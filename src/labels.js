/**
 * Couleur de texte (noir ou blanc) la plus lisible sur ce fond `#rrggbb` : celle qui offre le meilleur contraste (WCAG),
 * toujours au moins 4,5:1 sauf pour des gris moyens, où les deux le frôlent.
 *
 * @param {string} background
 */
export function readableTextColor(background) {
	const [r, g, b] = [1, 3, 5].map((i) => parseInt(background.slice(i, i + 2), 16) / 255)
		.map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4))
	const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b

	// contraste avec le blanc : 1,05 / (L + 0,05) ; avec le noir : (L + 0,05) / 0,05
	return 1.05 / (luminance + 0.05) >= (luminance + 0.05) / 0.05 ? '#fff' : '#000'
}
