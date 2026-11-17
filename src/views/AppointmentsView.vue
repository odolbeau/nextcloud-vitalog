<template>
	<NcAppContent
		:showDetails="selected !== null"
		:pageHeading="heading"
		:listSize="32"
		:listMaxWidth="50"
		@update:showDetails="onShowDetails">
		<template #list>
			<NcAppContentList class="list__pane">
				<div class="list__toolbar">
					<NcTextField
						v-model="query"
						class="list__search"
						:label="t('vitalog', 'Search appointments')"
						:placeholder="t('vitalog', 'Search…')"
						trailingButtonIcon="close"
						:showTrailingButton="query !== ''"
						:trailingButtonLabel="t('vitalog', 'Clear search')"
						@trailingButtonClick="query = ''" />
					<NcPopover v-if="groups.length > 0" v-model:shown="filterOpen" popupRole="dialog">
						<template #trigger="{ attrs }">
							<NcButton
								v-bind="attrs"
								:variant="activeFilters.length > 0 ? 'secondary' : 'tertiary'"
								:aria-label="t('vitalog', 'Filter')"
								:title="t('vitalog', 'Filter')"
								data-test="filter-button">
								<template #icon>
									<FilterVariantRemove v-if="activeFilters.length > 0" :size="20" />
									<FilterVariant v-else :size="20" />
								</template>
							</NcButton>
						</template>
						<div class="list__popover">
							<section v-for="group in groups" :key="group.key" class="list__group">
								<h3>{{ group.title }}</h3>
								<div role="radiogroup" :aria-label="group.title" class="list__choices">
									<NcCheckboxRadioSwitch
										v-for="option in group.options"
										:key="option.id"
										:modelValue="group.current"
										type="radio"
										:name="`vitalog-filter-${group.key}`"
										:value="option.id"
										@update:modelValue="setFilter(group.key, option.id)">
										<span v-if="option.icon" class="list__choice">
											<PractitionerIcon :icon="option.icon" :size="12" />
											{{ option.label }}
										</span>
										<template v-else>
											{{ option.label }}
										</template>
									</NcCheckboxRadioSwitch>
								</div>
							</section>
							<NcButton v-if="activeFilters.length > 0" variant="tertiary" @click="resetFilters">
								<template #icon>
									<Close :size="20" />
								</template>
								{{ t('vitalog', 'Reset the filter') }}
							</NcButton>
						</div>
					</NcPopover>
				</div>
				<ul v-if="activeFilters.length > 0" class="list__active-filter" :aria-label="t('vitalog', 'Active filters')">
					<li v-for="active in activeFilters" :key="active.key">
						<NcChip
							:text="active.text"
							:ariaLabelClose="t('vitalog', 'Remove the filter {name}', { name: active.text })"
							@close="setFilter(active.key, null)">
							<template #icon>
								<Tag v-if="active.key === 'label'" :size="16" />
								<CalendarClock v-else-if="active.key === 'when'" :size="16" />
								<Stethoscope v-else :size="16" />
							</template>
						</NcChip>
					</li>
				</ul>

				<NcLoadingIcon v-if="!store.loaded" class="list__loading" :size="32" />
				<ul v-else-if="items.length > 0" class="list" :aria-label="heading">
					<li
						v-for="year in years"
						:key="year.year"
						class="list__year"
						:data-test="`year-${year.year}`">
						<h3 :id="`vitalog-year-${year.year}`" class="list__year-title">
							{{ year.year }}
						</h3>
						<ul :aria-labelledby="`vitalog-year-${year.year}`">
							<template v-for="item in year.items" :key="item.id">
								<li v-if="item.id === firstPastId" class="list__today" data-test="today">
									{{ t('vitalog', 'Today') }}
								</li>
								<NcListItem
									:name="item.reason || t('vitalog', 'Medical appointment')"
									:active="item.id === selectedId"
									:data-test="`appointment-${item.id}`"
									compact
									@click.prevent="selectedId = item.id">
									<template #icon>
										<DateBadge :startsAt="item.startsAt" :muted="store.isPast(item)" :highlighted="item.id === nextId" />
									</template>
									<template #name>
										<span class="list__name">
											<PractitionerIcon :icon="appointmentIcon(item, store.practitionerById(item.practitionerId), store.placeById(item.placeId))" :size="14" />
											<span class="list__name-text">{{ item.reason || t('vitalog', 'Medical appointment') }}</span>
											<span v-if="item.id === nextId" class="list__next">{{ t('vitalog', 'Next') }}</span>
										</span>
									</template>
									<template #subname>
										{{ subname(item) }}
									</template>
									<template #details>
										<span>{{ formatTime(item.startsAt) }}</span>
									</template>
								</NcListItem>
							</template>
						</ul>
					</li>
				</ul>
				<NcEmptyContent
					v-else
					class="list__empty"
					:name="emptyTitle"
					:description="emptyDescription">
					<template #icon>
						<CalendarBlank />
					</template>
					<template v-if="!filtering && when !== 'past'" #action>
						<NcButton variant="primary" @click="emit('create')">
							{{ t('vitalog', 'New appointment') }}
						</NcButton>
					</template>
				</NcEmptyContent>
			</NcAppContentList>
		</template>

		<AppointmentDetails
			v-if="selected"
			:key="selected.id"
			:appointment="selected"
			@edit="emit('edit', selected)"
			@filterLabel="showLabel"
			@deleted="go({ appointment: null }, { replace: true })" />
		<NcEmptyContent
			v-else
			:name="t('vitalog', 'Select an appointment')"
			:description="t('vitalog', 'Pick an appointment in the list to see its details and documents.')">
			<template #icon>
				<CalendarBlank />
			</template>
		</NcEmptyContent>
	</NcAppContent>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppContentList from '@nextcloud/vue/components/NcAppContentList'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import Close from 'vue-material-design-icons/Close.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'
