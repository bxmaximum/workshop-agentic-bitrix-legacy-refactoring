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
	 * Переключение с проверкой, что вакансия существует и активна. CSRF не проверяется (баг №11).
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

		$isFavorite = $this->switchFavorite($vacancyId);

		return $result->setData([
			'favorite' => $isFavorite,
			'count' => count($this->getFavoriteIds()),
		]);
	}

	/**
	 * Добавить/убрать ID в сессии без проверок. Возвращает новое состояние.
	 */
	public function switchFavorite(int $vacancyId): bool
	{
		$fav = $this->getFavoriteIds();

		if (in_array($vacancyId, $fav, true))
		{
			$_SESSION[self::SESSION_KEY] = array_values(array_diff($fav, [$vacancyId]));

			return false;
		}

		$fav[] = $vacancyId;
		$_SESSION[self::SESSION_KEY] = $fav;

		return true;
	}
}
