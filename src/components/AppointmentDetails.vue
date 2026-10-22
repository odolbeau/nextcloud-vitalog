<template>
	<article class="details" :aria-label="title">
		<header class="details__header">
			<DateBadge :startsAt="appointment.startsAt" :muted="store.isPast(appointment)" />
			<div class="details__heading">
				<h2>{{ title }}</h2>
				<p class="details__when">
					{{ formatDateTime(appointment.startsAt) }} · {{ durationLabel }}
				</p>
			</div>
		</header>

		<div class="details__labels-row" @keydown.esc="editing = null">
			<template v-if="editing === 'labels'">
				<NcSelect
					v-model="labelSelection"
					class="details__grow"
					inputId="vitalog-details-labels"
					:ariaLabelCombobox="t('vitalog', 'Labels')"
					:filterBy="filterByText"
					:options="labelOptions"
					:placeholder="t('vitalog', 'Pick or type to create…')"
					:createOption="(name) => ({ id: null, label: name.trim() })"
					label="label"
					multiple
					taggable
					keepOpen />
				<NcButton
					variant="tertiary"
					:aria-label="t('vitalog', 'Done')"
					:title="t('vitalog', 'Done')"
					@click="editing = null">
					<template #icon>
						<Check :size="20" />
					</template>
				</NcButton>
			</template>
			<template v-else>
				<ul v-if="labels.length > 0" class="details__labels" :aria-label="t('vitalog', 'Labels')">
					<li v-for="label in labels" :key="label.id">
						<LabelChip
							:label="label"
							clickable
							:title="t('vitalog', 'Show all appointments labelled {name}', { name: label.name })"
							@click="emit('filterLabel', label.id)" />
					</li>
				</ul>
				<NcButton
					v-if="labels.length > 0"
					variant="tertiary"
					:title="t('vitalog', 'Edit the labels')"
					:aria-label="t('vitalog', 'Edit the labels')"
					@click="startEditing('labels')">
					<template #icon>
						<Pencil :size="20" />
					</template>
				</NcButton>
				<NcButton v-else variant="tertiary" @click="startEditing('labels')">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('vitalog', 'Add labels') }}
				</NcButton>
			</template>
		</div>

		<div class="details__actions">
			<NcButton @click="emit('edit')">
				<template #icon>
					<Pencil :size="20" />
				</template>
				{{ t('vitalog', 'Edit') }}
			</NcButton>
			<NcButton variant="error" @click="confirmingDelete = true">
				<template #icon>
					<Delete :size="20" />
				</template>
				{{ t('vitalog', 'Delete') }}
			</NcButton>
		</div>

		<dl class="details__facts">
			<dt>{{ t('vitalog', 'Practitioner') }}</dt>
			<dd class="details__row" @keydown.esc="editing = null">
				<template v-if="editing === 'practitioner'">
					<NcSelect
						:filterBy="filterByText"
						class="details__grow"
						inputId="vitalog-details-practitioner"
						:ariaLabelCombobox="t('vitalog', 'Practitioner')"
						:modelValue="null"
						:options="practitionerOptions"
						:placeholder="t('vitalog', 'Choose a practitioner')"
						label="label"
						@update:modelValue="(option) => pick('practitionerId', option)" />
					<NcButton variant="tertiary" :aria-label="t('vitalog', 'Cancel')" @click="editing = null">
						<template #icon>
							<Close :size="20" />
						</template>
					</NcButton>
				</template>
				<template v-else>
					<div v-if="practitioner">
						<strong>{{ practitioner.name }}</strong>
						<span v-if="practitioner.specialty"> · {{ practitioner.specialty }}</span>
						<a v-if="practitioner.phone" :href="`tel:${practitioner.phone}`" class="details__block">{{ practitioner.phone }}</a>
					</div>
					<NcButton v-else variant="tertiary" @click="startEditing('practitioner')">
						<template #icon>
							<Plus :size="20" />
						</template>
						{{ t('vitalog', 'Choose a practitioner') }}
					</NcButton>
					<span v-if="practitioner" class="details__tools">
						<NcButton
							v-if="practitioner.contactUrl"
							variant="tertiary"
							:href="practitioner.contactUrl"
							:title="t('vitalog', 'Open in Contacts')"
							:aria-label="t('vitalog', 'Open in Contacts')">
							<template #icon>
								<CardAccountDetails :size="20" />
							</template>
						</NcButton>
						<NcButton
							variant="tertiary"
							:title="t('vitalog', 'Change the practitioner')"
							:aria-label="t('vitalog', 'Change the practitioner')"
							@click="startEditing('practitioner')">
							<template #icon>
								<Pencil :size="20" />
							</template>
						</NcButton>
					</span>
				</template>
			</dd>
			<dt>{{ t('vitalog', 'Place') }}</dt>
			<dd class="details__row" @keydown.esc="editing = null">
				<template v-if="editing === 'place'">
					<NcSelect
						:filterBy="filterByText"
						class="details__grow"
						inputId="vitalog-details-place"
						:ariaLabelCombobox="t('vitalog', 'Place')"
						:modelValue="null"
						:options="placeOptions"
						:placeholder="t('vitalog', 'Choose a place')"
						label="label"
						@update:modelValue="(option) => pick('placeId', option)" />
					<NcButton variant="tertiary" :aria-label="t('vitalog', 'Cancel')" @click="editing = null">
						<template #icon>
							<Close :size="20" />
						</template>
					</NcButton>
				</template>
				<template v-else>
					<div v-if="place">
						<strong>{{ place.name }}</strong>
						<a
							v-if="place.address"
							class="details__block"
							:href="mapUrl">{{ place.address }}</a>
					</div>
					<NcButton v-else variant="tertiary" @click="startEditing('place')">
						<template #icon>
							<Plus :size="20" />
						</template>
						{{ t('vitalog', 'Choose a place') }}
					</NcButton>
					<span v-if="place" class="details__tools">
						<NcButton
							variant="tertiary"
							:title="t('vitalog', 'Change the place')"
							:aria-label="t('vitalog', 'Change the place')"
							@click="startEditing('place')">
							<template #icon>
								<Pencil :size="20" />
							</template>
						</NcButton>
					</span>
				</template>
			</dd>
			<dt>{{ t('vitalog', 'Notes') }}</dt>
			<dd v-if="editing === 'notes'" class="details__notes-editor">
				<NcTextArea
					v-model="notesDraft"
					:aria-label="t('vitalog', 'Notes')"
					labelOutside
					rows="5"
					@keydown.esc="editing = null"
					@keydown.ctrl.enter="saveNotes"
					@keydown.meta.enter="saveNotes" />
				<div class="details__notes-actions">
					<NcButton @click="editing = null">
						{{ t('vitalog', 'Cancel') }}
					</NcButton>
					<NcButton variant="primary" :disabled="saving" @click="saveNotes">
						{{ t('vitalog', 'Save') }}
					</NcButton>
				</div>
			</dd>
			<dd v-else class="details__row">
				<div v-if="appointment.notes" class="details__notes">
					{{ appointment.notes }}
				</div>
				<NcButton v-else variant="tertiary" @click="startEditing('notes')">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('vitalog', 'Add notes') }}
				</NcButton>
				<span v-if="appointment.notes" class="details__tools">
					<NcButton
						variant="tertiary"
						:title="t('vitalog', 'Edit the notes')"
						:aria-label="t('vitalog', 'Edit the notes')"
						@click="startEditing('notes')">
						<template #icon>
							<Pencil :size="20" />
						</template>
					</NcButton>
				</span>
			</dd>
			<dt>{{ t('vitalog', 'Calendar') }}</dt>
			<dd class="details__row">
				<span v-if="appointment.synced" class="details__synced"><CalendarCheck :size="18" /> {{ t('vitalog', 'Added to your calendar') }}</span>
				<span v-else class="details__muted">{{ calendarHint }}</span>
				<NcButton
					v-if="calendarUrl"
					class="details__open"
					variant="tertiary"
					:href="calendarUrl"
					:title="t('vitalog', 'Open in Calendar')"
					:aria-label="t('vitalog', 'Open in Calendar')">
					<template #icon>
						<CalendarMonth :size="20" />
					</template>
				</NcButton>
			</dd>
		</dl>

		<section class="details__attachments" :aria-label="t('vitalog', 'Documents')">
			<div class="details__attachments-header">
				<h3>{{ t('vitalog', 'Documents') }}</h3>
				<NcButton @click="pickFiles">
					<template #icon>
						<Paperclip :size="20" />
					</template>
					{{ t('vitalog', 'Attach a file') }}
				</NcButton>
			</div>
			<p v-if="appointment.attachments.length === 0" class="details__muted">
				{{ t('vitalog', 'Prescriptions, reports, results… attach files from your Nextcloud.') }}
			</p>
			<ul v-else>
				<li v-for="attachment in appointment.attachments" :key="attachment.id" class="details__file">
					<FileOutline :size="20" />
					<a
						v-if="attachment.name"
						:href="fileUrl(attachment)">{{ attachment.name }}</a>
					<span v-else class="details__muted">{{ t('vitalog', 'File no longer available') }}</span>
					<NcButton
						variant="tertiary"
						:aria-label="t('vitalog', 'Detach {name}', { name: attachment.name ?? '' })"
						@click="store.removeAttachment(appointment, attachment.id)">
						<template #icon>
							<Close :size="20" />
						</template>
					</NcButton>
				</li>
			</ul>
		</section>

		<NcDialog
			v-if="confirmingDelete"
			:name="t('vitalog', 'Delete this appointment?')"
			:message="t('vitalog', 'The appointment and its calendar event will be removed. Attached files are not deleted.')"
			:buttons="deleteButtons"
			@closing="confirmingDelete = false" />
	</article>
