<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use OCA\Vitalog\Db\Place;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Service\EventMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventMatcherTest extends TestCase {
	/** Noms tels qu'on les saisit : civilités, majuscules, noms composés, prénom et nom inversés, espaces en trop. */
	private const NAMES = [
		1 => 'Léa Fontaine-Morel',
		2 => 'Dr Julie BERTRAND',
		3 => 'Dr Garnier Thomas',
		4 => 'Dr  Diego Sanchez Ortega',
		5 => 'Dr Gaël Roussel',
		6 => 'Dr Marie-Hélène Dubreuil',
		7 => 'Dr Nina Colin',
		8 => 'Dr Paul Martin',
		9 => 'Dr Paul RENAUD',
		10 => 'Vidal',
	];

	/** @return list<Practitioner> */
	private static function practitioners(): array {
		$practitioners = [];
		foreach (self::NAMES as $id => $name) {
			$practitioner = new Practitioner();
			$practitioner->setId($id);
			$practitioner->setName($name);
			$practitioners[] = $practitioner;
		}

		return $practitioners;
	}

	/** @return iterable<string, array{string, ?int}> */
	public static function summaries(): iterable {
		yield 'Doctolib, nom d\'usage plus court' => ['Rendez-vous chez Mme Léa Fontaine', 1];
		yield 'majuscules et civilité' => ['Rendez-vous chez Dr Julie Bertrand', 2];
		yield 'prénom et nom inversés' => ['RDV Dr Thomas Garnier', 3];
		yield 'nom composé, sans accent' => ['Consultation Dr Diego Sanchez', 4];
		yield 'accents absents du titre' => ['Rendez-vous chez Dr Gael Roussel', 5];
		yield 'prénom composé' => ['Dr Marie Helene DUBREUIL', 6];
		yield 'motif et nom' => ['Contrôle annuel avec Nina Colin', 7];
		yield 'nom de famille seul : pas assez' => ['Rendez-vous chez Dr Colin', null];
		yield 'prénom commun à deux praticiens' => ['Rendez-vous chez Dr Paul', null];
		yield 'nom d\'un seul mot' => ['Rendez-vous chez Dr Vidal', 10];
		yield 'personne' => ['Prise de sang', null];
	}

	#[DataProvider('summaries')]
	public function testFindsThePractitionerNamedInTheSummary(string $summary, ?int $expected): void {
		self::assertSame($expected, EventMatcher::findPractitioner(self::practitioners(), $summary)?->getId());
	}

	public function testATieBetweenTwoPractitionersPicksNone(): void {
		$twins = array_map(static function (int $id): Practitioner {
			$practitioner = new Practitioner();
			$practitioner->setId($id);
			$practitioner->setName('Dr Claire Moreau');
			return $practitioner;
		}, [1, 2]);

		self::assertNull(EventMatcher::findPractitioner($twins, 'Rendez-vous chez Dr Claire Moreau'));
	}

	public function testFindsAPlaceByNameOrAddress(): void {
		$places = [];
		foreach ([1 => ['Cabinet Martin', null], 2 => ['Cabinet médical', '12 rue des Lilas, 75011 Paris']] as $id => [$name, $address]) {
			$place = new Place();
			$place->setId($id);
			$place->setName($name);
			$place->setAddress($address);
			$places[] = $place;
		}

		self::assertSame(1, EventMatcher::findPlace($places, 'cabinet martin'));
		self::assertSame(1, EventMatcher::findPlace($places, 'Cabinet Martin, 1 rue de la Paix'));
		self::assertSame(2, EventMatcher::findPlace($places, '12 Rue des Lilas, 75011 Paris'));
		self::assertSame(2, EventMatcher::findPlace($places, '12 rue des Lilas'));
		self::assertNull(EventMatcher::findPlace($places, '3 avenue Foch, Paris'));
		self::assertNull(EventMatcher::findPlace($places, null));
	}
}
