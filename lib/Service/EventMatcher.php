<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use OCA\Vitalog\Db\Appointment;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;

/**
 * Retrouve, pour un événement venu d'ailleurs (fichier .ics, calendrier), le praticien, le lieu et le motif parmi les
 * données de l'utilisateur. Les titres des plateformes de rendez-vous ne font souvent que nommer le praticien
 * (« Rendez-vous chez Mme Léa Fontaine ») : le nom est alors reconnu mot à mot, sans accents ni casse ni civilité.
 *
 * @psalm-type Match = array{practitionerId: ?int, placeId: ?int, reason: ?string}
 */
class EventMatcher {
	/** Mots sans valeur pour reconnaître un nom ou un motif : civilités, formules des titres d'événements. */
	private const NOISE = [
		'm', 'mr', 'mme', 'mlle', 'mmes', 'monsieur', 'madame', 'mademoiselle', 'dr', 'docteur', 'docteure', 'pr', 'professeur',
		'professeure', 'me', 'rendez', 'vous', 'rdv', 'consultation', 'chez', 'avec', 'aupres', 'de', 'du', 'des', 'le', 'la',
		'les', 'l', 'd', 'a', 'au', 'et', 'appointment', 'with', 'at', 'doctor', 'mrs', 'ms', 'miss', 'prof', 'the', 'via',
		'doctolib',
	];
	private const ACCENTS = [
		'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
		'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
		'õ' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
		'ß' => 'ss',
	];

	public function __construct(
		private PractitionerMapper $practitioners,
		private PlaceMapper $places,
		private AppointmentMapper $appointments,
	) {
	}

	/**
	 * Praticien reconnu dans le titre ; lieu reconnu dans la localisation, sinon le lieu habituel du praticien ; motif :
	 * le titre, sauf s'il ne fait que nommer le praticien (c'est alors le motif du dernier rendez-vous avec lui).
	 *
	 * @return Match
	 */
	public function match(string $userId, ?string $summary, ?string $location): array {
		$practitioner = self::findPractitioner($this->practitioners->findAll($userId), $summary);
		$placeId = self::findPlace($this->places->findAll($userId), $location) ?? $practitioner?->getPlaceId();
		$reason = $summary;
		if ($practitioner !== null && self::onlyNames($summary, $practitioner)) {
			$reason = $this->lastReason($practitioner, $userId);
		}

		return ['practitionerId' => $practitioner?->getId(), 'placeId' => $placeId, 'reason' => $reason];
	}

	/**
	 * Le praticien dont le plus de mots du nom figurent dans le texte : au moins deux (ou son seul mot). À égalité entre
	 * plusieurs praticiens, aucun n'est retenu.
	 *
	 * @param list<Practitioner> $practitioners
	 */
	public static function findPractitioner(array $practitioners, ?string $text): ?Practitioner {
		$words = self::words($text ?? '');
		if ($words === []) {
			return null;
		}
		$best = null;
		$bestScore = 0;
		$tie = false;
		foreach ($practitioners as $practitioner) {
			$name = self::words($practitioner->getName());
			if ($name === []) {
				continue;
			}
			$score = count(array_intersect($name, $words));
			if ($score < min(2, count($name))) {
				continue;
			}
			if ($score > $bestScore) {
				[$best, $bestScore, $tie] = [$practitioner, $score, false];
			} elseif ($score === $bestScore) {
				$tie = true;
			}
		}

		return $tie ? null : $best;
	}

	/**
	 * Un lieu dont le nom est la localisation ou son premier segment (« Nom, adresse »), ou dont l'adresse est la même.
	 *
	 * @param list<Place> $places
	 */
	public static function findPlace(array $places, ?string $location): ?int {
		if ($location === null || trim($location) === '') {
			return null;
		}
		$whole = self::flat($location);
		$candidates = [$whole, self::flat(explode(',', $location)[0])];
		foreach ($places as $place) {
			if (in_array(self::flat($place->getName()), $candidates, true)) {
				return $place->getId();
			}
		}
		foreach ($places as $place) {
			$address = self::flat($place->getAddress() ?? '');
			if ($address !== '' && ($address === $whole || str_contains($whole, $address) || str_contains($address, $whole))) {
				return $place->getId();
			}
		}

		return null;
	}

	/** Le titre ne contient rien d'autre que le nom du praticien et des mots sans valeur. */
	private static function onlyNames(?string $summary, Practitioner $practitioner): bool {
		return array_diff(self::words($summary ?? ''), self::words($practitioner->getName())) === [];
	}

	private function lastReason(Practitioner $practitioner, string $userId): ?string {
		$appointments = $this->appointments->findByPractitioner($practitioner->getId(), $userId);
		usort($appointments, static fn (Appointment $a, Appointment $b): int => $b->getStartsAt() <=> $a->getStartsAt());
		foreach ($appointments as $appointment) {
			if (($appointment->getReason() ?? '') !== '') {
				return $appointment->getReason();
			}
		}

		return null;
	}

	/**
	 * Mots significatifs d'un texte : minuscules, sans accents, découpés sur tout ce qui n'est pas une lettre ou un chiffre
	 * (« Fontaine-Morel » donne « fontaine » et « morel »), sans les mots sans valeur.
	 *
	 * @return list<string>
	 */
	private static function words(string $text): array {
		$words = preg_split('/[^a-z0-9]+/', self::plain($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

		return array_values(array_unique(array_diff($words, self::NOISE)));
	}

	/** Texte comparable : minuscules, sans accents, ponctuation et espaces réduits à une espace. */
	private static function flat(string $text): string {
		return trim((string)preg_replace('/[^a-z0-9]+/', ' ', self::plain($text)));
	}

	private static function plain(string $text): string {
		return strtr(mb_strtolower($text), self::ACCENTS);
	}
}
