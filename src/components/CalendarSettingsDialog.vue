<template>
	<NcDialog
		:name="t('vitalog', 'Calendar sync')"
		size="large"
		isForm
		@submit="submit"
		@closing="emit('close')">
		<div class="settings">
			<p>{{ t('vitalog', 'Planned appointments are added to a calendar of your choice, with a reminder the day before. Changes and cancellations are reflected automatically.') }}</p>
			<NcSelect
				v-model="selected"
				inputId="vitalog-calendar"
				:inputLabel="t('vitalog', 'Calendar')"
				:options="options"
				:clearable="false"
				label="label" />
			<NcCheckboxRadioSwitch
				v-model="importFromCalendar"
				type="switch"
				:disabled="selected.id === null">
				{{ t('vitalog', 'Create an appointment when I add an event to this calendar') }}
			</NcCheckboxRadioSwitch>
			<p class="settings__hint">
				{{ t('vitalog', 'Optional and off by default. The new appointment can then be completed in Vitalog (practitioner, notes, documents…). Events that are already over are marked as done. Recurring events are not supported and are ignored.') }}
			</p>
			<p class="settings__hint">
				{{ t('vitalog', 'The sync is one-way for events created in the calendar: later changes made to such an event in the calendar are not reflected in Vitalog. Changes made to an appointment in Vitalog are written to its event.') }}
			</p>
			<NcNoteCard v-if="options.length === 1" type="warning">
				{{ t('vitalog', 'No writable calendar found. Open the Calendar app once to create one.') }}
			</NcNoteCard>
		</div>
		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('vitalog', 'Cancel') }}
			</NcButton>
			<NcButton
				type="submit"
				variant="primary"
				:disabled="saving">
				{{ t('vitalog', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useDataStore } from '../stores/data.js'

const emit = defineEmits(['close'])

const store = useDataStore()
const saving = ref(false)
const options = computed(() => [
	{ id: null, label: t('vitalog', 'Sync disabled') },
	...store.settings.calendars.map((c) => ({ id: c.uri, label: c.name })),
])
const importFromCalendar = ref(store.settings.importFromCalendar)
const selected = ref(options.value.find((o) => o.id === store.settings.calendarUri) ?? options.value[0])

async function submit() {
	saving.value = true
	const saved = await store.saveCalendar(selected.value.id, selected.value.id !== null && importFromCalendar.value)
	saving.value = false
	if (saved) {
		emit('close')
	}
}
</script>

<style scoped lang="scss">
.settings {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-block: 8px;

	&__hint {
		color: var(--color-text-maxcontrast);
	}
}
</style>