</template>

<script setup>
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, nextTick, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import CalendarCheck from 'vue-material-design-icons/CalendarCheck.vue'
import CalendarMonth from 'vue-material-design-icons/CalendarMonth.vue'
import CardAccountDetails from 'vue-material-design-icons/CardAccountDetails.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import FileOutline from 'vue-material-design-icons/FileOutline.vue'
import Paperclip from 'vue-material-design-icons/Paperclip.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import DateBadge from './DateBadge.vue'
import LabelChip from './LabelChip.vue'
import { formatDateTime } from '../format.js'
import { filterByText } from '../search.js'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	appointment: { type: Object, required: true },
})
const emit = defineEmits(['edit', 'deleted', 'filterLabel'])

const store = useDataStore()
const confirmingDelete = ref(false)
const editing = ref(null)
const notesDraft = ref('')
const saving = ref(false)

// lien permanent de l'app Calendrier vers l'événement (par son UID)
const calendarUrl = computed(() => store.settings.calendarAppEnabled && props.appointment.calendarUid
	? generateUrl('/apps/calendar/object/{uid}', { uid: props.appointment.calendarUid })
	: null)
const labels = computed(() => store.labelsOf(props.appointment))
const practitioner = computed(() => store.practitionerById(props.appointment.practitionerId))
const place = computed(() => store.placeById(props.appointment.placeId))
const labelOptions = computed(() => store.labels.map((l) => ({ id: l.id, label: l.name })))
// chaque ajout ou retrait est enregistré aussitôt ; la sélection se déduit du rendez-vous
const labelSelection = computed({
	get: () => labels.value.map((l) => ({ id: l.id, label: l.name })),
	async set(options) {
		const labelIds = await store.resolveLabelIds(options)
		if (labelIds !== null) {
			await store.updateAppointment(props.appointment, { labelIds })
		}
	},
})
const practitionerOptions = computed(() => store.practitioners.map((p) => ({ id: p.id, label: p.specialty ? `${p.name} (${p.specialty})` : p.name })))
const placeOptions = computed(() => store.places.map((p) => ({ id: p.id, label: p.name })))
const title = computed(() => props.appointment.reason || t('vitalog', 'Medical appointment'))
const mapUrl = computed(() => 'https://www.openstreetmap.org/search?query=' + encodeURIComponent(place.value?.address ?? ''))

