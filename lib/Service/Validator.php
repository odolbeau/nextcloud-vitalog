<?php

declare(strict_types=1);

namespace OCA\Vitalog\Service;

use InvalidArgumentException;

/**
 * Contrôles d'entrée partagés par les services. Les limites reflètent les colonnes de la base.
 */
final class Validator {
	public const NAME_MAX = 255;
	public const PHONE_MAX = 64;
	public const NOTES_MAX = 10000;
	public const DURATION_MAX = 24 * 60;
	/** Prix en centimes (1 000 000,00). */
	public const PRICE_MAX = 100_000_000;

	/** Texte obligatoire : rogné, non vide, borné. */
	public static function requiredText(string $value, int $max, string $field): string {
		$value = trim($value);
		if ($value === '') {
			throw new InvalidArgumentException($field . ' is required');
		}

		return self::bounded($value, $max, $field);
	}

	/** Texte facultatif : rogné, null s'il est vide, borné. */
	public static function optionalText(?string $value, int $max, string $field): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim($value);

		return $value === '' ? null : self::bounded($value, $max, $field);
	}

	private static function bounded(string $value, int $max, string $field): string {
		if (mb_strlen($value) > $max) {
			throw new InvalidArgumentException($field . ' is too long (max ' . $max . ' characters)');
		}

		return $value;
	}
}
