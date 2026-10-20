<template>
	<NcDialog
		:name="unlinking ? t('vitalog', 'Unlink the calendar') : t('vitalog', 'Calendar sync')"
		size="normal"
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
				{{ t('vitalog', 'Your appointments are kept. Linking this calendar again later finds the events again.') }}
			</p>
		</div>
		<div v-else-if="linked" class="settings">
			<div class="settings__linked">
				<CalendarSync :size="28" />
				<div class="settings__linked-name">
					<span class="settings__hint">{{ t('vitalog', 'Linked calendar') }}</span>
					<strong>{{ linkedName }}</strong>
				</div>
				<NcButton data-test="unlink-calendar" @click="unlinking = true">
					{{ t('vitalog', 'Unlink calendar') }}
				</NcButton>
			</div>
			<NcCheckboxRadioSwitch
				:modelValue="importFromCalendar"
				type="switch"
				:disabled="saving"
				@update:modelValue="saveImport">
				{{ t('vitalog', 'Create an appointment for each event I add to this calendar') }}
			</NcCheckboxRadioSwitch>
			<p class="settings__hint">
				{{ t('vitalog', 'Recurring events are ignored. Later changes to such an event are not reflected in Vitalog.') }}
			</p>
		</div>
		<div v-else class="settings">
			<p>{{ t('vitalog', 'Add your appointments to one of your calendars, with a reminder the day before.') }}</p>
			<NcSelect
				v-model="selected"
				inputId="vitalog-calendar"
				:inputLabel="t('vitalog', 'Calendar')"
				:placeholder="t('vitalog', 'Choose a calendar')"
				:options="options"
				label="label" />
			<NcNoteCard v-if="options.length === 0" type="warning">
				{{ t('vitalog', 'No writable calendar found. Open the Calendar app once to create one.') }}
			</NcNoteCard>
			<fieldset class="settings__choices">
				<legend class="settings__legend">
					{{ t('vitalog', 'Import events (optional)') }}
				</legend>
				<NcCheckboxRadioSwitch
					v-model="importExisting"
					:disabled="selected === null">
					{{ t('vitalog', 'Create appointments from the events already in this calendar') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="importFromCalendar"
					:disabled="selected === null">
					{{ t('vitalog', 'Create an appointment for each event I add later') }}
				</NcCheckboxRadioSwitch>
			</fieldset>
			<p class="settings__hint">
				{{ t('vitalog', 'Recurring events are ignored. Later changes to such an event are not reflected in Vitalog.') }}
			</p>
		</div>
		<template #actions>
			<NcButton v-if="linked && !unlinking" variant="primary" @click="emit('close')">
				{{ t('vitalog', 'Close') }}
			</NcButton>
			<template v-else>
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
import CalendarSync from 'vue-material-design-icons/CalendarSync.vue'
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
	return t('vitalog', 'Link calendar')
})

function cancel() {
	if (unlinking.value) {
		unlinking.value = false
	} else {
		emit('close')
	}
}

// l'option d'import du calendrier lié s'applique tout de suite : pas de bouton « Enregistrer » en plus de « Délier »
async function saveImport(value) {
	saving.value = true
	const saved = await store.saveCalendar(store.settings.calendarUri, value, false)
	saving.value = false
	if (saved) {
		importFromCalendar.value = store.settings.importFromCalendar
	}
}

async function submit() {
	saving.value = true
	const done = unlinking.value
		? await store.unlinkCalendar(unlinkMode.value === 'delete')
		: await store.saveCalendar(selected.value.id, importFromCalendar.value, importExisting.value)
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

	&__legend {
		font-weight: bold;
		padding: 0;
	}

	&__linked {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 12px;
		border-radius: var(--border-radius-large);
		background: var(--color-background-hover);
	}

	&__linked-name {
		display: flex;
		flex: 1;
		flex-direction: column;
		min-width: 0;
	}
}
</style>