import FilterVariantRemove from 'vue-material-design-icons/FilterVariantRemove.vue'
import Stethoscope from 'vue-material-design-icons/Stethoscope.vue'
import Tag from 'vue-material-design-icons/Tag.vue'
import AppointmentDetails from '../components/AppointmentDetails.vue'
import DateBadge from '../components/DateBadge.vue'
import PractitionerIcon from '../components/PractitionerIcon.vue'
import { formatDate, formatTime, yearOf } from '../format.js'
import { go, nav } from '../navigation.js'
import { appointmentIcon, iconOf } from '../practitionerIcons.js'
import { contains } from '../search.js'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	// Période affichée : 'upcoming', 'past', ou null pour tous les rendez-vous.
	when: { type: String, default: null },
	// Libellé qui filtre la liste (identifiant, ou null).
	labelId: { type: Number, default: null },
	// Praticien qui filtre la liste (identifiant, ou null).
	practitionerId: { type: Number, default: null },
})
const emit = defineEmits(['create', 'edit', 'update:when', 'update:labelId', 'update:practitionerId', 'showLabel'])

const store = useDataStore()
const query = ref('')
// le rendez-vous sélectionné fait partie de l'URL : « précédent » revient à la liste (ou à la sélection d'avant)
const selectedId = computed({
	get: () => nav.appointment,
	set: (id) => go({ appointment: id }),
})
const filterOpen = ref(false)
// Un « 0 » signifie « tous » (les identifiants commencent à 1) : une valeur de bouton radio ne peut pas être null.
const periods = computed(() => ({ upcoming: t('vitalog', 'Upcoming'), past: t('vitalog', 'Past') }))
const groups = computed(() => [
	{
		key: 'when',
		title: t('vitalog', 'Period'),
		options: [{ id: 0, label: t('vitalog', 'All appointments') }, ...Object.entries(periods.value).map(([id, label]) => ({ id, label }))],
		current: props.when ?? 0,
	},
	{
		key: 'label',
		title: t('vitalog', 'Label'),
		options: store.labels.length > 0 ? [{ id: 0, label: t('vitalog', 'All labels') }, ...store.labels.map((l) => ({ id: l.id, label: l.name }))] : [],
		current: props.labelId ?? 0,
	},
	{
		key: 'practitioner',
		title: t('vitalog', 'Practitioner'),
		options: store.practitioners.length > 0 ? [{ id: 0, label: t('vitalog', 'All practitioners') }, ...store.practitioners.map((p) => ({ id: p.id, label: p.name, icon: iconOf(p) }))] : [],
		current: props.practitionerId ?? 0,
	},
].filter((group) => group.options.length > 0))
const activeFilters = computed(() => [
	props.when !== null && { key: 'when', text: periods.value[props.when] },
	props.labelId !== null && store.labelById(props.labelId) && { key: 'label', text: store.labelById(props.labelId).name },
	props.practitionerId !== null && store.practitionerById(props.practitionerId) && { key: 'practitioner', text: store.practitionerById(props.practitionerId).name },
].filter(Boolean))

