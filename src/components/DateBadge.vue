<template>
	<div class="date-badge" :class="{ 'date-badge--muted': muted }" aria-hidden="true">
		<span class="date-badge__month">{{ month }}</span>
		<span class="date-badge__day">{{ day }}</span>
		<span v-if="otherYear" class="date-badge__year">{{ year }}</span>
	</div>
</template>

<script setup>
import { computed } from 'vue'
import { dayOf, monthOf, yearOf } from '../format.js'

const props = defineProps({
	startsAt: { type: Number, required: true },
	muted: { type: Boolean, default: false },
})

const day = computed(() => dayOf(props.startsAt))
const month = computed(() => monthOf(props.startsAt))
const year = computed(() => yearOf(props.startsAt))
const otherYear = computed(() => year.value !== new Date().getFullYear())
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

	&__year {
		font-size: 0.65em;
		line-height: 1;
	}
}
</style>
