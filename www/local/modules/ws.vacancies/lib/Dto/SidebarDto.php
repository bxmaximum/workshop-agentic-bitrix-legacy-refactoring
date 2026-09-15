<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

final readonly class SidebarDto
{
	/**
	 * @param list<SectionDto> $sections
	 * @param list<VacancyDto> $popular
	 * @param array{total: int, vacancies: int}|null $weekSummary
	 * @param list<VacancyDto> $related
	 */
	public function __construct(
		public array $sections = [],
		public array $popular = [],
		public ?array $weekSummary = null,
		public array $related = [],
	) {
	}
}
