<?php

declare(strict_types=1);

namespace Ws\Faq\Repository;

use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Error;
use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\ORM\Data\DeleteResult;
use Bitrix\Main\ORM\Data\UpdateResult;
use Bitrix\Main\Result;
use Bitrix\Main\Type\DateTime;
use Ws\Faq\Dto\QuestionDto;
use Ws\Faq\Model\QuestionTable;

final class QuestionRepository
{
	private const ALLOWED_SORT = [
		'ID',
		'SORT',
		'IS_ACTIVE',
		'QUESTION',
		'CATEGORY_ID',
		'USEFUL_COUNT',
		'NOT_USEFUL_COUNT',
		'UPDATED_AT',
		'CREATED_AT',
	];

	/**
	 * @return list<QuestionDto>
	 */
	public function listActive(?int $categoryId = null): array
	{
		$query = QuestionTable::query()
			->setSelect([
				'ID',
				'CATEGORY_ID',
				'QUESTION',
				'ANSWER',
				'SORT',
				'USEFUL_COUNT',
				'NOT_USEFUL_COUNT',
				'IS_ACTIVE',
				'UPDATED_AT',
				'CREATED_AT',
				'CATEGORY_NAME' => 'CATEGORY.NAME',
			])
			->where('IS_ACTIVE', true)
			->setOrder(['SORT' => 'ASC', 'ID' => 'ASC']);

		if ($categoryId !== null)
		{
			$query->where('CATEGORY_ID', $categoryId);
		}

		return array_map(
			static fn(array $row): QuestionDto => QuestionDto::fromRow($row),
			$query->fetchAll()
		);
	}

	public function findById(int $id): ?QuestionDto
	{
		$row = QuestionTable::query()
			->setSelect([
				'ID',
				'CATEGORY_ID',
				'QUESTION',
				'ANSWER',
				'SORT',
				'USEFUL_COUNT',
				'NOT_USEFUL_COUNT',
				'IS_ACTIVE',
				'UPDATED_AT',
				'CREATED_AT',
				'CATEGORY_NAME' => 'CATEGORY.NAME',
			])
			->where('ID', $id)
			->where('IS_ACTIVE', true)
			->fetch();

		return $row ? QuestionDto::fromRow($row) : null;
	}

	public function getById(int $id): ?QuestionDto
	{
		$row = QuestionTable::query()
			->setSelect([
				'ID',
				'CATEGORY_ID',
				'QUESTION',
				'ANSWER',
				'SORT',
				'USEFUL_COUNT',
				'NOT_USEFUL_COUNT',
				'IS_ACTIVE',
				'UPDATED_AT',
				'CREATED_AT',
				'CATEGORY_NAME' => 'CATEGORY.NAME',
			])
			->where('ID', $id)
			->fetch();

		return $row ? QuestionDto::fromRow($row) : null;
	}

	/**
	 * @param array{categoryId?: ?int|false, isActive?: ?bool, search?: string} $filter
	 * @param array<string, string> $order
	 * @return array{items: list<QuestionDto>, total: int}
	 */
	public function listAdmin(array $filter, array $order, int $limit, int $offset): array
	{
		$query = QuestionTable::query()
			->setSelect([
				'ID',
				'CATEGORY_ID',
				'QUESTION',
				'ANSWER',
				'SORT',
				'USEFUL_COUNT',
				'NOT_USEFUL_COUNT',
				'IS_ACTIVE',
				'UPDATED_AT',
				'CREATED_AT',
				'CATEGORY_NAME' => 'CATEGORY.NAME',
			]);

		$this->applyAdminFilter($query, $filter);

		$safeOrder = [];
		foreach ($order as $field => $direction)
		{
			$field = strtoupper((string)$field);
			if (!in_array($field, self::ALLOWED_SORT, true))
			{
				continue;
			}
			$safeOrder[$field] = strtoupper((string)$direction) === 'DESC' ? 'DESC' : 'ASC';
		}
		if ($safeOrder === [])
		{
			$safeOrder = ['SORT' => 'ASC', 'ID' => 'ASC'];
		}

		$countQuery = clone $query;
		$total = (int)$countQuery->queryCountTotal();

		$rows = $query
			->setOrder($safeOrder)
			->setLimit(max(1, $limit))
			->setOffset(max(0, $offset))
			->fetchAll();

		return [
			'items' => array_map(
				static fn(array $row): QuestionDto => QuestionDto::fromRow($row),
				$rows
			),
			'total' => $total,
		];
	}

	/**
	 * @param array{categoryId?: ?int|false, isActive?: ?bool, search?: string} $filter
	 * @return list<int>
	 */
	public function listIds(array $filter = []): array
	{
		$query = QuestionTable::query()->setSelect(['ID']);
		$this->applyAdminFilter($query, $filter);

		return array_map(static fn(array $row): int => (int)$row['ID'], $query->fetchAll());
	}

	/**
	 * @param array{
	 *     QUESTION: string,
	 *     ANSWER: string,
	 *     CATEGORY_ID?: ?int,
	 *     SORT?: int,
	 *     IS_ACTIVE?: bool
	 * } $fields
	 */
	public function add(array $fields): AddResult
	{
		$now = new DateTime();

		return QuestionTable::add([
			'QUESTION' => (string)$fields['QUESTION'],
			'ANSWER' => (string)$fields['ANSWER'],
			'CATEGORY_ID' => $fields['CATEGORY_ID'] ?? null,
			'SORT' => (int)($fields['SORT'] ?? 500),
			'IS_ACTIVE' => (bool)($fields['IS_ACTIVE'] ?? true),
			'USEFUL_COUNT' => 0,
			'NOT_USEFUL_COUNT' => 0,
			'CREATED_AT' => $now,
			'UPDATED_AT' => $now,
		]);
	}

