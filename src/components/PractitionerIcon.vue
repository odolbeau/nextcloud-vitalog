<template>
	<span
		class="practitioner-icon"
		:style="{ '--practitioner-icon-color': color, '--practitioner-icon-size': `${box}px` }"
		:title="label || undefined"
		:aria-label="label || undefined"
		:aria-hidden="label ? undefined : 'true'"
		:role="label ? 'img' : undefined">
		<component :is="component" :size="size" />
	</span>
</template>

<script setup>
import { computed } from 'vue'
import { DEFAULT_ICON, iconColor } from '../practitionerIcons.js'
import { ICON_COMPONENTS } from '../practitionerIconViews.js'

const props = defineProps({
	/** Clé de l'icône (voir practitionerIcons.js). */
	icon: { type: String, default: DEFAULT_ICON },
	size: { type: Number, default: 20 },
	/** Nom accessible ; sans lui, l'icône est décorative. */
	label: { type: String, default: '' },
})

const component = computed(() => ICON_COMPONENTS[props.icon] ?? ICON_COMPONENTS[DEFAULT_ICON])
const color = computed(() => iconColor(props.icon))
const box = computed(() => Math.round(props.size * 1.75))
</script>

<style scoped lang="scss">
.practitioner-icon {
	display: inline-flex;
	flex-shrink: 0;
	align-items: center;
	justify-content: center;
	width: var(--practitioner-icon-size);
	height: var(--practitioner-icon-size);
	border-radius: 50%;
	background: color-mix(in srgb, var(--practitioner-icon-color) 16%, transparent);
	// assombrie en thème clair, éclaircie en thème sombre : lisible sur les deux fonds
	color: color-mix(in srgb, var(--practitioner-icon-color) 75%, var(--color-main-text));
}
</style>
