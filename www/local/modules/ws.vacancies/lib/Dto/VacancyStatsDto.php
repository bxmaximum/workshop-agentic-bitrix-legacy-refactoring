<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

final readonly class VacancyStatsDto
{
	public function __construct(
		public int $vacancyId,
		public int $views,
		public int $responseCount,
	) {
	}
}
