// Rendu des icônes de praticien : composant et nom traduit de chaque clé de `practitionerIcons.js`.
import { t } from '@nextcloud/l10n'
import Allergy from 'vue-material-design-icons/Allergy.vue'
import BabyFaceOutline from 'vue-material-design-icons/BabyFaceOutline.vue'
import Bone from 'vue-material-design-icons/Bone.vue'
import EarHearing from 'vue-material-design-icons/EarHearing.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import FoodApple from 'vue-material-design-icons/FoodApple.vue'
import FootPrint from 'vue-material-design-icons/FootPrint.vue'
import HeadCog from 'vue-material-design-icons/HeadCog.vue'
import HeartPulse from 'vue-material-design-icons/HeartPulse.vue'
import HospitalBox from 'vue-material-design-icons/HospitalBox.vue'
import HumanPregnant from 'vue-material-design-icons/HumanPregnant.vue'
import Lotion from 'vue-material-design-icons/Lotion.vue'
import Lungs from 'vue-material-design-icons/Lungs.vue'
import Needle from 'vue-material-design-icons/Needle.vue'
import Pill from 'vue-material-design-icons/Pill.vue'
import RadiologyBox from 'vue-material-design-icons/RadiologyBox.vue'
import Ribbon from 'vue-material-design-icons/Ribbon.vue'
import Run from 'vue-material-design-icons/Run.vue'
import Stethoscope from 'vue-material-design-icons/Stethoscope.vue'
import Stomach from 'vue-material-design-icons/Stomach.vue'
import TestTube from 'vue-material-design-icons/TestTube.vue'
import Tooth from 'vue-material-design-icons/Tooth.vue'
import Water from 'vue-material-design-icons/Water.vue'

export const ICON_COMPONENTS = {
	stethoscope: Stethoscope,
	oncology: Ribbon,
	tooth: Tooth,
	child: BabyFaceOutline,
	pregnancy: HumanPregnant,
	eye: Eye,
	ear: EarHearing,
	heart: HeartPulse,
	rehab: Run,
	brain: HeadCog,
	lungs: Lungs,
	allergy: Allergy,
	skin: Lotion,
	stomach: Stomach,
	kidney: Water,
	nutrition: FoodApple,
	foot: FootPrint,
	bone: Bone,
	imaging: RadiologyBox,
	lab: TestTube,
	nursing: Needle,
	pharmacy: Pill,
	hospital: HospitalBox,
}

export function iconName(key) {
	switch (key) {
		case 'oncology': return t('vitalog', 'Oncology')
		case 'tooth': return t('vitalog', 'Teeth')
		case 'child': return t('vitalog', 'Children')
		case 'pregnancy': return t('vitalog', 'Women\'s health')
		case 'eye': return t('vitalog', 'Eyes')
		case 'ear': return t('vitalog', 'Ears, nose and throat')
		case 'heart': return t('vitalog', 'Heart')
		case 'rehab': return t('vitalog', 'Rehabilitation')
		case 'brain': return t('vitalog', 'Mind and nerves')
		case 'lungs': return t('vitalog', 'Lungs')
		case 'allergy': return t('vitalog', 'Allergies')
		case 'skin': return t('vitalog', 'Skin')
		case 'stomach': return t('vitalog', 'Digestion')
		case 'kidney': return t('vitalog', 'Kidneys and urinary tract')
		case 'nutrition': return t('vitalog', 'Nutrition')
		case 'foot': return t('vitalog', 'Feet')
		case 'bone': return t('vitalog', 'Bones and joints')
		case 'imaging': return t('vitalog', 'Imaging')
		case 'lab': return t('vitalog', 'Laboratory')
		case 'nursing': return t('vitalog', 'Nursing and vaccines')
		case 'pharmacy': return t('vitalog', 'Pharmacy')
		case 'hospital': return t('vitalog', 'Hospital and surgery')
		default: return t('vitalog', 'General practice')
	}
}
