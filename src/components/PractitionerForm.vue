<template>
	<NcDialog
		:name="practitioner ? t('vitalog', 'Edit practitioner') : t('vitalog', 'New practitioner')"
		isForm
		@submit="submit"
		@closing="emit('close')">
		<div class="form">
			<NcTextField
				v-model="form.name"
				:label="t('vitalog', 'Name')"
				required
				autofocus />
			<NcTextField v-model="form.specialty" :label="t('vitalog', 'Specialty')" />
			<NcTextField v-model="form.phone" :label="t('vitalog', 'Phone')" type="tel" />
			<NcTextArea v-model="form.notes" :label="t('vitalog', 'Notes')" rows="3" />
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
	practitioner: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const store = useDataStore()
const saving = ref(false)
const form = reactive({
	name: props.practitioner?.name ?? '',
	specialty: props.practitioner?.specialty ?? '',
	phone: props.practitioner?.phone ?? '',
	notes: props.practitioner?.notes ?? '',
})

async function submit() {
	saving.value = true
	const saved = await store.savePractitioner({
		name: form.name.trim(),
		specialty: form.specialty.trim() || null,
		phone: form.phone.trim() || null,
		notes: form.notes.trim() || null,
	}, props.practitioner?.id)
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
