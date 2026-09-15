<?php

declare(strict_types=1);

namespace Ws\Faq\Dto;

final readonly class VoteInputDto
{
	public function __construct(
		public int $questionId,
		public bool $isUseful,
		public ?int $userId,
		public ?string $guestHash,
		public string $ipAddress,
	) {
	}
}
