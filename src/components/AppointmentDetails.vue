<template>
	<article class="details" :aria-label="title">
		<header class="details__header">
			<DateBadge :startsAt="appointment.startsAt" :muted="appointment.status !== 'planned'" />
			<div class="details__heading">
				<h2>{{ title }}</h2>
				<p class="details__when">
					{{ formatDateTime(appointment.startsAt) }} · {{ durationLabel }}
				</p>
			</div>
			<NcChip
				:text="statusLabel"
				:variant="statusVariant"
				noClose />
		</header>

		<NcNoteCard v-if="needsFollowUp" type="info">
			<p>{{ t('vitalog', 'This appointment is over. Did it take place?') }}</p>
			<div class="details__followup">
				<NcButton variant="primary" @click="setStatus('done')">
					{{ t('vitalog', 'Mark as done') }}
				</NcButton>
				<NcButton @click="setStatus('cancelled')">
					{{ t('vitalog', 'It was cancelled') }}
				</NcButton>
			</div>
		</NcNoteCard>

		<div class="details__actions">
			<NcButton @click="emit('edit')">
				<template #icon>
					<Pencil :size="20" />
				</template>
				{{ t('vitalog', 'Edit') }}
			</NcButton>
			<NcButton v-if="appointment.status === 'planned' && !needsFollowUp" @click="setStatus('done')">
				<template #icon>
					<Check :size="20" />
				</template>
				{{ t('vitalog', 'Mark as done') }}
			</NcButton>
			<NcButton v-if="appointment.status !== 'planned'" @click="setStatus('planned')">
				{{ t('vitalog', 'Mark as planned') }}
			</NcButton>
			<NcButton v-if="appointment.status === 'planned'" @click="setStatus('cancelled')">
				<template #icon>
					<Cancel :size="20" />
				</template>
				{{ t('vitalog', 'Cancel appointment') }}
			</NcButton>
			<NcButton variant="error" @click="confirmingDelete = true">
				<template #icon>
					<Delete :size="20" />
				</template>
				{{ t('vitalog', 'Delete') }}
			</NcButton>
		</div>

		<dl class="details__facts">
			<template v-if="practitioner">
				<dt>{{ t('vitalog', 'Practitioner') }}</dt>
				<dd class="details__person">
					<div>
						<strong>{{ practitioner.name }}</strong>
						<span v-if="practitioner.specialty"> · {{ practitioner.specialty }}</span>
						<a v-if="practitioner.phone" :href="`tel:${practitioner.phone}`" class="details__block">{{ practitioner.phone }}</a>
					</div>
					<NcButton
						v-if="practitioner.contactUrl"
						class="details__contact"
						variant="tertiary"
						:href="practitioner.contactUrl"
						target="_blank"
						:title="t('vitalog', 'Open in Contacts')"
						:aria-label="t('vitalog', 'Open in Contacts')">
						<template #icon>
							<CardAccountDetails :size="20" />
						</template>
					</NcButton>
				</dd>
			</template>
			<template v-if="place">
				<dt>{{ t('vitalog', 'Place') }}</dt>
				<dd>
					<strong>{{ place.name }}</strong>
					<a
						v-if="place.address"
						class="details__block"
						:href="mapUrl"
						target="_blank"
						rel="noopener noreferrer">{{ place.address }}</a>
				</dd>
			</template>
			<template v-if="appointment.notes">
				<dt>{{ t('vitalog', 'Notes') }}</dt>
				<dd class="details__notes">
					{{ appointment.notes }}
				</dd>
			</template>
			<dt>{{ t('vitalog', 'Calendar') }}</dt>
			<dd>
				<span v-if="appointment.synced" class="details__synced"><CalendarCheck :size="18" /> {{ t('vitalog', 'Added to your calendar') }}</span>
				<span v-else class="details__muted">{{ calendarHint }}</span>
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
						:href="fileUrl(attachment)"
						target="_blank"
						rel="noopener noreferrer">{{ attachment.name }}</a>
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
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import CalendarCheck from 'vue-material-design-icons/CalendarCheck.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import CardAccountDetails from 'vue-material-design-icons/CardAccountDetails.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import FileOutline from 'vue-material-design-icons/FileOutline.vue'
import Paperclip from 'vue-material-design-icons/Paperclip.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import DateBadge from './DateBadge.vue'
import { formatDateTime } from '../format.js'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	appointment: { type: Object, required: true },
})
const emit = defineEmits(['edit', 'deleted'])

const store = useDataStore()
const confirmingDelete = ref(false)

const practitioner = computed(() => store.practitionerById(props.appointment.practitionerId))
const place = computed(() => store.placeById(props.appointment.placeId))
const title = computed(() => props.appointment.reason || t('vitalog', 'Medical appointment'))
const mapUrl = computed(() => 'https://www.openstreetmap.org/search?query=' + encodeURIComponent(place.value?.address ?? ''))

const durationLabel = computed(() => {
	const minutes = props.appointment.duration
	return minutes >= 60 && minutes % 60 === 0
		? t('vitalog', '{hours} h', { hours: minutes / 60 })
		: t('vitalog', '{minutes} min', { minutes })
})

const statusLabel = computed(() => ({
	planned: t('vitalog', 'Planned'),
	done: t('vitalog', 'Done'),
	cancelled: t('vitalog', 'Cancelled'),
})[props.appointment.status])
const statusVariant = computed(() => ({ planned: 'primary', done: 'success', cancelled: 'tertiary' })[props.appointment.status])

const needsFollowUp = computed(() => props.appointment.status === 'planned'
	&& props.appointment.startsAt + props.appointment.duration * 60 < Date.now() / 1000)

const calendarHint = computed(() => store.settings.calendarUri
	? t('vitalog', 'Not added to your calendar (cancelled or sync failed).')
	: t('vitalog', 'Calendar sync is off. Enable it from the settings in the left sidebar.'))

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

const setStatus = (status) => store.setStatus(props.appointment, status)

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
.details {
	display: flex;
	flex-direction: column;
	gap: 20px;
	max-width: 760px;
	padding: 20px 20px 20px calc(var(--default-clickable-area) + 12px);

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

	&__followup,
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

	&__person {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
		padding-inline-end: 0;

		// le glyphe (20 px) s'aligne sur le bord droit du bloc, pas sur celui de la zone cliquable
		.details__contact {
			margin-inline-end: calc((20px - var(--default-clickable-area)) / 2);
		}

		> :first-child {
			flex: 1;
			min-width: 0;
		}
	}

	&__notes {
		white-space: pre-wrap;
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
