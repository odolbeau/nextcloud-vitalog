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
					<NcPopover v-if="store.labels.length > 0" v-model:shown="filterOpen" popupRole="dialog">
						<template #trigger="{ attrs }">
							<NcButton
								v-bind="attrs"
								:variant="labelFilter ? 'secondary' : 'tertiary'"
								:aria-label="t('vitalog', 'Filter by label')"
								:title="t('vitalog', 'Filter by label')"
								data-test="label-filter-button">
								<template #icon>
									<FilterVariantRemove v-if="labelFilter" :size="20" />
									<FilterVariant v-else :size="20" />
								</template>
							</NcButton>
						</template>
						<div class="list__popover">
							<div role="radiogroup" :aria-label="t('vitalog', 'Filter by label')" class="list__choices">
								<NcCheckboxRadioSwitch
									v-for="label in store.labels"
									:key="label.id"
									:modelValue="labelId"
									type="radio"
									name="vitalog-label-filter"
									:value="label.id"
									@update:modelValue="chooseLabel(label.id)">
									{{ label.name }}
								</NcCheckboxRadioSwitch>
							</div>
							<NcButton v-if="labelFilter" variant="tertiary" @click="resetLabelFilter">
								<template #icon>
									<Close :size="20" />
								</template>
								{{ t('vitalog', 'Reset the filter') }}
							</NcButton>
						</div>
					</NcPopover>
				</div>
				<div v-if="labelFilter" class="list__active-filter">
					<NcChip
						:text="labelFilter.label"
						:ariaLabelClose="t('vitalog', 'Remove the label filter')"
						@close="labelFilter = null" />
				</div>

				<NcLoadingIcon v-if="!store.loaded" class="list__loading" :size="32" />
				<ul v-else-if="items.length > 0" class="list" :aria-label="heading">
					<NcListItem
						v-for="item in items"
						:key="item.id"
						:name="item.reason || t('vitalog', 'Medical appointment')"
						:active="item.id === selectedId"
						:data-test="`appointment-${item.id}`"
						compact
						@click="selectedId = item.id">
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
			@deleted="selectedId = null" />
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
import { computed, ref, watch } from 'vue'
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
import AppointmentDetails from '../components/AppointmentDetails.vue'
import DateBadge from '../components/DateBadge.vue'
import { formatDate, formatTime } from '../format.js'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	filter: { type: String, required: true },
	// Identifiant à sélectionner (ex. après création).
	focusId: { type: Number, default: null },
	// Recherche appliquée à l'ouverture (ex. rendez-vous d'un praticien).
	initialQuery: { type: String, default: '' },
	// Libellé qui filtre la liste (identifiant, ou null).
	labelId: { type: Number, default: null },
})
const emit = defineEmits(['create', 'edit', 'update:labelId', 'showLabel'])

const store = useDataStore()
const query = ref(props.initialQuery)
const selectedId = ref(null)
const filterOpen = ref(false)
const labelOptions = computed(() => store.labels.map((l) => ({ id: l.id, label: l.name })))
const labelFilter = computed({
	get: () => labelOptions.value.find((o) => o.id === props.labelId) ?? null,
	set: (option) => emit('update:labelId', option?.id ?? null),
})

const heading = computed(() => ({
	upcoming: t('vitalog', 'Upcoming appointments'),
	past: t('vitalog', 'Past appointments'),
	all: t('vitalog', 'All appointments'),
})[props.filter])

const source = computed(() => store[props.filter])
const items = computed(() => {
	const needle = query.value.trim().toLowerCase()
	const labelled = props.labelId === null ? source.value : source.value.filter((a) => a.labelIds?.includes(props.labelId))
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

const filtering = computed(() => query.value !== '' || props.labelId !== null)
const emptyTitle = computed(() => filtering.value
	? t('vitalog', 'No match')
	: { upcoming: t('vitalog', 'Nothing planned'), past: t('vitalog', 'No past appointments'), all: t('vitalog', 'No appointments yet') }[props.filter])
const emptyDescription = computed(() => filtering.value
	? t('vitalog', 'No appointment matches your search.')
	: props.filter === 'past'
		? t('vitalog', 'Appointments that are over will show up here.')
		: t('vitalog', 'Keep track of your medical appointments in one place.'))

function chooseLabel(id) {
	emit('update:labelId', id)
	filterOpen.value = false
}

function resetLabelFilter() {
	labelFilter.value = null
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

watch(() => props.initialQuery, (value) => {
	query.value = value
})
watch(() => props.filter, () => {
	selectedId.value = null
})
watch(() => props.focusId, (id) => {
	if (id !== null) {
		selectedId.value = id
	}
}, { immediate: true })
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
		padding: 0 8px 8px calc(var(--default-clickable-area) + 12px);
	}

	&__choices {
		max-height: 300px;
		overflow-y: auto;
	}

	// le champ de recherche aligné sur les boutons voisins (NcTextField réserve 6 px au-dessus pour son libellé)
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
