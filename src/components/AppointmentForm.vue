<template>
	<NcDialog
		:name="appointment ? t('vitalog', 'Edit appointment') : t('vitalog', 'New appointment')"
		isForm
		size="normal"
		@submit="submit"
		@closing="emit('close')">
		<div class="form">
			<template v-if="!appointment">
				<input
					ref="icsInput"
					type="file"
					accept=".ics,text/calendar"
					hidden
					data-test="ics-input"
					@change="readIcs">
				<NcButton :disabled="reading" @click="icsInput.click()">
					<template #icon>
						<CalendarImport :size="20" />
					</template>
					{{ t('vitalog', 'Fill from an .ics file') }}
				</NcButton>
				<NcNoteCard v-if="existing" type="warning">
					<p>{{ t('vitalog', 'This event is already in Vitalog.') }}</p>
					<NcButton variant="secondary" @click="openExisting">
						{{ t('vitalog', 'Open the appointment') }}
					</NcButton>
				</NcNoteCard>
				<NcNoteCard v-else-if="filledFrom" type="info">
					{{ t('vitalog', 'Filled from {file}: check the details before saving.', { file: filledFrom }) }}
				</NcNoteCard>
			</template>

			<NcDateTimePickerNative
				id="vitalog-starts-at"
				v-model="startsAt"
				type="datetime-local"
				:label="t('vitalog', 'Date and time')"
				required />

			<NcSelect
				v-model="duration"
				:filterBy="filterByText"
				inputId="vitalog-duration"
				:inputLabel="t('vitalog', 'Duration')"
				:options="durations"
				:clearable="false"
				label="label" />

			<div class="form__row">
				<NcSelect
					v-model="practitioner"
					:filterBy="filterByText"
					class="form__grow"
					inputId="vitalog-practitioner"
					:inputLabel="t('vitalog', 'Practitioner')"
					:options="practitionerOptions"
					:placeholder="t('vitalog', 'None')"
					label="label" />
				<NcButton
					:aria-label="t('vitalog', 'New practitioner')"
					:title="t('vitalog', 'New practitioner')"
					@click="creating = 'practitioner'">
					<template #icon>
						<Plus :size="20" />
					</template>
				</NcButton>
			</div>

			<div class="form__row">
				<NcSelect
					:modelValue="place"
					:filterBy="filterByText"
					class="form__grow"
					inputId="vitalog-place"
					:inputLabel="t('vitalog', 'Place')"
					:options="placeOptions"
					:placeholder="t('vitalog', 'None')"
					label="label"
					@update:modelValue="pickPlace" />
				<NcButton
					:aria-label="t('vitalog', 'New place')"
					:title="t('vitalog', 'New place')"
					@click="creating = 'place'">
					<template #icon>
						<Plus :size="20" />
					</template>
				</NcButton>
			</div>

			<div v-if="location && place === null" class="form__hint">
				<span>{{ t('vitalog', 'Address in the file: {address}', { address: location }) }}</span>
				<NcButton variant="tertiary" @click="createPlaceFromLocation">
					{{ t('vitalog', 'Create this place') }}
				</NcButton>
			</div>

			<NcSelect
				v-model="labels"
				:filterBy="filterByText"
				inputId="vitalog-labels"
				:inputLabel="t('vitalog', 'Labels')"
				:options="labelOptions"
				:placeholder="t('vitalog', 'Pick or type to create…')"
				:createOption="(name) => toOption(null, name.trim())"
				label="label"
				multiple
				taggable
				keepOpen />

			<NcTextField
				v-model="reason"
				:maxlength="255"
				:label="t('vitalog', 'Reason')"
				:placeholder="t('vitalog', 'Check-up, follow-up, …')" />
			<NcTextField
				v-model="price"
				inputmode="decimal"
				:label="t('vitalog', 'Price')"
				:error="priceInvalid"
				:helperText="priceInvalid ? t('vitalog', 'Enter an amount such as 25 or 25.50.') : ''" />
			<NcTextArea v-model="notes" :label="t('vitalog', 'Notes')" rows="4" />
		</div>

		<PractitionerForm
			v-if="creating === 'practitioner'"
			@saved="(p) => practitioner = toOption(p.id, p.name)"
			@close="creating = null" />
		<PlaceForm
			v-if="creating === 'place'"
			@saved="(p) => pickPlace(toOption(p.id, p.name))"
			@close="creating = null" />
		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('vitalog', 'Cancel') }}
			</NcButton>
			<NcButton
				type="submit"
				variant="primary"
				:disabled="saving || !valid">
				{{ t('vitalog', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CalendarImport from 'vue-material-design-icons/CalendarImport.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import PlaceForm from './PlaceForm.vue'
import PractitionerForm from './PractitionerForm.vue'
import { nextHalfHour, parsePrice, priceToInput, toDate, toTimestamp } from '../format.js'
import { useDialogHistory } from '../navigation.js'
import { filterByText } from '../search.js'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	appointment: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

useDialogHistory(() => emit('close'))
const store = useDataStore()
const toOption = (id, label) => ({ id, label })

const labelOptions = computed(() => store.labels.map((l) => toOption(l.id, l.name)))

const durations = [15, 30, 45, 60, 90, 120].map((minutes) => ({
	id: minutes,
	label: minutes >= 60 && minutes % 60 === 0
		? t('vitalog', '{hours} h', { hours: minutes / 60 })
		: t('vitalog', '{minutes} min', { minutes }),
}))
const practitionerOptions = computed(() => store.practitionersByRecency.map((p) => toOption(p.id, p.specialty ? `${p.name} (${p.specialty})` : p.name)))
const placeOptions = computed(() => store.places.map((p) => toOption(p.id, p.name)))

const startsAt = ref(props.appointment ? toDate(props.appointment.startsAt) : nextHalfHour())
const duration = ref(durations.find((d) => d.id === (props.appointment?.duration ?? 30)) ?? toOption(props.appointment?.duration, t('vitalog', '{minutes} min', { minutes: props.appointment?.duration })))
const practitioner = ref(props.appointment?.practitionerId ? practitionerOptions.value.find((o) => o.id === props.appointment.practitionerId) ?? null : null)
const place = ref(props.appointment?.placeId ? placeOptions.value.find((o) => o.id === props.appointment.placeId) ?? null : null)
const labels = ref((props.appointment?.labelIds ?? []).map((id) => labelOptions.value.find((o) => o.id === id)).filter(Boolean))
const reason = ref(props.appointment?.reason ?? '')
const notes = ref(props.appointment?.notes ?? '')
const price = ref(priceToInput(props.appointment?.price))
const creating = ref(null)

// Le lieu habituel du praticien choisi remplit le lieu, tant qu'il est vide ou qu'il vient lui-même d'un praticien :
// un lieu choisi à la main n'est jamais remplacé.
const placeFromPractitioner = ref(false)
function pickPlace(option) {
	place.value = option
	placeFromPractitioner.value = false
}
watch(practitioner, (option) => {
	if (place.value !== null && !placeFromPractitioner.value) {
		return
	}
	const placeId = store.practitionerById(option?.id)?.placeId
	place.value = placeOptions.value.find((o) => o.id === placeId) ?? null
	placeFromPractitioner.value = place.value !== null
})
const saving = ref(false)

// Remplissage depuis un fichier .ics (pièce jointe d'une plateforme de rendez-vous) : le serveur reconnaît le praticien
// et le lieu, rien n'est enregistré avant « Enregistrer ». L'UID de l'événement suit, pour ne pas l'importer deux fois.
const icsInput = ref(null)
const reading = ref(false)
const calendarUid = ref(null)
const filledFrom = ref(null)
const existing = ref(null)
const location = ref(null)

async function readIcs(event) {
	const file = event.target.files?.[0]
	event.target.value = ''
	if (!file) {
		return
	}
	reading.value = true
	const draft = await store.draftFromIcs(await file.text())
	reading.value = false
	if (draft === null) {
		return
	}
	existing.value = draft.existingId ? store.appointments.find((a) => a.id === draft.existingId) ?? null : null
	if (existing.value) {
		return
	}
	startsAt.value = toDate(draft.startsAt)
	duration.value = durations.find((d) => d.id === draft.duration) ?? toOption(draft.duration, t('vitalog', '{minutes} min', { minutes: draft.duration }))
	practitioner.value = practitionerOptions.value.find((o) => o.id === draft.practitionerId) ?? null
	place.value = placeOptions.value.find((o) => o.id === draft.placeId) ?? null
	// le lieu habituel du praticien reste remplaçable si l'on change de praticien
	placeFromPractitioner.value = place.value !== null && store.practitionerById(draft.practitionerId)?.placeId === draft.placeId
	reason.value = draft.reason ?? ''
	notes.value = draft.notes ?? ''
	location.value = draft.location
	calendarUid.value = draft.calendarUid
	filledFrom.value = file.name
}

function openExisting() {
	emit('saved', existing.value)
	emit('close')
}

/** Crée le lieu indiqué par le fichier : la première partie de l'adresse sert de nom. */
async function createPlaceFromLocation() {
	const saved = await store.savePlace({ name: location.value.split(',')[0].trim(), address: location.value, notes: null })
	if (saved) {
		pickPlace(toOption(saved.id, saved.name))
	}
}

const priceInvalid = computed(() => Number.isNaN(parsePrice(price.value)))
const valid = computed(() => startsAt.value instanceof Date && !Number.isNaN(startsAt.value.getTime()) && !priceInvalid.value)

async function submit() {
	saving.value = true
	const labelIds = await store.resolveLabelIds(labels.value)
	if (labelIds === null) {
		saving.value = false
		return
	}
	const saved = await store.saveAppointment({
		startsAt: toTimestamp(startsAt.value),
		duration: duration.value.id,
		practitionerId: practitioner.value?.id ?? null,
		placeId: place.value?.id ?? null,
		reason: reason.value.trim() || null,
		notes: notes.value.trim() || null,
		price: parsePrice(price.value),
		labelIds,
		...(props.appointment ? {} : { calendarUid: calendarUid.value }),
	}, props.appointment?.id)
	saving.value = false
	if (saved) {
		emit('saved', saved)
		emit('close')
	}
}
</script>

<style scoped lang="scss">
.form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-block: 8px;

	&__row {
		display: flex;
		align-items: flex-end;
		gap: 8px;
	}

	&__hint {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 4px 8px;
		margin-top: -8px;
		color: var(--color-text-maxcontrast);
	}

	&__grow {
		flex: 1;
		min-width: 0;
	}
}
</style>
