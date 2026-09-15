<?php

declare(strict_types=1);

namespace Ws\Faq\Repository;

use Bitrix\Main\Error;
use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\ORM\Data\DeleteResult;
use Bitrix\Main\ORM\Data\UpdateResult;
use Bitrix\Main\Result;
use Bitrix\Main\Type\DateTime;
use Ws\Faq\Dto\CategoryDto;
use Ws\Faq\Model\CategoryTable;

final class CategoryRepository
{
	private const ALLOWED_SORT = ['ID', 'NAME', 'CODE', 'SORT', 'IS_ACTIVE', 'CREATED_AT'];

	/**
	 * @return list<CategoryDto>
	 */
	public function listActive(): array
	{
		$rows = CategoryTable::query()
			->setSelect(['ID', 'CODE', 'NAME', 'SORT', 'IS_ACTIVE'])
			->where('IS_ACTIVE', true)
			->setOrder(['SORT' => 'ASC', 'ID' => 'ASC'])
			->fetchAll();

		return array_map([$this, 'mapRow'], $rows);
	}

	/**
	 * @return list<CategoryDto>
	 */
	public function listAll(): array
	{
		$rows = CategoryTable::query()
			->setSelect(['ID', 'CODE', 'NAME', 'SORT', 'IS_ACTIVE'])
			->setOrder(['SORT' => 'ASC', 'ID' => 'ASC'])
			->fetchAll();

		return array_map([$this, 'mapRow'], $rows);
	}

	/**
	 * @param array{isActive?: ?bool, search?: string} $filter
	 * @param array<string, string> $order
	 * @return array{items: list<CategoryDto>, total: int}
	 */
	public function listAdmin(array $filter, array $order, int $limit, int $offset): array
	{
		$query = CategoryTable::query()
			->setSelect(['ID', 'CODE', 'NAME', 'SORT', 'IS_ACTIVE', 'CREATED_AT']);

		if (array_key_exists('isActive', $filter) && $filter['isActive'] !== null)
		{
			$query->where('IS_ACTIVE', (bool)$filter['isActive']);
		}

		$search = trim((string)($filter['search'] ?? ''));
		if ($search !== '')
		{
			$query->whereLike('NAME', '%' . $search . '%');
		}

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
			'items' => array_map([$this, 'mapRow'], $rows),
			'total' => $total,
		];
	}

	/**
	 * @return list<int>
	 */
	public function listIds(array $filter = []): array
	{
		$query = CategoryTable::query()->setSelect(['ID']);

		if (array_key_exists('isActive', $filter) && $filter['isActive'] !== null)
		{
			$query->where('IS_ACTIVE', (bool)$filter['isActive']);
		}

		$search = trim((string)($filter['search'] ?? ''));
		if ($search !== '')
		{
			$query->whereLike('NAME', '%' . $search . '%');
		}

		return array_map(static fn(array $row): int => (int)$row['ID'], $query->fetchAll());
	}

	public function findById(int $id): ?CategoryDto
	{
		$row = CategoryTable::query()
			->setSelect(['ID', 'CODE', 'NAME', 'SORT', 'IS_ACTIVE'])
			->where('ID', $id)
			->fetch();

		return $row ? $this->mapRow($row) : null;
	}

	/**
	 * @param array{NAME: string, CODE?: ?string, SORT?: int, IS_ACTIVE?: bool} $fields
	 */
	public function add(array $fields): AddResult
	{
		return CategoryTable::add([
			'NAME' => (string)$fields['NAME'],
			'CODE' => $fields['CODE'] ?? null,
			'SORT' => (int)($fields['SORT'] ?? 500),
			'IS_ACTIVE' => (bool)($fields['IS_ACTIVE'] ?? true),
			'CREATED_AT' => new DateTime(),
		]);
	}

	/**
	 * @param array{NAME?: string, CODE?: ?string, SORT?: int, IS_ACTIVE?: bool} $fields
	 */
	public function update(int $id, array $fields): UpdateResult
	{
		$data = [];
		if (array_key_exists('NAME', $fields))
		{
			$data['NAME'] = (string)$fields['NAME'];
		}
		if (array_key_exists('CODE', $fields))
		{
			$data['CODE'] = $fields['CODE'];
		}
		if (array_key_exists('SORT', $fields))
		{
			$data['SORT'] = (int)$fields['SORT'];
		}
		if (array_key_exists('IS_ACTIVE', $fields))
		{
			$data['IS_ACTIVE'] = (bool)$fields['IS_ACTIVE'];
		}

		return CategoryTable::update($id, $data);
	}

	public function delete(int $id): DeleteResult
	{
		return CategoryTable::delete($id);
	}

	public function setActive(int $id, bool $isActive): Result
	{
		$result = new Result();
		$updateResult = CategoryTable::update($id, ['IS_ACTIVE' => $isActive]);
		if (!$updateResult->isSuccess())
		{
			$result->addErrors($updateResult->getErrors());
			if ($result->getErrorCollection()->isEmpty())
			{
				$result->addError(new Error('Не удалось изменить активность категории', 'CATEGORY_ACTIVE_FAILED'));
			}
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function mapRow(array $row): CategoryDto
	{
		return new CategoryDto(
			id: (int)$row['ID'],
			code: isset($row['CODE']) && $row['CODE'] !== '' && $row['CODE'] !== null
				? (string)$row['CODE']
				: null,
			name: (string)$row['NAME'],
			sort: (int)$row['SORT'],
			isActive: $row['IS_ACTIVE'] === true || $row['IS_ACTIVE'] === 'Y',
		);
	}
}