const durationLabel = computed(() => {
	const minutes = props.appointment.duration
	return minutes >= 60 && minutes % 60 === 0
		? t('vitalog', '{hours} h', { hours: minutes / 60 })
		: t('vitalog', '{minutes} min', { minutes })
})

const calendarHint = computed(() => store.settings.calendarUri
	? t('vitalog', 'Not added to your calendar (sync failed).')
	: t('vitalog', 'Calendar sync is off. Enable it from the settings in the left sidebar.'))

// l'édition en place ne concerne que la fiche affichée : on la quitte quand on passe à un autre rendez-vous
watch(() => props.appointment.id, () => {
	editing.value = null
})

async function startEditing(field) {
	editing.value = field
	if (field === 'notes') {
		notesDraft.value = props.appointment.notes ?? ''
	}
	await nextTick()
	// le champ de recherche de vue-select n'est prêt qu'après son propre rendu
	await new Promise((resolve) => requestAnimationFrame(resolve))
	const input = document.getElementById(`vitalog-details-${field}`)
	if (field === 'notes') {
		input?.focus()
		return
	}
	// vue-select ne déplie sa liste (et ne prend le focus) que sur un clic : on le simule pour ne pas en demander un second
	input?.closest('.vs__selected-options')?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
}

async function pick(field, option) {
	if (!option) {
		return
	}
	if (await store.updateAppointment(props.appointment, { [field]: option.id })) {
		editing.value = null
	}
}

