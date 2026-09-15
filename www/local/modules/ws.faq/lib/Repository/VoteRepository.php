<?php

declare(strict_types=1);

namespace Ws\Faq\Repository;

use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\ORM\Data\UpdateResult;
use Bitrix\Main\Result;
use Bitrix\Main\Type\DateTime;
use Ws\Faq\Dto\VoteInputDto;
use Ws\Faq\Dto\VoteStatsDto;
use Ws\Faq\Model\VoteTable;

final class VoteRepository
{
	public function findUserVote(int $questionId, int $userId): ?array
	{
		$row = VoteTable::query()
			->setSelect(['ID', 'QUESTION_ID', 'USER_ID', 'GUEST_HASH', 'IS_USEFUL', 'IP_ADDRESS', 'CREATED_AT', 'UPDATED_AT'])
			->where('QUESTION_ID', $questionId)
			->where('USER_ID', $userId)
			->setLimit(1)
			->fetch();

		return $row ?: null;
	}

	public function findGuestVote(int $questionId, string $guestHash): ?array
	{
		$row = VoteTable::query()
			->setSelect(['ID', 'QUESTION_ID', 'USER_ID', 'GUEST_HASH', 'IS_USEFUL', 'IP_ADDRESS', 'CREATED_AT', 'UPDATED_AT'])
			->where('QUESTION_ID', $questionId)
			->where('GUEST_HASH', $guestHash)
			->setLimit(1)
			->fetch();

		return $row ?: null;
	}

	public function add(VoteInputDto $dto): AddResult
	{
		return VoteTable::add([
			'QUESTION_ID' => $dto->questionId,
			'USER_ID' => $dto->userId,
			'GUEST_HASH' => $dto->guestHash,
			'IS_USEFUL' => $dto->isUseful,
			'IP_ADDRESS' => $dto->ipAddress,
			'CREATED_AT' => new DateTime(),
			'UPDATED_AT' => new DateTime(),
		]);
	}

	public function updateVote(int $voteId, bool $newIsUseful): UpdateResult
	{
		return VoteTable::update($voteId, [
			'IS_USEFUL' => $newIsUseful,
			'UPDATED_AT' => new DateTime(),
		]);
	}

	public function getQuestionStats(int $questionId, int $usefulCount, int $notUsefulCount): VoteStatsDto
	{
		$totalVotes = $usefulCount + $notUsefulCount;
		$usefulPercent = $totalVotes > 0
			? round(($usefulCount / $totalVotes) * 100, 1)
			: 0.0;

		$row = VoteTable::query()
			->setSelect(['UPDATED_AT'])
			->where('QUESTION_ID', $questionId)
			->setOrder(['UPDATED_AT' => 'DESC'])
			->setLimit(1)
			->fetch();

		$lastVoteAt = null;
		if (is_array($row) && !empty($row['UPDATED_AT']))
		{
			$lastVoteAt = $row['UPDATED_AT'] instanceof DateTime
				? $row['UPDATED_AT']->toString()
				: (string)$row['UPDATED_AT'];
		}

		return new VoteStatsDto(
			totalVotes: $totalVotes,
			usefulCount: $usefulCount,
			notUsefulCount: $notUsefulCount,
			usefulPercent: $usefulPercent,
			lastVoteAt: $lastVoteAt,
		);
	}

	public function deleteByQuestionId(int $questionId): Result
	{
		$result = new Result();

		$rows = VoteTable::query()
			->setSelect(['ID'])
			->where('QUESTION_ID', $questionId)
			->fetchAll();

		foreach ($rows as $row)
		{
			$deleteResult = VoteTable::delete((int)$row['ID']);
			if (!$deleteResult->isSuccess())
			{
				$result->addErrors($deleteResult->getErrors());
			}
		}

		return $result;
	}
}
