<?php

declare(strict_types=1);

namespace Ws\Vacancies\Dto;

final readonly class VacancyDto
{
	/**
	 * @param list<string> $tags
	 */
	public function __construct(
		public int $id,
		public string $name,
		public string $code,
		public int $sectionId,
		public string $sectionName,
		public string $sectionUrl,
		public string $previewText,
		public string $previewTextType,
		public string $detailText,
		public string $detailTextType,
		public int $salaryFrom,
		public int $salaryTo,
		public string $salaryText,
		public string $city,
		public int $cityId,
		public string $experience,
		public int $experienceId,
		public bool $isHot,
		public bool $isNew,
		public array $tags,
		public string $dateFormatted,
		public string $dateText,
		public string $activeFrom,
		public int $views,
		public int $responseCount,
		public bool $isFavorite,
		public string $url,
		public string $contactEmail = '',
		public int $weekResponseCount = 0,
	) {
	}

	/**
	 * @param array<string, mixed> $fields
	 * @param array<string, mixed> $properties
	 * @param array<string, mixed> $extra
	 */
	public static function fromRow(array $fields, array $properties = [], array $extra = []): self
	{
		$tags = $extra['tags'] ?? $properties['TAGS'] ?? [];
		if (!is_array($tags))
		{
			$tags = $tags !== '' && $tags !== null ? [(string)$tags] : [];
		}

		return new self(
			id: (int)($fields['ID'] ?? 0),
			name: (string)($fields['NAME'] ?? ''),
			code: (string)($fields['CODE'] ?? ''),
			sectionId: (int)($fields['IBLOCK_SECTION_ID'] ?? $extra['sectionId'] ?? 0),
			sectionName: (string)($extra['sectionName'] ?? ''),
			sectionUrl: (string)($extra['sectionUrl'] ?? ''),
			previewText: (string)($fields['PREVIEW_TEXT'] ?? ''),
			previewTextType: (string)($fields['PREVIEW_TEXT_TYPE'] ?? 'text'),
			detailText: (string)($fields['DETAIL_TEXT'] ?? ''),
			detailTextType: (string)($fields['DETAIL_TEXT_TYPE'] ?? 'text'),
			salaryFrom: (int)($extra['salaryFrom'] ?? $properties['SALARY_FROM'] ?? 0),
			salaryTo: (int)($extra['salaryTo'] ?? $properties['SALARY_TO'] ?? 0),
			salaryText: (string)($extra['salaryText'] ?? ''),
			city: (string)($extra['city'] ?? $properties['CITY'] ?? ''),
			cityId: (int)($extra['cityId'] ?? $properties['CITY_ID'] ?? 0),
			experience: (string)($extra['experience'] ?? $properties['EXPERIENCE'] ?? ''),
			experienceId: (int)($extra['experienceId'] ?? $properties['EXPERIENCE_ID'] ?? 0),
			isHot: (bool)($extra['isHot'] ?? false),
			isNew: (bool)($extra['isNew'] ?? false),
			tags: array_values(array_map(static fn($t): string => (string)$t, $tags)),
			dateFormatted: (string)($extra['dateFormatted'] ?? ''),
			dateText: (string)($extra['dateText'] ?? ''),
			activeFrom: (string)($fields['ACTIVE_FROM'] ?? $extra['activeFrom'] ?? ''),
			views: (int)($extra['views'] ?? 0),
			responseCount: (int)($extra['responseCount'] ?? 0),
			isFavorite: (bool)($extra['isFavorite'] ?? false),
			url: (string)($extra['url'] ?? ''),
			contactEmail: (string)($extra['contactEmail'] ?? $properties['CONTACT_EMAIL'] ?? ''),
			weekResponseCount: (int)($extra['weekResponseCount'] ?? 0),
		);
	}
}
