<template>
	<NcDialog
		:name="t('vitalog', 'Calendar')"
		size="normal"
		isForm
		@submit="submit"
		@closing="emit('close')">
		<div ref="settingsElement" class="settings">
			<section class="settings__section">
				<h3>{{ t('vitalog', 'Your Vitalog calendar') }}</h3>
				<p>
					{{ t('vitalog', 'Your appointments appear in a read-only calendar, with a reminder the day before. Edit them here: the calendar follows.') }}
				</p>
				<!-- Entrée enregistre le nom au lieu de lancer l'import -->
				<NcTextField
					v-model="name"
					:label="t('vitalog', 'Calendar name')"
					:maxlength="255"
					@change="saveName"
					@keydown.enter.prevent="saveName" />
				<div class="settings__buttons">
					<NcColorPicker v-model="color" :container="pickerContainer">
						<NcButton>
							<template #icon>
								<span class="settings__swatch" :style="{ background: color }" aria-hidden="true" />
							</template>
							{{ t('vitalog', 'Calendar color') }}
						</NcButton>
					</NcColorPicker>
					<NcButton
						v-if="store.settings.calendarAppEnabled"
						:href="calendarAppUrl"
						variant="secondary">
						<template #icon>
							<CalendarMonth :size="20" />
						</template>
						{{ t('vitalog', 'Open the Calendar app') }}
					</NcButton>
				</div>
			</section>

			<section class="settings__section">
				<h3>{{ t('vitalog', 'Import from a calendar') }}</h3>
				<p class="settings__hint">
					{{ t('vitalog', 'Creates an appointment for each event of the calendar, past ones included. Events already imported and recurring events are skipped; later changes to the events are not reflected in Vitalog.') }}
				</p>
				<NcSelect
					v-model="selected"
					:filterBy="filterByText"
					inputId="vitalog-calendar"
					:inputLabel="t('vitalog', 'Calendar to import')"
					:placeholder="t('vitalog', 'Choose a calendar')"
					:options="options"
					label="label" />
				<NcNoteCard v-if="options.length === 0" type="warning">
					{{ t('vitalog', 'No calendar found. Open the Calendar app once to create one.') }}
				</NcNoteCard>
			</section>
		</div>
		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('vitalog', 'Close') }}
			</NcButton>
			<NcButton
				type="submit"
				variant="primary"
				:disabled="importing || selected === null">
				{{ t('vitalog', 'Import') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, onBeforeUnmount, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CalendarMonth from 'vue-material-design-icons/CalendarMonth.vue'
import { useDialogHistory } from '../navigation.js'
import { filterByText } from '../search.js'
import { useDataStore } from '../stores/data.js'

const emit = defineEmits(['close'])

useDialogHistory(() => emit('close'))
const store = useDataStore()
const calendarAppUrl = generateUrl('/apps/calendar')
const options = computed(() => store.settings.calendars.map((c) => ({ id: c.uri, label: c.name })))
const selected = ref(null)
const importing = ref(false)

// enregistré en quittant le champ ou avec Entrée ; vide, il reprend le nom enregistré
const name = ref(store.settings.calendarName ?? '')
async function saveName() {
	const value = name.value.trim()
	if (value === '' || value === store.settings.calendarName) {
		name.value = store.settings.calendarName ?? ''
		return
	}
	await store.updateCalendar({ name: value })
	name.value = store.settings.calendarName ?? ''
}

// sélecteur ouvert dans le fond du modal, comme pour les libellés (voir LabelForm)
const settingsElement = ref(null)
const pickerContainer = computed(() => settingsElement.value?.closest('.modal-mask') ?? 'body')
// le sélecteur avancé émet une couleur à chaque mouvement : on n'enregistre que la dernière
const pendingColor = ref(null)
let colorTimer
const color = computed({
	get: () => pendingColor.value ?? store.settings.calendarColor,
	set: (value) => {
		pendingColor.value = value
		clearTimeout(colorTimer)
		colorTimer = setTimeout(saveColor, 400)
	},
})

async function saveColor() {
	clearTimeout(colorTimer)
	if (pendingColor.value === null) {
		return
	}
	if (pendingColor.value !== store.settings.calendarColor) {
		await store.updateCalendar({ color: pendingColor.value })
	}
	pendingColor.value = null
}
onBeforeUnmount(() => {
	saveName()
	saveColor()
})

async function submit() {
	importing.value = true
	const imported = await store.importCalendar(selected.value.id)
	importing.value = false
	if (imported !== null) {
		emit('close')
	}
}
</script>

<style scoped lang="scss">
.settings {
	display: flex;
	flex-direction: column;
	gap: 20px;
	padding-block: 8px;

	&__section {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 8px;

		h3 {
			margin: 0;
			font-size: 1.1em;
		}

		> * {
			align-self: stretch;
		}

		> .button-vue {
			align-self: flex-start;
		}
	}

	&__buttons {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
	}

	&__swatch {
		width: 20px;
		height: 20px;
		border-radius: 50%;
	}

	&__hint {
		color: var(--color-text-maxcontrast);
	}
}
</style>
