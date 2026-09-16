<?php

declare(strict_types=1);

namespace Ws\Vacancies\Helper;

/**
 * Ссылки раздела вакансий. ЧПУ нет — только GET-параметры (баг №6).
 */
final class UrlBuilder
{
	public const DEFAULT_BASE_URL = '/vacancies/';

	public static function vacancy(string $code, int $id, string $baseUrl = self::DEFAULT_BASE_URL): string
	{
		if ($code !== '')
		{
			return $baseUrl . '?CODE=' . rawurlencode($code);
		}

		return $baseUrl . '?ID=' . $id;
	}

	public static function section(int $sectionId, string $baseUrl = self::DEFAULT_BASE_URL): string
	{
		return $baseUrl . '?section=' . $sectionId;
	}

	/**
	 * URL списка с текущими фильтрами, часть параметров заменяется.
	 * Пустые значения ('', null, 0, '0') из ссылки выбрасываются.
	 *
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $replace
	 */
	public static function list(string $baseUrl, array $current, array $replace = []): string
	{
		$params = [];
		foreach ($current as $key => $value)
		{
			if (self::isEmpty($value))
			{
				continue;
			}
			$params[$key] = $value;
		}
		foreach ($replace as $key => $value)
		{
			if (self::isEmpty($value))
			{
				unset($params[$key]);
			}
			else
			{
				$params[$key] = $value;
			}
		}

		if ($params === [])
		{
			return $baseUrl;
		}

		return $baseUrl . '?' . http_build_query($params);
	}

	private static function isEmpty(mixed $value): bool
	{
		return $value === '' || $value === null || $value === 0 || $value === '0';
	}
}
