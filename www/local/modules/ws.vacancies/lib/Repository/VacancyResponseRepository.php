<?php

declare(strict_types=1);

namespace Ws\Vacancies\Repository;

use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\Result;
use Bitrix\Main\Type\DateTime;
use Ws\Vacancies\Dto\VacancyResponseInputDto;
use Ws\Vacancies\Model\VacancyResponseTable;

final class VacancyResponseRepository
{
	public function create(VacancyResponseInputDto $dto): Result
	{
		return VacancyResponseTable::add([
			'VACANCY_ID' => $dto->vacancyId,
			'USER_ID' => $dto->userId,
			'NAME' => $dto->name,
			'EMAIL' => $dto->email,
			'PHONE' => $dto->phone,
			'MESSAGE' => $dto->message,
			'IP' => mb_substr($dto->ip, 0, 45),
			'STATUS' => 'NEW',
			'CREATED' => new DateTime(),
		]);
	}

	public function getValidCountByVacancyId(int $vacancyId): int
	{
		if ($vacancyId <= 0)
		{
			return 0;
		}

		$row = VacancyResponseTable::query()
			->addSelect(new ExpressionField('CNT', 'COUNT(*)'))
			->where('VACANCY_ID', $vacancyId)
			->whereNot('STATUS', 'SPAM')
			->fetch();

		return $row ? (int)$row['CNT'] : 0;
	}

	/**
	 * @param list<int> $vacancyIds
	 * @return array<int, int>
	 */
	public function getValidCountMap(array $vacancyIds): array
	{
		$ids = array_values(array_unique(array_filter(
			array_map(static fn($id): int => (int)$id, $vacancyIds),
			static fn(int $id): bool => $id > 0,
		)));

		if ($ids === [])
		{
			return [];
		}

		$rows = VacancyResponseTable::query()
			->setSelect(['VACANCY_ID'])
			->addSelect(new ExpressionField('CNT', 'COUNT(*)'))
			->whereIn('VACANCY_ID', $ids)
			->whereNot('STATUS', 'SPAM')
			->setGroup(['VACANCY_ID'])
			->fetchAll();

		$map = [];
		foreach ($rows as $row)
		{
			$map[(int)$row['VACANCY_ID']] = (int)$row['CNT'];
		}

		return $map;
	}

	/**
	 * Отклики за 7 дней без фильтра по SPAM (баг №2 для детальной страницы).
	 */
	public function getWeekCountIncludingSpam(int $vacancyId): int
	{
		if ($vacancyId <= 0)
		{
			return 0;
		}

		$since = (new DateTime())->add('-7 days');

		$row = VacancyResponseTable::query()
			->addSelect(new ExpressionField('CNT', 'COUNT(*)'))
			->where('VACANCY_ID', $vacancyId)
			->where('CREATED', '>=', $since)
			->fetch();

		return $row ? (int)$row['CNT'] : 0;
	}

	/**
	 * @return array{total: int, vacancies: int}
	 */
	public function getWeekSummary(): array
	{
		$since = (new DateTime())->add('-7 days');

		$row = VacancyResponseTable::query()
			->addSelect(new ExpressionField('CNT', 'COUNT(*)'))
			->addSelect(new ExpressionField('VAC', 'COUNT(DISTINCT %s)', ['VACANCY_ID']))
			->where('CREATED', '>=', $since)
			->whereNot('STATUS', 'SPAM')
			->fetch();

		return [
			'total' => $row ? (int)$row['CNT'] : 0,
			'vacancies' => $row ? (int)$row['VAC'] : 0,
		];
	}
}
