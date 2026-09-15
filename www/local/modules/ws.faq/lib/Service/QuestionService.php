<?php

declare(strict_types=1);

namespace Ws\Faq\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Ws\Faq\Dto\QuestionDto;
use Ws\Faq\Dto\VoteStatsDto;
use Ws\Faq\Repository\QuestionRepository;
use Ws\Faq\Repository\VoteRepository;

final class QuestionService
{
	private const CACHE_TTL = 3600;
	private const CACHE_DIR = '/ws_faq/questions';
	private const CACHE_TAG_LIST = 'ws_faq_list';

	public function __construct(
		private readonly QuestionRepository $questions,
		private readonly VoteRepository $votes,
	) {
	}

	/**
	 * @return list<QuestionDto>
	 */
	public function listActive(?int $categoryId = null): array
	{
		$cache = Cache::createInstance();
		$cacheId = 'ws_faq_questions_' . ($categoryId ?? 'all');

		if ($cache->initCache(self::CACHE_TTL, $cacheId, self::CACHE_DIR))
		{
			$rows = $cache->getVars();

			return array_map(
				static fn(array $row): QuestionDto => QuestionDto::fromRow($row),
				is_array($rows) ? $rows : []
			);
		}

		if ($cache->startDataCache())
		{
			$taggedCache = Application::getInstance()->getTaggedCache();
			$taggedCache->startTagCache(self::CACHE_DIR);
			$taggedCache->registerTag(self::CACHE_TAG_LIST);

			$items = $this->questions->listActive($categoryId);
			foreach ($items as $item)
			{
				$taggedCache->registerTag('ws_faq_item_' . $item->id);
			}

			$rows = array_map(static fn(QuestionDto $dto): array => [
				'ID' => $dto->id,
				'CATEGORY_ID' => $dto->categoryId,
				'QUESTION' => $dto->question,
				'ANSWER' => $dto->answer,
				'SORT' => $dto->sort,
				'USEFUL_COUNT' => $dto->usefulCount,
				'NOT_USEFUL_COUNT' => $dto->notUsefulCount,
				'CATEGORY_NAME' => $dto->categoryName,
				'IS_ACTIVE' => $dto->isActive,
				'UPDATED_AT' => $dto->updatedAt,
				'CREATED_AT' => $dto->createdAt,
			], $items);

			$taggedCache->endTagCache();
			$cache->endDataCache($rows);

			return $items;
		}

		return [];
	}

	public function findById(int $id): ?QuestionDto
	{
		return $this->questions->findById($id);
	}

	public function getById(int $id): ?QuestionDto
	{
		return $this->questions->getById($id);
	}

	/**
	 * @param array{categoryId?: ?int|false, isActive?: ?bool, search?: string} $filter
	 * @param array<string, string> $order
	 * @return array{items: list<QuestionDto>, total: int}
	 */
	public function listAdmin(array $filter, array $order, int $limit, int $offset): array
	{
		return $this->questions->listAdmin($filter, $order, $limit, $offset);
	}

	/**
	 * @param array{categoryId?: ?int|false, isActive?: ?bool, search?: string} $filter
	 * @return list<int>
	 */
	public function listIds(array $filter = []): array
	{
		return $this->questions->listIds($filter);
	}

	public function getVoteStats(int $questionId): ?VoteStatsDto
	{
		$question = $this->questions->getById($questionId);
		if ($question === null)
		{
			return null;
		}

		return $this->votes->getQuestionStats(
			$questionId,
			$question->usefulCount,
			$question->notUsefulCount
		);
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
	public function save(?int $id, array $fields): Result
	{
		$result = new Result();

		$question = trim((string)($fields['QUESTION'] ?? ''));
		$answer = trim((string)($fields['ANSWER'] ?? ''));

		if ($question === '')
		{
			$result->addError(new Error('Укажите текст вопроса', 'QUESTION_REQUIRED'));

			return $result;
		}

		if (mb_strlen($question) > 500)
		{
			$result->addError(new Error('Текст вопроса не должен превышать 500 символов', 'QUESTION_TOO_LONG'));

			return $result;
		}

		if ($answer === '')
		{
			$result->addError(new Error('Укажите текст ответа', 'ANSWER_REQUIRED'));

			return $result;
		}

		$categoryId = $fields['CATEGORY_ID'] ?? null;
		if ($categoryId !== null && (int)$categoryId <= 0)
		{
			$categoryId = null;
		}
		elseif ($categoryId !== null)
		{
			$categoryId = (int)$categoryId;
		}

		$payload = [
			'QUESTION' => $question,
			'ANSWER' => $answer,
			'CATEGORY_ID' => $categoryId,
			'SORT' => (int)($fields['SORT'] ?? 500),
			'IS_ACTIVE' => (bool)($fields['IS_ACTIVE'] ?? true),
		];

		if ($id === null || $id <= 0)
		{
			$addResult = $this->questions->add($payload);
			if (!$addResult->isSuccess())
			{
				$result->addErrors($addResult->getErrors());

				return $result;
			}

			$newId = (int)$addResult->getId();
			$this->clearCache($newId);
			$result->setData(['id' => $newId]);

			return $result;
		}

		if ($this->questions->getById($id) === null)
		{
			$result->addError(new Error('Вопрос не найден', 'QUESTION_NOT_FOUND'));

			return $result;
		}

		$updateResult = $this->questions->update($id, $payload);
		if (!$updateResult->isSuccess())
		{
			$result->addErrors($updateResult->getErrors());

			return $result;
		}

		$this->clearCache($id);
		$result->setData(['id' => $id]);

		return $result;
	}

	public function setActive(int $id, bool $isActive): Result
	{
		$result = $this->questions->setActive($id, $isActive);
		if ($result->isSuccess())
		{
			$this->clearCache($id);
		}

		return $result;
	}

	public function delete(int $id): Result
	{
		$result = new Result();

		if ($this->questions->getById($id) === null)
		{
			$result->addError(new Error('Вопрос не найден', 'QUESTION_NOT_FOUND'));

			return $result;
		}

		$connection = Application::getConnection();
		$connection->startTransaction();
		try
		{
			$votesResult = $this->votes->deleteByQuestionId($id);
			if (!$votesResult->isSuccess())
			{
				$connection->rollbackTransaction();
				$result->addErrors($votesResult->getErrors());

				return $result;
			}

			$deleteResult = $this->questions->delete($id);
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
			$result->addError(new Error('Ошибка удаления вопроса', 'QUESTION_DELETE_FAILED'));

			return $result;
		}

		$this->clearCache($id);

		return $result;
	}

	public function clearCache(?int $questionId = null): void
	{
		$taggedCache = Application::getInstance()->getTaggedCache();
		$taggedCache->clearByTag(self::CACHE_TAG_LIST);

		if ($questionId !== null)
		{
			$taggedCache->clearByTag('ws_faq_item_' . $questionId);
		}
	}
}
