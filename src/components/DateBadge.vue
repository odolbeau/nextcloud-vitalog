<template>
	<div class="date-badge" :class="{ 'date-badge--muted': muted }" aria-hidden="true">
		<span class="date-badge__month">{{ month }}</span>
		<span class="date-badge__day">{{ day }}</span>
	</div>
</template>

<script setup>
import { computed } from 'vue'
import { dayOf, monthOf } from '../format.js'

const props = defineProps({
	startsAt: { type: Number, required: true },
	muted: { type: Boolean, default: false },
})

const day = computed(() => dayOf(props.startsAt))
const month = computed(() => monthOf(props.startsAt))
</script>

<style scoped lang="scss">
.date-badge {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 44px;
	height: 44px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	line-height: 1.1;

	&--muted {
		background: var(--color-background-dark);
		color: var(--color-text-maxcontrast);
	}

	&__month {
		font-size: 0.7em;
		text-transform: uppercase;
	}

	&__day {
		font-size: 1.2em;
		font-weight: bold;
	}
}
</style>