	/**
	 * @param array{
	 *     QUESTION?: string,
	 *     ANSWER?: string,
	 *     CATEGORY_ID?: ?int,
	 *     SORT?: int,
	 *     IS_ACTIVE?: bool
	 * } $fields
	 */
	public function update(int $id, array $fields): UpdateResult
	{
		$data = ['UPDATED_AT' => new DateTime()];

		if (array_key_exists('QUESTION', $fields))
		{
			$data['QUESTION'] = (string)$fields['QUESTION'];
		}
		if (array_key_exists('ANSWER', $fields))
		{
			$data['ANSWER'] = (string)$fields['ANSWER'];
		}
		if (array_key_exists('CATEGORY_ID', $fields))
		{
			$data['CATEGORY_ID'] = $fields['CATEGORY_ID'];
		}
		if (array_key_exists('SORT', $fields))
		{
			$data['SORT'] = (int)$fields['SORT'];
		}
		if (array_key_exists('IS_ACTIVE', $fields))
		{
			$data['IS_ACTIVE'] = (bool)$fields['IS_ACTIVE'];
		}

		return QuestionTable::update($id, $data);
	}

	public function delete(int $id): DeleteResult
	{
		return QuestionTable::delete($id);
	}

	public function setActive(int $id, bool $isActive): Result
	{
		$result = new Result();
		$updateResult = QuestionTable::update($id, [
			'IS_ACTIVE' => $isActive,
			'UPDATED_AT' => new DateTime(),
		]);
		if (!$updateResult->isSuccess())
		{
			$result->addErrors($updateResult->getErrors());
			if ($result->getErrorCollection()->isEmpty())
			{
				$result->addError(new Error('Не удалось изменить активность вопроса', 'QUESTION_ACTIVE_FAILED'));
			}
		}

		return $result;
	}

	public function clearCategory(int $categoryId): Result
	{
		$result = new Result();

		$rows = QuestionTable::query()
			->setSelect(['ID'])
			->where('CATEGORY_ID', $categoryId)
			->fetchAll();

		foreach ($rows as $row)
		{
			$updateResult = QuestionTable::update((int)$row['ID'], [
				'CATEGORY_ID' => null,
				'UPDATED_AT' => new DateTime(),
			]);
			if (!$updateResult->isSuccess())
			{
				$result->addErrors($updateResult->getErrors());
			}
		}

		return $result;
	}

	public function incrementCounter(int $id, bool $isUseful): Result
	{
		$result = new Result();
		$field = $isUseful ? 'USEFUL_COUNT' : 'NOT_USEFUL_COUNT';

		$updateResult = QuestionTable::update($id, [
			$field => new SqlExpression('?# + ?i', $field, 1),
			'UPDATED_AT' => new DateTime(),
		]);

		if (!$updateResult->isSuccess())
		{
			$result->addErrors($updateResult->getErrors());
		}

		return $result;
	}

	public function switchCounters(int $id, bool $toUseful): Result
	{
		$result = new Result();

		if ($toUseful)
		{
			$fields = [
				'USEFUL_COUNT' => new SqlExpression('?# + ?i', 'USEFUL_COUNT', 1),
				'NOT_USEFUL_COUNT' => new SqlExpression('?# - ?i', 'NOT_USEFUL_COUNT', 1),
			];
		}
		else
		{
			$fields = [
				'USEFUL_COUNT' => new SqlExpression('?# - ?i', 'USEFUL_COUNT', 1),
				'NOT_USEFUL_COUNT' => new SqlExpression('?# + ?i', 'NOT_USEFUL_COUNT', 1),
			];
		}

		$fields['UPDATED_AT'] = new DateTime();

		$updateResult = QuestionTable::update($id, $fields);
		if (!$updateResult->isSuccess())
		{
			$result->addErrors($updateResult->getErrors());
			if ($result->getErrorCollection()->isEmpty())
			{
				$result->addError(new Error('Не удалось обновить счётчики', 'COUNTER_UPDATE_FAILED'));
			}
		}

		return $result;
	}

	/**
	 * @param \Bitrix\Main\ORM\Query\Query $query
	 * @param array{categoryId?: ?int|false, isActive?: ?bool, search?: string} $filter
	 */
	private function applyAdminFilter(object $query, array $filter): void
	{
		if (array_key_exists('categoryId', $filter))
		{
			$categoryId = $filter['categoryId'];
			if ($categoryId === false)
			{
				$query->whereNull('CATEGORY_ID');
			}
			elseif ($categoryId !== null)
			{
				$query->where('CATEGORY_ID', (int)$categoryId);
			}
		}

		if (array_key_exists('isActive', $filter) && $filter['isActive'] !== null)
		{
			$query->where('IS_ACTIVE', (bool)$filter['isActive']);
		}

		$search = trim((string)($filter['search'] ?? ''));
		if ($search !== '')
		{
			$query->whereLike('QUESTION', '%' . $search . '%');
		}
	}
}
