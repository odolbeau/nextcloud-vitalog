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
					:active="view === item.id"
					:data-test="`nav-${item.id}`"
					@click="view = item.id">
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
					:active="view === 'practitioners'"
					data-test="nav-practitioners"
					@click="view = 'practitioners'">
					<template #icon>
						<Stethoscope :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem
					:name="t('vitalog', 'Places')"
					:active="view === 'places'"
					data-test="nav-places"
					@click="view = 'places'">
					<template #icon>
						<MapMarker :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>

			<template #footer>
				<ul class="footer">
					<NcAppNavigationItem
						:name="t('vitalog', 'Calendar sync')"
						data-test="nav-settings"
						@click="settingsOpen = true">
						<template #icon>
							<CalendarSync :size="20" />
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
		<PractitionersView v-else-if="view === 'practitioners'" />
		<PlacesView v-else-if="view === 'places'" />
		<AppointmentsView
			v-else
			:filter="view"
			:focusId="focusId"
			:initialQuery="initialQuery"
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
import { computed, onMounted, ref } from 'vue'
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
import CalendarSync from 'vue-material-design-icons/CalendarSync.vue'
import MapMarker from 'vue-material-design-icons/MapMarker.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Stethoscope from 'vue-material-design-icons/Stethoscope.vue'
import AppointmentForm from './components/AppointmentForm.vue'
import CalendarSettingsDialog from './components/CalendarSettingsDialog.vue'
import AppointmentsView from './views/AppointmentsView.vue'
import PlacesView from './views/PlacesView.vue'
import PractitionersView from './views/PractitionersView.vue'
import { useDataStore } from './stores/data.js'

const store = useDataStore()
const view = ref('upcoming')
const formOpen = ref(false)
const editing = ref(null)
const settingsOpen = ref(false)
const focusId = ref(null)
// lien « Rendez-vous Vitalog » du menu des contacts : ?practitioner=ID affiche les rendez-vous de ce praticien
const initialQuery = ref('')

const appointmentViews = computed(() => [
	{ id: 'upcoming', name: t('vitalog', 'Upcoming'), icon: CalendarArrowRight, count: store.upcoming.length },
	{ id: 'past', name: t('vitalog', 'Past'), icon: CalendarCheck, count: 0 },
	{ id: 'all', name: t('vitalog', 'All'), icon: CalendarMonth, count: 0 },
])

function openForm(appointment = null) {
	editing.value = appointment
	formOpen.value = true
}

function onSaved(appointment) {
	if (!['practitioners', 'places'].includes(view.value)) {
		// l'enregistrement peut faire passer le rendez-vous d'une liste à l'autre : on bascule sur celle qui le contient
		view.value = store.upcoming.some((a) => a.id === appointment.id) ? 'upcoming' : 'past'
	} else {
		view.value = 'upcoming'
	}
	focusId.value = null
	// laisser le temps au changement de vue avant de sélectionner
	queueMicrotask(() => {
		focusId.value = appointment.id
	})
}

onMounted(async () => {
	await store.load()
	const id = Number(new URLSearchParams(window.location.search).get('practitioner'))
	const practitioner = id ? store.practitionerById(id) : null
	if (practitioner) {
		view.value = 'all'
		initialQuery.value = practitioner.name
	}
})
</script>

<style scoped lang="scss">
.footer {
	padding: 4px;
}
</style>
