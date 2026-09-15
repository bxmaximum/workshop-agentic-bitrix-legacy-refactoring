<?php

declare(strict_types=1);

namespace Ws\Faq\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\Result;
use Ws\Faq\Dto\QuestionDto;
use Ws\Faq\Dto\VoteInputDto;
use Ws\Faq\Dto\VoteResultDto;
use Ws\Faq\Repository\QuestionRepository;
use Ws\Faq\Repository\VoteRepository;

final class VoteService
{
	public function __construct(
		private readonly QuestionRepository $questions,
		private readonly VoteRepository $votes,
		private readonly QuestionService $questionService,
	) {
	}

	/**
	 * Карта голосов текущего посетителя: questionId => isUseful.
	 *
	 * @param list<int> $questionIds
	 * @return array<int, bool>
	 */
	public function getVisitorVotesMap(?int $userId, ?string $guestHash, array $questionIds): array
	{
		$map = [];
		foreach ($questionIds as $questionId)
		{
			$questionId = (int)$questionId;
			if ($questionId <= 0)
			{
				continue;
			}

			$row = null;
			if ($userId !== null)
			{
				$row = $this->votes->findUserVote($questionId, $userId);
			}
			elseif ($guestHash !== null && $guestHash !== '')
			{
				$row = $this->votes->findGuestVote($questionId, $guestHash);
			}

			if ($row !== null)
			{
				$map[$questionId] = $this->normalizeIsUseful($row['IS_USEFUL'] ?? null);
			}
		}

		return $map;
	}

	public function vote(VoteInputDto $dto): Result
	{
		$result = new Result();

		if ($dto->userId === null && ($dto->guestHash === null || $dto->guestHash === ''))
		{
			return $result->addError(new Error('Не удалось определить голосующего', 'VOTER_UNDEFINED'));
		}

		$question = $this->questions->findById($dto->questionId);
		if ($question === null)
		{
			return $result->addError(new Error('Вопрос не найден', 'QUESTION_NOT_FOUND'));
		}

		$existingVote = $this->findExistingVote($dto);
		if ($existingVote !== null)
		{
			$previousIsUseful = $this->normalizeIsUseful($existingVote['IS_USEFUL'] ?? null);
			if ($previousIsUseful === $dto->isUseful)
			{
				return $this->successResult(
					$question,
					$dto->isUseful,
					'Ваш голос уже учтён',
					'ALREADY_VOTED'
				);
			}

			return $this->changeVote($dto, $question, (int)$existingVote['ID']);
		}

		return $this->addVote($dto, $question);
	}

	private function addVote(VoteInputDto $dto, QuestionDto $question): Result
	{
		$result = new Result();
		$connection = Application::getConnection();
		$connection->startTransaction();

		try
		{
			$addResult = $this->votes->add($dto);
			if (!$addResult->isSuccess())
			{
				$connection->rollbackTransaction();

				return $result->addErrors($addResult->getErrors());
			}

			$counterResult = $this->questions->incrementCounter($dto->questionId, $dto->isUseful);
			if (!$counterResult->isSuccess())
			{
				$connection->rollbackTransaction();

				return $result->addErrors($counterResult->getErrors());
			}

			$connection->commitTransaction();
		}
		catch (\Throwable)
		{
			$connection->rollbackTransaction();

			return $result->addError(new Error('Ошибка фиксации голоса', 'VOTE_FAILED'));
		}

		$updated = $this->questions->findById($dto->questionId) ?? $question;
		$this->questionService->clearCache($dto->questionId);
		$this->sendVoteEvent($dto, false);

		return $this->successResult(
			$updated,
			$dto->isUseful,
			'Спасибо, ваш голос учтён!',
			'VOTED'
		);
	}

	private function changeVote(VoteInputDto $dto, QuestionDto $question, int $voteId): Result
	{
		$result = new Result();
		$connection = Application::getConnection();
		$connection->startTransaction();

		try
		{
			$updateResult = $this->votes->updateVote($voteId, $dto->isUseful);
			if (!$updateResult->isSuccess())
			{
				$connection->rollbackTransaction();

				return $result->addErrors($updateResult->getErrors());
			}

			$counterResult = $this->questions->switchCounters($dto->questionId, $dto->isUseful);
			if (!$counterResult->isSuccess())
			{
				$connection->rollbackTransaction();

				return $result->addErrors($counterResult->getErrors());
			}

			$connection->commitTransaction();
		}
		catch (\Throwable)
		{
			$connection->rollbackTransaction();

			return $result->addError(new Error('Ошибка фиксации голоса', 'VOTE_FAILED'));
		}

		$updated = $this->questions->findById($dto->questionId) ?? $question;
		$this->questionService->clearCache($dto->questionId);
		$this->sendVoteEvent($dto, true);

		return $this->successResult(
			$updated,
			$dto->isUseful,
			'Спасибо, ваш голос обновлён!',
			'VOTE_CHANGED'
		);
	}

	private function findExistingVote(VoteInputDto $dto): ?array
	{
		if ($dto->userId !== null)
		{
			return $this->votes->findUserVote($dto->questionId, $dto->userId);
		}

		return $this->votes->findGuestVote($dto->questionId, (string)$dto->guestHash);
	}

	private function successResult(
		QuestionDto $question,
		bool $isUseful,
		string $message,
		string $status
	): Result {
		$dto = new VoteResultDto(
			questionId: $question->id,
			userVotedIsUseful: $isUseful,
			usefulCount: $question->usefulCount,
			notUsefulCount: $question->notUsefulCount,
			message: $message,
		);

		return (new Result())->setData([
			'status' => $status,
			...$dto->toArray(),
		]);
	}

	private function sendVoteEvent(VoteInputDto $dto, bool $changed): void
	{
		(new Event('ws.faq', 'OnVoteAdd', [
			'vote' => [
				'questionId' => $dto->questionId,
				'isUseful' => $dto->isUseful,
				'userId' => $dto->userId,
				'guestHash' => $dto->guestHash,
				'ipAddress' => $dto->ipAddress,
				'changed' => $changed,
			],
		]))->send();
	}

	private function normalizeIsUseful(mixed $value): bool
	{
		return $value === true || $value === 'Y' || $value === 1 || $value === '1';
	}
}
