<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

final readonly class VacancyListDto
{
	/**
	 * @param list<VacancyDto> $items
	 */
	public function __construct(
		public array $items,
		public int $totalCount,
		public int $totalPages,
		public int $currentPage,
		public int $pageSize,
	) {
	}
}
