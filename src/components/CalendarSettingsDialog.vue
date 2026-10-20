<template>
	<NcDialog
		:name="unlinking ? t('vitalog', 'Unlink the calendar') : t('vitalog', 'Calendar sync')"
		size="large"
		isForm
		@submit="submit"
		@closing="emit('close')">
		<div v-if="unlinking" class="settings">
			<p>{{ t('vitalog', 'What should happen to the events Vitalog wrote in {calendar}?', { calendar: linkedName }) }}</p>
			<fieldset class="settings__choices">
				<legend class="hidden-visually">
					{{ t('vitalog', 'Events written by Vitalog') }}
				</legend>
				<NcCheckboxRadioSwitch
					v-model="unlinkMode"
					type="radio"
					name="vitalog-unlink"
					value="keep">
					{{ t('vitalog', 'Keep the events in the calendar') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="unlinkMode"
					type="radio"
					name="vitalog-unlink"
					value="delete">
					{{ t('vitalog', 'Delete the events from the calendar') }}
				</NcCheckboxRadioSwitch>
			</fieldset>
			<p class="settings__hint">
				{{ t('vitalog', 'Your appointments are never deleted. If you link this calendar again later, Vitalog finds its events again and updates them instead of duplicating them.') }}
			</p>
		</div>
		<div v-else-if="linked" class="settings">
			<p>{{ t('vitalog', 'Your appointments are written to the calendar {calendar}. To use another calendar, unlink this one first.', { calendar: linkedName }) }}</p>
			<p class="settings__hint">
				{{ t('vitalog', 'Changing an appointment updates its event. Renaming or deleting a practitioner, place or label does not update the events already written.') }}
			</p>
			<NcCheckboxRadioSwitch
				v-model="importFromCalendar"
				type="switch">
				{{ t('vitalog', 'Create an appointment when I add an event to this calendar') }}
			</NcCheckboxRadioSwitch>
			<p class="settings__hint">
				{{ importHint }}
			</p>
			<NcButton data-test="unlink-calendar" @click="unlinking = true">
				{{ t('vitalog', 'Unlink calendar') }}
			</NcButton>
		</div>
		<div v-else class="settings">
			<p>{{ t('vitalog', 'Link a calendar to add your appointments to it, with a reminder the day before. Changing an appointment updates its event.') }}</p>
			<p class="settings__hint">
				{{ t('vitalog', 'Renaming or deleting a practitioner, place or label does not update the events already written. To use another calendar later, you will have to unlink this one first.') }}
			</p>
			<NcSelect
				v-model="selected"
				inputId="vitalog-calendar"
				:inputLabel="t('vitalog', 'Calendar')"
				:placeholder="t('vitalog', 'Choose a calendar')"
				:options="options"
				label="label" />
			<NcCheckboxRadioSwitch
				v-model="importExisting"
				:disabled="selected === null">
				{{ t('vitalog', 'Also create appointments from the events already in this calendar') }}
			</NcCheckboxRadioSwitch>
			<p class="settings__hint">
				{{ t('vitalog', 'Done once, when you link. Past events are included; recurring events are ignored.') }}
			</p>
			<NcCheckboxRadioSwitch
				v-model="importFromCalendar"
				type="switch"
				:disabled="selected === null">
				{{ t('vitalog', 'Create an appointment when I add an event to this calendar') }}
			</NcCheckboxRadioSwitch>
			<p class="settings__hint">
				{{ importHint }}
			</p>
			<NcNoteCard v-if="options.length === 0" type="warning">
				{{ t('vitalog', 'No writable calendar found. Open the Calendar app once to create one.') }}
			</NcNoteCard>
		</div>
		<template #actions>
			<NcButton @click="cancel">
				{{ t('vitalog', 'Cancel') }}
			</NcButton>
			<NcButton
				type="submit"
				:variant="unlinking && unlinkMode === 'delete' ? 'error' : 'primary'"
				:disabled="saving || (!linked && selected === null)">
				{{ submitLabel }}
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
import { useDialogHistory } from '../navigation.js'
import { useDataStore } from '../stores/data.js'

const emit = defineEmits(['close'])

useDialogHistory(() => emit('close'))
const store = useDataStore()
const saving = ref(false)
// l'état « lié » est celui du serveur au moment d'ouvrir la boîte : lier ou délier la ferme
const linked = computed(() => store.settings.calendarUri !== null)
const options = computed(() => store.settings.calendars.map((c) => ({ id: c.uri, label: c.name })))
const linkedName = computed(() => options.value.find((o) => o.id === store.settings.calendarUri)?.label ?? store.settings.calendarUri)
const importHint = t('vitalog', 'Optional and off by default. The new appointment can then be completed in Vitalog (practitioner, notes, documents…). Recurring events are not supported and are ignored. Later changes made to such an event in the calendar are not reflected in Vitalog.')

const selected = ref(null)
const importFromCalendar = ref(store.settings.importFromCalendar)
// ponctuel, jamais mémorisé
const importExisting = ref(false)
const unlinking = ref(false)
// garder les événements est le choix qui ne détruit rien
const unlinkMode = ref('keep')

const submitLabel = computed(() => {
	if (unlinking.value) {
		return t('vitalog', 'Unlink')
	}
	return linked.value ? t('vitalog', 'Save') : t('vitalog', 'Link calendar')
})

function cancel() {
	if (unlinking.value) {
		unlinking.value = false
	} else {
		emit('close')
	}
}

async function submit() {
	saving.value = true
	let done
	if (unlinking.value) {
		done = await store.unlinkCalendar(unlinkMode.value === 'delete')
	} else if (linked.value) {
		done = await store.saveCalendar(store.settings.calendarUri, importFromCalendar.value, false)
	} else {
		done = await store.saveCalendar(selected.value.id, importFromCalendar.value, importExisting.value)
	}
	saving.value = false
	if (done) {
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

	&__choices {
		border: none;
		margin: 0;
		padding: 0;
	}
}
</style>
