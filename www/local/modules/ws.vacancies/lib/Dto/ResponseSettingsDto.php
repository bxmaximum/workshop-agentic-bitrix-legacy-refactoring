<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

/**
 * Настройки отправки отклика (из параметров компонента или значения по умолчанию).
 */
final readonly class ResponseSettingsDto
{
	public function __construct(
		public int $timeout = 60,
		public string $eventName = 'LEGACY_VACANCY_RESPONSE',
		public string $emailTo = '',
		public string $baseUrl = '/vacancies/',
		public string $siteHost = '',
	) {
	}
}
