<template>
	<NcAppContent
		:showDetails="selected !== null"
		:pageHeading="heading"
		:listSize="32"
		:listMaxWidth="50"
		@update:showDetails="onShowDetails">
		<template #list>
			<NcAppContentList>
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
										{{ option.label }}
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
								<Stethoscope v-else :size="16" />
							</template>
						</NcChip>
					</li>
				</ul>

				<NcLoadingIcon v-if="!store.loaded" class="list__loading" :size="32" />
				<ul v-else-if="items.length > 0" class="list" :aria-label="heading">
					<NcListItem
						v-for="item in items"
						:key="item.id"
						:name="item.reason || t('vitalog', 'Medical appointment')"
						:active="item.id === selectedId"
						:data-test="`appointment-${item.id}`"
						compact
						@click.prevent="selectedId = item.id">
						<template #icon>
							<DateBadge :startsAt="item.startsAt" :muted="store.isPast(item)" />
						</template>
						<template #subname>
							{{ subname(item) }}
						</template>
						<template #details>
							<span>{{ formatTime(item.startsAt) }}</span>
						</template>
					</NcListItem>
				</ul>
				<NcEmptyContent
					v-else
					class="list__empty"
					:name="emptyTitle"
					:description="emptyDescription">
					<template #icon>
						<CalendarBlank />
					</template>
					<template v-if="!filtering && filter !== 'past'" #action>
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
import Close from 'vue-material-design-icons/Close.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'
import FilterVariantRemove from 'vue-material-design-icons/FilterVariantRemove.vue'
import Stethoscope from 'vue-material-design-icons/Stethoscope.vue'
import Tag from 'vue-material-design-icons/Tag.vue'
import AppointmentDetails from '../components/AppointmentDetails.vue'
import DateBadge from '../components/DateBadge.vue'
import { formatDate, formatTime } from '../format.js'
import { go, nav } from '../navigation.js'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	filter: { type: String, required: true },
	// Libellé qui filtre la liste (identifiant, ou null).
	labelId: { type: Number, default: null },
	// Praticien qui filtre la liste (identifiant, ou null).
	practitionerId: { type: Number, default: null },
})
const emit = defineEmits(['create', 'edit', 'update:labelId', 'update:practitionerId', 'showLabel'])

const store = useDataStore()
const query = ref('')
// le rendez-vous sélectionné fait partie de l'URL : « précédent » revient à la liste (ou à la sélection d'avant)
const selectedId = computed({
	get: () => nav.appointment,
	set: (id) => go({ appointment: id }),
})
const filterOpen = ref(false)
// Un « 0 » signifie « tous » (les identifiants commencent à 1) : une valeur de bouton radio ne peut pas être null.
const groups = computed(() => [
	{
		key: 'label',
		title: t('vitalog', 'Label'),
		options: store.labels.length > 0 ? [{ id: 0, label: t('vitalog', 'All labels') }, ...store.labels.map((l) => ({ id: l.id, label: l.name }))] : [],
		current: props.labelId ?? 0,
	},
	{
		key: 'practitioner',
		title: t('vitalog', 'Practitioner'),
		options: store.practitioners.length > 0 ? [{ id: 0, label: t('vitalog', 'All practitioners') }, ...store.practitioners.map((p) => ({ id: p.id, label: p.name }))] : [],
		current: props.practitionerId ?? 0,
	},
].filter((group) => group.options.length > 0))
const activeFilters = computed(() => [
	props.labelId !== null && store.labelById(props.labelId) && { key: 'label', text: store.labelById(props.labelId).name },
	props.practitionerId !== null && store.practitionerById(props.practitionerId) && { key: 'practitioner', text: store.practitionerById(props.practitionerId).name },
].filter(Boolean))

const heading = computed(() => ({
	upcoming: t('vitalog', 'Upcoming appointments'),
	past: t('vitalog', 'Past appointments'),
	all: t('vitalog', 'All appointments'),
})[props.filter])

const source = computed(() => store[props.filter])
const items = computed(() => {
	const needle = query.value.trim().toLowerCase()
	const labelled = source.value.filter((a) => (props.labelId === null || a.labelIds?.includes(props.labelId))
		&& (props.practitionerId === null || a.practitionerId === props.practitionerId))
	if (needle === '') {
		return labelled
	}
	return labelled.filter((a) => [
		a.reason,
		a.notes,
		store.practitionerById(a.practitionerId)?.name,
		store.practitionerById(a.practitionerId)?.specialty,
		store.placeById(a.placeId)?.name,
		...store.labelsOf(a).map((l) => l.name),
	].some((text) => text?.toLowerCase().includes(needle)))
})
const selected = computed(() => store.appointments.find((a) => a.id === selectedId.value) ?? null)

const filtering = computed(() => query.value !== '' || props.labelId !== null || props.practitionerId !== null)
const emptyTitle = computed(() => filtering.value
	? t('vitalog', 'No match')
	: { upcoming: t('vitalog', 'Nothing planned'), past: t('vitalog', 'No past appointments'), all: t('vitalog', 'No appointments yet') }[props.filter])
const emptyDescription = computed(() => filtering.value
	? t('vitalog', 'No appointment matches your search.')
	: props.filter === 'past'
		? t('vitalog', 'Appointments that are over will show up here.')
		: t('vitalog', 'Keep track of your medical appointments in one place.'))

function setFilter(key, id) {
	emit(key === 'label' ? 'update:labelId' : 'update:practitionerId', id || null)
}

function resetFilters() {
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
