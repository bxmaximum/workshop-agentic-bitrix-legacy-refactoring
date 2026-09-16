<?php

declare(strict_types=1);

namespace Ws\Vacancies\Helper;

/**
 * Очистка пользовательского ввода и простые проверки контактов.
 */
final class Text
{
	/**
	 * Однострочное поле: без тегов, с одиночными пробелами, обрезка по длине.
	 */
	public static function clean(mixed $value, int $maxLength = 255): string
	{
		if (is_array($value))
		{
			$value = implode(', ', $value);
		}

		$value = trim(strip_tags((string)$value));
		$value = str_replace(["\r", "\n", "\t"], ' ', $value);
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;

		return self::cut($value, $maxLength);
	}

	/**
	 * Многострочное поле: без тегов, переносы сохраняются.
	 */
	public static function cleanText(mixed $value, int $maxLength = 2000): string
	{
		$value = trim(strip_tags((string)$value));

		return self::cut($value, $maxLength);
	}

	public static function isEmail(string $email): bool
	{
		return (bool)preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $email);
	}

	/**
	 * Телефон: от 10 до 15 цифр, остальные символы игнорируются.
	 */
	public static function isPhone(string $phone): bool
	{
		$digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

		return strlen($digits) >= 10 && strlen($digits) <= 15;
	}

	private static function cut(string $value, int $maxLength): string
	{
		if ($maxLength > 0 && mb_strlen($value) > $maxLength)
		{
			return mb_substr($value, 0, $maxLength);
		}

		return $value;
	}
}
