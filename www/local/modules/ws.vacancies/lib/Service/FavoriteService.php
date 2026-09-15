<?php

declare(strict_types=1);

namespace Ws\Vacancies\Service;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Ws\Vacancies\Repository\VacancyRepository;

final class FavoriteService
{
	private const SESSION_KEY = 'LEGACY_VACANCY_FAV';

	public function __construct(
		private readonly VacancyRepository $vacancies,
	) {
	}

	/**
	 * @return list<int>
	 */
	public function getFavoriteIds(): array
	{
		if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY]))
		{
			$_SESSION[self::SESSION_KEY] = [];
		}

		return array_values(array_map(static fn($id): int => (int)$id, $_SESSION[self::SESSION_KEY]));
	}

	public function isFavorite(int $vacancyId): bool
	{
		return in_array($vacancyId, $this->getFavoriteIds(), true);
	}

	/**
	 * Добавление/удаление из сессии. CSRF не проверяется (баг №11).
	 */
	public function toggle(int $vacancyId): Result
	{
		$result = new Result();

		if ($vacancyId <= 0)
		{
			return $result->addError(new Error('Не указана вакансия', 'VACANCY_ID_REQUIRED'));
		}

		if (!$this->vacancies->existsActive($vacancyId))
		{
			return $result->addError(new Error('Вакансия не найдена', 'VACANCY_NOT_FOUND'));
		}

		$fav = $this->getFavoriteIds();
		$isFavorite = in_array($vacancyId, $fav, true);

		if ($isFavorite)
		{
			$fav = array_values(array_diff($fav, [$vacancyId]));
			$isFavorite = false;
		}
		else
		{
			$fav[] = $vacancyId;
			$isFavorite = true;
		}

		$_SESSION[self::SESSION_KEY] = $fav;

		return $result->setData([
			'favorite' => $isFavorite,
			'count' => count($fav),
		]);
	}
}
