<template>
	<NcAppContent
		:showDetails="selected !== null"
		:pageHeading="heading"
		@update:showDetails="onShowDetails">
		<template #list>
			<NcAppContentList>
				<div class="list__toolbar">
					<NcTextField
						v-model="query"
						:label="t('vitalog', 'Search appointments')"
						:placeholder="t('vitalog', 'Search…')"
						trailingButtonIcon="close"
						:showTrailingButton="query !== ''"
						:trailingButtonLabel="t('vitalog', 'Clear search')"
						@trailingButtonClick="query = ''" />
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
					<template v-if="query === '' && filter !== 'past'" #action>
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
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
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
})
const emit = defineEmits(['create', 'edit'])

const store = useDataStore()
const query = ref(props.initialQuery)
const selectedId = ref(null)

const heading = computed(() => ({
	upcoming: t('vitalog', 'Upcoming appointments'),
	past: t('vitalog', 'Past appointments'),
	all: t('vitalog', 'All appointments'),
})[props.filter])

const source = computed(() => store[props.filter])
const items = computed(() => {
	const needle = query.value.trim().toLowerCase()
	if (needle === '') {
		return source.value
	}
	return source.value.filter((a) => [
		a.reason,
		a.notes,
		store.practitionerById(a.practitionerId)?.name,
		store.practitionerById(a.practitionerId)?.specialty,
		store.placeById(a.placeId)?.name,
	].some((text) => text?.toLowerCase().includes(needle)))
})
const selected = computed(() => store.appointments.find((a) => a.id === selectedId.value) ?? null)

const emptyTitle = computed(() => query.value !== ''
	? t('vitalog', 'No match')
	: { upcoming: t('vitalog', 'Nothing planned'), past: t('vitalog', 'No past appointments'), all: t('vitalog', 'No appointments yet') }[props.filter])
const emptyDescription = computed(() => query.value !== ''
	? t('vitalog', 'No appointment matches your search.')
	: props.filter === 'past'
		? t('vitalog', 'Appointments that are over will show up here.')
		: t('vitalog', 'Keep track of your medical appointments in one place.'))

function onShowDetails(show) {
	if (!show) {
		selectedId.value = null
	}
}

function subname(item) {
	const parts = [
		store.practitionerById(item.practitionerId)?.name,
		store.placeById(item.placeId)?.name,
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
		padding: 8px 8px 8px calc(var(--default-clickable-area) + 12px);
	}

	&__loading {
		margin-top: 40px;
	}

	&__empty {
		margin-top: 40px;
	}
}
</style>
