<template>
	<NcDialog
		:name="appointment ? t('vitalog', 'Edit appointment') : t('vitalog', 'New appointment')"
		isForm
		size="normal"
		@submit="submit"
		@closing="emit('close')">
		<div class="form">
			<NcDateTimePickerNative
				id="vitalog-starts-at"
				v-model="startsAt"
				type="datetime-local"
				:label="t('vitalog', 'Date and time')"
				required />

			<NcSelect
				v-model="duration"
				inputId="vitalog-duration"
				:inputLabel="t('vitalog', 'Duration')"
				:options="durations"
				:clearable="false"
				label="label" />

			<div class="form__row">
				<NcSelect
					v-model="practitioner"
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
					v-model="place"
					class="form__grow"
					inputId="vitalog-place"
					:inputLabel="t('vitalog', 'Place')"
					:options="placeOptions"
					:placeholder="t('vitalog', 'None')"
					label="label" />
				<NcButton
					:aria-label="t('vitalog', 'New place')"
					:title="t('vitalog', 'New place')"
					@click="creating = 'place'">
					<template #icon>
						<Plus :size="20" />
					</template>
				</NcButton>
			</div>

			<NcSelect
				v-model="labels"
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
			<NcTextArea v-model="notes" :label="t('vitalog', 'Notes')" rows="4" />
		</div>

		<PractitionerForm
			v-if="creating === 'practitioner'"
			@saved="(p) => practitioner = toOption(p.id, p.name)"
			@close="creating = null" />
		<PlaceForm
			v-if="creating === 'place'"
			@saved="(p) => place = toOption(p.id, p.name)"
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
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Plus from 'vue-material-design-icons/Plus.vue'
import PlaceForm from './PlaceForm.vue'
import PractitionerForm from './PractitionerForm.vue'
import { nextHalfHour, toDate, toTimestamp } from '../format.js'
import { useDialogHistory } from '../navigation.js'
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
const practitionerOptions = computed(() => store.practitioners.map((p) => toOption(p.id, p.specialty ? `${p.name} (${p.specialty})` : p.name)))
const placeOptions = computed(() => store.places.map((p) => toOption(p.id, p.name)))

const startsAt = ref(props.appointment ? toDate(props.appointment.startsAt) : nextHalfHour())
const duration = ref(durations.find((d) => d.id === (props.appointment?.duration ?? 30)) ?? toOption(props.appointment?.duration, t('vitalog', '{minutes} min', { minutes: props.appointment?.duration })))
const practitioner = ref(props.appointment?.practitionerId ? practitionerOptions.value.find((o) => o.id === props.appointment.practitionerId) ?? null : null)
const place = ref(props.appointment?.placeId ? placeOptions.value.find((o) => o.id === props.appointment.placeId) ?? null : null)
const labels = ref((props.appointment?.labelIds ?? []).map((id) => labelOptions.value.find((o) => o.id === id)).filter(Boolean))
const reason = ref(props.appointment?.reason ?? '')
const notes = ref(props.appointment?.notes ?? '')
const creating = ref(null)
const saving = ref(false)

const valid = computed(() => startsAt.value instanceof Date && !Number.isNaN(startsAt.value.getTime()))

/** Les libellés tapés à la volée n'existent pas encore : on les crée (ou on réutilise l'existant du même nom). */
async function resolveLabelIds() {
	const ids = []
	for (const option of labels.value) {
		let id = option.id
		if (id === null) {
			const existing = store.labels.find((l) => l.name.toLowerCase() === option.label.toLowerCase())
			id = existing?.id ?? (await store.saveLabel({ name: option.label, color: null }))?.id ?? null
		}
		if (id === null) {
			return null
		}
		ids.push(id)
	}

	return [...new Set(ids)]
}

async function submit() {
	saving.value = true
	const labelIds = await resolveLabelIds()
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
		labelIds,
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

	&__grow {
		flex: 1;
		min-width: 0;
	}
}
</style>