async function saveNotes() {
	saving.value = true
	const saved = await store.updateAppointment(props.appointment, { notes: notesDraft.value.trim() || null })
	saving.value = false
	if (saved) {
		editing.value = null
	}
}

const deleteButtons = [
	{ label: t('vitalog', 'Cancel'), callback: () => {} },
	{
		label: t('vitalog', 'Delete'),
		variant: 'error',
		callback: async () => {
			if (await store.deleteAppointment(props.appointment.id)) {
				emit('deleted')
			}
		},
	},
]

const fileUrl = (attachment) => generateUrl('/f/{fileId}', { fileId: attachment.fileId })

async function pickFiles() {
	try {
		const nodes = await getFilePickerBuilder(t('vitalog', 'Choose files to attach'))
			.setMultiSelect(true)
			.allowDirectories(false)
			.addButton({
				label: t('vitalog', 'Attach'),
				variant: 'primary',
				callback: () => {},
			})
			.build()
			.pickNodes()
		for (const node of nodes) {
			await store.addAttachment(props.appointment, node.fileid)
		}
	} catch {
		// sélecteur fermé sans choix
	}
}
</script>

<style scoped lang="scss">
.details__labels {
	display: flex;
	flex: 1;
	flex-wrap: wrap;
	gap: 6px;
	min-width: 0;
	margin: 0;
	padding: 0;
	list-style: none;
}

.details {
	display: flex;
	flex-direction: column;
	gap: 20px;
	max-width: 760px;
	padding: 20px 20px 20px calc(var(--default-clickable-area) + 12px);

	&__labels-row {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__header {
		display: flex;
		align-items: center;
		gap: 16px;
	}

	&__heading {
		flex: 1;
		min-width: 0;

		h2 {
			margin: 0;
			overflow-wrap: anywhere;
		}
	}

	&__when {
		margin: 2px 0 0;
		color: var(--color-text-maxcontrast);

		&::first-letter {
			text-transform: uppercase;
		}
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		margin-top: 8px;
	}

	&__facts {
		display: grid;
		grid-template-columns: max-content 1fr;
		gap: 12px 24px;
		margin: 0;

		dt {
			color: var(--color-text-maxcontrast);
		}

		dd {
			margin: 0;
		}
	}

	&__block {
		display: block;
	}

	&__row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
		padding-inline-end: 0;

		// le glyphe (20 px) s'aligne sur le bord droit du bloc, pas sur celui de la zone cliquable
		.details__open {
			margin-inline-end: calc((20px - var(--default-clickable-area)) / 2);
		}

		> :first-child {
			flex: 1;
			min-width: 0;
		}

		// un bouton « Ajouter… » garde sa largeur naturelle
		> .button-vue:first-child {
			flex: 0 1 auto;
			margin-inline-start: -12px;
		}
	}

	&__notes {
		white-space: pre-wrap;
	}

	&__notes-editor {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	&__notes-actions {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
	}

	&__grow {
		flex: 1;
		min-width: 0;
	}

	&__tools {
		display: flex;
		flex-shrink: 0;
	}

	&__synced {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		color: var(--color-success-text);
	}

	&__muted {
		color: var(--color-text-maxcontrast);
	}

	&__attachments-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;

		h3 {
			margin: 0;
		}
	}

	&__file {
		display: flex;
		align-items: center;
		gap: 8px;
		padding-block: 4px;

		a {
			flex: 1;
			overflow-wrap: anywhere;
		}
	}
}

@media (max-width: 600px) {
	.details__facts {
		grid-template-columns: 1fr;
		gap: 2px;

		dd {
			margin-bottom: 10px;
		}
	}
}
</style>
