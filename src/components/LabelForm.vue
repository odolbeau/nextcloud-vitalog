<template>
	<NcDialog
		:name="label ? t('vitalog', 'Edit label') : t('vitalog', 'New label')"
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
			<fieldset class="form__colors">
				<legend>{{ t('vitalog', 'Color') }}</legend>
				<label class="swatch">
					<input
						v-model="form.color"
						class="swatch__input"
						type="radio"
						name="vitalog-label-color"
						:value="null">
					<span class="swatch__box swatch__box--none" aria-hidden="true" />
					<span class="swatch__name">{{ t('vitalog', 'None') }}</span>
				</label>
				<label v-for="color in colors" :key="color.value" class="swatch">
					<input
						v-model="form.color"
						class="swatch__input"
						type="radio"
						name="vitalog-label-color"
						:value="color.value">
					<span class="swatch__box" :style="{ background: color.value }" aria-hidden="true" />
					<span class="swatch__name">{{ color.label }}</span>
				</label>
			</fieldset>
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
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { LABEL_COLORS } from '../labels.js'
import { useDialogHistory } from '../navigation.js'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	label: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

useDialogHistory(() => emit('close'))
const store = useDataStore()
const saving = ref(false)
const form = reactive({
	name: props.label?.name ?? '',
	color: props.label?.color ?? null,
})
const colors = [
	{ value: LABEL_COLORS[0].value, label: t('vitalog', 'Red') },
	{ value: LABEL_COLORS[1].value, label: t('vitalog', 'Orange') },
	{ value: LABEL_COLORS[2].value, label: t('vitalog', 'Yellow') },
	{ value: LABEL_COLORS[3].value, label: t('vitalog', 'Green') },
	{ value: LABEL_COLORS[4].value, label: t('vitalog', 'Teal') },
	{ value: LABEL_COLORS[5].value, label: t('vitalog', 'Blue') },
	{ value: LABEL_COLORS[6].value, label: t('vitalog', 'Purple') },
	{ value: LABEL_COLORS[7].value, label: t('vitalog', 'Pink') },
]

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

	&__colors {
		display: flex;
		flex-wrap: wrap;
		gap: 4px 12px;
		margin: 0;
		padding: 0;
		border: 0;

		legend {
			margin-bottom: 4px;
			padding: 0;
			font-weight: bold;
		}
	}
}

.swatch {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	cursor: pointer;

	&__input {
		position: absolute;
		opacity: 0;
	}

	&__box {
		width: 20px;
		height: 20px;
		border: 2px solid transparent;
		border-radius: 50%;

		&--none {
			border-color: var(--color-border-maxcontrast);
			background: var(--color-background-dark);
		}
	}

	&__input:checked + &__box {
		outline: 2px solid var(--color-main-text);
		outline-offset: 2px;
	}

	&__input:focus-visible + &__box {
		outline: 2px solid var(--color-primary-element);
		outline-offset: 2px;
	}
}
</style>
