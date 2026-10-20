<template>
	<NcAppContent :pageHeading="t('vitalog', 'Places')">
		<div class="reference">
			<header class="reference__header">
				<h2>{{ t('vitalog', 'Places') }}</h2>
				<NcButton variant="primary" @click.prevent="editing = {}">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('vitalog', 'New place') }}
				</NcButton>
			</header>

			<NcLoadingIcon v-if="!store.loaded" :size="32" />
			<NcEmptyContent
				v-else-if="store.places.length === 0"
				:name="t('vitalog', 'No places yet')"
				:description="t('vitalog', 'Add the clinics, surgeries and labs you go to, to reuse them across appointments.')">
				<template #icon>
					<MapMarker />
				</template>
			</NcEmptyContent>
			<ul v-else class="reference__list" :aria-label="t('vitalog', 'Places')">
				<NcListItem
					v-for="place in store.places"
					:key="place.id"
					:name="place.name"
					:data-test="`place-${place.id}`"
					@click.prevent="editing = place">
					<template #subname>
						{{ place.address }}
					</template>
					<template #extra-actions>
						<NcButton
							variant="tertiary"
							:aria-label="t('vitalog', 'Delete {name}', { name: place.name })"
							@click.stop="deleting = place">
							<template #icon>
								<Delete :size="20" />
							</template>
						</NcButton>
					</template>
				</NcListItem>
			</ul>
		</div>

		<PlaceForm
			v-if="editing"
			:place="editing.id ? editing : null"
			@close="editing = null" />
		<NcDialog
			v-if="deleting"
			:name="t('vitalog', 'Delete {name}?', { name: deleting.name })"
			:message="t('vitalog', 'Appointments at this place are kept, but will no longer be linked to it.')"
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
import Delete from 'vue-material-design-icons/Delete.vue'
import MapMarker from 'vue-material-design-icons/MapMarker.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import PlaceForm from '../components/PlaceForm.vue'
import { useDataStore } from '../stores/data.js'

const store = useDataStore()
const editing = ref(null)
const deleting = ref(null)

const deleteButtons = [
	{ label: t('vitalog', 'Cancel'), callback: () => {} },
	{ label: t('vitalog', 'Delete'), variant: 'error', callback: () => store.deletePlace(deleting.value.id) },
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
