<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

use Ws\Vacancies\Helper\UrlBuilder;

/**
 * Настройки страниц раздела (из параметров компонента).
 */
final readonly class PageSettingsDto
{
	public function __construct(
		public string $baseUrl = UrlBuilder::DEFAULT_BASE_URL,
		public string $defaultSort = 'date',
		public int $popularCount = 5,
		public int $relatedCount = 3,
	) {
	}
}
