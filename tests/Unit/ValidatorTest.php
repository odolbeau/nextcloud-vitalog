<?php

declare(strict_types=1);

namespace OCA\Vitalog\Tests\Unit;

use InvalidArgumentException;
use OCA\Vitalog\Service\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase {
	public function testRequiredTextIsTrimmed(): void {
		self::assertSame('Dr Martin', Validator::requiredText('  Dr Martin ', 255, 'name'));
	}

	public function testRequiredTextRejectsBlank(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('name is required');
		Validator::requiredText("  \t\n ", 255, 'name');
	}

	public function testRequiredTextRejectsTooLong(): void {
		$this->expectException(InvalidArgumentException::class);
		Validator::requiredText(str_repeat('a', 256), 255, 'name');
	}

	public function testLengthIsCountedInCharactersNotBytes(): void {
		self::assertSame(str_repeat('é', 255), Validator::requiredText(str_repeat('é', 255), 255, 'name'));
	}

	public function testOptionalTextNormalisesEmptyToNull(): void {
		self::assertNull(Validator::optionalText(null, 10, 'notes'));
		self::assertNull(Validator::optionalText('   ', 10, 'notes'));
		self::assertSame('ok', Validator::optionalText(' ok ', 10, 'notes'));
	}

	public function testOptionalTextRejectsTooLong(): void {
		$this->expectException(InvalidArgumentException::class);
		Validator::optionalText(str_repeat('a', 11), 10, 'notes');
	}
}
