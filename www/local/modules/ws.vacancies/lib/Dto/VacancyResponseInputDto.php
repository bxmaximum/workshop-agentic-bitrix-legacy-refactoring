<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

final readonly class VacancyResponseInputDto
{
	public function __construct(
		public int $vacancyId,
		public string $name,
		public string $email,
		public string $phone,
		public string $message,
		public string $ip,
		public int $userId = 0,
	) {
	}
}
