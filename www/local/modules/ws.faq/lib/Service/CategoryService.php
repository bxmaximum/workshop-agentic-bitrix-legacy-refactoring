<?php

declare(strict_types=1);

namespace Ws\Faq\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Ws\Faq\Dto\CategoryDto;
use Ws\Faq\Repository\CategoryRepository;
use Ws\Faq\Repository\QuestionRepository;

final class CategoryService
{
	private const CACHE_TTL = 3600;
	private const CACHE_DIR = '/ws_faq/categories';
	private const CACHE_TAG = 'ws_faq_list';

	public function __construct(
		private readonly CategoryRepository $categories,
		private readonly QuestionRepository $questions,
	) {
	}

	/**
	 * @return list<CategoryDto>
	 */
	public function listActive(): array
	{
		$cache = Cache::createInstance();
		$cacheId = 'ws_faq_categories_active';

		if ($cache->initCache(self::CACHE_TTL, $cacheId, self::CACHE_DIR))
		{
			$rows = $cache->getVars();

			return $this->mapRows(is_array($rows) ? $rows : []);
		}

		if ($cache->startDataCache())
		{
			$taggedCache = Application::getInstance()->getTaggedCache();
			$taggedCache->startTagCache(self::CACHE_DIR);
			$taggedCache->registerTag(self::CACHE_TAG);

			$items = $this->categories->listActive();
			$rows = array_map(static fn(CategoryDto $dto): array => [
				'ID' => $dto->id,
				'CODE' => $dto->code,
				'NAME' => $dto->name,
				'SORT' => $dto->sort,
				'IS_ACTIVE' => $dto->isActive,
			], $items);

			$taggedCache->endTagCache();
			$cache->endDataCache($rows);

			return $items;
		}

		return [];
	}

	/**
	 * @return list<CategoryDto>
	 */
	public function listAll(): array
	{
		return $this->categories->listAll();
	}

	/**
	 * @param array{isActive?: ?bool, search?: string} $filter
	 * @param array<string, string> $order
	 * @return array{items: list<CategoryDto>, total: int}
	 */
	public function listAdmin(array $filter, array $order, int $limit, int $offset): array
	{
		return $this->categories->listAdmin($filter, $order, $limit, $offset);
	}

	/**
	 * @param array{isActive?: ?bool, search?: string} $filter
	 * @return list<int>
	 */
	public function listIds(array $filter = []): array
	{
		return $this->categories->listIds($filter);
	}

	public function getById(int $id): ?CategoryDto
	{
		return $this->categories->findById($id);
	}

	/**
	 * @param array{NAME: string, CODE?: ?string, SORT?: int, IS_ACTIVE?: bool} $fields
	 */
	public function save(?int $id, array $fields): Result
	{
		$result = new Result();

		$name = trim((string)($fields['NAME'] ?? ''));
		if ($name === '')
		{
			$result->addError(new Error('Укажите название категории', 'CATEGORY_NAME_REQUIRED'));

			return $result;
		}

		$code = $fields['CODE'] ?? null;
		if ($code !== null)
		{
			$code = trim((string)$code);
			$code = $code === '' ? null : $code;
		}

		$payload = [
			'NAME' => $name,
			'CODE' => $code,
			'SORT' => (int)($fields['SORT'] ?? 500),
			'IS_ACTIVE' => (bool)($fields['IS_ACTIVE'] ?? true),
		];

		if ($id === null || $id <= 0)
		{
			$addResult = $this->categories->add($payload);
			if (!$addResult->isSuccess())
			{
				$result->addErrors($addResult->getErrors());

				return $result;
			}

			$this->clearCache();
			$result->setData(['id' => (int)$addResult->getId()]);

			return $result;
		}

		if ($this->categories->findById($id) === null)
		{
			$result->addError(new Error('Категория не найдена', 'CATEGORY_NOT_FOUND'));

			return $result;
		}

		$updateResult = $this->categories->update($id, $payload);
		if (!$updateResult->isSuccess())
		{
			$result->addErrors($updateResult->getErrors());

			return $result;
		}

		$this->clearCache();
		$result->setData(['id' => $id]);

		return $result;
	}

	public function setActive(int $id, bool $isActive): Result
	{
		$result = $this->categories->setActive($id, $isActive);
		if ($result->isSuccess())
		{
			$this->clearCache();
		}

		return $result;
	}

	public function delete(int $id): Result
	{
		$result = new Result();

		if ($this->categories->findById($id) === null)
		{
			$result->addError(new Error('Категория не найдена', 'CATEGORY_NOT_FOUND'));

			return $result;
		}

		$connection = Application::getConnection();
		$connection->startTransaction();
		try
		{
			$clearResult = $this->questions->clearCategory($id);
			if (!$clearResult->isSuccess())
			{
				$connection->rollbackTransaction();
				$result->addErrors($clearResult->getErrors());

				return $result;
			}

			$deleteResult = $this->categories->delete($id);
			if (!$deleteResult->isSuccess())
			{
				$connection->rollbackTransaction();
				$result->addErrors($deleteResult->getErrors());

				return $result;
			}

			$connection->commitTransaction();
		}
		catch (\Throwable $e)
		{
			$connection->rollbackTransaction();
			$result->addError(new Error('Ошибка удаления категории', 'CATEGORY_DELETE_FAILED'));

			return $result;
		}

		$this->clearCache();

		return $result;
	}

	public function clearCache(): void
	{
		Application::getInstance()->getTaggedCache()->clearByTag(self::CACHE_TAG);
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<CategoryDto>
	 */
	private function mapRows(array $rows): array
	{
		$result = [];
		foreach ($rows as $row)
		{
			$result[] = new CategoryDto(
				id: (int)$row['ID'],
				code: isset($row['CODE']) && $row['CODE'] !== '' && $row['CODE'] !== null
					? (string)$row['CODE']
					: null,
				name: (string)$row['NAME'],
				sort: (int)$row['SORT'],
				isActive: (bool)$row['IS_ACTIVE'],
			);
		}

		return $result;
	}
}
