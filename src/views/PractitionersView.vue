<template>
	<NcAppContent :pageHeading="t('vitalog', 'Practitioners')">
		<div class="reference">
			<header class="reference__header">
				<h2>{{ t('vitalog', 'Practitioners') }}</h2>
				<NcButton variant="primary" @click.prevent="editing = {}">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('vitalog', 'New practitioner') }}
				</NcButton>
			</header>

			<NcLoadingIcon v-if="!store.loaded" :size="32" />
			<NcEmptyContent
				v-else-if="store.practitioners.length === 0"
				:name="t('vitalog', 'No practitioners yet')"
				:description="t('vitalog', 'Add your doctors, dentists, specialists… to reuse them across appointments.')">
				<template #icon>
					<Stethoscope />
				</template>
			</NcEmptyContent>
			<ul v-else class="reference__list" :aria-label="t('vitalog', 'Practitioners')">
				<NcListItem
					v-for="practitioner in store.practitioners"
					:key="practitioner.id"
					:name="practitioner.name"
					:data-test="`practitioner-${practitioner.id}`"
					@click.prevent="editing = practitioner">
					<template #subname>
						{{ [practitioner.specialty, practitioner.phone].filter(Boolean).join(' · ') }}
					</template>
					<template #extra-actions>
						<NcButton
							variant="tertiary"
							:aria-label="t('vitalog', 'Show appointments with {name}', { name: practitioner.name })"
							:title="t('vitalog', 'Show appointments')"
							@click.stop="emit('filter', practitioner.id)">
							<template #icon>
								<CalendarSearch :size="20" />
							</template>
						</NcButton>
						<NcButton
							v-if="practitioner.contactUrl"
							variant="tertiary"
							:href="practitioner.contactUrl"
							:aria-label="t('vitalog', 'Open {name} in Contacts', { name: practitioner.name })"
							@click.stop>
							<template #icon>
								<CardAccountDetails :size="20" />
							</template>
						</NcButton>
						<NcButton
							variant="tertiary"
							:aria-label="t('vitalog', 'Delete {name}', { name: practitioner.name })"
							@click.stop="deleting = practitioner">
							<template #icon>
								<Delete :size="20" />
							</template>
						</NcButton>
					</template>
				</NcListItem>
			</ul>
		</div>

		<PractitionerForm
			v-if="editing"
			:practitioner="editing.id ? editing : null"
			@close="editing = null" />
		<NcDialog
			v-if="deleting"
			:name="t('vitalog', 'Delete {name}?', { name: deleting.name })"
			:message="t('vitalog', 'Appointments with this practitioner are kept, but will no longer be linked to them.')"
			:buttons="deleteButtons"
			@closing="deleting = null" />
	</NcAppContent>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import CalendarSearch from 'vue-material-design-icons/CalendarSearch.vue'
import CardAccountDetails from 'vue-material-design-icons/CardAccountDetails.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Stethoscope from 'vue-material-design-icons/Stethoscope.vue'
import PractitionerForm from '../components/PractitionerForm.vue'
import { useDataStore } from '../stores/data.js'

const emit = defineEmits(['filter'])

const store = useDataStore()
const editing = ref(null)
const deleting = ref(null)

const deleteButtons = [
	{ label: t('vitalog', 'Cancel'), callback: () => {} },
	{ label: t('vitalog', 'Delete'), variant: 'error', callback: () => store.deletePractitioner(deleting.value.id) },
]
</script>

<style scoped lang="scss">
.reference {
	max-width: 760px;
	padding: 20px 20px 20px calc(var(--default-clickable-area) + 12px);

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 16px;

		h2 {
			margin: 0;
		}
	}
}
</style>
