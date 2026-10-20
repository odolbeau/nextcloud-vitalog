<template>
	<NcAppContent :pageHeading="t('vitalog', 'Labels')">
		<div class="reference">
			<header class="reference__header">
				<h2>{{ t('vitalog', 'Labels') }}</h2>
				<NcButton variant="primary" @click.prevent="editing = {}">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('vitalog', 'New label') }}
				</NcButton>
			</header>

			<NcLoadingIcon v-if="!store.loaded" :size="32" />
			<NcEmptyContent
				v-else-if="store.labels.length === 0"
				:name="t('vitalog', 'No labels yet')"
				:description="t('vitalog', 'Use labels to bring together appointments about the same topic, whoever you see and wherever it takes place.')">
				<template #icon>
					<TagMultiple />
				</template>
			</NcEmptyContent>
			<ul v-else class="reference__list" :aria-label="t('vitalog', 'Labels')">
				<NcListItem
					v-for="label in store.labels"
					:key="label.id"
					:name="label.name"
					:data-test="`label-row-${label.id}`"
					@click.prevent="editing = label">
					<template #icon>
						<span
							class="dot"
							:class="{ 'dot--none': !label.color }"
							:style="label.color ? { background: label.color } : null"
							aria-hidden="true" />
					</template>
					<template #subname>
						{{ t('vitalog', 'Appointments: {count}', { count: store.countForLabel(label.id) }) }}
					</template>
					<template #extra-actions>
						<NcButton
							variant="tertiary"
							:aria-label="t('vitalog', 'Show appointments labelled {name}', { name: label.name })"
							:title="t('vitalog', 'Show appointments')"
							@click.stop="emit('filter', label.id)">
							<template #icon>
								<CalendarSearch :size="20" />
							</template>
						</NcButton>
						<NcButton
							variant="tertiary"
							:aria-label="t('vitalog', 'Delete {name}', { name: label.name })"
							@click.stop="deleting = label">
							<template #icon>
								<Delete :size="20" />
							</template>
						</NcButton>
					</template>
				</NcListItem>
			</ul>
		</div>

		<LabelForm
			v-if="editing"
			:label="editing.id ? editing : null"
			@close="editing = null" />
		<NcDialog
			v-if="deleting"
			:name="t('vitalog', 'Delete {name}?', { name: deleting.name })"
			:message="t('vitalog', 'Appointments with this label are kept, but will no longer carry it.')"
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
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import TagMultiple from 'vue-material-design-icons/TagMultiple.vue'
import LabelForm from '../components/LabelForm.vue'
import { useDataStore } from '../stores/data.js'

const emit = defineEmits(['filter'])

const store = useDataStore()
const editing = ref(null)
const deleting = ref(null)

const deleteButtons = [
	{ label: t('vitalog', 'Cancel'), callback: () => {} },
	{ label: t('vitalog', 'Delete'), variant: 'error', callback: () => store.deleteLabel(deleting.value.id) },
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

.dot {
	display: inline-block;
	width: 20px;
	height: 20px;
	border-radius: 50%;

	&--none {
		border: 2px solid var(--color-border-maxcontrast);
		background: var(--color-background-dark);
	}
}
</style>
