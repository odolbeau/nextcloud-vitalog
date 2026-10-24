<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use InvalidArgumentException;
use OCA\Vitalog\Contacts\ContactException;
use OCA\Vitalog\Contacts\ContactGateway;
use OCA\Vitalog\Db\AppointmentMapper;
use OCA\Vitalog\Db\PlaceMapper;
use OCA\Vitalog\Db\Practitioner;
use OCA\Vitalog\Db\PractitionerMapper;
use OCA\Vitalog\Service\PractitionerService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PractitionerContactTest extends TestCase {
	private PractitionerMapper&MockObject $mapper;
	private AppointmentMapper&MockObject $appointments;
	private ContactGateway&MockObject $contacts;
	private PractitionerService $service;

	private const CONTACT = ['uid' => 'uid-1', 'book' => 'contacts', 'name' => 'Dr Spike', 'specialty' => 'Cardiologue', 'phone' => '0102030405'];

	protected function setUp(): void {
		$this->mapper = $this->createMock(PractitionerMapper::class);
		$this->mapper->method('insert')->willReturnArgument(0);
		$this->mapper->method('update')->willReturnArgument(0);
		$this->appointments = $this->createMock(AppointmentMapper::class);
		$this->contacts = $this->createMock(ContactGateway::class);
		$this->contacts->method('isAvailable')->willReturn(true);
		$this->contacts->method('url')->willReturn('/contacts/uid-1');
		$this->service = new PractitionerService($this->mapper, $this->appointments, $this->contacts, $this->createMock(PlaceMapper::class));
	}

	private function practitioner(?string $contactUid = null): Practitioner {
		$practitioner = new Practitioner();
		$practitioner->setId(5);
		$practitioner->setUserId('alice');
		$practitioner->setName('Ancien nom');
		$practitioner->setContactUid($contactUid);
		$this->mapper->method('find')->willReturn($practitioner);

		return $practitioner;
	}

	public function testCreateLinkedCopiesTheContact(): void {
		$this->contacts->method('find')->with('uid-1')->willReturn(self::CONTACT);

		$practitioner = $this->service->create('alice', 'Ignoré', null, null, 'uid-1');

		self::assertSame('Dr Spike', $practitioner->getName());
		self::assertSame('Cardiologue', $practitioner->getSpecialty());
		self::assertSame('0102030405', $practitioner->getPhone());
		self::assertSame('uid-1', $practitioner->getContactUid());
		self::assertSame('contacts', $practitioner->getContactBook());
		self::assertSame(Practitioner::CONTACT_LINKED, $practitioner->getContactStatus());
	}

	public function testCreateWithUnknownContactIsRejected(): void {
		$this->contacts->method('find')->willReturn(null);
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->service->create('alice', 'X', null, null, 'nope');
	}

	public function testReadingRefreshesTheLocalCopy(): void {
		$practitioner = $this->practitioner('uid-1');
		$this->mapper->method('findAll')->willReturn([$practitioner]);
		$this->contacts->method('find')->willReturn(self::CONTACT);

		[$read] = $this->service->findAll('alice');

		self::assertSame('Dr Spike', $read->getName());
		self::assertSame(Practitioner::CONTACT_LINKED, $read->getContactStatus());
		self::assertSame('/contacts/uid-1', $read->jsonSerialize()['contactUrl']);
	}

	public function testMissingContactKeepsTheLocalCopy(): void {
		$practitioner = $this->practitioner('uid-1');
		$this->contacts->method('find')->willReturn(null);
		$this->mapper->expects(self::never())->method('update');

		$read = $this->service->find(5, 'alice');

		self::assertSame('Ancien nom', $read->getName());
		self::assertSame('uid-1', $read->getContactUid());
		self::assertSame(Practitioner::CONTACT_MISSING, $practitioner->getContactStatus());
	}

	public function testUnavailableContactsAppIsNotReportedAsMissing(): void {
		$contacts = $this->createMock(ContactGateway::class);
		$contacts->method('isAvailable')->willReturn(false);
		$contacts->expects(self::never())->method('find');
		$service = new PractitionerService($this->mapper, $this->appointments, $contacts, $this->createMock(PlaceMapper::class));
		$this->practitioner('uid-1');

		self::assertSame(Practitioner::CONTACT_UNAVAILABLE, $service->find(5, 'alice')->getContactStatus());
	}

	public function testLinkedFieldsAreLocked(): void {
		$this->practitioner('uid-1');
		$this->contacts->method('find')->willReturn(self::CONTACT);

		$updated = $this->service->update(5, 'alice', 'Autre nom', 'Autre', '999', 'uid-1', 7);

		self::assertSame('Dr Spike', $updated->getName());
		self::assertSame('Cardiologue', $updated->getSpecialty());
		self::assertSame('0102030405', $updated->getPhone());
		// le lieu habituel est propre à Vitalog : il reste modifiable
		self::assertSame(7, $updated->getPlaceId());
	}

	public function testUnlinkingKeepsTheDataAndUnlocksIt(): void {
		$practitioner = $this->practitioner('uid-1');
		$practitioner->setContactBook('contacts');

		$updated = $this->service->update(5, 'alice', 'Nom libre', null, null, null);

		self::assertSame('Nom libre', $updated->getName());
		self::assertNull($updated->getContactUid());
		self::assertNull($updated->getContactBook());
		self::assertSame(Practitioner::CONTACT_NONE, $updated->getContactStatus());
	}

	public function testStaysEditableWhenTheLinkedContactIsMissing(): void {
		$this->practitioner('uid-1');
		$this->contacts->method('find')->willReturn(null);

		$updated = $this->service->update(5, 'alice', 'Nom corrigé', null, null, 'uid-1');

		self::assertSame('Nom corrigé', $updated->getName());
		self::assertSame('uid-1', $updated->getContactUid());
	}

	public function testCreateContactPushesThePractitionerAndLinksIt(): void {
		$practitioner = $this->practitioner();
		$practitioner->setSpecialty('ORL');
		$this->contacts->expects(self::once())->method('create')->with('Ancien nom', 'ORL', null)->willReturn(self::CONTACT);
		$this->contacts->method('find')->willReturn(self::CONTACT);

		$linked = $this->service->createContact(5, 'alice');

		self::assertSame('uid-1', $linked->getContactUid());
		self::assertSame(Practitioner::CONTACT_LINKED, $linked->getContactStatus());
	}

	public function testCreateContactFailureAndDoubleLinkAreRejected(): void {
		$this->practitioner();
		$this->contacts->method('create')->willThrowException(new ContactException('No writable address book'));
		try {
			$this->service->createContact(5, 'alice');
			self::fail('expected failure');
		} catch (InvalidArgumentException $e) {
			self::assertSame('No writable address book', $e->getMessage());
		}

		$mapper = $this->createMock(PractitionerMapper::class);
		$linked = new Practitioner();
		$linked->setContactUid('uid-1');
		$mapper->method('find')->willReturn($linked);
		$this->expectException(InvalidArgumentException::class);
		(new PractitionerService($mapper, $this->appointments, $this->contacts, $this->createMock(PlaceMapper::class)))->createContact(5, 'alice');
	}
}
