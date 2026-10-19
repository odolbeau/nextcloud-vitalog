<template>
	<NcContent appName="vitalog">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem name="Rendez-vous" :to="{ path: '/' }" />
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<NcEmptyContent name="Vitalog" description="Aucun rendez-vous pour l'instant." />
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
</script>
