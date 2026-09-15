<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

final readonly class SectionDto
{
	public function __construct(
		public int $id,
		public string $name,
		public string $code,
		public int $count,
		public string $url,
		public bool $selected = false,
	) {
	}
}
