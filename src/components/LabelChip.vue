<template>
	<component
		:is="clickable ? 'button' : 'span'"
		class="chip"
		:class="{ 'chip--colored': label.color, 'chip--active': active }"
		:style="label.color ? { '--chip-color': label.color } : null"
		:type="clickable ? 'button' : null"
		:aria-pressed="toggle ? String(active) : null"
		:data-test="`label-${label.id}`">
		<span class="chip__name">{{ label.name }}</span>
	</component>
</template>

<script setup>
defineProps({
	label: { type: Object, required: true },
	clickable: { type: Boolean, default: false },
	// bouton à bascule (filtre) et son état
	toggle: { type: Boolean, default: false },
	active: { type: Boolean, default: false },
})
</script>

<style scoped lang="scss">
.chip {
	display: inline-flex;
	align-items: center;
	max-width: 100%;
	padding: 2px 10px;
	border: 2px solid transparent;
	border-radius: var(--border-radius-pill);
	background: var(--color-background-dark);
	color: var(--color-main-text);
	font: inherit;
	font-size: 0.9em;
	line-height: 1.4;

	&--colored {
		background: var(--chip-color);
		color: #fff;
	}

	&__name {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&:is(button) {
		cursor: pointer;

		&:hover {
			border-color: var(--color-primary-element);
		}

		&:focus-visible {
			outline: 2px solid var(--color-main-text);
			outline-offset: 1px;
		}
	}

	&--active {
		border-color: var(--color-main-text);
		font-weight: bold;
	}
}
</style>
