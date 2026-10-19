<template>
	<NcDialog
		:name="place ? t('vitalog', 'Edit place') : t('vitalog', 'New place')"
		isForm
		@submit="submit"
		@closing="emit('close')">
		<div class="form">
			<NcTextField
				v-model="form.name"
				:maxlength="255"
				:label="t('vitalog', 'Name')"
				required
				autofocus />
			<NcTextArea v-model="form.address" :label="t('vitalog', 'Address')" rows="3" />
		</div>
		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('vitalog', 'Cancel') }}
			</NcButton>
			<NcButton
				type="submit"
				variant="primary"
				:disabled="saving || !form.name.trim()">
				{{ t('vitalog', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { reactive, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	place: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const store = useDataStore()
const saving = ref(false)
const form = reactive({
	name: props.place?.name ?? '',
	address: props.place?.address ?? '',
})

async function submit() {
	saving.value = true
	const saved = await store.savePlace({
		name: form.name.trim(),
		address: form.address.trim() || null,
	}, props.place?.id)
	saving.value = false
	if (saved) {
		emit('saved', saved)
		emit('close')
	}
}
</script>

<style scoped lang="scss">
.form {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding-block: 8px;
}
</style>
