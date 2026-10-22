<template>
	<NcDialog
		:name="label ? t('vitalog', 'Edit label') : t('vitalog', 'New label')"
		isForm
		@submit="submit"
		@closing="emit('close')">
		<div ref="formElement" class="form">
			<NcTextField
				v-model="form.name"
				:maxlength="255"
				:label="t('vitalog', 'Name')"
				required
				autofocus />
			<NcColorPicker v-model="color" :container="pickerContainer" clearable>
				<NcButton>
					<template #icon>
						<span
							class="form__swatch"
							:class="{ 'form__swatch--none': !form.color }"
							:style="form.color ? { background: form.color } : null"
							aria-hidden="true" />
					</template>
					{{ t('vitalog', 'Color') }}
				</NcButton>
			</NcColorPicker>
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
import { computed, reactive, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useDialogHistory } from '../navigation.js'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	label: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

useDialogHistory(() => emit('close'))
const store = useDataStore()
const saving = ref(false)
// Téléporté dans <body>, le sélecteur serait hors du piège à focus de la boîte de dialogue, qui reprend le focus et fait
// parfois perdre le clic sur une couleur : on l'ouvre dans le fond du modal (piégé aussi, mais sans rogner le popover)
const formElement = ref(null)
const pickerContainer = computed(() => formElement.value?.closest('.modal-mask') ?? 'body')
const form = reactive({
	name: props.label?.name ?? '',
	color: props.label?.color ?? null,
})
const color = computed({
	get: () => form.color ?? undefined,
	set: (value) => {
		form.color = value ?? null
	},
})

async function submit() {
	saving.value = true
	const saved = await store.saveLabel({ name: form.name.trim(), color: form.color }, props.label?.id)
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

	&__swatch {
		width: 20px;
		height: 20px;
		border-radius: 50%;

		&--none {
			border: 2px solid var(--color-border-maxcontrast);
			background: var(--color-background-dark);
		}
	}
}
</style>
