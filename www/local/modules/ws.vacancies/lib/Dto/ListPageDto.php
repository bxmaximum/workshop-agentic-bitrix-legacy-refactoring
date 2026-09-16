<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

/**
 * Всё, что нужно для страницы списка вакансий.
 */
final readonly class ListPageDto
{
	/**
	 * @param array<string, string> $sortUrls код сортировки => URL
	 * @param array<int, string> $cities
	 * @param array<int, string> $experience
	 */
	public function __construct(
		public VacancyListDto $list,
		public NavigationDto $nav,
		public array $sortUrls,
		public string $resetUrl,
		public bool $filterActive,
		public SidebarDto $sidebar,
		public array $cities,
		public array $experience,
		public PageMetaDto $meta,
	) {
	}
}
