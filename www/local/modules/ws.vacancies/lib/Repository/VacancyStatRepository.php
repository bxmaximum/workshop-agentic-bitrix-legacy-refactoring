<?php

declare(strict_types=1);

namespace Ws\Vacancies\Repository;

use Bitrix\Main\Application;
use Ws\Vacancies\Model\VacancyStatTable;

final class VacancyStatRepository
{
	public function getViews(int $vacancyId): int
	{
		if ($vacancyId <= 0)
		{
			return 0;
		}

		$row = VacancyStatTable::query()
			->setSelect(['VIEWS'])
			->where('VACANCY_ID', $vacancyId)
			->setLimit(1)
			->fetch();

		return $row ? (int)$row['VIEWS'] : 0;
	}

	/**
	 * @param list<int> $vacancyIds
	 * @return array<int, int>
	 */
	public function getViewsMap(array $vacancyIds): array
	{
		$ids = array_values(array_unique(array_filter(
			array_map(static fn($id): int => (int)$id, $vacancyIds),
			static fn(int $id): bool => $id > 0,
		)));

		if ($ids === [])
		{
			return [];
		}

		$rows = VacancyStatTable::query()
			->setSelect(['VACANCY_ID', 'VIEWS'])
			->whereIn('VACANCY_ID', $ids)
			->fetchAll();

		$map = [];
		foreach ($rows as $row)
		{
			$map[(int)$row['VACANCY_ID']] = (int)$row['VIEWS'];
		}

		return $map;
	}

	public function incrementViews(int $vacancyId): void
	{
		if ($vacancyId <= 0)
		{
			return;
		}

		$connection = Application::getConnection();
		$helper = $connection->getSqlHelper();
		$now = $helper->getCurrentDateTimeFunction();

		$connection->queryExecute(
			'INSERT INTO ' . VacancyStatTable::getTableName()
			. ' (VACANCY_ID, VIEWS, LAST_VIEW) VALUES ('
			. $vacancyId . ', 1, ' . $now . ')'
			. ' ON DUPLICATE KEY UPDATE VIEWS = VIEWS + 1, LAST_VIEW = ' . $now
		);
	}

	/**
	 * @return array<int, int> vacancyId => views
	 */
	public function getTopPopularIds(int $limit): array
	{
		if ($limit <= 0)
		{
			$limit = 5;
		}

		$rows = VacancyStatTable::query()
			->setSelect(['VACANCY_ID', 'VIEWS'])
			->setOrder(['VIEWS' => 'DESC', 'VACANCY_ID' => 'ASC'])
			->setLimit($limit)
			->fetchAll();

		$result = [];
		foreach ($rows as $row)
		{
			$result[(int)$row['VACANCY_ID']] = (int)$row['VIEWS'];
		}

		return $result;
	}
}
