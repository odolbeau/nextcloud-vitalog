<template>
	<NcDialog
		:name="t('vitalog', 'Calendar sync')"
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
				:placeholder="t('vitalog', 'Sync disabled')"
				label="label" />
			<NcNoteCard v-if="options.length === 0" type="warning">
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
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useDataStore } from '../stores/data.js'

const emit = defineEmits(['close'])

const store = useDataStore()
const saving = ref(false)
const options = computed(() => store.settings.calendars.map((c) => ({ id: c.uri, label: c.name })))
const selected = ref(options.value.find((o) => o.id === store.settings.calendarUri) ?? null)

async function submit() {
	saving.value = true
	const saved = await store.saveCalendar(selected.value?.id ?? null)
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
}
</style>
