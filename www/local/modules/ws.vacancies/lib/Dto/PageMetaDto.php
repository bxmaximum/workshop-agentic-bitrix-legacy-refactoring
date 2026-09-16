<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

/**
 * Заголовок, метатеги и хлебные крошки страницы.
 */
final readonly class PageMetaDto
{
	/**
	 * @param list<array{name: string, url: string}> $breadcrumbs
	 */
	public function __construct(
		public string $title,
		public string $description = '',
		public ?string $keywords = null,
		public array $breadcrumbs = [],
	) {
	}
}
