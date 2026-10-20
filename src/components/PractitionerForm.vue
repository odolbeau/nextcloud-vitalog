<template>
	<NcDialog
		:name="practitioner ? t('vitalog', 'Edit practitioner') : t('vitalog', 'New practitioner')"
		isForm
		@submit="submit"
		@closing="emit('close')">
		<div class="form">
			<template v-if="contactsAvailable || contact">
				<NcSelect
					v-model="contact"
					inputId="vitalog-contact"
					:inputLabel="t('vitalog', 'Contact')"
					:placeholder="t('vitalog', 'Search your contacts…')"
					:options="options"
					:filterable="false"
					:loading="searching"
					label="label"
					@search="onSearch"
					@update:modelValue="onPick" />
				<p v-if="linked" class="form__hint">
					{{ t('vitalog', 'Name, specialty and phone come from the contact.') }}
					<a
						v-if="practitioner?.contactUrl"
						:href="practitioner.contactUrl"
						target="_blank"
						rel="noopener">{{ t('vitalog', 'Edit in Contacts') }}</a>
				</p>
				<NcNoteCard v-else-if="contact && practitioner?.contactStatus === 'missing'" type="warning">
					{{ t('vitalog', 'The linked contact no longer exists. The details below are kept; clear the contact field to unlink.') }}
				</NcNoteCard>
				<NcNoteCard v-else-if="contact && practitioner?.contactStatus === 'unavailable'" type="info">
					{{ t('vitalog', 'The Contacts app is not available right now. The saved details are shown.') }}
				</NcNoteCard>
			</template>
			<NcTextField
				v-model="form.name"
				:maxlength="255"
				:label="t('vitalog', 'Name')"
				:disabled="linked"
				required
				autofocus />
			<NcTextField
				v-model="form.specialty"
				:maxlength="255"
				:disabled="linked"
				:label="t('vitalog', 'Specialty')" />
			<NcTextField
				v-model="form.phone"
				:maxlength="64"
				:disabled="linked"
				:label="t('vitalog', 'Phone')"
				type="tel" />
			<NcTextArea v-model="form.notes" :label="t('vitalog', 'Notes')" rows="3" />
		</div>
		<template #actions>
			<NcButton
				v-if="practitioner && !contact && contactsAvailable"
				:disabled="saving"
				@click="addToContacts">
				{{ t('vitalog', 'Add to my contacts') }}
			</NcButton>
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
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import * as api from '../api.js'
import { useDataStore } from '../stores/data.js'

const props = defineProps({
	practitioner: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const store = useDataStore()
const saving = ref(false)
const form = reactive({
	name: props.practitioner?.name ?? '',
	specialty: props.practitioner?.specialty ?? '',
	phone: props.practitioner?.phone ?? '',
	notes: props.practitioner?.notes ?? '',
})

const toOption = (c) => ({ uid: c.uid, label: c.specialty ? `${c.name} · ${c.specialty}` : c.name, contact: c })
const contact = ref(props.practitioner?.contactUid
	? { uid: props.practitioner.contactUid, label: props.practitioner.name }
	: null)
const contactsAvailable = ref(false)
const options = ref([])
const searching = ref(false)
// seules les fiches retrouvées verrouillent les champs : une fiche supprimée laisse la copie locale modifiable
const linked = computed(() => contact.value !== null && !['missing', 'unavailable'].includes(props.practitioner?.contactStatus))
let timer = null
let latest = 0

async function search(term) {
	const id = ++latest
	searching.value = true
	try {
		const result = await api.searchContacts(term)
		if (id === latest) {
			contactsAvailable.value = result.available
			options.value = result.contacts.map(toOption)
		}
	} catch {
		// la recherche est une aide : sans elle, le formulaire reste utilisable
	} finally {
		if (id === latest) {
			searching.value = false
		}
	}
}
function onSearch(term) {
	clearTimeout(timer)
	timer = setTimeout(() => search(term), 250)
}
function onPick(option) {
	if (option?.contact) {
		form.name = option.contact.name
		form.specialty = option.contact.specialty ?? ''
		form.phone = option.contact.phone ?? ''
	}
}
async function addToContacts() {
	saving.value = true
	const saved = await store.createContactFor(props.practitioner)
	saving.value = false
	if (saved) {
		emit('saved', saved)
		emit('close')
	}
}

onMounted(() => search(''))
onBeforeUnmount(() => clearTimeout(timer))

async function submit() {
	saving.value = true
	const saved = await store.savePractitioner({
		name: form.name.trim(),
		specialty: form.specialty.trim() || null,
		phone: form.phone.trim() || null,
		notes: form.notes.trim() || null,
		contactUid: contact.value?.uid ?? null,
	}, props.practitioner?.id)
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

	&__hint {
		color: var(--color-text-maxcontrast);
	}
}
</style>