const heading = t('vitalog', 'Appointments')

// recherche et filtres par libellé et par praticien
function matches(a) {
	if ((props.labelId !== null && !a.labelIds?.includes(props.labelId)) || (props.practitionerId !== null && a.practitionerId !== props.practitionerId)) {
		return false
	}
	const needle = query.value.trim()
	return needle === '' || [
		a.reason,
		a.notes,
		store.practitionerById(a.practitionerId)?.name,
		store.practitionerById(a.practitionerId)?.specialty,
		store.placeById(a.placeId)?.name,
		...store.labelsOf(a).map((l) => l.name),
	].some((text) => contains(text, needle))
}

// un intertitre à chaque changement d'année, dans l'ordre de la liste
function byYear(list) {
	const result = []
	for (const item of list) {
		const year = yearOf(item.startsAt)
		if (result.at(-1)?.year !== year) {
			result.push({ year, items: [] })
		}
		result.at(-1).items.push(item)
	}
	return result
}

// un seul ordre, du plus récent au plus ancien : les rendez-vous à venir en haut, puis les passés
const upcoming = computed(() => (props.when === 'past' ? [] : store.upcoming.filter(matches)))
const past = computed(() => (props.when === 'upcoming' ? [] : store.past.filter(matches)))
const items = computed(() => [...upcoming.value].reverse().concat(past.value))
const years = computed(() => byYear(items.value))
// le prochain rendez-vous (parmi ceux affichés), juste au-dessus du séparateur « Aujourd'hui », est mis en avant
const nextId = computed(() => upcoming.value[0]?.id ?? null)
// le séparateur n'a de sens qu'entre des rendez-vous à venir et des passés
const firstPastId = computed(() => (upcoming.value.length > 0 ? past.value[0]?.id ?? null : null))

const selected = computed(() => store.appointments.find((a) => a.id === selectedId.value) ?? null)

const filtering = computed(() => query.value !== '' || props.labelId !== null || props.practitionerId !== null)
const emptyTitle = computed(() => filtering.value
	? t('vitalog', 'No match')
	: { upcoming: t('vitalog', 'Nothing planned'), past: t('vitalog', 'No past appointments') }[props.when] ?? t('vitalog', 'No appointments yet'))
const emptyDescription = computed(() => filtering.value
	? t('vitalog', 'No appointment matches your search.')
	: props.when === 'past'
		? t('vitalog', 'Appointments that are over will show up here.')
		: t('vitalog', 'Keep track of your medical appointments in one place.'))

function setFilter(key, id) {
	emit({ when: 'update:when', label: 'update:labelId', practitioner: 'update:practitionerId' }[key], id || null)
}

