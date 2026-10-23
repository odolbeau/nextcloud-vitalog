<template>
	<NcContent appName="vitalog">
		<NcAppNavigation :aria-label="t('vitalog', 'Vitalog navigation')">
			<template #search>
				<span />
			</template>
			<NcAppNavigationNew :text="t('vitalog', 'New appointment')" @click="openForm()">
				<template #icon>
					<Plus :size="20" />
				</template>
			</NcAppNavigationNew>

			<template #list>
				<NcAppNavigationItem
					v-for="item in appointmentViews"
					:key="item.id"
					:name="item.name"
					:active="nav.view === item.id"
					:data-test="`nav-${item.id}`"
					@click.prevent="go({ view: item.id })">
					<template #icon>
						<component :is="item.icon" :size="20" />
					</template>
					<template v-if="item.count" #counter>
						<NcCounterBubble :count="item.count" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationSpacer />
				<NcAppNavigationItem
					:name="t('vitalog', 'Practitioners')"
					:active="nav.view === 'practitioners'"
					data-test="nav-practitioners"
					@click.prevent="go({ view: 'practitioners' })">
					<template #icon>
						<Stethoscope :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem
					:name="t('vitalog', 'Labels')"
					:active="nav.view === 'labels'"
					data-test="nav-labels"
					@click.prevent="go({ view: 'labels' })">
					<template #icon>
						<TagMultiple :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem
					:name="t('vitalog', 'Places')"
					:active="nav.view === 'places'"
					data-test="nav-places"
					@click.prevent="go({ view: 'places' })">
					<template #icon>
						<MapMarker :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>

			<template #footer>
				<ul class="footer">
					<NcAppNavigationItem
						:name="t('vitalog', 'Calendar')"
						data-test="nav-settings"
						@click.prevent="settingsOpen = true">
						<template #icon>
							<CalendarMonth :size="20" />
						</template>
					</NcAppNavigationItem>
				</ul>
			</template>
		</NcAppNavigation>

		<NcAppContent v-if="store.loadError" :pageHeading="t('vitalog', 'Error')">
			<NcEmptyContent
				:name="t('vitalog', 'Could not load your data')"
				:description="t('vitalog', 'Check your connection and try again.')">
				<template #icon>
					<AlertCircleOutline />
				</template>
				<template #action>
					<NcButton variant="primary" @click="store.load()">
						{{ t('vitalog', 'Retry') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</NcAppContent>
		<PractitionersView v-else-if="nav.view === 'practitioners'" @filter="showPractitioner" />
		<PlacesView v-else-if="nav.view === 'places'" />
		<LabelsView v-else-if="nav.view === 'labels'" @filter="showLabel" />
		<AppointmentsView
			v-else
			:filter="nav.view"
			:labelId="nav.label"
			:practitionerId="nav.practitioner"
			@update:labelId="(id) => go({ label: id })"
			@update:practitionerId="(id) => go({ practitioner: id })"
			@showLabel="showLabel"
			@create="openForm()"
			@edit="openForm" />

		<AppointmentForm
			v-if="formOpen"
			:appointment="editing"
			@saved="onSaved"
			@close="formOpen = false" />
		<CalendarSettingsDialog v-if="settingsOpen" @close="settingsOpen = false" />
	</NcContent>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref, watch } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcAppNavigationSpacer from '@nextcloud/vue/components/NcAppNavigationSpacer'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CalendarArrowRight from 'vue-material-design-icons/CalendarArrowRight.vue'
import CalendarCheck from 'vue-material-design-icons/CalendarCheck.vue'
import CalendarMonth from 'vue-material-design-icons/CalendarMonth.vue'
import MapMarker from 'vue-material-design-icons/MapMarker.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Stethoscope from 'vue-material-design-icons/Stethoscope.vue'
import TagMultiple from 'vue-material-design-icons/TagMultiple.vue'
import AppointmentForm from './components/AppointmentForm.vue'
import CalendarSettingsDialog from './components/CalendarSettingsDialog.vue'
import AppointmentsView from './views/AppointmentsView.vue'
import LabelsView from './views/LabelsView.vue'
import PlacesView from './views/PlacesView.vue'
import PractitionersView from './views/PractitionersView.vue'
import { go, nav } from './navigation.js'
import { useDataStore } from './stores/data.js'

const store = useDataStore()
const formOpen = ref(false)
const editing = ref(null)
const settingsOpen = ref(false)

// un élément supprimé (ou un lien périmé) ne doit pas laisser un filtre ou une sélection fantôme
watch(() => [store.loaded, nav.label, nav.practitioner, nav.appointment, store.labels, store.practitioners, store.appointments], () => {
	if (!store.loaded) {
		return
	}
	const stale = {}
	for (const [key, known] of [['label', store.labelById], ['practitioner', store.practitionerById], ['appointment', (id) => store.appointments.find((a) => a.id === id)]]) {
		if (nav[key] !== null && !known(nav[key])) {
			stale[key] = null
		}
	}
	if (Object.keys(stale).length > 0) {
		go(stale, { replace: true })
	}
})

const appointmentViews = computed(() => [
	{ id: 'upcoming', name: t('vitalog', 'Upcoming'), icon: CalendarArrowRight, count: store.upcoming.length },
	{ id: 'past', name: t('vitalog', 'Past'), icon: CalendarCheck, count: 0 },
	{ id: 'all', name: t('vitalog', 'All'), icon: CalendarMonth, count: 0 },
])

function openForm(appointment = null) {
	editing.value = appointment
	formOpen.value = true
}

/** Tous les rendez-vous portant ce libellé, passés et à venir. */
function showLabel(id) {
	go({ label: id, view: 'all' })
}

/** Tous les rendez-vous avec ce praticien, passés et à venir. */
function showPractitioner(id) {
	go({ practitioner: id, view: 'all' })
}

function onSaved(appointment) {
	// l'enregistrement peut faire passer le rendez-vous d'une liste à l'autre : on bascule sur celle qui le contient
	const appointmentView = store.upcoming.some((a) => a.id === appointment.id) ? 'upcoming' : 'past'
	go({ view: ['practitioners', 'places', 'labels'].includes(nav.view) ? 'upcoming' : appointmentView, appointment: appointment.id })
}

onMounted(() => store.load())
</script>

<style scoped lang="scss">
.footer {
	padding: 4px;
}
</style>
