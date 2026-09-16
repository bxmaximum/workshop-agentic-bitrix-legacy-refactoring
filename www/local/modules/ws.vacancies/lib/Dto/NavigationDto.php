<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

/**
 * Постраничная навигация списка: ссылка на 1-ю страницу всегда без page=1 (баг №7).
 */
final readonly class NavigationDto
{
	/**
	 * @param array<int, string> $urls номер страницы => URL
	 */
	public function __construct(
		public int $page,
		public int $pages,
		public int $total,
		public int $pageSize,
		public string $prevUrl,
		public string $nextUrl,
		public array $urls,
	) {
	}
}