function resetFilters() {
	emit('update:when', null)
	emit('update:labelId', null)
	emit('update:practitionerId', null)
	filterOpen.value = false
}

function showLabel(id) {
	emit('showLabel', id)
}

function onShowDetails(show) {
	if (!show) {
		selectedId.value = null
	}
}

function subname(item) {
	const parts = [
		store.practitionerById(item.practitionerId)?.name,
		store.placeById(item.placeId)?.name,
		store.labelsOf(item).map((l) => l.name).join(', '),
	].filter(Boolean)
	// la date figure déjà dans la pastille ; sans praticien ni lieu on affiche tout de même la date complète
	return parts.length > 0 ? parts.join(' · ') : formatDate(item.startsAt)
}

</script>

<style scoped lang="scss">
.placeholder {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
}

.list {
	padding: 0 8px 8px;

	// Contournement : sur un écran étroit, le CSS du serveur (core/css/mobile.scss) retire le `max-height` de la liste
	// (`max-height: unset`, pensé pour une page qui défile elle-même), alors que le bloc de @nextcloud/vue qui la contient
	// ne défile pas : la liste dépasse l'écran et ses derniers éléments sont inatteignables. On la borne à son bloc pour
	// qu'elle défile (constaté avec Nextcloud 35.0.2 et @nextcloud/vue 9.13.1 et 9.14.0).
	&__pane {
		max-height: 100%;
	}

	&__year {
		list-style: none;
	}

	// séparateur entre les rendez-vous à venir et les passés
	&__today {
		display: flex;
		align-items: center;
		gap: 8px;
		margin: 4px 8px;
		color: var(--color-primary-element);
		font-size: 0.85em;
		font-weight: bold;
		list-style: none;

		&::after {
			content: '';
			flex: 1;
			border-top: 2px solid var(--color-primary-element);
		}
	}

	&__next {
		flex-shrink: 0;
		padding: 0 6px;
		border-radius: var(--border-radius-pill);
		background-color: var(--color-primary-element);
		color: var(--color-primary-element-text);
		font-size: 0.8em;
		font-weight: normal;
		line-height: 1.6;
	}

	&__choice {
		display: inline-flex;
		align-items: center;
		gap: 8px;
	}

	&__name {
		display: flex;
		align-items: center;
		gap: 6px;
		min-width: 0;
	}

	&__name-text {
		overflow: hidden;
		text-overflow: ellipsis;
	}

	// l'année reste visible en haut de la liste pendant le défilement, à droite du bouton du menu (comme la recherche)
	&__year-title {
		position: sticky;
		top: 0;
		z-index: 1;
		margin: 0;
		padding: 8px 8px 4px calc(var(--default-clickable-area) + 4px);
		min-height: var(--default-clickable-area);
		box-sizing: border-box;
		background-color: var(--color-main-background);
		font-size: 1em;
		font-weight: bold;
		color: var(--color-text-maxcontrast);
	}

	&__toolbar {
		display: flex;
		align-items: flex-start;
		gap: 4px;
		padding: 8px 8px 8px calc(var(--default-clickable-area) + 12px);
	}

	&__search.input-field {
		flex: 1;
		min-width: 0;
		margin-block-start: 0;
	}

	&__active-filter {
		display: flex;
		flex-wrap: wrap;
		gap: 4px 6px;
		margin: 0;
		padding: 0 8px 8px calc(var(--default-clickable-area) + 12px);
		list-style: none;
	}

	&__group h3 {
		margin: 0 0 4px;
		font-size: 1em;
	}

	&__choices {
		max-height: 200px;
		overflow-y: auto;
	}

	&__popover {
		display: flex;
		flex-direction: column;
		gap: 8px;
		width: 260px;
		max-width: 100%;
		padding: 12px;
	}

	&__loading {
		margin-top: 40px;
	}

	&__empty {
		margin-top: 40px;
	}
}
</style>
