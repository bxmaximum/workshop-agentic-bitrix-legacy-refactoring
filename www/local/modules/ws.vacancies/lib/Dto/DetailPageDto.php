<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

/**
 * Всё, что нужно для детальной страницы вакансии.
 */
final readonly class DetailPageDto
{
	public function __construct(
		public VacancyDto $item,
		public SidebarDto $sidebar,
		public PageMetaDto $meta,
	) {
	}
}
