<?php

declare(strict_types=1);

namespace Ws\Faq\Dto;

final readonly class CategoryDto
{
	public function __construct(
		public int $id,
		public ?string $code,
		public string $name,
		public int $sort,
		public bool $isActive,
	) {
	}
}
