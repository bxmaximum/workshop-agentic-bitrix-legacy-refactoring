<?php

declare(strict_types=1);

namespace Ws\Faq\Dto;

final readonly class VoteStatsDto
{
	public function __construct(
		public int $totalVotes,
		public int $usefulCount,
		public int $notUsefulCount,
		public float $usefulPercent,
		public ?string $lastVoteAt,
	) {
	}
}
