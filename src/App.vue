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
					:name="t('vitalog', 'Appointments')"
					:active="nav.view === 'appointments'"
					data-test="nav-appointments"
					@click.prevent="go({ view: 'appointments' })">
					<template #icon>
						<CalendarHeart :size="20" />
					</template>
					<template v-if="store.upcoming.length > 0" #counter>
						<NcCounterBubble :count="store.upcoming.length" :title="t('vitalog', 'Upcoming appointments')" />
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
			:when="nav.when"
			:labelId="nav.label"
			:practitionerId="nav.practitioner"
			@update:when="(when) => go({ when })"
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
import { onMounted, ref, watch } from 'vue'
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
import CalendarHeart from 'vue-material-design-icons/CalendarHeart.vue'
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

function openForm(appointment = null) {
	editing.value = appointment
	formOpen.value = true
}

/** Tous les rendez-vous portant ce libellé, passés et à venir. */
function showLabel(id) {
	go({ label: id, view: 'appointments', when: null })
}

/** Tous les rendez-vous avec ce praticien, passés et à venir. */
function showPractitioner(id) {
	go({ practitioner: id, view: 'appointments', when: null })
}

function onSaved(appointment) {
	// l'enregistrement peut faire sortir le rendez-vous de la période filtrée : on retire alors ce filtre pour l'afficher
	const when = nav.when === null || nav.when === (store.isPast(appointment) ? 'past' : 'upcoming') ? nav.when : null
	go({ view: 'appointments', when, appointment: appointment.id })
}

onMounted(() => store.load())
</script>

<style scoped lang="scss">
.footer {
	padding: 4px;
}
</style>
