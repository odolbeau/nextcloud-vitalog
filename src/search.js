/** Minuscules et sans accents : « Hélène » et « helene » sont équivalents pour une recherche. */
export const normalize = (text) => String(text ?? '').normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase()

export const contains = (text, needle) => normalize(text).includes(normalize(needle))

/** `filterBy` d'un NcSelect : le filtre par défaut distingue les accents. */
export const filterByText = (option, label, search) => contains(label, search)
