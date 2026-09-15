<?php

declare(strict_types=1);

namespace Ws\Faq\Dto;

final readonly class VoteResultDto
{
	public function __construct(
		public int $questionId,
		public bool $userVotedIsUseful,
		public int $usefulCount,
		public int $notUsefulCount,
		public string $message,
	) {
	}

	public function toArray(): array
	{
		return [
			'questionId' => $this->questionId,
			'userVotedIsUseful' => $this->userVotedIsUseful,
			'usefulCount' => $this->usefulCount,
			'notUsefulCount' => $this->notUsefulCount,
			'message' => $this->message,
		];
	}
}
